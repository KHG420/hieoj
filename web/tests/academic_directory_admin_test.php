<?php
/**
 * 管理员手动同步学院班级：离线测试（无网络、无数据库、无浏览器）。
 *
 * 覆盖：
 *   - base64 登录协议（encoded = base64(账号) + "%%%" + base64(密码)）；
 *   - 真实格式的学院 / 班级分页解析与完整快照构建；
 *   - 同源跳转边界（http->https 升级、拒绝跨域 / 跨端口 / 非预期路径）；
 *   - 登录页识别与拒绝；截断 / 字段缺失分页拒绝；
 *   - 权限、CSRF、challenge/preview nonce 过期拒绝；
 *   - 注入 fake 传输的完整 challenge + preview 编排（不联网、不假装真实登录）；
 *   - 采集成功后清理远端 cookie；session 状态不含密码 / 账号 / encoded。
 *
 * 运行：php web/tests/academic_directory_admin_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$GLOBALS['AD_FAIL'] = 0;
$GLOBALS['AD_CHECKS'] = 0;

function ad_check($condition, $message)
{
    $GLOBALS['AD_CHECKS']++;
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $message . "\n";
    if (!$condition) {
        $GLOBALS['AD_FAIL']++;
    }
}

function ad_check_throws($callable, $message)
{
    try {
        $callable();
        ad_check(false, $message . '（未抛异常）');
    } catch (Throwable $e) {
        ad_check(true, $message);
    }
}

require_once dirname(__DIR__) . '/include/academic_directory.php';
require_once dirname(__DIR__) . '/include/academic_directory_sync.php';
require_once dirname(__DIR__) . '/include/academic_directory_source.php';

// ---------------------------------------------------------------------------
// 测试替身：注入式传输（不联网）
// ---------------------------------------------------------------------------
class AcademicDirectoryTestHttp
{
    public $handler;
    public $cookies = array('test-cookie-line');
    public $requests = array();
    public $lastPostFields = null;

    public function __construct($handler)
    {
        $this->handler = $handler;
    }

    public function setCookies(array $lines)
    {
        $this->cookies = $lines;
    }

    public function cookies()
    {
        return $this->cookies;
    }

    public function get($url)
    {
        $this->requests[] = array('GET', $url);
        return call_user_func($this->handler, 'GET', $url);
    }

    public function post($url, array $fields)
    {
        $this->requests[] = array('POST', $url);
        $this->lastPostFields = $fields;
        return call_user_func($this->handler, 'POST', $url);
    }
}

// ---------------------------------------------------------------------------
// 真实格式 fixture
// ---------------------------------------------------------------------------
function ad_fixture_login_html()
{
    return '<!doctype html><html><head><title>登录</title></head><body>'
        . '<form id="loginForm" name="loginForm" action="/jsxsd/xk/LoginToXk" method="post">'
        . '<input type="hidden" name="loginMethod" value="LoginToXk">'
        . '<input type="text" id="userAccount" name="userAccount">'
        . '<input type="password" id="userPassword" name="userPassword">'
        . '<input type="text" id="RANDOMCODE" name="RANDOMCODE">'
        . '<img src="/jsxsd/verifycode.servlet" id="SafeCodeImg">'
        . '<input name="encoded" id="encoded" type="hidden" value="">'
        . '</form></body></html>';
}

function ad_fixture_student_html()
{
    return '<!doctype html><html><head><title>学生首页</title></head><body>'
        . '<frameset cols="*"><frame src="/jsxsd/framework/xsMainV.htmlx" name="mainFrame"></frameset>'
        . '</body></html>';
}

function ad_fixture_frm_html()
{
    return '<!doctype html><html><body>'
        . '<iframe id="ifrm" src="http://jwcmis.hnie.edu.cn//Logon.do?method=toFinGlKbCx&amp;token=FAKETOKEN123" width="100%"></iframe>'
        . '</body></html>';
}

function ad_fixture_colleges_html()
{
    return '<!doctype html><html><body><select id="yxbh" name="yxbh">'
        . '<option value="">--请选择--</option>'
        . '<option value="01">【01】电气与信息工程学院</option>'
        . '<option value="36">【80】研究生院（研究生工作部）</option>'
        . '</select></body></html>';
}

function ad_fixture_row($field0, $bh, $bj, $field6, $college)
{
    return array(
        'ind' => '1',
        'bh' => $bh,
        'bj' => $bj,
        'xx0103$xqmc' => '南校区',
        'xx0301$dwmc' => $college,
        'jx01nd$zymc' => '电气工程及其自动化',
        'field0' => $field0,
        'field6' => $field6,
        'field7' => '1',
    );
}

function ad_fixture_class_page($page, $pageNum, $total, $each, array $rows)
{
    $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
    return '<!doctype html><html><head><script>var table = null;var qz_option = null;(function(){'
        . 'table = layui.table;qz_option={elem: "#dataTables",id: "dataTable",limit: 30,cellMinWidth: 100,'
        . 'done:function(res, curr, count){var state = "";for (var i in res.data) {var item = res.data[i];}},'
        . 'cols: [[{field:"ind",title:"序号"},{field:"bh",title:"班级编号"},{field:"bj",title:"班级名称"},'
        . '{field:"xx0301$dwmc",title:"所属院系"}]],'
        . 'data: ' . $json . ',even: true,};'
        . '$("#tag_paginationView").createPage({pageNum:' . (int)$pageNum . ',current:' . (int)$page . ',total:' . (int)$total
        . ',each:' . (int)$each . ',backfun: function(e) { reloadPageOnFy(e.current,30); }});'
        . '})(this)</script></head><body>'
        . '<input type="hidden" id="dataTotal" name = "dataTotal" value=\'' . (int)$total . '\'/>'
        . '<input type="hidden" id="pageSize" name = "pageSize" value=\'' . (int)$each . '\'/>'
        . '<input type="hidden" id="PageNum" name = "PageNum" value=\'' . (int)$page . '\' />'
        . '</body></html>';
}

$GLOBALS['AD_TOTAL'] = 3;
$GLOBALS['AD_EACH'] = 2;
$GLOBALS['AD_PAGE1'] = ad_fixture_class_page(1, 2, 3, 2, array(
    ad_fixture_row('c001', '2004010101', '电气工程0401', '01', '电气与信息工程学院'),
    ad_fixture_row('c002', '2014360101', '动力工程2014', '36', '研究生院（研究生工作部）'),
));
$GLOBALS['AD_PAGE2'] = ad_fixture_class_page(2, 2, 3, 2, array(
    ad_fixture_row('c003', '2004010102', '电气工程0402', '01', '电气与信息工程学院'),
));

function ad_test_handler($pages, array $overrides = array())
{
    return function ($method, $url) use ($pages, $overrides) {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str((string)$query, $q);

        if ($method === 'POST' && $path === '/jsxsd/xk/LoginToXk') {
            if (isset($overrides['login'])) {
                return $overrides['login'];
            }
            return array('status' => 302, 'body' => '', 'headers' => array('location' => '/jsxsd/framework/xsMainV.htmlx'));
        }
        if ($path === '/jsxsd/') {
            return array('status' => 200, 'body' => ad_fixture_login_html(), 'headers' => array());
        }
        if ($path === '/jsxsd/verifycode.servlet') {
            return array('status' => 200, 'body' => 'FAKECAPTCHA', 'headers' => array('content-type' => 'image/png'));
        }
        if ($path === '/jsxsd/framework/xsMainV.htmlx') {
            if (isset($overrides['student'])) {
                return $overrides['student'];
            }
            return array('status' => 200, 'body' => ad_fixture_student_html(), 'headers' => array());
        }
        if ($path === '/jsxsd/view/kbxx/kbcx/llsykb_frm.jsp') {
            return array('status' => 200, 'body' => ad_fixture_frm_html(), 'headers' => array());
        }
        if ($path === '/Logon.do') {
            return array('status' => 200, 'body' => 'logon ok', 'headers' => array());
        }
        if ($path === '/tkglAction.do') {
            if (isset($overrides['colleges'])) {
                return $overrides['colleges'];
            }
            return array('status' => 200, 'body' => ad_fixture_colleges_html(), 'headers' => array());
        }
        if ($path === '/common/llsykb/xx04_select.htmlx') {
            $page = isset($q['PageNum']) ? (int)$q['PageNum'] : 1;
            if (isset($pages[$page])) {
                return array('status' => 200, 'body' => $pages[$page], 'headers' => array());
            }
        }
        return array('status' => 404, 'body' => 'not found', 'headers' => array());
    };
}

// ---------------------------------------------------------------------------
// 1. 协议：base64 / 登录页 / 同源跳转
// ---------------------------------------------------------------------------
ad_check(
    academic_directory_source_encode_credentials('2021001', 'pass123')
        === base64_encode('2021001') . '%%%' . base64_encode('pass123'),
    'encoded = base64(账号) + "%%%" + base64(密码)'
);
ad_check(
    academic_directory_source_encode_credentials('abc', '') === base64_encode('abc') . '%%%',
    '空密码仍产生合法 encoded（不落盘）'
);

ad_check(academic_directory_source_is_login_page(ad_fixture_login_html()), '登录页被识别');
ad_check(!academic_directory_source_is_login_page(ad_fixture_student_html()), '学生首页不被识别为登录页');
ad_check(!academic_directory_source_is_login_page(''), '空页面不是登录页');
ad_check(academic_directory_source_is_student_page(ad_fixture_student_html()), '学生首页被确认');
ad_check(!academic_directory_source_is_student_page(ad_fixture_login_html()), '登录页不被确认为学生首页');

$origin = 'https://jwcmis.hnie.edu.cn';
ad_check(
    academic_directory_source_same_origin_url($origin, 'http://jwcmis.hnie.edu.cn//Logon.do?method=toFinGlKbCx&token=abc', '/Logon.do')
        === 'https://jwcmis.hnie.edu.cn/Logon.do?method=toFinGlKbCx&token=abc',
    '同源 http 地址升级为 https 并规范化路径'
);
ad_check_throws(function () use ($origin) {
    academic_directory_source_same_origin_url($origin, 'http://evil.example.com//Logon.do?method=toFinGlKbCx&token=abc', '/Logon.do');
}, '拒绝跨域跳转');
ad_check_throws(function () use ($origin) {
    academic_directory_source_same_origin_url($origin, 'https://jwcmis.hnie.edu.cn:8443/Logon.do?method=toFinGlKbCx&token=abc', '/Logon.do');
}, '拒绝跨端口跳转');
ad_check_throws(function () use ($origin) {
    academic_directory_source_same_origin_url($origin, 'https://jwcmis.hnie.edu.cn/other', '/Logon.do');
}, '拒绝非预期路径');
ad_check_throws(function () use ($origin) {
    academic_directory_source_same_origin_url($origin, 'file:///etc/passwd');
}, '拒绝非 HTTP scheme');

$logon = academic_directory_source_parse_logon_url(ad_fixture_frm_html(), $origin);
ad_check(
    $logon === 'https://jwcmis.hnie.edu.cn/Logon.do?method=toFinGlKbCx&token=FAKETOKEN123',
    '课表会话交换地址解析并校验通过'
);
ad_check_throws(function () use ($origin) {
    academic_directory_source_parse_logon_url('<iframe src="http://evil.example.com//Logon.do?method=toFinGlKbCx&token=x"></iframe>', $origin);
}, '课表交换地址跨域拒绝');
ad_check_throws(function () use ($origin) {
    academic_directory_source_parse_logon_url('<iframe src="/Logon.do?method=other&token=x"></iframe>', $origin);
}, '课表交换方法不匹配拒绝');

// ---------------------------------------------------------------------------
// 2. 解析 + 快照构建
// ---------------------------------------------------------------------------
$colleges = academic_directory_source_parse_colleges(ad_fixture_colleges_html());
ad_check(count($colleges) === 2, '解析 2 个学院');
ad_check($colleges[1] === array('source_id' => '36', 'code' => '80', 'name' => '研究生院（研究生工作部）'), '学院 source_id/code/名称正确');

$parsed = academic_directory_source_parse_class_page($GLOBALS['AD_PAGE1']);
ad_check($parsed['total'] === 3, 'dataTotal 解析为 3');
ad_check($parsed['pagination'] === array('pageNum' => 2, 'current' => 1, 'total' => 3, 'each' => 2), 'createPage 四个字段解析正确');
ad_check(count($parsed['rows']) === 2, '首页 2 行');

$snapshot = academic_directory_source_build_snapshot($colleges, array(
    array('page' => 1, 'html' => $GLOBALS['AD_PAGE1']),
    array('page' => 2, 'html' => $GLOBALS['AD_PAGE2']),
));
$normalized = academic_directory_snapshot_validate($snapshot);
ad_check($snapshot['total'] === 3 && count($snapshot['classes']) === 3, '完整分页构建 3 班快照');
ad_check($normalized['total'] === 3, '快照通过既有 academic_directory_snapshot_validate');
ad_check(!isset($snapshot['classes'][0]['ind']), '快照仅保留必要字段（控制 session 体积）');

// 页数 / 条数 / 字段不一致必须拒绝（截断分页）
ad_check_throws(function () use ($colleges) {
    academic_directory_source_build_snapshot($colleges, array(
        array('page' => 1, 'html' => ad_fixture_class_page(1, 2, 3, 2, array(
            ad_fixture_row('c001', '2004010101', '电气工程0401', '01', '电气与信息工程学院'),
        ))),
        array('page' => 2, 'html' => $GLOBALS['AD_PAGE2']),
    ));
}, '首页条数与 each 不符拒绝');

ad_check_throws(function () use ($colleges) {
    academic_directory_source_build_snapshot($colleges, array(
        array('page' => 1, 'html' => $GLOBALS['AD_PAGE1']),
    ));
}, '抓取页数少于 createPage.pageNum 拒绝');

ad_check_throws(function () use ($colleges) {
    $missingCurrent = str_replace('current:1,', '', $GLOBALS['AD_PAGE1']);
    academic_directory_source_build_snapshot($colleges, array(
        array('page' => 1, 'html' => $missingCurrent),
        array('page' => 2, 'html' => $GLOBALS['AD_PAGE2']),
    ));
}, 'createPage 缺少 current 拒绝（不把截断当成功）');

ad_check_throws(function () use ($colleges) {
    $badTotal = ad_fixture_class_page(2, 3, 3, 2, array(
        ad_fixture_row('c003', '2004010102', '电气工程0402', '01', '电气与信息工程学院'),
    ));
    academic_directory_source_build_snapshot($colleges, array(
        array('page' => 1, 'html' => $GLOBALS['AD_PAGE1']),
        array('page' => 2, 'html' => $badTotal),
    ));
}, 'pageNum 与抓取页数不一致拒绝');

ad_check_throws(function () {
    academic_directory_source_build_snapshot(academic_directory_source_parse_colleges(ad_fixture_colleges_html()), array(
        array('page' => 1, 'html' => ad_fixture_class_page(1, 1, 1, 1, array(
            ad_fixture_row('c009', '2004010109', '未知学院班', '99', '不存在学院'),
        ))),
    ));
}, '班级引用未知学院拒绝');

// ---------------------------------------------------------------------------
// 3. 权限 / CSRF / nonce 过期
// ---------------------------------------------------------------------------
ad_check(academic_directory_source_is_admin(array('OJ_administrator' => true), 'OJ'), 'administrator 可访问');
ad_check(!academic_directory_source_is_admin(array('OJ_contest_creator' => true), 'OJ'), 'contest_creator 拒绝');
ad_check(!academic_directory_source_is_admin(array('OJ_problem_editor' => true), 'OJ'), 'problem_editor 拒绝');
ad_check(!academic_directory_source_is_admin(array('OJ_user_id' => 'x'), 'OJ'), '未登录用户拒绝');

$csrfSession = array('OJ_postkey' => 'abc123');
ad_check(academic_directory_source_csrf_check($csrfSession, 'OJ', 'abc123'), '正确 postkey 通过');
ad_check(!academic_directory_source_csrf_check($csrfSession, 'OJ', 'abc124'), '错误 postkey 拒绝');
ad_check(!academic_directory_source_csrf_check($csrfSession, 'OJ', null), '缺失 postkey 拒绝');
ad_check(!academic_directory_source_csrf_check(array(), 'OJ', 'abc123'), '无 session postkey 拒绝');

$now = 1000000;
$challengeState = array('challenge_nonce' => 'n1', 'challenge_expiry' => $now + 600);
ad_check(academic_directory_source_challenge_check($challengeState, 'n1', $now), 'challenge nonce 有效');
ad_check(!academic_directory_source_challenge_check($challengeState, 'n2', $now), 'challenge nonce 不匹配拒绝');
ad_check(!academic_directory_source_challenge_check($challengeState, 'n1', $now + 601), 'challenge 过期拒绝');
ad_check(!academic_directory_source_challenge_check(null, 'n1', $now), '无 challenge 状态拒绝');

$previewState = array('preview_nonce' => 'p1', 'preview_expiry' => $now + 600, 'snapshot' => array('colleges' => array(), 'classes' => array(), 'total' => 1));
ad_check(academic_directory_source_preview_check($previewState, 'p1', $now), 'preview nonce 有效');
ad_check(!academic_directory_source_preview_check($previewState, 'p2', $now), 'preview nonce 不匹配拒绝');
ad_check(!academic_directory_source_preview_check($previewState, 'p1', $now + 601), 'preview 过期拒绝');
ad_check(!academic_directory_source_preview_check(array('preview_nonce' => 'p1', 'preview_expiry' => $now + 600), 'p1', $now), '缺少快照拒绝');

// ---------------------------------------------------------------------------
// 4. session 状态不含密码 / 账号 / encoded
// ---------------------------------------------------------------------------
$challengeState = academic_directory_source_new_challenge_state(array('cookie-line'), 'PNGBYTES', 'image/png', $now);
ad_check($challengeState['cookies'] === array('cookie-line'), 'challenge 保存远端 cookie');
ad_check(strpos($challengeState['captcha_uri'], 'data:image/png;base64,') === 0, 'challenge 保存验证码 data URI');
ad_check(preg_match('/^[0-9a-f]{32}$/', $challengeState['challenge_nonce']) === 1, 'challenge nonce 为随机 32 hex');
$encodedState = json_encode($challengeState);
ad_check(strpos($encodedState, 'password') === false && strpos($encodedState, 'encoded') === false, 'challenge 状态不含 password/encoded 字段');

$previewState = academic_directory_source_new_preview_state($snapshot, $now);
ad_check($previewState['cookies'] === array() && $previewState['captcha_uri'] === '', '采集成功后清理全部远端 cookie 与验证码');
ad_check(is_array($previewState['snapshot']) && $previewState['snapshot']['total'] === 3, 'preview 状态保存已校验快照');
ad_check(academic_directory_source_preview_check($previewState, $previewState['preview_nonce'], $now), '新 preview nonce 立即可用');

$session = array();
academic_directory_source_state_set($session, 'OJ', $challengeState);
ad_check(academic_directory_source_state_get($session, 'OJ') === $challengeState, 'state 存取一致');
academic_directory_source_state_clear($session, 'OJ');
ad_check(academic_directory_source_state_get($session, 'OJ') === null, 'state 清理干净');

// ---------------------------------------------------------------------------
// 5. 注入 fake 传输的完整 challenge + preview
// ---------------------------------------------------------------------------
$config = academic_directory_source_config();
$config['page_size'] = 2;
$config['max_pages'] = 10;

$fake = new AcademicDirectoryTestHttp(ad_test_handler(array(1 => $GLOBALS['AD_PAGE1'], 2 => $GLOBALS['AD_PAGE2'])));
$challenge = academic_directory_source_challenge($fake, $config);
ad_check(is_array($challenge['cookies']), 'challenge 返回 cookie 列表');
ad_check($challenge['body'] === 'FAKECAPTCHA' && $challenge['content_type'] === 'image/png', 'challenge 返回验证码与 content-type');

$fake2 = new AcademicDirectoryTestHttp(ad_test_handler(array(1 => $GLOBALS['AD_PAGE1'], 2 => $GLOBALS['AD_PAGE2'])));
$fake2->setCookies($challenge['cookies']);
$snapshot2 = academic_directory_source_preview($fake2, $config, 'testuser', 'secret-pw', '1234');
ad_check($snapshot2['total'] === 3 && count($snapshot2['classes']) === 3, 'fake 传输下 preview 采集完整 3 班');
ad_check($fake2->lastPostFields['loginMethod'] === 'LoginToXk', '登录 POST 使用 LoginToXk');
ad_check($fake2->lastPostFields['userAccount'] === 'testuser', '登录 POST 携带账号');
ad_check($fake2->lastPostFields['userPassword'] === '', '登录 POST 密码字段为空');
ad_check($fake2->lastPostFields['RANDOMCODE'] === '1234', '登录 POST 携带验证码');
ad_check(
    $fake2->lastPostFields['encoded'] === base64_encode('testuser') . '%%%' . base64_encode('secret-pw'),
    '登录 POST encoded 符合协议'
);
$exchanged = false;
$httpOnly = true;
foreach ($fake2->requests as $request) {
    if (strpos($request[1], '/Logon.do?method=toFinGlKbCx') !== false) {
        $exchanged = true;
        if (strpos($request[1], 'https://jwcmis.hnie.edu.cn/') !== 0) {
            $httpOnly = false;
        }
    }
    if (stripos($request[1], 'http://') === 0) {
        $httpOnly = false;
    }
}
ad_check($exchanged, '执行了课表会话交换');
ad_check($httpOnly, '所有请求均为 HTTPS 同源');
ad_check(strpos(json_encode($snapshot2), 'secret-pw') === false, '快照中不含密码');

// 登录失败（远端返回登录页）必须拒绝
$fakeLoginFail = new AcademicDirectoryTestHttp(ad_test_handler(
    array(1 => $GLOBALS['AD_PAGE1'], 2 => $GLOBALS['AD_PAGE2']),
    array('login' => array('status' => 200, 'body' => ad_fixture_login_html(), 'headers' => array()))
));
ad_check_throws(function () use ($fakeLoginFail, $config) {
    academic_directory_source_preview($fakeLoginFail, $config, 'testuser', 'bad', '0000');
}, '登录页响应被拒绝');

// 学生确认页仍是登录页也必须拒绝
$fakeStudentFail = new AcademicDirectoryTestHttp(ad_test_handler(
    array(1 => $GLOBALS['AD_PAGE1'], 2 => $GLOBALS['AD_PAGE2']),
    array('student' => array('status' => 200, 'body' => ad_fixture_login_html(), 'headers' => array()))
));
ad_check_throws(function () use ($fakeStudentFail, $config) {
    academic_directory_source_preview($fakeStudentFail, $config, 'testuser', 'bad', '0000');
}, '学生确认页为登录页时拒绝');

// 分页截断（第 2 页缺失）必须拒绝
$fakeTruncated = new AcademicDirectoryTestHttp(ad_test_handler(array(1 => $GLOBALS['AD_PAGE1'])));
ad_check_throws(function () use ($fakeTruncated, $config) {
    academic_directory_source_preview($fakeTruncated, $config, 'testuser', 'secret-pw', '1234');
}, '缺少后续分页时拒绝（不产出截断快照）');

// 学院页缺少 yxbh（未登录）必须拒绝
$fakeCollegesFail = new AcademicDirectoryTestHttp(ad_test_handler(
    array(1 => $GLOBALS['AD_PAGE1'], 2 => $GLOBALS['AD_PAGE2']),
    array('colleges' => array('status' => 200, 'body' => '<html><body>no options</body></html>', 'headers' => array()))
));
ad_check_throws(function () use ($fakeCollegesFail, $config) {
    academic_directory_source_preview($fakeCollegesFail, $config, 'testuser', 'secret-pw', '1234');
}, '学院页缺少 yxbh 时拒绝');

// ---------------------------------------------------------------------------
// 6. 预览计划展示（纯函数）
// ---------------------------------------------------------------------------
$viewSnapshot = array(
    'colleges' => array(array('source_id' => '01', 'code' => '01', 'name' => '电气与信息工程学院')),
    'classes' => array(
        array('field0' => 'c1', 'bh' => '2004010101', 'bj' => '电气工程0401', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
        array('field0' => 'c2', 'bh' => '2004010102', 'bj' => '电气工程0402', 'field6' => '01', 'xx0301$dwmc' => '电气与信息工程学院'),
    ),
    'total' => 2,
);
$plan = array(
    'college_inserts' => array(array('id' => 1, 'name' => '电气与信息工程学院', 'source_id' => '01')),
    'college_updates' => array(),
    'class_inserts' => array(array('source_id' => 'c1', 'num' => '2004010101', 'value' => '电气工程0401', 'collegiate_id' => 1)),
    'class_updates' => array(array('school_id' => 5, 'source_id' => 'c2', 'num' => '2004010102', 'value' => '电气工程0402', 'collegiate_id' => 1)),
    'conflicts' => array(),
);
$planView = academic_directory_source_plan_view($viewSnapshot, $plan);
ad_check($planView['total_class_changes'] === 2 && count($planView['class_changes']) === 2, '预览统计班级变更数');
ad_check(count($planView['affected_colleges']) === 1 && $planView['affected_colleges'][0]['insert'] === 2 && $planView['affected_colleges'][0]['update'] === 1, '预览受影响学院统计');
ad_check($planView['has_changes'] === true, '有变更时标记 has_changes');

// ---------------------------------------------------------------------------
// 7. 安全错误文案
// ---------------------------------------------------------------------------
ad_check(
    academic_directory_source_safe_message(new RuntimeException('登录失败，请检查账号、密码和验证码')) === '登录失败，请检查账号、密码和验证码',
    '自有可读错误原样返回'
);
ad_check(
    academic_directory_source_safe_message(new RuntimeException('cURL error: http://jwcmis.hnie.edu.cn//Logon.do?token=SECRET')) === '操作失败，请重试。',
    '含 URL / token / curl 的异常被脱敏'
);
ad_check(
    academic_directory_source_safe_message(new RuntimeException(str_repeat('长', 500))) === '操作失败，请重试。',
    '超长异常文案被脱敏'
);
ad_check(
    strpos(academic_directory_source_safe_message(new RuntimeException('数据库尚未执行 migration：collegiate.source_id 缺失，请先运行 cli/academic_directory_migrate.php --apply')), 'migration') !== false,
    'migration 提示保留可操作性'
);

// ---------------------------------------------------------------------------
// 8. 页面代码层约束：不缓存、不回显密码、隐藏域不含 password/encoded
// ---------------------------------------------------------------------------
$pageSrc = file_get_contents(__DIR__ . '/../admin/academic_directory.php');
ad_check(strpos($pageSrc, 'Cache-Control: no-store') !== false, '后台页面设置 no-store');
ad_check(preg_match('/type\s*=\s*["\']hidden["\'][^>]*name\s*=\s*["\']?password/i', $pageSrc) === 0, '页面隐藏域不含 password');
ad_check(strpos($pageSrc, 'name="encoded"') === false, '页面不含 encoded 字段');
ad_check(strpos($pageSrc, 'source_state_set($_SESSION, $OJ_NAME, $state)') !== false, '确认只使用服务端 session 快照');

$sourceSrc = file_get_contents(__DIR__ . '/../include/academic_directory_source.php');
ad_check(strpos($sourceSrc, 'CURLOPT_VERBOSE') === false, '传输层不启用 CURLOPT_VERBOSE');
ad_check(strpos($sourceSrc, 'CURLOPT_COOKIEJAR') === false, '传输层不创建磁盘 cookie 文件');
ad_check(strpos($sourceSrc, 'CURLOPT_SSL_VERIFYPEER => true') !== false, '传输层开启 TLS 校验');

$importSrc = file_get_contents(__DIR__ . '/../cli/academic_directory_import.php');
ad_check(strpos($importSrc, 'academic_directory_sync_execute') !== false, 'CLI 调用共享执行器');
ad_check(preg_match('/(INSERT|UPDATE|DELETE)[^;]*\busers\b/i', $importSrc) === 0, 'importer 不写 users');

echo "\n";
echo "共 {$GLOBALS['AD_CHECKS']} 项检查，失败 {$GLOBALS['AD_FAIL']} 项\n";
exit($GLOBALS['AD_FAIL'] === 0 ? 0 : 1);
