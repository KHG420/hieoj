#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""macOS 现有 Chrome 会话学院班级快照采集器。

只通过 osascript 复用用户已打开的、已登录的 jwcmis Chrome 标签：
不启动浏览器、不新建窗口/标签、不切换标签、不改设置、不保存账号密码。

输出格式与 live-directory.json 一致：
    {"colleges": [{"source_id","code","name"}...], "total": N, "classes": [...]}

任何解析/校验/网络失败都会以非零退出，并且不覆盖已有快照文件。
"""

import argparse
import html as html_lib
import json
import os
import re
import subprocess
import sys
import tempfile

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DEFAULT_OUT = os.path.normpath(os.path.join(SCRIPT_DIR, '..', '..', 'output', 'academic-directory.json'))

DEFAULT_CONFIG = {
    'origin': 'https://jwcmis.hnie.edu.cn',
    'tab_match': 'jwcmis.hnie.edu.cn',
    'frm_path': '/jsxsd/view/kbxx/kbcx/llsykb_frm.jsp',
    'colleges_path': '/tkglAction.do?method=llsykbFind&kbtype=xx04&init=1&isview=1',
    'classes_path': '/common/llsykb/xx04_select.htmlx?id=xx04id&name=xx04mc&type=1&where=',
    'page_size': 500,
    'max_pages': 60,
}


class CollectorError(RuntimeError):
    """采集或校验失败。"""


# --------------------------------------------------------------------------
# HTML 解析（纯函数，便于单元测试；不执行远端脚本）
# --------------------------------------------------------------------------

_TAG_RE = re.compile(r'<[^>]*>')


def _text(fragment):
    return html_lib.unescape(_TAG_RE.sub('', fragment)).strip()


def extract_balanced_array(source, start):
    """从 source[start] == '[' 开始，按括号/字符串状态截取完整 JSON 数组。"""
    if start < 0 or start >= len(source) or source[start] != '[':
        raise CollectorError('未找到 qz_option data 数组起始位置')
    depth = 0
    quote = None
    escaped = False
    i = start
    while i < len(source):
        ch = source[i]
        if quote is not None:
            if escaped:
                escaped = False
            elif ch == '\\':
                escaped = True
            elif ch == quote:
                quote = None
        elif ch in ('"', "'"):
            quote = ch
        elif ch == '[':
            depth += 1
        elif ch == ']':
            depth -= 1
            if depth == 0:
                return source[start:i + 1]
        i += 1
    raise CollectorError('qz_option data 数组未闭合')


def parse_qz_data(page_html):
    """解析页面内联脚本里的 qz_option data（合法 JSON 数组），不使用 eval。"""
    anchor = re.search(r'qz_option\s*=\s*\{', page_html)
    if not anchor:
        raise CollectorError('页面缺少 qz_option 对象')
    rel = page_html.find('data:', anchor.end())
    if rel < 0:
        raise CollectorError('页面 qz_option 缺少 data 字段')
    bracket = page_html.find('[', rel)
    if bracket < 0:
        raise CollectorError('页面 qz_option data 不是数组')
    raw = extract_balanced_array(page_html, bracket)
    try:
        rows = json.loads(raw)
    except ValueError as exc:
        raise CollectorError('qz_option data 不是合法 JSON：%s' % exc)
    if not isinstance(rows, list):
        raise CollectorError('qz_option data 不是数组')
    return rows


def parse_total(page_html):
    patterns = (
        r'id\s*=\s*["\']?dataTotal["\']?[^>]*?value\s*=\s*["\']?(\d+)',
        r'value\s*=\s*["\']?(\d+)["\']?[^>]*?id\s*=\s*["\']?dataTotal',
    )
    for pattern in patterns:
        match = re.search(pattern, page_html, re.IGNORECASE)
        if match:
            return int(match.group(1))
    return None


def parse_pagination(page_html):
    """解析 createPage({...}) 中的 pageNum/current/total/each。"""
    match = re.search(r'createPage\s*\(\s*\{(.*?)\}\s*\)', page_html, re.DOTALL)
    if not match:
        return {}
    body = match.group(1)
    result = {}
    for key in ('pageNum', 'current', 'total', 'each'):
        found = re.search(key + r'\s*:\s*(\d+)', body)
        if found:
            result[key] = int(found.group(1))
    return result


def parse_colleges(page_html):
    """从 #yxbh select 解析学院：option.value 为 source_id，option.text 为【code】名称。"""
    select = re.search(
        r'<select\b[^>]*(?:id|name)\s*=\s*["\']?yxbh["\']?[^>]*>(.*?)</select>',
        page_html,
        re.IGNORECASE | re.DOTALL,
    )
    if not select:
        raise CollectorError('未找到 #yxbh 学院下拉')
    colleges = []
    seen_source = set()
    seen_code = set()
    for attrs, inner in re.findall(r'<option\b([^>]*)>(.*?)</option>', select.group(1), re.IGNORECASE | re.DOTALL):
        value_match = re.search(r'value\s*=\s*["\']([^"\']*)["\']', attrs, re.IGNORECASE)
        source_id = (value_match.group(1).strip() if value_match else '')
        if source_id == '':
            continue
        label = _text(inner)
        code_match = re.match(r'^\s*[【\[]\s*(\d+)\s*[】\]]\s*(.+?)\s*$', label)
        if not code_match:
            code_match = re.match(r'^\s*(\d{1,3})\s*[\s、.\-]+\s*(.+?)\s*$', label)
        if not code_match:
            raise CollectorError('无法从学院选项解析 code：%r' % label)
        code_raw = code_match.group(1)
        code = int(code_raw)
        name = code_match.group(2).strip()
        if code <= 0:
            raise CollectorError('学院 %s 的 code 非法：%s' % (source_id, code_raw))
        if not name:
            raise CollectorError('学院 %s 名称为空' % source_id)
        if source_id in seen_source:
            raise CollectorError('学院 source_id 重复：%s' % source_id)
        if code in seen_code:
            raise CollectorError('学院 code 重复：%d' % code)
        seen_source.add(source_id)
        seen_code.add(code)
        colleges.append({'source_id': source_id, 'code': code_raw, 'name': name})
    if not colleges:
        raise CollectorError('学院下拉没有有效选项')
    return colleges


