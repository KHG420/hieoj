#!/usr/bin/env python3
# ============================================================
# frp-notify-test.py —— frp-notify.py 单元测试
#
# 只 mock 两个外部边界：探针子进程（notify.run_probe）与 PushPlus HTTPS
# （notify.pushplus_post）；状态机、JSON 落盘、flock、消息构造都跑真实逻辑。
# 全程使用临时目录，绝不访问真实 PushPlus、绝不触碰 /etc 或 /var/lib 部署路径，
# 也不读取真实 token。
#
# 运行（仓库根目录）：
#   python3 docker/tests/frp-notify-test.py
# ============================================================
import fcntl
import importlib.util
import io
import json
import os
import shutil
import sys
import tempfile
import unittest
import urllib.error
from contextlib import redirect_stderr, redirect_stdout
from unittest import mock

# 不因 import 被测模块而在仓库里写 __pycache__。
sys.dont_write_bytecode = True

HERE = os.path.dirname(os.path.abspath(__file__))
MODULE_PATH = os.path.join(HERE, os.pardir, "operations", "frp-notify.py")
SERVICE_PATH = os.path.join(HERE, os.pardir, "operations", "frp-notify.service")
TIMER_PATH = os.path.join(HERE, os.pardir, "operations", "frp-notify.timer")

_spec = importlib.util.spec_from_file_location("frp_notify", MODULE_PATH)
notify = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(notify)

TOKEN = "TEST-TOKEN-DO-NOT-LEAK-1234567890"


