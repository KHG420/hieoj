#!/usr/bin/env python3
# ============================================================
# frp-network-diagnostics-test.py —— frp-network-diagnostics.py 单元测试
#
# 只 mock 两个外部边界：子进程（diag.run_command）与 TCP socket（diag.tcp_probe）。
# 快照组装、输出解析、issue 标签、journal 截断/脱敏、state、日志目录权限与
# 4MiB 轮转全部跑真实逻辑。所有路径都在临时目录，绝不触碰 /var/log、
# 真实网络或部署路径。
#
# 运行（仓库根目录）：
#   python3 docker/tests/frp-network-diagnostics-test.py
# ============================================================
import fcntl
import importlib.util
import io
import json
import os
import shutil
import socket
import stat
import sys
import tempfile
import time
import unittest
from contextlib import redirect_stderr, redirect_stdout
from unittest import mock

# 不因 import 被测模块而在仓库里写 __pycache__。
sys.dont_write_bytecode = True

HERE = os.path.dirname(os.path.abspath(__file__))
MODULE_PATH = os.path.join(
    HERE, os.pardir, "operations", "frp-network-diagnostics.py")
SERVICE_PATH = os.path.join(
    HERE, os.pardir, "operations", "frp-network-diagnostics.service")
TIMER_PATH = os.path.join(
    HERE, os.pardir, "operations", "frp-network-diagnostics.timer")

_spec = importlib.util.spec_from_file_location("frp_net_diag", MODULE_PATH)
diag = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(diag)

# ---- 固定 fixture：真实 iproute2/curl/getent/systemctl 输出形状 ----
ADDR_OUTPUT = (
    "2: ens160    inet 172.31.0.96/24 brd 172.31.0.255 scope global ens160\\"
    "       valid_lft forever preferred_lft forever\n"
)
LINK_OUTPUT = (
    "2: ens160: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc fq_codel "
    "state UP mode DEFAULT group default qlen 1000\\    "
    "link/ether 00:50:56:aa:bb:cc brd ff:ff:ff:ff:ff:ff\n"
    "    RX: bytes  packets  errors  dropped missed  mcast   \n"
    "    1000000    2000     0       0       0       0       \n"
    "    TX: bytes  packets  errors  dropped carrier collsns \n"
    "    2000000    3000     0       0       0       0       \n"
)
LINK_ONELINE_OUTPUT = (
    # iproute2 5.15.0 实测输出：`ip -s -o link` 把统计块用 '\' 连成一行。
    "11: eth0@if1116: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 65535 qdisc "
    "noqueue state UP mode DEFAULT group default \\    link/ether "
    "1a:05:5a:53:00:10 brd ff:ff:ff:ff:ff:ff link-netnsid 0\\    RX:  bytes "
    "packets errors dropped  missed   mcast           \\           152       "
    "2      0       0       0       0 \\    TX:  bytes packets errors dropped "
    "carrier collsns           \\            42       1      0       0       "
    "0       0 \n")
ADDR_ONELINE_OUTPUT = (
    "11: eth0    inet 172.17.0.6/16 brd 172.17.255.255 scope global eth0\\"
    "       valid_lft forever preferred_lft forever\n")
LINK_DOWN_OUTPUT = (
    "2: ens160: <BROADCAST,MULTICAST> mtu 1500 qdisc fq_codel "
    "state DOWN mode DEFAULT group default qlen 1000\\    "
    "link/ether 00:50:56:aa:bb:cc brd ff:ff:ff:ff:ff:ff\n"
    "    RX: bytes  packets  errors  dropped missed  mcast   \n"
    "    1000       20       0       0       0       0       \n"
    "    TX: bytes  packets  errors  dropped carrier collsns \n"
    "    2000       30       0       0       0       0       \n"
)
ROUTE_OUTPUT = (
    "default via 172.31.0.1 dev ens160 proto dhcp src 172.31.0.96 metric 100\n")
NEIGH_OUTPUT = "172.31.0.1 dev ens160 lladdr 00:11:22:33:44:55 REACHABLE\n"
GETENT_OUTPUT = (
    "110.242.68.66  STREAM www.baidu.com\n"
    "110.242.68.66  DGRAM  www.baidu.com\n"
    "39.156.66.10   STREAM www.baidu.com\n"
)
FRPC_OUTPUT = (
    "LoadState=loaded\n"
    "ActiveState=active\n"
    "SubState=running\n"
    "MainPID=4321\n"
    "ActiveEnterTimestamp=Sun 2026-09-20 12:00:00 CST\n"
    "ExecMainStartTimestamp=Sun 2026-09-20 12:00:00 CST\n"
)
JOURNAL_OUTPUT = (
    "2026-09-21T10:22:01+08:00 app frpc[4321]: login to server success\n"
    "2026-09-21T10:22:02+08:00 app frpc[4321]: start proxy success\n"
)
PING_OUTPUT = "1 packets transmitted, 1 received, 0% packet loss, time 0ms\n"
PING_LOSS_OUTPUT = (
    "1 packets transmitted, 0 received, 100% packet loss, time 0ms\n")
