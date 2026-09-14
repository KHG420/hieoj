<?php
/**
 * 学院班级目录：教务源采集（登录 / 验证码 / 学院 / 班级分页）。
 *
 * 设计要点：
 *   - 只依赖 PHP cURL + 正则/JSON，不引入新依赖，也不执行远端 JS；
 *   - 固定 origin（函数内写死，不接受用户传入 URL）；
 *   - 传输层用内存 cookie（不落盘），并允许测试注入 fake 传输对象；
 *   - 纯解析函数可脱离网络单独测试；
 *   - 本文件不在 include 时连接数据库，也不输出任何内容。
 *
 * 协议（来自 login-source.html / login-conwork.js，已验证）：
 *   POST /jsxsd/xk/LoginToXk
 *     loginMethod=LoginToXk
 *     userAccount=<账号>
 *     userPassword=            （空，密码只放在 encoded 里）
 *     RANDOMCODE=<验证码>
 *     encoded=base64(账号) + "%%%" + base64(密码)
 */

/** 固定源地址与抓取上限。真实部署不允许用户设置 endpoint。 */
function academic_directory_source_config()
{
    return array(
        'origin' => 'https://jwcmis.hnie.edu.cn',
        'login_path' => '/jsxsd/',
        'captcha_path' => '/jsxsd/verifycode.servlet',
        'login_post_path' => '/jsxsd/xk/LoginToXk',
        'student_path' => '/jsxsd/framework/xsMainV.htmlx',
        'frm_path' => '/jsxsd/view/kbxx/kbcx/llsykb_frm.jsp',
        'colleges_path' => '/tkglAction.do?method=llsykbFind&kbtype=xx04&init=1&isview=1',
        'classes_path' => '/common/llsykb/xx04_select.htmlx?id=xx04id&name=xx04mc&type=1&where=',
        'page_size' => 500,
        'max_pages' => 60,
        'max_page_bytes' => 2097152,
        'max_total_bytes' => 25165824,
        'max_redirects' => 3,
        'connect_timeout' => 10,
        'read_timeout' => 30,
    );
}

/** 临时 challenge / preview 的服务端有效期（秒）。 */
function academic_directory_source_ttl()
{
    return 600;
}

// ---------------------------------------------------------------------------
// 解析（纯函数）
// ---------------------------------------------------------------------------

/**
 * 从 $source[$start] == '[' 起按括号 / 字符串状态截取完整 JSON 数组。
 */
function academic_directory_source_extract_balanced_array($source, $start)
{
    if (!is_string($source) || $start < 0 || $start >= strlen($source) || $source[$start] !== '[') {
        throw new RuntimeException('未找到 qz_option data 数组起始位置');
    }
    $depth = 0;
    $quote = null;
    $escaped = false;
    $length = strlen($source);
    for ($i = $start; $i < $length; $i++) {
        $ch = $source[$i];
        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === '\\') {
                $escaped = true;
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === '"' || $ch === "'") {
            $quote = $ch;
        } elseif ($ch === '[') {
            $depth++;
        } elseif ($ch === ']') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }
    throw new RuntimeException('qz_option data 数组未闭合');
}

/** 解析页面内联脚本里的 qz_option data（合法 JSON 数组），不使用 eval。 */
function academic_directory_source_parse_qz_data($pageHtml)
{
    if (!is_string($pageHtml) || $pageHtml === '') {
        throw new RuntimeException('班级页面为空');
    }
    if (!preg_match('/qz_option\s*=\s*\{/', $pageHtml)) {
        throw new RuntimeException('页面缺少 qz_option 对象');
    }
    $anchor = strpos($pageHtml, 'qz_option');
    $rel = strpos($pageHtml, 'data:', $anchor);
    if ($rel === false) {
        throw new RuntimeException('页面 qz_option 缺少 data 字段');
    }
    $bracket = strpos($pageHtml, '[', $rel);
    if ($bracket === false) {
        throw new RuntimeException('页面 qz_option data 不是数组');
    }
    $raw = academic_directory_source_extract_balanced_array($pageHtml, $bracket);
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        throw new RuntimeException('qz_option data 不是合法 JSON 数组');
    }
    return $rows;
}

/** 读取隐藏域 dataTotal。 */
function academic_directory_source_parse_total($pageHtml)
{
    $patterns = array(
        '/id\s*=\s*["\']?dataTotal["\']?[^>]*?value\s*=\s*["\']?(\d+)/i',
        '/value\s*=\s*["\']?(\d+)["\']?[^>]*?id\s*=\s*["\']?dataTotal/i',
    );
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $pageHtml, $m)) {
            return (int)$m[1];
        }
    }
    return null;
}

