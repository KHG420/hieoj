# 注册验证码邮件（腾讯云 SES）运维说明

适用于统一 Compose 部署：web 服务 `compose.yaml` + php-fpm pool
`docker/conf/php/www.conf`。注册、找回密码、修改邮箱共用明文 GET 端点
`/sendemail.php`；成功响应串保持既有约定「验证码已发送至您的邮箱，请查收。」。

## 配置来源

- 凭据只保存在服务器 Compose 项目目录下的 `.env`，**严禁提交到 Git**。需要两个键：
  - `TENCENTCLOUD_SECRET_ID`
  - `TENCENTCLOUD_SECRET_KEY`
- `compose.yaml` 通过 `${TENCENTCLOUD_SECRET_ID:-}` / `${TENCENTCLOUD_SECRET_KEY:-}`
  显式注入 web 容器；服务器 `.env` 缺少任一键时容器内为空字符串，端点安全失败关闭。
- php-fpm 默认 `clear_env = yes`，会清空容器环境变量，因此 `docker/conf/php/www.conf`
  必须显式加入 `env[TENCENTCLOUD_SECRET_ID]` / `env[TENCENTCLOUD_SECRET_KEY]` 放行，
  否则 `getenv()` 读不到。
- 保护服务器 `.env`：仅 root / 部署账号可读（`chmod 600 .env`），不要 `cat`、不要
  写入 shell 历史、不要贴进工单或聊天。

## 生效步骤

`.env`、`compose.yaml` 或 php-fpm pool 任一变更后，web 需要重建（pool 配置烘焙进镜像）：

```bash
cd /opt/hnieoj
docker compose up -d --no-deps --build --wait web
docker compose ps
```

## 不打印明文的安全检查

```bash
# .env 是否含键（只输出计数，不打印值）
grep -c '^TENCENTCLOUD_SECRET_ID=' .env
grep -c '^TENCENTCLOUD_SECRET_KEY=' .env

# 容器内是否注入且非空（只输出 yes / no）
docker compose exec -T web sh -lc \
  'test -n "$TENCENTCLOUD_SECRET_ID" && test -n "$TENCENTCLOUD_SECRET_KEY" && echo yes || echo no'

# 容器内 CLI 进程是否可见（只验证 CLI 环境变量，不代表 php-fpm 工作进程；只输出 bool）
docker compose exec -T web php -r \
  'var_dump(getenv("TENCENTCLOUD_SECRET_ID") !== false && getenv("TENCENTCLOUD_SECRET_ID") !== "");'
```

注意：`docker compose exec ... php -r` 只是容器 CLI 环境检查，它不经过 php-fpm 的
`clear_env = yes` / `env[...]` 放行逻辑，因此**不能**证明 php-fpm 工作进程也能读到凭据。
要验证完整 FPM 链路，请对真实端点发起一次注册验证码请求（收件人用自己的邮箱）：缺凭据时
会返回「邮件服务暂时不可用，请稍后再试。」，并在容器日志出现
`Tencent Cloud SES credentials are not configured.`。不要为此新增公开诊断端点。

不要使用 `printenv TENCENTCLOUD_SECRET_ID`、`docker compose config`（会展开并打印值）
或其它会回显凭据的命令。

## 行为约定

- 验证码：6 位、`random_int`，成功后写入 `$_SESSION['code']`，
  `$_SESSION['time'] = time() + 180`，`$_SESSION['email']` 为收件人；沿用既有 session
  键约定，`register.php` / `forgetpassword.php` / `modify2.php` 直接读取。
- 只有腾讯云 SES 返回 HTTP 2xx、合法 JSON、非空 `Response.MessageId` 且无
  `Response.Error` 时才写入验证码与限流。空 / 畸形 / 供应商 Error / 网络失败一律
  失败关闭：不写验证码、不续期、不产生冷却。
- 反滥用：同一 `REMOTE_ADDR` + 规范化收件邮箱 3 分钟一次；不同邮箱互不影响，
  不再用共享校园出口 IP 单独拦人。冷却只在发送成功后写入。
- 同一邮箱已有有效验证码时返回等待；换成其它邮箱可立即重发（纠正拼写）。
- 失败只记录脱敏后的 `code` / `requestId`（或网络 `code`），不输出凭据、签名、
  原始响应或供应商错误详情。

## 回归测试（无网络、无真实邮件、无真实凭据）

```bash
docker run --rm --network none -v "$PWD:/workspace:ro" --entrypoint php \
  hnieoj-unified-web:latest /workspace/docker/tests/sendemail-registration.php
```

测试在 `php -n` 子进程中直接执行真实 `web/sendemail.php`，以 userland stub 替换 curl
边界并记录缓存读写，校验成功 / 缺凭据 / 供应商拒绝 / HTTP 错误 / 空响应 / 畸形响应 /
缺 MessageId / 网络失败的 session 与冷却行为。

## 常见日志

- `Tencent Cloud SES credentials are not configured.`：web 容器内两个环境变量缺失，
  按上文补齐服务器 `.env` 并重建 web。
- `sendemail: SES send rejected http=... code=... requestId=...`：腾讯云拒绝，按 code
  排查模板 / 发信地址 / 权限；该行不含密钥与原始响应。
- `sendemail: SES transport failure code=28`：调用超时（15s），检查出网 / 防火墙。
