#!/bin/bash
# 可执行回归测试：定期同步 wrapper（run-directory-sync.sh）必须能用 macOS 自带的
# /bin/bash 3.2 正常运行。中文标点紧邻未加花括号的变量（如日志路径变量后面直接跟
# 全角逗号）在
# bash 3.2 下会被解析成变量名的一部分，配合 set -u 直接以 unbound variable 退出，
# 本测试锁定该行为不再回归。
#
# 覆盖：
#   1) 采集失败：非零退出、保留旧快照、绝不调用 importer
#   2) 采集成功默认 dry-run：调用 importer 但不带 --apply，stdin 就是快照
#   3) --apply 透传给 importer
#   4) importer 失败：wrapper 非零退出
#   5) 采集成功但快照为空：不导入
#   6) 容器内缺少 importer：不导入
#   7) 安装脚本在 /bin/bash 3.2 下可运行并生成 plist
#
# 只使用真实 /bin/bash 与假 collector/docker 可执行文件，不联网、不碰数据库、
# 不启动浏览器、不部署。
set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
WRAPPER="$SCRIPT_DIR/run-directory-sync.sh"
INSTALLER="$SCRIPT_DIR/install-launchagent.sh"
BASH_BIN="/bin/bash"

PASS=0
FAIL=0
TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/acad-sync-wrapper-test.XXXXXX")"
cleanup() { rm -rf "$TMP_ROOT"; }
trap cleanup EXIT

ok() { PASS=$((PASS + 1)); echo "  ok - $1"; }
bad() { FAIL=$((FAIL + 1)); echo "  not ok - $1"; }
check() { # check <desc> <expected> <actual>
  if [ "$2" = "$3" ]; then
    ok "$1"
  else
    bad "$1 (expected [$2], got [$3])"
  fi
}
check_contains() { # check_contains <desc> <needle> <file>
  if grep -qF -- "$2" "$3" 2>/dev/null; then
    ok "$1"
  else
    bad "$1 (missing [$2] in $3)"
  fi
}
check_not_contains() { # check_not_contains <desc> <needle> <file>
  if grep -qF -- "$2" "$3" 2>/dev/null; then
    bad "$1 (unexpected [$2] in $3)"
  else
    ok "$1"
  fi
}

# ---------------------------------------------------------------------------
# 假的可执行文件
# ---------------------------------------------------------------------------
STUB_BIN="$TMP_ROOT/bin"
mkdir -p "$STUB_BIN"

cat > "$STUB_BIN/fake-collector" <<'STUB'
#!/bin/bash
set -u
out=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    --out) out="$2"; shift 2 ;;
    *) shift ;;
  esac
done
case "${FAKE_COLLECT_MODE:-ok}" in
  fail) echo "collector: 模拟采集失败" >&2; exit 3 ;;
  empty) exit 0 ;;
  ok)
    if [ -n "$out" ]; then
      printf '%s' "${FAKE_COLLECT_CONTENT:-snapshot-content}" > "$out"
    fi
    exit 0
    ;;
  *) echo "unknown FAKE_COLLECT_MODE" >&2; exit 9 ;;
esac
STUB

cat > "$STUB_BIN/docker" <<'STUB'
#!/bin/bash
set -u
is_php=0
is_test=0
for a in "$@"; do
  if [ "$a" = "php" ]; then is_php=1; fi
  if [ "$a" = "test" ]; then is_test=1; fi
done
if [ "$is_php" -eq 1 ]; then
  if [ -n "${FAKE_DOCKER_STDIN:-}" ]; then
    cat > "$FAKE_DOCKER_STDIN"
  else
    cat > /dev/null
  fi
  printf 'importer %s\n' "$*" >> "${FAKE_DOCKER_LOG:-/dev/null}"
  exit "${FAKE_DOCKER_IMPORT_EXIT:-0}"
fi
printf 'exec %s\n' "$*" >> "${FAKE_DOCKER_LOG:-/dev/null}"
if [ "$is_test" -eq 1 ]; then
  exit "${FAKE_DOCKER_TEST_EXIT:-0}"