/** 解析 createPage({pageNum,current,total,each})。 */
function academic_directory_source_parse_pagination($pageHtml)
{
    if (!preg_match('/createPage\s*\(\s*\{(.*?)\}\s*\)/s', $pageHtml, $m)) {
        return array();
    }
    $body = $m[1];
    $result = array();
    foreach (array('pageNum', 'current', 'total', 'each') as $key) {
        if (preg_match('/' . $key . '\s*:\s*(\d+)/', $body, $found)) {
            $result[$key] = (int)$found[1];
        }
    }
    return $result;
}

/** 去掉标签并反转义，得到纯文本。 */
function academic_directory_source_text($fragment)
{
    return trim(html_entity_decode(strip_tags((string)$fragment), ENT_QUOTES, 'UTF-8'));
}

/**
 * 从 #yxbh select 解析学院：option.value 为 source_id，option.text 为【code】名称。
 */
function academic_directory_source_parse_colleges($pageHtml)
{
    if (!preg_match(
        '/<select\b[^>]*(?:id|name)\s*=\s*["\']?yxbh["\']?[^>]*>(.*?)<\/select>/is',
        $pageHtml,
        $select
    )) {
        throw new RuntimeException('未找到 #yxbh 学院下拉');
    }
    $colleges = array();
    $seenSource = array();
    $seenCode = array();
    if (!preg_match_all('/<option\b([^>]*)>(.*?)<\/option>/is', $select[1], $options, PREG_SET_ORDER)) {
        throw new RuntimeException('学院下拉没有有效选项');
    }
    foreach ($options as $option) {
        if (!preg_match('/value\s*=\s*["\']([^"\']*)["\']/i', $option[1], $valueMatch)) {
            continue;
        }
        $sourceId = trim($valueMatch[1]);
        if ($sourceId === '') {
            continue;
        }
        $label = academic_directory_source_text($option[2]);
        $codeRaw = null;
        $name = null;
        if (preg_match('/^\s*[【\[]\s*(\d+)\s*[】\]]\s*(.+?)\s*$/u', $label, $codeMatch)) {
            $codeRaw = $codeMatch[1];
            $name = $codeMatch[2];
        } elseif (preg_match('/^\s*(\d{1,3})\s*[\s、.\-]+\s*(.+?)\s*$/u', $label, $codeMatch)) {
            $codeRaw = $codeMatch[1];
            $name = $codeMatch[2];
        }
        if ($codeRaw === null) {
            throw new RuntimeException('无法从学院选项解析 code');
        }
        $code = (int)$codeRaw;
        $name = trim($name);
        if ($code <= 0) {
            throw new RuntimeException("学院 {$sourceId} 的 code 非法");
        }
        if ($name === '') {
            throw new RuntimeException("学院 {$sourceId} 名称为空");
        }
        if (isset($seenSource[$sourceId])) {
            throw new RuntimeException("学院 source_id 重复：{$sourceId}");
        }
        if (isset($seenCode[$code])) {
            throw new RuntimeException("学院 code 重复：{$code}");
        }
        $seenSource[$sourceId] = true;
        $seenCode[$code] = true;
        $colleges[] = array('source_id' => $sourceId, 'code' => (string)$codeRaw, 'name' => $name);
    }
    if (count($colleges) === 0) {
        throw new RuntimeException('学院下拉没有有效选项');
    }
    return $colleges;
}

/** 解析单页：返回 array('rows'=>..,'pagination'=>..,'total'=>..)。 */
function academic_directory_source_parse_class_page($pageHtml)
{
    $rows = academic_directory_source_parse_qz_data($pageHtml);
    $pagination = academic_directory_source_parse_pagination($pageHtml);
    $total = academic_directory_source_parse_total($pageHtml);
    if ($total === null && isset($pagination['total'])) {
        $total = (int)$pagination['total'];
    }
    return array('rows' => $rows, 'pagination' => $pagination, 'total' => $total);
}

/**
 * 由学院列表 + 全部分页构建快照，逐页校验 createPage/dataTotal/条数一致。
 * 任一分页字段缺失、页数 / 条数 / 编号 / 源 ID 不一致都拒绝，保证不产出截断快照。
 *
 * @param array $pages array(array('page'=>int,'html'=>string), ...)
 */