BODY = ("<html><head><title>算法设计在线评测系统</title></head>"
        "<body>OJ homepage</body></html>")

FRP_TARGET = "%s:%d" % (diag.FRP_HOST, diag.FRP_PORT)
SSH_TARGET = "%s:%d" % (diag.FRP_HOST, diag.SSH_PORT)


def _read(path):
    with open(path, "r", encoding="utf-8") as handle:
        return handle.read()


class FakeOS:
    """OS/网络边界替身：固定命令返回固定输出，绝不执行真实命令。"""

    def __init__(self):
        self.calls = []
        self.timeouts = []
        self.tcp_calls = []
        self.overrides = {}
        self.curl_error = None
        self.curl_rc = 0
        self.http_code = "200"
        self.http_body = BODY
        self.ip_route = ROUTE_OUTPUT
        self.ip_addr = ADDR_OUTPUT
        self.ip_link = LINK_OUTPUT
        self.ip_neigh = NEIGH_OUTPUT
        self.getent_rc = 0
        self.getent_output = GETENT_OUTPUT
        self.ping_rc = 0
        self.ping_output = PING_OUTPUT
        self.systemctl_rc = 0
        self.systemctl_output = FRPC_OUTPUT
        self.journal_rc = 0
        self.journal_output = JOURNAL_OUTPUT
        self.tcp_errors = {}

    @staticmethod
    def _result(rc=0, stdout="", stderr="", error=None, truncated=False):
        return {"rc": rc, "stdout": stdout, "stderr": stderr, "error": error,
                "stdout_truncated": truncated, "stderr_truncated": False}

    def run_command(self, args, timeout, cap=diag.OUTPUT_CAP):
        args = [str(item) for item in args]
        self.calls.append(args)
        self.timeouts.append(timeout)
        name = args[0]
        if name in self.overrides:
            override = self.overrides[name]
            if callable(override):
                return override(args, timeout, cap)
            override = dict(override)
            if override.get("error") and "rc" not in override:
                override["rc"] = None
            return self._result(**override)
        if name == "curl":
            return self._curl(args)
        if name == "ip":
            return self._ip(args)
        if name == "getent":
            return self._result(rc=self.getent_rc, stdout=self.getent_output)
        if name == "ping":
            return self._result(rc=self.ping_rc, stdout=self.ping_output)
        if name == "systemctl":
            return self._result(rc=self.systemctl_rc,
                                stdout=self.systemctl_output)
        if name == "journalctl":
            return self._result(rc=self.journal_rc, stdout=self.journal_output)
        return self._result(rc=127, error="missing_command")

    def _curl(self, args):
        if self.curl_error is not None:
            return self._result(error=self.curl_error)
        body_path = None
        for index, item in enumerate(args):
            if item == "--output" and index + 1 < len(args):
                body_path = args[index + 1]
        if body_path:
            with open(body_path, "wb") as handle:
                handle.write(self.http_body.encode("utf-8"))
        return self._result(rc=self.curl_rc, stdout=self.http_code)

    def _ip(self, args):
        if "route" in args:
            return self._result(stdout=self.ip_route)
        if "addr" in args:
            return self._result(stdout=self.ip_addr)
        if "neigh" in args:
            return self._result(stdout=self.ip_neigh)
        if "link" in args:
            return self._result(stdout=self.ip_link)
        return self._result(rc=1, stderr="unknown ip invocation\n")

    def tcp_probe(self, host, port, timeout=diag.CONNECT_TIMEOUT_SECONDS):
        self.tcp_calls.append((host, port, timeout))
        error = self.tcp_errors.get("%s:%d" % (host, port))
        if error:
            return {"ok": False, "error": error}
        return {"ok": True, "error": None}