fi
exit 0
STUB
chmod +x "$STUB_BIN/fake-collector" "$STUB_BIN/docker"

# 自检：该 bug 只在 UTF-8 locale 下触发。若当前 /bin/bash 不把它当变量名，
# 说明回归保护被环境弱化，明确提示（不当作失败）。
if env -i PATH="/usr/bin:/bin" LANG="C.UTF-8" LC_ALL="C.UTF-8" \
  "$BASH_BIN" -c 'set -u; v=1; echo "$v，"' >/dev/null 2>&1; then
  echo "注意：当前 $BASH_BIN 在 UTF-8 locale 下未复现「中文标点紧邻变量」问题，回归保护可能弱化"
fi

run_wrapper() { # run_wrapper <case-dir> <mode> <content> <test-exit> <import-exit> [wrapper args...]
  case_dir="$1"; mode="$2"; content="$3"; test_exit="$4"; import_exit="$5"
  shift 5
  env -i \
    PATH="$STUB_BIN:/usr/bin:/bin" \
    HOME="$TMP_ROOT/home" \
    ACADEMIC_SYNC_PYTHON="$STUB_BIN/fake-collector" \
    ACADEMIC_SYNC_COMPOSE="$case_dir/compose.yaml" \
    ACADEMIC_SYNC_SNAPSHOT="$case_dir/snapshot.json" \
    ACADEMIC_SYNC_LOG_DIR="$case_dir/logs" \
    ACADEMIC_SYNC_SERVICE="web" \
    LANG="C.UTF-8" \
    LC_ALL="C.UTF-8" \
    FAKE_COLLECT_MODE="$mode" \
    FAKE_COLLECT_CONTENT="$content" \
    FAKE_DOCKER_LOG="$case_dir/docker.log" \
    FAKE_DOCKER_STDIN="$case_dir/import.stdin" \
    FAKE_DOCKER_TEST_EXIT="$test_exit" \
    FAKE_DOCKER_IMPORT_EXIT="$import_exit" \
    "$BASH_BIN" "$WRAPPER" "$@" > "$case_dir/stdout.log" 2>&1
}

new_case() { # new_case <name>
  case_dir="$TMP_ROOT/$1"
  mkdir -p "$case_dir/logs"
  : > "$case_dir/docker.log"
  : > "$case_dir/import.stdin"
  printf '%s\n' "$case_dir"
}

# ---------------------------------------------------------------------------
# 1) 采集失败：保留旧快照，不调用 docker
# ---------------------------------------------------------------------------
echo "[1] 采集失败 -> 保留旧快照且不导入"
C1="$(new_case collect-fail)"
printf 'OLD-SNAPSHOT' > "$C1/snapshot.json"
rc=0
run_wrapper "$C1" fail "" 0 0 || rc=$?
if [ "$rc" -ne 0 ]; then ok "wrapper 非零退出 (rc=$rc)"; else bad "wrapper 应非零退出"; fi
check "旧快照未被覆盖" "OLD-SNAPSHOT" "$(cat "$C1/snapshot.json")"
if [ ! -s "$C1/docker.log" ]; then ok "采集失败未调用 docker/importer"; else bad "采集失败却调用了 docker"; fi
check_contains "输出说明保留旧快照" "保留旧快照" "$C1/stdout.log"

# ---------------------------------------------------------------------------
# 2) 采集成功默认 dry-run
# ---------------------------------------------------------------------------
echo "[2] 采集成功默认 dry-run -> importer 不带 --apply，stdin=快照"
C2="$(new_case dry-run)"
rc=0
run_wrapper "$C2" ok '{"total":2,"colleges":[]}' 0 0 || rc=$?
check "wrapper 退出码" "0" "$rc"
check "快照已更新" '{"total":2,"colleges":[]}' "$(cat "$C2/snapshot.json")"
check_contains "调用容器内 test -f" "exec compose" "$C2/docker.log"
check_contains "调用 importer" "importer" "$C2/docker.log"
check_not_contains "默认不带 --apply" "--apply" "$C2/docker.log"
check "importer stdin 等于快照" '{"total":2,"colleges":[]}' "$(cat "$C2/import.stdin")"
check_contains "日志中文行可打印" "开始同步" "$C2/stdout.log"
check_contains "输出说明 dry-run" "dry-run 完成" "$C2/stdout.log"