function academic_directory_source_build_snapshot(array $colleges, array $pages)
{
    if (count($colleges) === 0) {
        throw new RuntimeException('学院列表为空');
    }
    $collegeBySource = array();
    foreach ($colleges as $college) {
        $collegeBySource[(string)$college['source_id']] = (string)$college['name'];
    }

    $pageNumbers = array();
    foreach ($pages as $page) {
        if (!isset($page['page'], $page['html'])) {
            throw new RuntimeException('班级分页数据缺少 page 字段');
        }
        $pageNumbers[] = (int)$page['page'];
    }
    sort($pageNumbers);
    if ($pageNumbers !== range(1, count($pages))) {
        throw new RuntimeException('班级分页不连续');
    }

    $allRows = array();
    $expectedTotal = null;
    $pageSize = null;
    $totalPages = count($pages);

    usort($pages, function ($a, $b) {
        return (int)$a['page'] - (int)$b['page'];
    });

    foreach ($pages as $page) {
        $pageNo = (int)$page['page'];
        $parsed = academic_directory_source_parse_class_page((string)$page['html']);
        $pagination = $parsed['pagination'];
        foreach (array('pageNum', 'current', 'total', 'each') as $key) {
            if (!isset($pagination[$key])) {
                throw new RuntimeException("第 {$pageNo} 页分页信息缺少 {$key}");
            }
        }
        $each = (int)$pagination['each'];
        $createPageTotal = (int)$pagination['total'];
        $total = $parsed['total'];
        if ($total === null || $total <= 0) {
            throw new RuntimeException("第 {$pageNo} 页缺少 total");
        }
        if ($createPageTotal <= 0 || $createPageTotal !== $total) {
            throw new RuntimeException("第 {$pageNo} 页 createPage total 与 dataTotal 不一致");
        }
        if ($each <= 0) {
            throw new RuntimeException("第 {$pageNo} 页 each 非法");
        }
        if ((int)$pagination['current'] !== $pageNo) {
            throw new RuntimeException("第 {$pageNo} 页 current 不匹配");
        }
        if ($expectedTotal === null) {
            $expectedTotal = $total;
        } elseif ($total !== $expectedTotal) {
            throw new RuntimeException("第 {$pageNo} 页 total 与首页不一致");
        }
        if ($pageSize === null) {
            $pageSize = $each;
        } elseif ($each !== $pageSize) {
            throw new RuntimeException("第 {$pageNo} 页 each 与首页不一致");
        }
        if ((int)$pagination['pageNum'] !== $totalPages) {
            throw new RuntimeException("第 {$pageNo} 页 pageNum 与抓取页数不一致");
        }
        if ((int)$pagination['pageNum'] !== (int)ceil($expectedTotal / $pageSize)) {
            throw new RuntimeException("第 {$pageNo} 页 pageNum 与 total/each 不一致");
        }
        $remaining = $expectedTotal - ($pageNo - 1) * $pageSize;
        $expectedRows = $remaining >= $pageSize ? $pageSize : $remaining;
        if (count($parsed['rows']) !== $expectedRows) {
            throw new RuntimeException("第 {$pageNo} 页条数 " . count($parsed['rows']) . " 与预期 {$expectedRows} 不符");
        }
        foreach ($parsed['rows'] as $row) {
            $allRows[] = $row;
        }
    }

    if (count($allRows) !== $expectedTotal) {
        throw new RuntimeException('总条数 ' . count($allRows) . " 与 total {$expectedTotal} 不一致");
    }

    $seenSource = array();
    $seenBh = array();
    $classes = array();
    foreach ($allRows as $row) {
        $sourceId = isset($row['field0']) ? trim((string)$row['field0']) : '';
        $bh = isset($row['bh']) ? trim((string)$row['bh']) : '';
        $bj = isset($row['bj']) ? trim((string)$row['bj']) : '';
        $collegeSource = isset($row['field6']) ? trim((string)$row['field6']) : '';
        $collegeName = isset($row['xx0301$dwmc']) ? trim((string)$row['xx0301$dwmc']) : '';
        if ($sourceId === '') {
            throw new RuntimeException('存在缺少 field0 的班级');
        }
        if ($bh === '' || $bj === '') {
            throw new RuntimeException("班级 {$sourceId} 缺少编号或名称");
        }
        if (!isset($collegeBySource[$collegeSource])) {
            throw new RuntimeException("班级 {$sourceId} 引用了未知学院 {$collegeSource}");
        }
        if ($collegeName !== '' && $collegeName !== $collegeBySource[$collegeSource]) {
            throw new RuntimeException("班级 {$sourceId} 的学院名称与学院列表不一致");
        }
        if (isset($seenSource[$sourceId])) {
            throw new RuntimeException("班级稳定源ID重复：{$sourceId}");
        }
        if (isset($seenBh[$bh])) {
            throw new RuntimeException("班级显示编号重复：{$bh}");
        }
        $seenSource[$sourceId] = true;
        $seenBh[$bh] = true;
        // 只保留执行器 / 校验所需的字段，控制 session 体积；格式与原快照一致。
        $classes[] = array(
            'field0' => $sourceId,
            'bh' => $bh,
            'bj' => $bj,
            'field6' => $collegeSource,
            'xx0301$dwmc' => $collegeName,
        );
    }

    return array('colleges' => $colleges, 'total' => $expectedTotal, 'classes' => $classes);
}