class NotifyTestCase(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp(prefix="frp-notify-test.")
        self.addCleanup(shutil.rmtree, self.tmp, ignore_errors=True)
        self.state_dir = os.path.join(self.tmp, "state")
        self.state_file = os.path.join(self.state_dir, "state.json")
        self.config_path = os.path.join(self.tmp, "config.json")
        self._write_config(self.config_path, TOKEN)

    @staticmethod
    def _write_config(path, token):
        with open(path, "w", encoding="utf-8") as handle:
            json.dump({"pushplus_token": token}, handle)
        os.chmod(path, 0o600)

    def capture(self, func, *args, **kwargs):
        out, err = io.StringIO(), io.StringIO()
        with redirect_stdout(out), redirect_stderr(err):
            result = func(*args, **kwargs)
        return result, out.getvalue(), err.getvalue()

    def state(self):
        with open(self.state_file, encoding="utf-8") as handle:
            return json.load(handle)

    def seed(self, **values):
        state = dict(notify.DEFAULT_STATE)
        state.update(values)
        os.makedirs(self.state_dir, mode=0o700, exist_ok=True)
        with open(self.state_file, "w", encoding="utf-8") as handle:
            json.dump(state, handle)

    def tick(self, status, now, reject=False, sender=None):
        """跑一轮 run_tick；探针与 HTTPS 被 mock，返回 (rc, sends, out, err)。"""
        sends = []

        def fake_send(token, title, content):
            sends.append((token, title, content))
            if sender is not None:
                return sender(len(sends))
            if reject:
                return False, "", "http status 502"
            return True, "msg-%d" % len(sends), "accepted"

        with mock.patch.object(notify, "run_probe", return_value=status), \
                mock.patch.object(notify, "send_notification", side_effect=fake_send):
            rc, out, err = self.capture(
                notify.run_tick,
                config_path=self.config_path,
                state_dir=self.state_dir,
                now=now,
            )
        return rc, sends, out, err


class OutageAndRecoveryTests(NotifyTestCase):
    BASE = 1_700_000_000

    def test_no_alerts_when_all_healthy(self):
        for index in range(5):
            rc, sends, out, err = self.tick("OK", self.BASE + index * 60)
            self.assertEqual(rc, 0)
            self.assertEqual(sends, [])
        self.assertFalse(self.state()["incident_active"])

    def test_one_or_two_failures_send_nothing(self):
        rc, sends, out, err = self.tick("FAILED", self.BASE)
        self.assertEqual(sends, [])
        rc, sends, out, err = self.tick("FAILED", self.BASE + 60)
        self.assertEqual(sends, [])
        # 中间恢复一次即清零，之前的 2 次失败不构成事故。
        self.tick("OK", self.BASE + 120)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 180)
        self.assertEqual(sends, [])
        self.assertEqual(self.state()["consecutive_failures"], 1)

    def test_three_failures_send_one_outage_alert(self):
        self.tick("FAILED", self.BASE)
        self.tick("FAILED", self.BASE + 60)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 120)
        self.assertEqual(rc, 0)
        self.assertEqual(len(sends), 1)
        _, title, content = sends[0]
        self.assertIn("不可用", title)
        self.assertIn("https://hnieacm.com/", content)
        self.assertIn("https://www.hnieacm.com/", content)
        self.assertIn(notify.format_cst(self.BASE), content)
        state = self.state()
        self.assertTrue(state["incident_active"])
        self.assertEqual(state["incident_start_epoch"], self.BASE)
        self.assertEqual(state["last_accepted_epoch"], self.BASE + 120)
        self.assertEqual(state["pending"], "")
        # 第 4 次失败不重发首次告警。
        rc, sends, out, err = self.tick("FAILED", self.BASE + 180)
        self.assertEqual(sends, [])

    def test_accepted_outage_does_not_delay_recovery(self):
        # 首次告警被接受后不应残留 300s 重试冷却，否则恢复通知会被无谓推迟。
        self.tick("FAILED", self.BASE)
        self.tick("FAILED", self.BASE + 60)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 120)
        self.assertEqual(len(sends), 1)
        self.assertIn("不可用", sends[0][1])
        self.assertEqual(self.state()["next_attempt_epoch"], 0)
        self.tick("OK", self.BASE + 180)
        rc, sends, out, err = self.tick("OK", self.BASE + 240)
        self.assertEqual(len(sends), 1)
        self.assertIn("恢复", sends[0][1])

    def test_delayed_recovery_acceptance_reports_confirmed_time(self):
        # 恢复在 +120 首次确认但提交被拒；+420 才受理时，时间/时长仍按 +120 计算。
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            last_accepted_epoch=self.BASE,
        )
        confirmed = self.BASE + 120
        self.tick("OK", self.BASE + 60)
        rc, sends, out, err = self.tick("OK", confirmed, reject=True)
        self.assertEqual(len(sends), 1)
        self.assertEqual(self.state()["recovery_confirmed_epoch"], confirmed)
        # 重试期间持续健康，恢复时刻不被推后。
        self.tick("OK", self.BASE + 180)
        self.assertEqual(self.state()["recovery_confirmed_epoch"], confirmed)
        rc, sends, out, err = self.tick("OK", self.BASE + 420)
        self.assertEqual(len(sends), 1)
        content = sends[0][2]
        self.assertIn(notify.format_cst(confirmed), content)
        self.assertIn(notify.format_duration(confirmed - self.BASE), content)

    def test_two_healthy_after_incident_send_recovery_with_duration(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            consecutive_failures=5,
            last_accepted_epoch=self.BASE,
        )
        rc, sends, out, err = self.tick("OK", self.BASE + 600)
        self.assertEqual(sends, [])
        rc, sends, out, err = self.tick("OK", self.BASE + 660)
        self.assertEqual(len(sends), 1)
        _, title, content = sends[0]
        self.assertIn("恢复", title)
        self.assertIn(notify.format_duration(660), content)
        self.assertIn(notify.format_cst(self.BASE), content)
        state = self.state()
        self.assertFalse(state["incident_active"])
        self.assertEqual(state["pending"], "")

    def test_healthy_once_then_failure_has_no_duplicate_initial_alert(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            consecutive_failures=4,
            last_accepted_epoch=self.BASE + 100,
        )
        self.tick("OK", self.BASE + 200)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 260)
        self.assertEqual(sends, [])
        # 再次稳定 2 次后仍发恢复，而不是重发首次告警。
        self.tick("OK", self.BASE + 320)
        rc, sends, out, err = self.tick("OK", self.BASE + 380)
        self.assertEqual(len(sends), 1)
        self.assertIn("恢复", sends[0][1])

    def test_new_failure_while_recovery_pending_cancels_stale_recovery(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            last_accepted_epoch=self.BASE,
        )
        # 连续 2 次健康 → 恢复待发，但提交失败。
        self.tick("OK", self.BASE + 60)
        rc, sends, out, err = self.tick("OK", self.BASE + 120, reject=True)
        self.assertEqual(len(sends), 1)
        self.assertEqual(self.state()["pending"], "recovery")
        # 恢复未发出即再次失败：取消恢复，继续同一次事故，不发首次告警。
        rc, sends, out, err = self.tick("FAILED", self.BASE + 180)
        self.assertEqual(sends, [])
        self.assertEqual(self.state()["pending"], "")
        self.assertTrue(self.state()["incident_active"])
        # 再稳定 2 次才发恢复；本次发送仍受上一次失败尝试的 300s 冷却约束。
        self.tick("OK", self.BASE + 480)
        rc, sends, out, err = self.tick("OK", self.BASE + 540)
        self.assertEqual(len(sends), 1)
        self.assertIn("恢复", sends[0][1])

    def test_recovery_pending_retries_after_300(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            last_accepted_epoch=self.BASE,
        )
        self.tick("OK", self.BASE + 60)
        rc, sends, out, err = self.tick("OK", self.BASE + 120, reject=True)
        self.assertEqual(self.state()["next_attempt_epoch"], self.BASE + 420)
        rc, sends, out, err = self.tick("OK", self.BASE + 180, reject=True)
        self.assertEqual(sends, [])
        self.assertIn("pending", out + err)
        rc, sends, out, err = self.tick("OK", self.BASE + 420)
        self.assertEqual(len(sends), 1)
        self.assertIn("恢复", sends[0][1])