class DiagnosticsTestCase(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix="frp-net-diag-test.")
        self.addCleanup(shutil.rmtree, self.tmp, ignore_errors=True)
        self.log_dir = os.path.join(self.tmp, "log")
        self.fake = FakeOS()

    def tick(self, log_dir=None, fake=None):
        fake = fake or self.fake
        out, err = io.StringIO(), io.StringIO()
        with mock.patch.object(diag, "run_command",
                               side_effect=fake.run_command), \
                mock.patch.object(diag, "tcp_probe",
                                  side_effect=fake.tcp_probe):
            with redirect_stdout(out), redirect_stderr(err):
                rc = diag.run_tick(log_dir=log_dir or self.log_dir)
        return rc, out.getvalue(), err.getvalue()

    def records(self, log_dir=None):
        path = os.path.join(log_dir or self.log_dir, diag.EVENTS_FILENAME)
        return [json.loads(line) for line in _read(path).splitlines()
                if line.strip()]

    def record(self, log_dir=None):
        return self.records(log_dir)[0]

    def state(self, log_dir=None):
        return json.loads(_read(
            os.path.join(log_dir or self.log_dir, diag.STATE_FILENAME)))

    def mode(self, path):
        return stat.S_IMODE(os.stat(path).st_mode)


class RunCommandBoundaryTests(unittest.TestCase):
    """真实子进程边界（不 mock）：缺失命令、超时、输出上限。"""

    def test_missing_command_label(self):
        result = diag.run_command(["hnieoj-definitely-not-a-command"], 2)
        self.assertEqual(result["error"], "missing_command")
        self.assertIsNone(result["rc"])
        self.assertEqual(result["stdout"], "")

    def test_timeout_label_kills_the_process(self):
        started = time.monotonic()
        result = diag.run_command(
            [sys.executable, "-c", "import time; time.sleep(30)"], 1)
        self.assertEqual(result["error"], "timeout")
        self.assertIsNone(result["rc"])
        self.assertLess(time.monotonic() - started, 6)

    def test_output_is_capped(self):
        result = diag.run_command(
            [sys.executable, "-c", "print('x' * 100000)"], 10, cap=64)
        self.assertEqual(result["rc"], 0)
        self.assertIsNone(result["error"])
        self.assertEqual(len(result["stdout"]), 64)
        self.assertTrue(result["stdout_truncated"])