// ---------------------------------------------------------------------------
// 协议（纯函数 + 校验）
// ---------------------------------------------------------------------------

/** encoded = base64(账号) + "%%%" + base64(密码)（标准 base64，与 conwork.js 一致）。 */
function academic_directory_source_encode_credentials($account, $password)
{
    return base64_encode((string)$account) . '%%%' . base64_encode((string)$password);
}

/** 页面是否是登录表单（登录失败 / 未登录的标志）。 */
function academic_directory_source_is_login_page($html)
{
    if (!is_string($html) || $html === '') {
        return false;
    }
    if (preg_match('/id\s*=\s*["\']?loginForm["\']?/i', $html)) {
        return true;
    }
    if (preg_match('/name\s*=\s*["\']?userPassword["\']?/i', $html)) {
        return true;
    }
    return (bool)(preg_match('#/jsxsd/xk/LoginToXk#i', $html) && preg_match('/RANDOMCODE/i', $html));
}

/** 页面是否是登录后的学生首页（非登录页且带学生框架标记）。 */
function academic_directory_source_is_student_page($html)
{
    if (academic_directory_source_is_login_page($html)) {
        return false;
    }
    return (bool)preg_match('#xsMain|mainFrame|jsxsd/framework#i', $html);
}

/**
 * 校验并规范化同源地址：仅允许与 origin 同主机同端口，强制 HTTPS；
 * 拒绝其他 scheme / host / 端口 / 用户信息。允许可选路径前缀白名单。
 */