class RetryAndReminderTests(NotifyTestCase):
    BASE = 1_700_000_000

    def test_delivery_failure_not_recorded_as_accepted_and_retried_after_300(self):
        self.tick("FAILED", self.BASE)
        self.tick("FAILED", self.BASE + 60)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 120, reject=True)
        self.assertEqual(len(sends), 1)
        state = self.state()
        self.assertFalse(state["incident_active"])
        self.assertEqual(state["last_accepted_epoch"], 0)
        self.assertEqual(state["pending"], "outage")
        self.assertEqual(state["next_attempt_epoch"], self.BASE + 420)
        # 冷却期内只记日志，不重试。
        rc, sends, out, err = self.tick("FAILED", self.BASE + 180, reject=True)
        self.assertEqual(sends, [])
        self.assertIn("pending", out + err)
        # 300s 后重试并接受。
        rc, sends, out, err = self.tick("FAILED", self.BASE + 420)
        self.assertEqual(len(sends), 1)
        state = self.state()
        self.assertTrue(state["incident_active"])
        self.assertEqual(state["last_accepted_epoch"], self.BASE + 420)

    def test_persistent_failure_reminder_at_1800(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            consecutive_failures=10,
            last_accepted_epoch=self.BASE,
        )
        rc, sends, out, err = self.tick("FAILED", self.BASE + 1799)
        self.assertEqual(sends, [])
        rc, sends, out, err = self.tick("FAILED", self.BASE + 1800)
        self.assertEqual(len(sends), 1)
        self.assertIn("提醒", sends[0][1])
        # 提醒被接受后重新计时。
        rc, sends, out, err = self.tick("FAILED", self.BASE + 3599)
        self.assertEqual(sends, [])
        rc, sends, out, err = self.tick("FAILED", self.BASE + 3600)
        self.assertEqual(len(sends), 1)

    def test_outage_never_accepted_then_recovery_sends_no_recovered_notice(self):
        self.tick("FAILED", self.BASE)
        self.tick("FAILED", self.BASE + 60)
        self.tick("FAILED", self.BASE + 120, reject=True)
        self.assertEqual(self.state()["pending"], "outage")
        rc, sends, out, err = self.tick("OK", self.BASE + 180)
        self.assertEqual(sends, [])
        state = self.state()
        self.assertEqual(state["pending"], "")
        self.assertFalse(state["incident_active"])

    def test_failed_delivery_before_send_persists_cooldown(self):
        # 发送前先落盘 next_attempt_epoch：即使提交失败也不会每分钟重发。
        self.tick("FAILED", self.BASE)
        self.tick("FAILED", self.BASE + 60)
        self.tick("FAILED", self.BASE + 120, reject=True)
        self.assertEqual(self.state()["last_attempt_epoch"], self.BASE + 120)
        self.assertEqual(self.state()["next_attempt_epoch"], self.BASE + 420)

    def test_daily_limit_pauses_until_next_cst_day_while_checks_continue(self):
        def limited(_count):
            return False, "", notify.DAILY_LIMIT_REASON

        self.tick("FAILED", self.BASE)
        self.tick("FAILED", self.BASE + 60)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 120, sender=limited)
        self.assertEqual(len(sends), 1)
        self.assertIn("daily limit", err)
        state = self.state()
        self.assertEqual(state["last_accepted_epoch"], 0)
        self.assertEqual(state["pending"], "outage")
        pause_until = notify.next_cst_day_start(self.BASE + 120)
        self.assertEqual(state["next_attempt_epoch"], pause_until)
        self.assertIn("00:00:00", notify.format_cst(pause_until))
        self.assertGreater(pause_until, self.BASE + 120)
        # 暂停期间探针照常累计连续失败，但不发起请求（避免继续请求延长封禁）。
        for offset in (180, 240, 300):
            rc, sends, out, err = self.tick("FAILED", self.BASE + offset)
            self.assertEqual(sends, [])
        self.assertEqual(self.state()["consecutive_failures"], 6)
        # 次日 0 点后事故仍在，恢复投递。
        rc, sends, out, err = self.tick("FAILED", pause_until)
        self.assertEqual(len(sends), 1)
        self.assertIn("不可用", sends[0][1])


