#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""collect_directory.py 的单元测试（不访问网络/浏览器）。"""

import importlib.util
import json
import os
import tempfile
import unittest

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location('collect_directory', os.path.join(HERE, 'collect_directory.py'))
collector = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(collector)


def _row(source_id, bh, bj, college_source, college_name):
    return {
        'ind': '1', 'bh': bh, 'bj': bj, 'xx0103$xqmc': '南校区',
        'xx0301$dwmc': college_name, 'jx01nd$zymc': '专业',
        'field0': source_id, 'field6': college_source, 'field7': '1',
    }


def colleges_page():
    return (
        '<html><body><select name="yxbh" id="yxbh">'
        '<option value="">---请选择---</option>'
        '<option value="01">【01】电气与信息工程学院</option>'
        '<option value="36">【80】研究生院（研究生工作部）</option>'
        '</select></body></html>'
    )


def class_page(rows, total, current, pages, each):
    return class_page_parts(rows, pages, current, total, each)


def class_page_parts(rows, page_num, current, total, each):
    """按需省略 pageNum/current/total/each（None 表示不输出该字段）。"""
    data = json.dumps(rows, ensure_ascii=False)
    fields = []
    if page_num is not None:
        fields.append('pageNum:' + str(page_num))
    if current is not None:
        fields.append('current:' + str(current))
    if total is not None:
        fields.append('total:' + str(total))
    if each is not None:
        fields.append('each:' + str(each))
    pagination = ','.join(fields)
    total_input = '' if total is None else (
        "<input type=\"hidden\" id=\"dataTotal\" name = \"dataTotal\" value='" + str(total) + "'/>"
    )
    return (
        '<html><script>var table = null;var qz_option = null;(function(g){'
        "qz_option={elem: '#dataTables',id: 'dataTable',limit: 30,"
        "initSort:{field:'bh', type:'asc'},cols: [[{field:'ind'}]],"
        'data: ' + data + ',even: true, };'
        '$("#tag_paginationView").createPage({' + pagination
        + ',backfun: function(e) { reloadPageOnFy(e.current, 500); }});'
        '})(this)</script>' + total_input + '</html>'
    )


def sample_payload():
    r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
    r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
    r3 = _row('36', '2014360101', '动力工程2014', '36', '研究生院（研究生工作部）')
    return {
        'ok': True,
        'collegesHtml': colleges_page(),
        'pages': [
            {'page': 1, 'html': class_page([r1, r2], 3, 1, 2, 2)},
            {'page': 2, 'html': class_page([r3], 3, 2, 2, 2)},
        ],
    }