function academic_directory_source_same_origin_url($origin, $target, $allowedPath = null)
{
    $originParts = parse_url((string)$origin);
    if (!is_array($originParts) || !isset($originParts['scheme'], $originParts['host'])) {
        throw new RuntimeException('源地址无效');
    }
    $originScheme = strtolower($originParts['scheme']);
    $originHost = strtolower($originParts['host']);
    if ($originScheme !== 'https') {
        throw new RuntimeException('源地址必须是 HTTPS');
    }
    $originPort = isset($originParts['port']) ? (int)$originParts['port'] : 443;

    $target = trim((string)$target);
    if ($target === '') {
        throw new RuntimeException('跳转地址为空');
    }
    if (strpos($target, '//') === 0) {
        $target = $originScheme . ':' . $target;
    }
    $parts = parse_url($target);
    if ($parts === false) {
        throw new RuntimeException('跳转地址无效');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('拒绝含用户信息的地址');
    }
    if (isset($parts['scheme'])) {
        $targetScheme = strtolower($parts['scheme']);
        if (!in_array($targetScheme, array('http', 'https'), true)) {
            throw new RuntimeException('拒绝非 HTTP 跳转');
        }
        if (!isset($parts['host'])) {
            throw new RuntimeException('跳转地址缺少主机');
        }
    }
    if (isset($parts['host'])) {
        if (strtolower($parts['host']) !== $originHost) {
            throw new RuntimeException('拒绝跨域跳转');
        }
        $targetScheme = strtolower(isset($parts['scheme']) ? $parts['scheme'] : $originScheme);
        if (!in_array($targetScheme, array('http', 'https'), true)) {
            throw new RuntimeException('拒绝非 HTTP 跳转');
        }
        $targetPort = isset($parts['port']) ? (int)$parts['port'] : 443;
        if ($targetPort !== $originPort) {
            throw new RuntimeException('拒绝跨端口跳转');
        }
    }
    $path = isset($parts['path']) ? (string)$parts['path'] : '/';
    if ($path === '') {
        $path = '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    $path = preg_replace('#^/+#', '/', $path);
    if ($allowedPath !== null) {
        $allowed = '/' . ltrim((string)$allowedPath, '/');
        if (strpos($path, $allowed) !== 0) {
            throw new RuntimeException('拒绝非预期路径');
        }
    }
    $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
    return 'https://' . $originHost . $path . $query;
}

/**
 * 从课表模块页解析 iframe 的 /Logon.do?method=toFinGlKbCx&token=... 地址，
 * 校验同源 / 路径 / 方法与 token 后返回 HTTPS 完整地址（不输出 token）。
 */
function academic_directory_source_parse_logon_url($html, $origin)
{
    if (!preg_match('#src\s*=\s*["\']([^"\']*/Logon\.do\?[^"\']*toFinGlKbCx[^"\']*)["\']#i', (string)$html, $m)) {
        throw new RuntimeException('未找到课表会话交换地址');
    }
    $raw = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    $url = academic_directory_source_same_origin_url($origin, $raw, '/Logon.do');
    $parts = parse_url($url);
    parse_str(isset($parts['query']) ? $parts['query'] : '', $query);
    if (!isset($query['method']) || $query['method'] !== 'toFinGlKbCx') {
        throw new RuntimeException('课表会话交换参数不正确');
    }
    if (!isset($query['token']) || (string)$query['token'] === '') {
        throw new RuntimeException('课表会话交换缺少令牌');
    }
    return $url;
}

// ---------------------------------------------------------------------------
// 传输层（内存 cookie，不落盘）
// ---------------------------------------------------------------------------

/**
 * cURL 传输：单句柄 + 内存 cookie 引擎，手动处理同源跳转。
 *
 * 具备 get()/post()/cookies()/setCookies() 四个方法；测试可注入同形状的 fake 对象。
 */
class AcademicDirectoryHttp
{
    private $ch;
    private $config;
    private $closed = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('无法初始化网络请求');
        }
        $this->ch = $ch;
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_COOKIEFILE => '',            // 仅内存 cookie，不创建磁盘文件
            CURLOPT_FOLLOWLOCATION => false,     // 跳转一律手动校验
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => (int)$config['connect_timeout'],
            CURLOPT_TIMEOUT => (int)$config['read_timeout'],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HNIEOJ-AcademicDirectory/1.0)',
            CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8'),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_ENCODING => '',
        ));
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if (!$this->closed && $this->ch) {
            curl_close($this->ch);
            $this->closed = true;
        }
    }

    /** 恢复服务端 session 中保存的远端 cookie（Netscape 行）。 */
    public function setCookies(array $lines)
    {
        foreach ($lines as $line) {
            if (is_string($line) && $line !== '') {
                curl_setopt($this->ch, CURLOPT_COOKIELIST, $line);
            }
        }
    }

    /** 导出当前内存 cookie（Netscape 行）。 */
    public function cookies()
    {
        $list = curl_getinfo($this->ch, CURLINFO_COOKIELIST);
        return is_array($list) ? $list : array();
    }

    public function get($url)
    {
        return $this->send('GET', $url, null);
    }

    public function post($url, array $fields)
    {
        return $this->send('POST', $url, $fields);
    }

    private function send($method, $url, $fields)
    {
        $maxBytes = (int)$this->config['max_page_bytes'];
        $body = '';
        $headers = array();
        $ch = $this->ch;

        curl_setopt($ch, CURLOPT_URL, $url);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$headers) {
            $trimmed = trim($line);
            if ($trimmed !== '' && strpos($trimmed, ':') !== false) {
                $pair = explode(':', $trimmed, 2);
                $headers[strtolower(trim($pair[0]))] = trim($pair[1]);
            }
            return strlen($line);
        });
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$body, $maxBytes) {
            $body .= $chunk;
            if (strlen($body) > $maxBytes) {
                return 0; // 超限中止，防止一次拉取过大响应
            }
            return strlen($chunk);
        });

        $ok = curl_exec($ch);
        if ($ok === false) {
            throw new RuntimeException('网络请求失败');
        }
        return array(
            'status' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => $body,
            'headers' => $headers,
        );
    }
}

/** 读取响应头（小写名）。 */
function academic_directory_source_http_header(array $headers, $name)
{
    $name = strtolower((string)$name);
    return isset($headers[$name]) ? $headers[$name] : null;
}

/** 同源 GET，手动跟随有限次同源跳转（http 自动升级为 https）。 */
function academic_directory_source_http_get($http, array $config, $url, $allowedPath = null)
{
    $current = academic_directory_source_same_origin_url($config['origin'], $url, $allowedPath);
    $max = (int)$config['max_redirects'];
    for ($i = 0; $i <= $max; $i++) {
        $res = $http->get($current);
        $status = (int)$res['status'];
        if (in_array($status, array(301, 302, 303, 307, 308), true)) {
            if ($i === $max) {
                throw new RuntimeException('远端跳转次数过多');
            }
            $location = academic_directory_source_http_header($res['headers'], 'location');
            if ($location === null || $location === '') {
                throw new RuntimeException('远端返回了无效跳转');
            }
            $current = academic_directory_source_same_origin_url($config['origin'], $location);
            continue;
        }
        return $res;
    }
    throw new RuntimeException('远端跳转次数过多');
}

// ---------------------------------------------------------------------------
// 采集编排
// ---------------------------------------------------------------------------

/**
 * 第一步：建立远端会话并取验证码。
 * 返回 cookies（服务端保存）、验证码图片字节与 content-type。
 */