class UnknownProbeTests(NotifyTestCase):
    BASE = 1_700_000_000

    def test_unknown_resets_streaks_and_is_not_recovery(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            consecutive_failures=2,
            last_accepted_epoch=self.BASE,
        )
        rc, sends, out, err = self.tick("UNKNOWN", self.BASE + 60)
        self.assertEqual(rc, 0)
        self.assertEqual(sends, [])
        state = self.state()
        self.assertEqual(state["consecutive_failures"], 0)
        self.assertEqual(state["consecutive_healthy"], 0)
        self.assertTrue(state["incident_active"])
        self.assertIn("unknown", (out + err).lower())

    def test_unknown_after_two_healthy_delays_recovery(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            last_accepted_epoch=self.BASE,
        )
        self.tick("OK", self.BASE + 60)
        self.tick("UNKNOWN", self.BASE + 120)
        rc, sends, out, err = self.tick("OK", self.BASE + 180)
        self.assertEqual(sends, [])  # 健康计数已被未知清零，需重新累计 2 次
        rc, sends, out, err = self.tick("OK", self.BASE + 240)
        self.assertEqual(len(sends), 1)

    def test_unknown_invalidates_pending_recovery(self):
        # 待发恢复（提交被拒）遇到未知：作废恢复，不能凭 1 次健康就发送。
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            last_accepted_epoch=self.BASE,
        )
        self.tick("OK", self.BASE + 60)
        rc, sends, out, err = self.tick("OK", self.BASE + 120, reject=True)
        self.assertEqual(self.state()["pending"], "recovery")
        self.tick("UNKNOWN", self.BASE + 180)
        state = self.state()
        self.assertEqual(state["pending"], "")
        self.assertTrue(state["incident_active"])
        self.assertEqual(state["recovery_confirmed_epoch"], 0)
        rc, sends, out, err = self.tick("OK", self.BASE + 240)
        self.assertEqual(sends, [])
        self.assertEqual(self.state()["consecutive_healthy"], 1)
        # 第二次健康时上一次拒绝的 300s 冷却已过，恢复才允许发送。
        rc, sends, out, err = self.tick("OK", self.BASE + 540)
        self.assertEqual(len(sends), 1)
        self.assertIn("恢复", sends[0][1])