class HealthySnapshotTests(DiagnosticsTestCase):
    """AC1：健康快照字段、时间戳、上下文、无正文、真实权限。"""

    CHECK_NAMES = ("local_http", "tcp_frp_7000", "tcp_ssh_22", "tcp_dns_53",
                   "resolver", "default_route", "gateway_ping", "neighbor",
                   "interface", "frpc_service")

    def test_healthy_snapshot_shape(self):
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        self.assertEqual(err, "")
        self.assertEqual(len(self.records()), 1)
        record = self.record()
        self.assertEqual(record["schema"], diag.SCHEMA)
        self.assertEqual(record["hostname"], socket.gethostname())
        self.assertEqual(record["iface"], "ens160")
        self.assertRegex(
            record["ts"], r"^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$")
        self.assertGreater(record["epoch"], 1_600_000_000)
        self.assertIsInstance(record["elapsed_ms"], int)
        for name in self.CHECK_NAMES:
            check = record["checks"][name]
            self.assertTrue(check["ok"], name)
            self.assertTrue(check["observed"], name)
            self.assertIsNone(check["error"], name)
            self.assertIsInstance(check["duration_ms"], int, name)
        self.assertEqual(record["checks"]["local_http"]["http_code"], "200")
        self.assertTrue(record["checks"]["local_http"]["marker"])
        self.assertEqual(record["checks"]["tcp_frp_7000"]["target"], FRP_TARGET)
        self.assertEqual(record["checks"]["tcp_ssh_22"]["target"], SSH_TARGET)
        self.assertEqual(
            record["checks"]["tcp_dns_53"]["target"],
            "%s:%d" % (diag.DNS_HOST, diag.DNS_PORT))
        self.assertEqual(record["checks"]["resolver"]["addresses"],
                         ["110.242.68.66", "39.156.66.10"])
        self.assertEqual(record["checks"]["default_route"]["gateway"],
                         "172.31.0.1")
        self.assertEqual(record["checks"]["default_route"]["dev"], "ens160")
        self.assertEqual(record["checks"]["default_route"]["routes"],
                         [{"gateway": "172.31.0.1", "dev": "ens160"}])
        self.assertEqual(record["checks"]["interface"]["addresses"],
                         ["172.31.0.96/24"])
        self.assertEqual(record["checks"]["interface"]["operstate"], "UP")
        self.assertTrue(record["checks"]["interface"]["carrier"])
        self.assertEqual(record["checks"]["interface"]["rx_bytes"], 1000000)
        self.assertEqual(record["checks"]["interface"]["tx_bytes"], 2000000)
        self.assertEqual(record["checks"]["interface"]["rx_packets"], 2000)
        self.assertEqual(record["checks"]["interface"]["tx_packets"], 3000)
        self.assertEqual(record["checks"]["neighbor"]["gateway_state"],
                         "REACHABLE")
        self.assertTrue(record["checks"]["neighbor"]["gateway_has_lladdr"])
        self.assertEqual(record["checks"]["gateway_ping"]["packet_loss_percent"],
                         0)
        self.assertEqual(record["checks"]["frpc_service"]["active"], "active")
        self.assertEqual(record["checks"]["frpc_service"]["main_pid"], 4321)
        self.assertEqual(record["checks"]["frpc_service"]["load_state"],
                         "loaded")
        self.assertIn("2026-09-20", record["checks"]["frpc_service"]["since"])
        self.assertEqual(record["issues"], [])
        self.assertEqual(record["failed_checks"], [])
        self.assertEqual(record["primary_issues"], [])
        self.assertFalse(record["primary_failure"])
        self.assertTrue(record["probes_complete"])
        self.assertNotIn("journal", record)
        # 连接边界只收到字面 IP，从不接收域名（IP 探测不解析 DNS）。
        self.assertEqual(
            sorted(self.fake.tcp_calls),
            sorted([(diag.FRP_HOST, diag.FRP_PORT, diag.CONNECT_TIMEOUT_SECONDS),
                    (diag.FRP_HOST, diag.SSH_PORT, diag.CONNECT_TIMEOUT_SECONDS),
                    (diag.DNS_HOST, diag.DNS_PORT,
                     diag.CONNECT_TIMEOUT_SECONDS)]))

    def test_healthy_snapshot_contains_no_http_body(self):
        self.tick()
        raw = _read(os.path.join(self.log_dir, diag.EVENTS_FILENAME))
        self.assertNotIn("<html>", raw)
        self.assertNotIn("</body>", raw)
        self.assertNotIn(diag.OJ_MARKER, raw)
        self.assertIn('"marker": true', raw)
        self.assertIn('"body_bytes":', raw)
        # 临时正文目录已清理，正文不落盘。
        self.assertFalse(os.path.exists(
            os.path.join(self.log_dir, "local-body")))

    def test_permissions_are_restrictive(self):
        self.tick()
        self.assertEqual(self.mode(self.log_dir), 0o700)
        for name in (diag.EVENTS_FILENAME, diag.STATE_FILENAME,
                     diag.LOCK_FILENAME):
            self.assertEqual(
                self.mode(os.path.join(self.log_dir, name)), 0o600, name)

    def test_state_is_minimal(self):
        self.tick()
        state = self.state()
        self.assertEqual(set(state),
                         {"last_epoch", "primary_failure", "issues"})
        self.assertFalse(state["primary_failure"])
        self.assertEqual(state["issues"], [])

    def test_all_commands_have_bounded_timeouts(self):
        self.tick()
        self.assertTrue(self.fake.calls)
        self.assertEqual(len(self.fake.calls), len(self.fake.timeouts))
        for timeout in self.fake.timeouts:
            self.assertIsInstance(timeout, (int, float))
            self.assertGreater(timeout, 0)
            self.assertLessEqual(timeout, 5)

    def test_tcp_probe_never_resolves_dns(self):
        # 非字面 IP 直接拒绝，不构造 socket、不做 DNS 查询。
        self.assertEqual(diag.tcp_probe("www.baidu.com", 7000, 0.1),
                         {"ok": False, "error": "non_literal_address"})
        self.assertEqual(diag.tcp_probe("", 7000, 0.1),
                         {"ok": False, "error": "non_literal_address"})

    def test_service_and_timer_contract(self):
        service = _read(SERVICE_PATH)
        self.assertIn("Type=oneshot", service)
        self.assertIn("TimeoutStartSec=45s", service)
        self.assertIn("UMask=0077", service)
        self.assertIn(
            "ExecStart=/usr/bin/python3 "
            "/usr/local/sbin/hnieoj-frp-network-diagnostics.py", service)
        self.assertNotIn("Restart=", service)
        timer = _read(TIMER_PATH)
        self.assertIn("OnBootSec=30s", timer)
        self.assertIn("OnUnitActiveSec=60s", timer)
        self.assertIn("AccuracySec=1s", timer)
        self.assertIn("Unit=frp-network-diagnostics.service", timer)