function academic_directory_source_challenge($http, array $config)
{
    $login = academic_directory_source_http_get($http, $config, $config['origin'] . $config['login_path']);
    if ((int)$login['status'] !== 200) {
        throw new RuntimeException('无法打开教务登录页');
    }
    $captcha = academic_directory_source_http_get($http, $config, $config['origin'] . $config['captcha_path']);
    if ((int)$captcha['status'] !== 200 || (string)$captcha['body'] === '') {
        throw new RuntimeException('无法获取验证码图片');
    }
    $contentType = 'image/jpeg';
    $rawType = academic_directory_source_http_header($captcha['headers'], 'content-type');
    if ($rawType !== null && stripos($rawType, 'image/') === 0) {
        $parts = explode(';', $rawType);
        $contentType = strtolower(trim($parts[0]));
    }
    return array(
        'cookies' => $http->cookies(),
        'body' => (string)$captcha['body'],
        'content_type' => $contentType,
    );
}

/** 班级分页 URL（PageNum 从 1 开始）。 */
function academic_directory_source_page_url(array $config, $page)
{
    return $config['origin'] . $config['classes_path']
        . '&PageNum=' . (int)$page . '&pageSize=' . (int)$config['page_size'];
}

/** 抓取全部班级分页；任何一页失败 / 超限 / 总数变化都拒绝。 */
function academic_directory_source_fetch_pages($http, array $config)
{
    $first = academic_directory_source_http_get($http, $config, academic_directory_source_page_url($config, 1));
    if ((int)$first['status'] !== 200) {
        throw new RuntimeException('无法读取班级第 1 页');
    }
    $totalBytes = strlen($first['body']);
    $parsed = academic_directory_source_parse_class_page($first['body']);
    $total = $parsed['total'];
    if ($total === null || $total <= 0) {
        throw new RuntimeException('班级首页缺少总数');
    }
    $each = isset($parsed['pagination']['each']) ? (int)$parsed['pagination']['each'] : (int)$config['page_size'];
    if ($each <= 0) {
        throw new RuntimeException('班级分页大小非法');
    }
    $pageCount = (int)ceil($total / $each);
    if ($pageCount < 1 || $pageCount > (int)$config['max_pages']) {
        throw new RuntimeException('班级页数超出上限');
    }

    $pages = array(array('page' => 1, 'html' => $first['body']));
    for ($page = 2; $page <= $pageCount; $page++) {
        $res = academic_directory_source_http_get($http, $config, academic_directory_source_page_url($config, $page));
        if ((int)$res['status'] !== 200) {
            throw new RuntimeException("无法读取班级第 {$page} 页");
        }
        $totalBytes += strlen($res['body']);
        if ($totalBytes > (int)$config['max_total_bytes']) {
            throw new RuntimeException('班级数据超出大小上限');
        }
        $parsedPage = academic_directory_source_parse_class_page($res['body']);
        if ($parsedPage['total'] !== $total) {
            throw new RuntimeException("第 {$page} 页总数与首页不一致");
        }
        $pages[] = array('page' => $page, 'html' => $res['body']);
    }
    return $pages;
}

/**
 * 第二步：用临时账号 / 密码 / 验证码登录并完整采集，返回快照。
 *
 * 采集与分页完整性校验始终针对**完整**数据；在完整构建并通过既有
 * academic_directory_snapshot_validate() 之后，再收窄到目录同步范围
 * （仅 2024 级及以后，含 2024）。没有任何符合范围的班级时抛异常，不返回空快照。
 * 密码只在本函数内存中存在；返回前 $encoded / POST 字段均被丢弃。
 */