class StateAndSafetyTests(NotifyTestCase):
    BASE = 1_700_000_000

    def test_state_persists_across_ticks(self):
        rc, sends, out, err = self.tick("FAILED", self.BASE)
        self.assertTrue(os.path.exists(self.state_file))
        state = self.state()
        self.assertEqual(state["consecutive_failures"], 1)
        self.assertEqual(state["first_failure_epoch"], self.BASE)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 60)
        self.assertEqual(self.state()["consecutive_failures"], 2)

    def test_token_never_leaks_to_logs_or_state(self):
        self.tick("FAILED", self.BASE, reject=True)
        self.tick("FAILED", self.BASE + 60, reject=True)
        rc, sends, out, err = self.tick("FAILED", self.BASE + 120, reject=True)
        self.assertEqual(sends[0][0], TOKEN)  # 配置确实被读取并交给发送函数
        combined = out + err
        self.assertNotIn(TOKEN, combined)
        with open(self.state_file, encoding="utf-8") as handle:
            self.assertNotIn(TOKEN, handle.read())

    def test_missing_config_fails_safely_without_probing(self):
        probe = mock.Mock(return_value="FAILED")
        missing = os.path.join(self.tmp, "missing.json")
        with mock.patch.object(notify, "run_probe", probe):
            rc, out, err = self.capture(
                notify.run_tick,
                config_path=missing,
                state_dir=self.state_dir,
                now=self.BASE,
            )
        self.assertNotEqual(rc, 0)
        probe.assert_not_called()
        self.assertFalse(os.path.exists(self.state_file))
        self.assertNotIn(TOKEN, out + err)

    def test_malformed_config_fails_safely(self):
        bad = os.path.join(self.tmp, "bad.json")
        with open(bad, "w", encoding="utf-8") as handle:
            handle.write("{not valid json")
        rc, out, err = self.capture(
            notify.run_tick, config_path=bad, state_dir=self.state_dir, now=self.BASE
        )
        self.assertNotEqual(rc, 0)
        self.assertIn("configuration error", out + err)

    def test_empty_token_rejected(self):
        self._write_config(self.config_path, "   ")
        rc, out, err = self.capture(
            notify.run_tick,
            config_path=self.config_path,
            state_dir=self.state_dir,
            now=self.BASE,
        )
        self.assertNotEqual(rc, 0)

    def test_busy_lock_skips_tick(self):
        os.makedirs(self.state_dir, mode=0o700, exist_ok=True)
        fd = os.open(os.path.join(self.state_dir, "lock"),
                     os.O_RDWR | os.O_CREAT, 0o600)
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        probe = mock.Mock(return_value="FAILED")
        sender = mock.Mock(return_value=(True, "x", "accepted"))
        try:
            with mock.patch.object(notify, "run_probe", probe), \
                    mock.patch.object(notify, "send_notification", sender):
                rc, out, err = self.capture(
                    notify.run_tick,
                    config_path=self.config_path,
                    state_dir=self.state_dir,
                    now=self.BASE,
                )
        finally:
            fcntl.flock(fd, fcntl.LOCK_UN)
            os.close(fd)
        self.assertEqual(rc, 0)
        probe.assert_not_called()
        sender.assert_not_called()
        self.assertIn("in progress", out)


class HttpBoundaryTests(NotifyTestCase):
    def test_acceptance_rules(self):
        cases = [
            (200, b'{"code":200,"data":"mid-1"}', True, "mid-1"),
            (200, b'{"code":500,"data":null}', False, ""),
            (200, b'{"code":900,"msg":"\\u7528\\u6237\\u53d7\\u9650"}', False, ""),
            (200, b"not json", False, ""),
            (502, b'{"code":200,"data":"mid-2"}', False, ""),
        ]
        for status, body, accepted, message_id in cases:
            with mock.patch.object(notify, "pushplus_post", return_value=(status, body)):
                got = notify.send_notification(TOKEN, "t", "c")
            self.assertEqual(got[0], accepted, (status, body, got))
            self.assertEqual(got[1], message_id, (status, body, got))
            self.assertNotIn(TOKEN, got[2])

    def test_code_900_classified_as_daily_limit(self):
        with mock.patch.object(notify, "pushplus_post",
                              return_value=(200, b'{"code":900,"data":null}')):
            accepted, message_id, reason = notify.send_notification(TOKEN, "t", "c")
        self.assertFalse(accepted)
        self.assertEqual(message_id, "")
        self.assertEqual(reason, notify.DAILY_LIMIT_REASON)
        self.assertNotIn(TOKEN, reason)

    def test_http_error_is_rejected(self):
        error = urllib.error.HTTPError(notify.PUSHPLUS_URL, 502, "bad", {}, None)
        with mock.patch.object(notify, "pushplus_post", side_effect=error):
            accepted, message_id, reason = notify.send_notification(TOKEN, "t", "c")
        self.assertFalse(accepted)
        self.assertEqual(message_id, "")
        self.assertNotIn(TOKEN, reason)

    def test_request_payload_fields(self):
        captured = {}

        def fake_post(payload):
            captured["payload"] = json.loads(payload.decode("utf-8"))
            return 200, b'{"code":200,"data":"x"}'

        with mock.patch.object(notify, "pushplus_post", side_effect=fake_post):
            notify.send_notification(TOKEN, "T", "C")
        self.assertEqual(captured["payload"]["token"], TOKEN)
        self.assertEqual(captured["payload"]["title"], "T")
        self.assertEqual(captured["payload"]["content"], "C")
        self.assertEqual(captured["payload"]["template"], "txt")
        # 邮件通道；不传 option，使用 PushPlus 默认发信通道（收件邮箱在个人资料绑定）。
        self.assertEqual(captured["payload"]["channel"], "mail")
        self.assertNotIn("option", captured["payload"])

    def test_post_is_proxy_free_bounded_and_tls_verified(self):
        seen = {}

        class FakeResponse:
            def getcode(self):
                return 200

            def read(self):
                return b'{"code":200,"data":"1"}'

            def __enter__(self):
                return self

            def __exit__(self, *exc):
                return False

        class FakeOpener:
            def open(self, request, timeout=None):
                seen["request"] = request
                seen["timeout"] = timeout
                return FakeResponse()

        def fake_build_opener(*handlers):
            seen["handlers"] = handlers
            return FakeOpener()

        with mock.patch.object(notify.urllib.request, "build_opener", side_effect=fake_build_opener):
            status, _ = notify.pushplus_post(b'{"token":"x"}')
        self.assertEqual(status, 200)
        self.assertEqual(seen["timeout"], notify.HTTP_TIMEOUT_SECONDS)
        self.assertEqual(seen["request"].full_url, notify.PUSHPLUS_URL)
        self.assertEqual(seen["request"].get_method(), "POST")
        proxies = [h for h in seen["handlers"] if isinstance(h, notify.urllib.request.ProxyHandler)]
        self.assertEqual(len(proxies), 1)
        self.assertEqual(proxies[0].proxies, {})