class ReadOnlySafetyTests(DiagnosticsTestCase):
    """AC3：不读配置/环境、不执行变更命令、并发有界。"""

    ALLOWED_COMMANDS = {"curl", "ip", "ping", "getent", "journalctl",
                        "systemctl"}

    def test_commands_are_read_only_and_path_free(self):
        self.tick()
        self.assertTrue(self.fake.calls)
        for argv in self.fake.calls:
            self.assertIn(argv[0], self.ALLOWED_COMMANDS)
            for argument in argv[1:]:
                self.assertFalse(argument.startswith(("/etc", "/var/log",
                                                      "/var/lib")), argv)
        for argv in self.fake.calls:
            if argv[0] == "systemctl":
                self.assertEqual(argv[1], "show")
                for forbidden in ("restart", "start", "stop", "enable",
                                  "disable", "reload", "kill", "login"):
                    self.assertNotIn(forbidden, argv)
            if argv[0] == "curl":
                self.assertIn("--noproxy", argv)
                self.assertIn("*", argv)

    def test_module_reads_no_environment_or_config(self):
        source = _read(MODULE_PATH)
        self.assertNotIn("os.environ", source)
        self.assertNotIn("getenv", source)
        self.assertNotIn("/etc/", source)
        self.assertNotIn("import requests", source)

    def test_concurrency_and_timeouts_are_bounded(self):
        self.assertIsInstance(diag.MAX_WORKERS, int)
        self.assertGreaterEqual(diag.MAX_WORKERS, 2)
        self.assertLessEqual(diag.MAX_WORKERS, 12)
        for name in ("CURL_MAX_TIME", "CURL_CONNECT_TIMEOUT", "IP_TIMEOUT",
                     "CONNECT_TIMEOUT_SECONDS", "GETENT_TIMEOUT",
                     "PING_TIMEOUT", "SYSTEMCTL_TIMEOUT", "JOURNAL_TIMEOUT"):
            value = getattr(diag, name)
            self.assertGreater(value, 0, name)
            self.assertLessEqual(value, 5, name)
        self.assertLessEqual(len(diag.PRIMARY_CHECKS),
                             len(HealthySnapshotTests.CHECK_NAMES))

    def test_non_overlap_skips_tick(self):
        os.makedirs(self.log_dir, mode=0o700, exist_ok=True)
        lock_path = os.path.join(self.log_dir, diag.LOCK_FILENAME)
        fd = os.open(lock_path, os.O_RDWR | os.O_CREAT, 0o600)
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        try:
            rc, out, err = self.tick()
        finally:
            fcntl.flock(fd, fcntl.LOCK_UN)
            os.close(fd)
        self.assertEqual(rc, 0)
        self.assertIn("in progress", out)
        self.assertEqual(self.fake.calls, [])
        self.assertFalse(os.path.exists(
            os.path.join(self.log_dir, diag.EVENTS_FILENAME)))

    def test_write_failure_on_non_directory_exits_nonzero(self):
        path = os.path.join(self.tmp, "not-a-dir")
        with open(path, "w", encoding="utf-8") as handle:
            handle.write("x")
        rc, out, err = self.tick(log_dir=path)
        self.assertEqual(rc, 1)
        self.assertIn("WARN", err)
        self.assertEqual(self.fake.calls, [])

    def test_lock_open_failure_exits_nonzero(self):
        os.makedirs(self.log_dir, mode=0o700, exist_ok=True)
        os.mkdir(os.path.join(self.log_dir, diag.LOCK_FILENAME))
        rc, out, err = self.tick()
        self.assertEqual(rc, 1)
        self.assertIn("cannot open lock file", err)
        self.assertEqual(self.fake.calls, [])

    def test_write_failure_on_events_directory_exits_nonzero(self):
        os.makedirs(self.log_dir, mode=0o700, exist_ok=True)
        os.mkdir(os.path.join(self.log_dir, diag.EVENTS_FILENAME))
        rc, out, err = self.tick()
        self.assertEqual(rc, 1)
        self.assertIn("cannot write evidence", err)

    def test_state_write_failure_still_writes_evidence(self):
        os.makedirs(self.log_dir, mode=0o700, exist_ok=True)
        os.mkdir(os.path.join(self.log_dir, diag.STATE_FILENAME))
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        self.assertIn("cannot update state", err)
        self.assertEqual(len(self.records()), 1)

    def test_rotation_is_bounded(self):
        os.makedirs(self.log_dir, mode=0o700, exist_ok=True)
        record = {"schema": diag.SCHEMA, "payload": "x" * (1024 * 1024)}
        for _ in range(40):
            diag.write_event(self.log_dir, record)
        base = os.path.join(self.log_dir, diag.EVENTS_FILENAME)
        self.assertEqual(self.mode(base), 0o600)
        self.assertLessEqual(os.path.getsize(base), diag.MAX_BYTES)
        total = os.path.getsize(base)
        for index in range(1, diag.BACKUP_COUNT + 1):
            path = "%s.%d" % (base, index)
            self.assertTrue(os.path.exists(path), path)
            self.assertEqual(self.mode(path), 0o600, path)
            total += os.path.getsize(path)
        self.assertFalse(os.path.exists(
            "%s.%d" % (base, diag.BACKUP_COUNT + 1)))
        self.assertLessEqual(total, diag.MAX_BYTES * (diag.BACKUP_COUNT + 1))
        # 当前文件仍可继续追加，不会因为轮转丢证据。
        diag.write_event(self.log_dir, {"schema": diag.SCHEMA})
        self.assertGreater(os.path.getsize(base), 0)