function academic_directory_source_preview($http, array $config, $account, $password, $captcha)
{
    $account = (string)$account;
    $password = (string)$password;
    $captcha = (string)$captcha;
    if ($account === '' || $password === '' || $captcha === '') {
        throw new RuntimeException('账号、密码和验证码不能为空');
    }

    $encoded = academic_directory_source_encode_credentials($account, $password);
    $postFields = array(
        'loginMethod' => 'LoginToXk',
        'userAccount' => $account,
        'userPassword' => '',
        'RANDOMCODE' => $captcha,
        'encoded' => $encoded,
    );
    $loginUrl = academic_directory_source_same_origin_url($config['origin'], $config['origin'] . $config['login_post_path']);
    $res = $http->post($loginUrl, $postFields);
    unset($encoded, $postFields);
    $status = (int)$res['status'];
    if ($status !== 200 && !in_array($status, array(301, 302, 303, 307, 308), true)) {
        throw new RuntimeException('登录请求失败');
    }
    if ($status === 200 && academic_directory_source_is_login_page($res['body'])) {
        throw new RuntimeException('登录失败，请检查账号、密码和验证码');
    }

    $student = academic_directory_source_http_get($http, $config, $config['origin'] . $config['student_path']);
    if ((int)$student['status'] !== 200 || !academic_directory_source_is_student_page($student['body'])) {
        throw new RuntimeException('登录失败，请检查账号、密码和验证码');
    }

    $frm = academic_directory_source_http_get($http, $config, $config['origin'] . $config['frm_path']);
    if ((int)$frm['status'] !== 200) {
        throw new RuntimeException('无法打开课表模块');
    }
    $logonUrl = academic_directory_source_parse_logon_url($frm['body'], $config['origin']);
    $exchanged = academic_directory_source_http_get($http, $config, $logonUrl, '/Logon.do');
    if ((int)$exchanged['status'] !== 200) {
        throw new RuntimeException('课表会话交换失败');
    }

    $collegesRes = academic_directory_source_http_get($http, $config, $config['origin'] . $config['colleges_path']);
    if ((int)$collegesRes['status'] !== 200 || stripos($collegesRes['body'], 'yxbh') === false) {
        throw new RuntimeException('登录状态失效，无法读取学院列表');
    }
    $colleges = academic_directory_source_parse_colleges($collegesRes['body']);
    $pages = academic_directory_source_fetch_pages($http, $config);
    $snapshot = academic_directory_source_build_snapshot($colleges, $pages);
    // 先完整校验（与 CLI 导入 / 旧快照完全同一格式），再收窄到 2024 级及以后（含 2024）。
    // 筛选发生在完整构建 / 校验之后，不会掩盖截断或坏数据。
    return academic_directory_sync_scope_snapshot($snapshot);
}

// ---------------------------------------------------------------------------
// 服务端 session 状态 / 权限 / CSRF / nonce
// ---------------------------------------------------------------------------

function academic_directory_source_state_key($ojName)
{
    return (string)$ojName . '_academic_directory_sync';
}

function academic_directory_source_state_get(array $session, $ojName)
{
    $key = academic_directory_source_state_key($ojName);
    if (!isset($session[$key]) || !is_array($session[$key])) {
        return null;
    }
    return $session[$key];
}

function academic_directory_source_state_set(array &$session, $ojName, array $state)
{
    $session[academic_directory_source_state_key($ojName)] = $state;
}

function academic_directory_source_state_clear(array &$session, $ojName)
{
    unset($session[academic_directory_source_state_key($ojName)]);
}

/** 仅 administrator 角色可访问（contest_creator / problem_editor / 普通用户拒绝）。 */
function academic_directory_source_is_admin(array $session, $ojName)
{
    return isset($session[(string)$ojName . '_administrator']);
}

/** 现有 postkey + hash_equals 的 CSRF 校验。 */
function academic_directory_source_csrf_check(array $session, $ojName, $postkey)
{
    $key = (string)$ojName . '_postkey';
    if (!isset($session[$key]) || !is_string($postkey) || $postkey === '') {
        return false;
    }
    return hash_equals((string)$session[$key], $postkey);
}

/** challenge（验证码会话）是否仍有效：nonce 匹配且未过期。 */
function academic_directory_source_challenge_check($state, $nonce, $now)
{
    if (!is_array($state) || !isset($state['challenge_nonce'], $state['challenge_expiry'])) {
        return false;
    }
    if (!is_string($nonce) || $nonce === '' || $state['challenge_nonce'] === null) {
        return false;
    }
    if (!hash_equals((string)$state['challenge_nonce'], $nonce)) {
        return false;
    }
    return (int)$state['challenge_expiry'] >= (int)$now;
}

/** preview（已校验快照）是否仍有效：nonce 匹配、未过期且确有快照。 */
function academic_directory_source_preview_check($state, $nonce, $now)
{
    if (!is_array($state) || !isset($state['preview_nonce'], $state['preview_expiry'], $state['snapshot'])) {
        return false;
    }
    if (!is_string($nonce) || $nonce === '' || $state['preview_nonce'] === null) {
        return false;
    }
    if (!hash_equals((string)$state['preview_nonce'], $nonce)) {
        return false;
    }
    if ((int)$state['preview_expiry'] < (int)$now) {
        return false;
    }
    return is_array($state['snapshot']);
}

/**
 * 取得验证码后的 challenge 状态：只含服务端 cookie、验证码图片、10 分钟 nonce。
 * 不含账号 / 密码 / encoded。
 */
function academic_directory_source_new_challenge_state($cookies, $captchaBody, $contentType, $now)
{
    $type = preg_match('#^image/[a-z0-9.+-]+$#i', (string)$contentType) ? strtolower((string)$contentType) : 'image/jpeg';
    return array(
        'challenge_nonce' => bin2hex(random_bytes(16)),
        'challenge_expiry' => (int)$now + academic_directory_source_ttl(),
        'cookies' => array_values(is_array($cookies) ? $cookies : array()),
        'captcha_uri' => 'data:' . $type . ';base64,' . base64_encode((string)$captchaBody),
        'preview_nonce' => null,
        'preview_expiry' => null,
        'snapshot' => null,
    );
}