class TestNotificationTests(NotifyTestCase):
    BASE = 1_700_000_000

    def test_test_notification_does_not_touch_state(self):
        self.seed(
            incident_active=True,
            incident_start_epoch=self.BASE,
            consecutive_failures=7,
            last_accepted_epoch=self.BASE,
            pending="outage",
            next_attempt_epoch=self.BASE + 50,
        )
        with open(self.state_file, "rb") as handle:
            before = handle.read()
        sends = []

        def fake_send(token, title, content):
            sends.append((token, title, content))
            return True, "test-mid", "accepted"

        with mock.patch.object(notify, "CONFIG_PATH", self.config_path), \
                mock.patch.object(notify, "send_notification", side_effect=fake_send):
            rc, out, err = self.capture(notify.main, ["--test-notification"])
        self.assertEqual(rc, 0)
        self.assertEqual(len(sends), 1)
        self.assertIn("测试", sends[0][1])
        self.assertIn("测试通知", sends[0][2])
        self.assertNotIn(TOKEN, out + err)
        with open(self.state_file, "rb") as handle:
            self.assertEqual(handle.read(), before)

    def test_test_notification_missing_config_exits_nonzero(self):
        missing = os.path.join(self.tmp, "missing.json")
        with mock.patch.object(notify, "CONFIG_PATH", missing):
            rc, out, err = self.capture(notify.main, ["--test-notification"])
        self.assertNotEqual(rc, 0)
        self.assertIn("configuration error", out + err)

    def test_test_notification_rejected_exits_nonzero(self):
        with mock.patch.object(notify, "CONFIG_PATH", self.config_path), \
                mock.patch.object(notify, "send_notification",
                                  return_value=(False, "", "http status 502")):
            rc, out, err = self.capture(notify.main, ["--test-notification"])
        self.assertNotEqual(rc, 0)
        self.assertIn("not accepted", out + err)


class DeploymentContractTests(unittest.TestCase):
    def test_service_unit_contract(self):
        with open(SERVICE_PATH, encoding="utf-8") as handle:
            text = handle.read()
        self.assertIn("ExecStart=/usr/bin/python3 /usr/local/sbin/hnieoj-frp-notify.py", text)
        self.assertIn("Type=oneshot", text)
        self.assertIn("StateDirectory=hnieoj-frp-notify", text)
        self.assertIn("StateDirectoryMode=0700", text)
        self.assertIn("TimeoutStartSec=50s", text)
        self.assertNotIn("EnvironmentFile", text)

    def test_timer_unit_contract(self):
        with open(TIMER_PATH, encoding="utf-8") as handle:
            text = handle.read()
        self.assertIn("OnUnitActiveSec=60s", text)
        self.assertIn("AccuracySec=1s", text)
        self.assertIn("Unit=frp-notify.service", text)


if __name__ == "__main__":
    unittest.main(verbosity=2)