def parse_class_page(page_html):
    rows = parse_qz_data(page_html)
    pagination = parse_pagination(page_html)
    total = parse_total(page_html)
    if total is None:
        total = pagination.get('total')
    return rows, pagination, total


# --------------------------------------------------------------------------
# 快照构建与写出
# --------------------------------------------------------------------------

def build_snapshot(payload):
    if not isinstance(payload, dict):
        raise CollectorError('采集结果不是对象')
    if not payload.get('ok'):
        raise CollectorError(payload.get('error') or '采集失败')
    colleges = parse_colleges(payload.get('collegesHtml') or '')
    college_by_source = {c['source_id']: c for c in colleges}

    pages = payload.get('pages') or []
    if not pages:
        raise CollectorError('没有班级分页数据')
    try:
        pages = sorted(pages, key=lambda p: int(p['page']))
    except (KeyError, TypeError, ValueError):
        raise CollectorError('班级分页数据缺少 page 字段')
    if [int(p['page']) for p in pages] != list(range(1, len(pages) + 1)):
        raise CollectorError('班级分页不连续')

    all_rows = []
    expected_total = None
    page_size = None
    total_pages = len(pages)
    for index, page in enumerate(pages):
        page_no = int(page['page'])
        rows, pagination, total = parse_class_page(page.get('html') or '')
        missing = [key for key in ('pageNum', 'current', 'total', 'each') if key not in pagination]
        if missing:
            raise CollectorError('第 %d 页分页信息缺少 %s' % (page_no, '/'.join(missing)))
        each = pagination['each']
        create_page_total = pagination['total']
        if total is None or total <= 0:
            raise CollectorError('第 %d 页缺少 total' % page_no)
        if create_page_total <= 0 or create_page_total != total:
            raise CollectorError('第 %d 页 createPage total 与 dataTotal 不一致' % page_no)
        if each <= 0:
            raise CollectorError('第 %d 页 each 非法：%r' % (page_no, each))
        if pagination['current'] != page_no:
            raise CollectorError('第 %d 页 current 不匹配' % page_no)
        if expected_total is None:
            expected_total = total
        elif total != expected_total:
            raise CollectorError('第 %d 页 total 与首页不一致' % page_no)
        if page_size is None:
            page_size = each
        elif each != page_size:
            raise CollectorError('第 %d 页 each 与首页不一致' % page_no)
        if pagination['pageNum'] != total_pages:
            raise CollectorError('第 %d 页 pageNum 与抓取页数不一致' % page_no)
        if pagination['pageNum'] != (expected_total + page_size - 1) // page_size:
            raise CollectorError('第 %d 页 pageNum 与 total/each 不一致' % page_no)
        remaining = expected_total - (page_no - 1) * page_size
        expected_rows = page_size if remaining >= page_size else remaining
        if len(rows) != expected_rows:
            raise CollectorError(
                '第 %d 页条数 %d 与预期 %d 不符' % (page_no, len(rows), expected_rows)
            )
        all_rows.extend(rows)

    if len(all_rows) != expected_total:
        raise CollectorError('总条数 %d 与 total %d 不一致' % (len(all_rows), expected_total))

    seen_source = {}
    seen_bh = {}
    for row in all_rows:
        source_id = str(row.get('field0', '')).strip()
        bh = str(row.get('bh', '')).strip()
        bj = str(row.get('bj', '')).strip()
        college_source = str(row.get('field6', '')).strip()
        college_name = str(row.get('xx0301$dwmc', '')).strip()
        if not source_id:
            raise CollectorError('存在缺少 field0 的班级')
        if not bh or not bj:
            raise CollectorError('班级 %s 缺少编号或名称' % source_id)
        if college_source not in college_by_source:
            raise CollectorError('班级 %s 引用了未知学院 %s' % (source_id, college_source))
        if college_name and college_name != college_by_source[college_source]['name']:
            raise CollectorError('班级 %s 的学院名称与学院列表不一致' % source_id)
        if source_id in seen_source:
            raise CollectorError('班级稳定源ID重复：%s' % source_id)
        if bh in seen_bh:
            raise CollectorError('班级显示编号重复：%s' % bh)
        seen_source[source_id] = True
        seen_bh[bh] = True

    return {'colleges': colleges, 'total': expected_total, 'classes': all_rows}