/**
 * 采集成功后：丢弃全部远端 cookie 与验证码，只保留已校验快照 + 新的 preview nonce。
 */
function academic_directory_source_new_preview_state(array $snapshot, $now)
{
    return array(
        'challenge_nonce' => null,
        'challenge_expiry' => null,
        'cookies' => array(),
        'captcha_uri' => '',
        'preview_nonce' => bin2hex(random_bytes(16)),
        'preview_expiry' => (int)$now + academic_directory_source_ttl(),
        'snapshot' => $snapshot,
    );
}

// ---------------------------------------------------------------------------
// 预览展示（纯函数）
// ---------------------------------------------------------------------------

/**
 * 由原始快照 + 计划生成“受影响学院 / 变更样例”，供后台预览展示。
 */
function academic_directory_source_plan_view(array $snapshot, array $plan, $limit = 20)
{
    $limit = max(1, (int)$limit);
    $collegeNameBySource = array();
    foreach ($snapshot['colleges'] as $college) {
        $collegeNameBySource[(string)$college['source_id']] = (string)$college['name'];
    }
    $classCollegeSource = array();
    foreach ($snapshot['classes'] as $class) {
        $classCollegeSource[(string)$class['field0']] = (string)$class['field6'];
    }

    $affected = array();
    $bump = function ($name, $kind) use (&$affected) {
        $name = (string)$name;
        if (!isset($affected[$name])) {
            $affected[$name] = array('name' => $name, 'insert' => 0, 'update' => 0, 'total' => 0);
        }
        $affected[$name][$kind]++;
        $affected[$name]['total']++;
    };

    foreach ($plan['college_inserts'] as $op) {
        $bump($op['name'], 'insert');
    }
    foreach ($plan['college_updates'] as $op) {
        $bump($op['name'], 'update');
    }
    foreach ($plan['class_inserts'] as $op) {
        $source = (string)$op['source_id'];
        $collegeSource = isset($classCollegeSource[$source]) ? $classCollegeSource[$source] : '';
        $name = isset($collegeNameBySource[$collegeSource]) ? $collegeNameBySource[$collegeSource] : $collegeSource;
        $bump($name, 'insert');
    }
    foreach ($plan['class_updates'] as $op) {
        $source = (string)$op['source_id'];
        $collegeSource = isset($classCollegeSource[$source]) ? $classCollegeSource[$source] : '';
        $name = isset($collegeNameBySource[$collegeSource]) ? $collegeNameBySource[$collegeSource] : $collegeSource;
        $bump($name, 'update');
    }

    $affectedList = array_values($affected);
    usort($affectedList, function ($a, $b) {
        if ($a['total'] === $b['total']) {
            return strcmp($a['name'], $b['name']);
        }
        return $b['total'] - $a['total'];
    });

    $changes = array();
    $totalChanges = 0;
    foreach (array(array('class_inserts', '新增'), array('class_updates', '更新')) as $pair) {
        list($key, $label) = $pair;
        foreach ($plan[$key] as $op) {
            $totalChanges++;
            if (count($changes) >= $limit) {
                continue;
            }
            $source = (string)$op['source_id'];
            $collegeSource = isset($classCollegeSource[$source]) ? $classCollegeSource[$source] : '';
            $name = isset($collegeNameBySource[$collegeSource]) ? $collegeNameBySource[$collegeSource] : $collegeSource;
            $changes[] = array(
                'type' => $label,
                'bh' => (string)$op['num'],
                'bj' => (string)$op['value'],
                'college' => $name,
            );
        }
    }

    return array(
        'affected_colleges' => $affectedList,
        'class_changes' => $changes,
        'total_class_changes' => $totalChanges,
        'has_changes' => (count($plan['college_inserts']) + count($plan['college_updates'])
            + count($plan['class_inserts']) + count($plan['class_updates'])) > 0,
    );
}

/**
 * 展示用安全错误文案：绝不返回原始远端 HTML / URL / token / 异常原文。
 */
function academic_directory_source_safe_message($e)
{
    $message = is_object($e) && method_exists($e, 'getMessage') ? (string)$e->getMessage() : (string)$e;
    if ($message === '' || strlen($message) > 400) {
        return '操作失败，请重试。';
    }
    if (preg_match('#SQLSTATE|PDO|curl|https?://|token#i', $message)) {
        return '操作失败，请重试。';
    }
    return $message;
}