class UnhealthyFixtureTests(DiagnosticsTestCase):
    """AC2：各类故障 fixture 产生可区分的原始证据。"""

    def test_tcp_timeout_with_local_web_healthy(self):
        self.fake.tcp_errors[FRP_TARGET] = "timeout"
        self.fake.tcp_errors[SSH_TARGET] = "timeout"
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        record = self.record()
        self.assertTrue(record["checks"]["local_http"]["ok"])
        self.assertEqual(record["checks"]["tcp_frp_7000"]["error"], "timeout")
        self.assertEqual(record["checks"]["tcp_ssh_22"]["error"], "timeout")
        self.assertTrue(record["checks"]["tcp_dns_53"]["ok"])
        self.assertTrue(record["primary_failure"])
        self.assertIn("tcp_frp_7000: timeout", record["issues"])
        self.assertTrue(record["probes_complete"])
        self.assertEqual(record["journal"]["reason"], "primary_failure")
        self.assertTrue(self.state()["primary_failure"])

    def test_dns_failure_with_tcp_healthy(self):
        self.fake.getent_rc = 2
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        record = self.record()
        self.assertEqual(record["checks"]["resolver"]["error"], "dns_error")
        self.assertFalse(record["checks"]["resolver"]["ok"])
        self.assertEqual(record["checks"]["resolver"]["rc"], 2)
        for name in ("tcp_frp_7000", "tcp_ssh_22", "tcp_dns_53"):
            self.assertTrue(record["checks"][name]["ok"], name)
        self.assertTrue(record["primary_failure"])
        self.assertIn("resolver: dns_error", record["issues"])

    def test_dns_subprocess_timeout_is_distinct(self):
        self.fake.overrides["getent"] = {"error": "timeout"}
        self.tick()
        record = self.record()
        self.assertEqual(record["checks"]["resolver"]["error"], "timeout")
        self.assertTrue(record["primary_failure"])
        self.assertFalse(record["probes_complete"])

    def test_local_web_wrong_marker(self):
        self.fake.http_body = "<html><title>Welcome to nginx</title></html>"
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        record = self.record()
        check = record["checks"]["local_http"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "marker_missing")
        self.assertEqual(check["http_code"], "200")
        self.assertFalse(check["marker"])
        self.assertTrue(record["primary_failure"])

    def test_local_web_non_200(self):
        self.fake.http_code = "502"
        self.tick()
        check = self.record()["checks"]["local_http"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "http_502")
        self.assertFalse(check["marker"])
        self.assertTrue(self.record()["primary_failure"])

    def test_local_web_connection_refused(self):
        # curl 拿不到响应时 write-out 输出占位码 000，不应被当成 HTTP 状态码。
        self.fake.curl_rc = 7
        self.fake.http_code = "000"
        self.tick()
        check = self.record()["checks"]["local_http"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "curl_exit_7")
        self.assertIsNone(check["http_code"])
        self.assertTrue(self.record()["primary_failure"])

    def test_missing_command_is_never_healthy(self):
        self.fake.overrides["getent"] = {"error": "missing_command"}
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        record = self.record()
        check = record["checks"]["resolver"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "missing_command")
        self.assertIsNone(check["rc"])
        self.assertFalse(record["probes_complete"])
        self.assertTrue(record["primary_failure"])
        self.assertIn("resolver: missing_command", record["issues"])

    def test_missing_context_command_is_not_reported_healthy(self):
        self.fake.overrides["ip"] = {"error": "missing_command"}
        self.tick()
        record = self.record()
        for name in ("default_route", "interface", "neighbor"):
            self.assertFalse(record["checks"][name]["ok"], name)
            self.assertEqual(record["checks"][name]["error"],
                             "missing_command", name)
        self.assertFalse(record["probes_complete"])
        self.assertIn("default_route: missing_command", record["issues"])
        self.assertIn("interface: missing_command", record["issues"])
        # 主判据（本机/公网可达性）仍正常，因此不产生 outage 结论。
        self.assertFalse(record["primary_failure"])

    def test_subprocess_timeout_is_recorded(self):
        self.fake.overrides["curl"] = {"error": "timeout"}
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        check = self.record()["checks"]["local_http"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "timeout")
        self.assertTrue(self.record()["primary_failure"])

    def test_refused_and_timeout_are_distinguishable(self):
        self.fake.tcp_errors[FRP_TARGET] = "refused"
        self.fake.tcp_errors[SSH_TARGET] = "timeout"
        self.tick()
        record = self.record()
        self.assertEqual(record["checks"]["tcp_frp_7000"]["error"], "refused")
        self.assertEqual(record["checks"]["tcp_ssh_22"]["error"], "timeout")

    def test_gateway_ping_alone_is_not_a_primary_failure(self):
        self.fake.ping_rc = 1
        self.fake.ping_output = PING_LOSS_OUTPUT
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        record = self.record()
        check = record["checks"]["gateway_ping"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "no_reply")
        self.assertEqual(check["packet_loss_percent"], 100)
        self.assertEqual(record["failed_checks"], ["gateway_ping"])
        self.assertEqual(record["primary_issues"], [])
        self.assertFalse(record["primary_failure"])
        # 观测本身是完整的（ping 有结论），只是结果为「无回包」。
        self.assertTrue(record["probes_complete"])
        self.assertNotIn("journal", record)
        self.assertIn("gateway_ping: no_reply", record["issues"])

    def test_interface_counters_on_real_oneline_iproute_output(self):
        # 直接喂入 iproute2 5.15.0 的真实 `ip -s -o link` 输出形状。
        self.fake.ip_addr = ADDR_ONELINE_OUTPUT
        self.fake.ip_link = LINK_ONELINE_OUTPUT
        with mock.patch.object(diag, "run_command",
                               side_effect=self.fake.run_command):
            check = diag.check_interface("eth0")
        self.assertTrue(check["observed"])
        self.assertTrue(check["ok"])
        self.assertEqual(check["addresses"], ["172.17.0.6/16"])
        self.assertEqual(check["operstate"], "UP")
        self.assertTrue(check["carrier"])
        self.assertEqual(check["mtu"], 65535)
        self.assertEqual(check["rx_bytes"], 152)
        self.assertEqual(check["rx_packets"], 2)
        self.assertEqual(check["rx_errors"], 0)
        self.assertEqual(check["tx_bytes"], 42)
        self.assertEqual(check["tx_packets"], 1)

    def test_default_route_prefers_app_interface(self):
        self.fake.ip_route = ("default via 10.8.0.1 dev wg0 metric 50\n"
                              "default via 172.31.0.1 dev ens160 metric 100\n")
        self.tick()
        check = self.record()["checks"]["default_route"]
        self.assertTrue(check["ok"])
        self.assertEqual(check["gateway"], "172.31.0.1")
        self.assertEqual(check["dev"], "ens160")
        self.assertEqual(check["routes"],
                         [{"gateway": "10.8.0.1", "dev": "wg0"},
                          {"gateway": "172.31.0.1", "dev": "ens160"}])

    def test_context_degradation_is_not_primary(self):
        self.fake.ip_route = ""
        self.fake.ip_link = LINK_DOWN_OUTPUT
        self.fake.ip_addr = ""
        self.tick()
        record = self.record()
        self.assertEqual(record["checks"]["default_route"]["error"],
                         "no_default_route")
        self.assertEqual(record["checks"]["gateway_ping"]["error"],
                         "no_gateway")
        self.assertEqual(record["checks"]["interface"]["error"], "down")
        self.assertEqual(record["checks"]["neighbor"]["error"], None)
        self.assertFalse(record["primary_failure"])
        self.assertFalse(record["probes_complete"])

    def test_neighbor_failed_state_is_reported(self):
        self.fake.ip_neigh = "172.31.0.1 dev ens160 FAILED\n"
        self.tick()
        check = self.record()["checks"]["neighbor"]
        self.assertFalse(check["ok"])
        self.assertEqual(check["error"], "failed")
        self.assertFalse(check["gateway_has_lladdr"])

    def test_fixtures_produce_distinguishable_bounded_records(self):
        scenarios = {}

        def make(name):
            fake = FakeOS()
            if name == "tcp_timeout":
                fake.tcp_errors[FRP_TARGET] = "timeout"
            elif name == "dns_failure":
                fake.getent_rc = 2
            elif name == "marker_missing":
                fake.http_body = "<html><title>nginx</title></html>"
            elif name == "http_502":
                fake.http_code = "502"
            elif name == "missing_command":
                fake.overrides["getent"] = {"error": "missing_command"}
            elif name == "subprocess_timeout":
                fake.overrides["curl"] = {"error": "timeout"}
            return fake

        for name in ("healthy", "tcp_timeout", "dns_failure", "marker_missing",
                     "http_502", "missing_command", "subprocess_timeout"):
            log_dir = os.path.join(self.tmp, name)
            rc, out, err = self.tick(log_dir=log_dir, fake=make(name))
            self.assertEqual(rc, 0, name)
            record = self.records(log_dir)[0]
            self.assertLess(len(json.dumps(record, ensure_ascii=False)),
                            16384, name)
            scenarios[name] = tuple(record["issues"])
        self.assertEqual(scenarios["healthy"], ())
        self.assertEqual(scenarios["tcp_timeout"],
                         ("tcp_frp_7000: timeout",))
        self.assertEqual(scenarios["dns_failure"], ("resolver: dns_error",))
        self.assertEqual(scenarios["marker_missing"],
                         ("local_http: marker_missing",))
        self.assertEqual(scenarios["http_502"], ("local_http: http_502",))
        self.assertEqual(scenarios["missing_command"],
                         ("resolver: missing_command",))
        self.assertEqual(scenarios["subprocess_timeout"],
                         ("local_http: timeout",))
        self.assertEqual(len(set(scenarios.values())), len(scenarios))