def write_snapshot_atomic(path, snapshot):
    directory = os.path.dirname(os.path.abspath(path))
    if directory and not os.path.isdir(directory):
        os.makedirs(directory)
    fd, tmp_path = tempfile.mkstemp(prefix='.academic-directory-', suffix='.json.tmp', dir=directory or '.')
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as handle:
            json.dump(snapshot, handle, ensure_ascii=False, separators=(',', ':'))
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(tmp_path, path)
    except BaseException:
        try:
            os.unlink(tmp_path)
        except OSError:
            pass
        raise


# --------------------------------------------------------------------------
# Chrome / osascript
# --------------------------------------------------------------------------

def chrome_fetch(config):
    payload_template = os.path.join(SCRIPT_DIR, 'collect_payload.js')
    runner = os.path.join(SCRIPT_DIR, 'run_chrome_js.js')
    with open(payload_template, 'r', encoding='utf-8') as handle:
        payload_js = handle.read()
    if '__ACADEMIC_SYNC_CONFIG__' not in payload_js:
        raise CollectorError('collect_payload.js 缺少配置占位符')
    payload_js = payload_js.replace('__ACADEMIC_SYNC_CONFIG__', json.dumps(config, ensure_ascii=False))

    fd, payload_path = tempfile.mkstemp(prefix='.academic-payload-', suffix='.js')
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as handle:
            handle.write(payload_js)
        proc = subprocess.run(
            ['osascript', '-l', 'JavaScript', runner, payload_path, config['tab_match']],
            capture_output=True, text=True, timeout=config.get('timeout', 180),
        )
    finally:
        try:
            os.unlink(payload_path)
        except OSError:
            pass
    if proc.returncode != 0:
        raise CollectorError('osascript 执行失败：%s' % (proc.stderr.strip() or 'unknown error'))
    try:
        return json.loads(proc.stdout.strip())
    except ValueError:
        raise CollectorError('osascript 未返回合法 JSON')


def run_collector(fetch, out_path):
    payload = fetch()
    snapshot = build_snapshot(payload)
    write_snapshot_atomic(out_path, snapshot)
    return snapshot


def main(argv=None):
    parser = argparse.ArgumentParser(description='采集教务学院班级快照（复用现有 Chrome 登录会话）')
    parser.add_argument('--out', default=DEFAULT_OUT, help='快照输出路径')
    parser.add_argument('--origin', default=DEFAULT_CONFIG['origin'], help='教务系统 origin')
    parser.add_argument('--page-size', type=int, default=DEFAULT_CONFIG['page_size'], help='每页条数')
    parser.add_argument('--timeout', type=int, default=180, help='osascript 超时秒数')
    args = parser.parse_args(argv)

    config = dict(DEFAULT_CONFIG)
    config['origin'] = args.origin.rstrip('/')
    config['page_size'] = args.page_size
    config['timeout'] = args.timeout

    try:
        snapshot = run_collector(lambda: chrome_fetch(config), args.out)
    except CollectorError as exc:
        sys.stderr.write('采集失败：%s\n' % exc)
        return 1
    except Exception as exc:  # noqa: BLE001 - 明确失败而非静默
        sys.stderr.write('采集异常：%s\n' % exc)
        return 1

    sys.stdout.write('采集成功：%d 个学院，%d 个班级 -> %s\n' % (
        len(snapshot['colleges']), snapshot['total'], args.out))
    return 0


if __name__ == '__main__':
    sys.exit(main())