# ---------------------------------------------------------------------------
# 3) --apply 透传
# ---------------------------------------------------------------------------
echo "[3] --apply -> importer 收到 --apply"
C3="$(new_case apply)"
rc=0
run_wrapper "$C3" ok '{"total":1}' 0 0 --apply || rc=$?
check "wrapper 退出码" "0" "$rc"
check_contains "importer 收到 --apply" "--apply" "$C3/docker.log"
check_contains "输出说明已写入" "已写入数据库" "$C3/stdout.log"

# ---------------------------------------------------------------------------
# 4) importer 失败 -> wrapper 非零
# ---------------------------------------------------------------------------
echo "[4] importer 失败 -> wrapper 非零退出"
C4="$(new_case import-fail)"
rc=0
run_wrapper "$C4" ok '{"total":1}' 0 4 --apply || rc=$?
if [ "$rc" -ne 0 ]; then ok "wrapper 非零退出 (rc=$rc)"; else bad "importer 失败时 wrapper 应非零退出"; fi
check_contains "输出说明导入失败" "导入失败" "$C4/stdout.log"

# ---------------------------------------------------------------------------
# 5) 采集成功但快照为空 -> 不导入
# ---------------------------------------------------------------------------
echo "[5] 空快照 -> 不导入"
C5="$(new_case empty-snapshot)"
rc=0
run_wrapper "$C5" empty "" 0 0 || rc=$?
if [ "$rc" -ne 0 ]; then ok "wrapper 非零退出 (rc=$rc)"; else bad "空快照时 wrapper 应非零退出"; fi
if [ ! -s "$C5/docker.log" ]; then ok "空快照未调用 docker/importer"; else bad "空快照却调用了 docker"; fi

# ---------------------------------------------------------------------------
# 6) 容器缺少 importer -> 不导入
# ---------------------------------------------------------------------------
echo "[6] 容器缺少 importer -> 不导入"
C6="$(new_case missing-importer)"
rc=0
run_wrapper "$C6" ok '{"total":1}' 1 0 --apply || rc=$?
if [ "$rc" -ne 0 ]; then ok "wrapper 非零退出 (rc=$rc)"; else bad "缺少 importer 时 wrapper 应非零退出"; fi
check_contains "提示重新构建镜像" "请先重新构建" "$C6/stdout.log"
if grep -q '^importer ' "$C6/docker.log" 2>/dev/null; then
  bad "未调用 importer (docker.log 仍有 importer 调用)"
else
  ok "未调用 importer"
fi

# ---------------------------------------------------------------------------
# 7) 安装脚本可在 bash 3.2 下运行
# ---------------------------------------------------------------------------
echo "[7] install-launchagent.sh 在 /bin/bash 3.2 下生成 plist"
C7="$(new_case install)"
mkdir -p "$C7/home"
rc=0
env -i PATH="/usr/bin:/bin" HOME="$C7/home" LANG="C.UTF-8" LC_ALL="C.UTF-8" \
  "$BASH_BIN" "$INSTALLER" > "$C7/stdout.log" 2>&1 || rc=$?
check "installer 退出码" "0" "$rc"
PLIST="$C7/home/Library/LaunchAgents/com.hnieoj.academic-directory-sync.plist"
if [ -s "$PLIST" ]; then ok "plist 已生成"; else bad "plist 未生成"; fi
check_contains "plist 展开项目路径" "$ROOT_DIR" "$PLIST"
check_not_contains "plist 无占位符残留" "__PROJECT_DIR__" "$PLIST"
check_contains "输出生成路径" "已生成" "$C7/stdout.log"

# ---------------------------------------------------------------------------
echo
echo "wrapper 回归测试：$PASS 通过，$FAIL 失败"
if [ "$FAIL" -ne 0 ]; then
  exit 1
fi
exit 0