class JournalTests(DiagnosticsTestCase):
    """AC2：primary 失败/恢复时的有界 journal，脱敏且可降级。"""

    def test_journal_only_on_failure_and_recovery(self):
        self.fake.tcp_errors[FRP_TARGET] = "timeout"
        self.tick()
        first = self.record()
        self.assertEqual(first["journal"]["reason"], "primary_failure")
        self.assertTrue(any("login to server success" in line
                            for line in first["journal"]["lines"]))

        self.fake.tcp_errors.clear()
        self.tick()
        second = self.records()[1]
        self.assertFalse(second["primary_failure"])
        self.assertEqual(second["journal"]["reason"], "recovery")
        self.assertIn("journalctl", [argv[0] for argv in self.fake.calls])

        self.tick()
        third = self.records()[2]
        self.assertNotIn("journal", third)
        self.assertFalse(self.state()["primary_failure"])

    def test_journal_is_bounded_to_30_lines_and_8kib(self):
        lines = []
        for index in range(200):
            lines.append("2026-09-21T10:22:%02d+08:00 app frpc[4321]: "
                         "line-%03d %s" % (index % 60, index, "x" * 400))
        self.fake.journal_output = "\n".join(lines) + "\n"
        self.fake.tcp_errors[FRP_TARGET] = "timeout"
        self.tick()
        journal = self.record()["journal"]
        self.assertLessEqual(len(journal["lines"]), 30)
        self.assertLessEqual(
            len("\n".join(journal["lines"]).encode("utf-8")), 8192)
        self.assertTrue(journal["truncated"])
        self.assertIn("line-199", journal["lines"][-1])
        self.assertNotIn("line-000", "\n".join(journal["lines"]))

    def test_journal_redacts_credential_like_values(self):
        self.fake.journal_output = (
            "2026-09-21T10:22:01+08:00 app frpc[4321]: token=SUPERSECRETVALUE\n"
            "2026-09-21T10:22:02+08:00 app frpc[4321]: password: hunter2\n")
        self.fake.tcp_errors[FRP_TARGET] = "timeout"
        self.tick()
        raw = _read(os.path.join(self.log_dir, diag.EVENTS_FILENAME))
        self.assertNotIn("SUPERSECRETVALUE", raw)
        self.assertNotIn("hunter2", raw)
        self.assertIn("<redacted>", raw)

    def test_journal_failure_does_not_break_the_record(self):
        self.fake.overrides["journalctl"] = {"error": "timeout"}
        self.fake.tcp_errors[FRP_TARGET] = "timeout"
        rc, out, err = self.tick()
        self.assertEqual(rc, 0)
        journal = self.record()["journal"]
        self.assertEqual(journal["error"], "timeout")
        self.assertEqual(journal["lines"], [])
        self.assertTrue(self.record()["primary_failure"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