class ParseTests(unittest.TestCase):
    def test_parse_colleges(self):
        colleges = collector.parse_colleges(colleges_page())
        self.assertEqual(len(colleges), 2)
        self.assertEqual(colleges[0], {'source_id': '01', 'code': '01', 'name': '电气与信息工程学院'})
        self.assertEqual(colleges[1]['code'], '80')

    def test_parse_qz_data_and_pagination(self):
        html = class_page([_row('x', '2024010101', '电气工程2401', '01', '电气与信息工程学院')], 1, 1, 1, 500)
        rows = collector.parse_qz_data(html)
        self.assertEqual(len(rows), 1)
        pag = collector.parse_pagination(html)
        self.assertEqual(pag['total'], 1)
        self.assertEqual(pag['each'], 500)
        self.assertEqual(collector.parse_total(html), 1)

    def test_build_snapshot_ok(self):
        snapshot = collector.build_snapshot(sample_payload())
        self.assertEqual(snapshot['total'], 3)
        self.assertEqual(len(snapshot['classes']), 3)
        self.assertEqual(snapshot['colleges'][1]['name'], '研究生院（研究生工作部）')

    def test_rejects_not_ok(self):
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot({'ok': False, 'error': 'not logged in'})

    def test_rejects_page_row_count(self):
        payload = sample_payload()
        payload['pages'][0]['html'] = class_page(
            [_row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')], 3, 1, 2, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_total_change(self):
        payload = sample_payload()
        r3 = _row('36', '2014360101', '动力工程2014', '36', '研究生院（研究生工作部）')
        payload['pages'][1]['html'] = class_page([r3], 4, 2, 2, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_duplicate_source_id(self):
        payload = sample_payload()
        dup = _row('aaa', '2024010199', '电气工程2499', '01', '电气与信息工程学院')
        payload['pages'][1]['html'] = class_page([dup], 3, 2, 2, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_unknown_college(self):
        payload = sample_payload()
        bad = _row('ccc', '2024010103', '电气工程2403', '99', '未知学院')
        payload['pages'][0]['html'] = class_page(
            [_row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院'), bad], 3, 1, 2, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_missing_pages(self):
        payload = sample_payload()
        payload['pages'] = [payload['pages'][1]]
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_missing_current(self):
        payload = sample_payload()
        r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
        r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
        payload['pages'][0]['html'] = class_page_parts([r1, r2], 2, None, 3, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_missing_page_num(self):
        payload = sample_payload()
        r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
        r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
        payload['pages'][0]['html'] = class_page_parts([r1, r2], None, 1, 3, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_missing_each(self):
        payload = sample_payload()
        r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
        r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
        payload['pages'][0]['html'] = class_page_parts([r1, r2], 2, 1, 3, None)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_missing_total_field(self):
        payload = sample_payload()
        r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
        r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
        payload['pages'][0]['html'] = class_page_parts([r1, r2], 2, 1, None, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_page_num_mismatch(self):
        payload = sample_payload()
        r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
        r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
        # pageNum=5 与抓取页数 2 / total 3 each 2 都不一致
        payload['pages'][0]['html'] = class_page_parts([r1, r2], 5, 1, 3, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_current_mismatch(self):
        payload = sample_payload()
        r3 = _row('36', '2014360101', '动力工程2014', '36', '研究生院（研究生工作部）')
        payload['pages'][1]['html'] = class_page([r3], 3, 1, 2, 2)
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)

    def test_rejects_data_total_mismatch(self):
        payload = sample_payload()
        r1 = _row('aaa', '2024010101', '电气工程2401', '01', '电气与信息工程学院')
        r2 = _row('bbb', '2024010102', '电气工程2402', '01', '电气与信息工程学院')
        # createPage total=3 但 dataTotal=4
        html = class_page_parts([r1, r2], 2, 1, 3, 2).replace("value='3'", "value='4'")
        payload['pages'][0]['html'] = html
        with self.assertRaises(collector.CollectorError):
            collector.build_snapshot(payload)


class WriteTests(unittest.TestCase):
    def test_failure_keeps_old_snapshot(self):
        with tempfile.TemporaryDirectory() as tmp:
            out = os.path.join(tmp, 'snapshot.json')
            with open(out, 'w', encoding='utf-8') as handle:
                handle.write('OLD-SNAPSHOT')

            def failing_fetch():
                raise collector.CollectorError('boom')

            with self.assertRaises(collector.CollectorError):
                collector.run_collector(failing_fetch, out)
            with open(out, 'r', encoding='utf-8') as handle:
                self.assertEqual(handle.read(), 'OLD-SNAPSHOT')
            self.assertEqual([n for n in os.listdir(tmp) if n != 'snapshot.json'], [])

    def test_success_writes_and_replaces(self):
        with tempfile.TemporaryDirectory() as tmp:
            out = os.path.join(tmp, 'snapshot.json')
            with open(out, 'w', encoding='utf-8') as handle:
                handle.write('OLD-SNAPSHOT')
            snapshot = collector.run_collector(sample_payload, out)
            self.assertEqual(snapshot['total'], 3)
            with open(out, 'r', encoding='utf-8') as handle:
                written = json.load(handle)
            self.assertEqual(written['total'], 3)
            self.assertEqual(len(written['classes']), 3)
            self.assertEqual([n for n in os.listdir(tmp) if n != 'snapshot.json'], [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
