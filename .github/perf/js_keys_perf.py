#!/usr/bin/env python3
"""
Measures cleantalk-antispam/v1/js_keys__get on a clean WP install.

Server-side metrics are read from the Server-Timing header, which the plugin sends
only when APBCT_SERVICE__PERF_METRICS_HEADER is true. Thresholds come from env
(GitHub repository variables); an empty threshold is not checked.
Never exits non-zero: problems are reported as workflow warnings and in the job summary.
"""

import os
import re
import sys
import time
import urllib.error
import urllib.request

URL = os.environ.get('PERF_URL', 'http://localhost:8080/?rest_route=/cleantalk-antispam/v1/js_keys__get')
REQUESTS = int(os.environ.get('PERF_REQUESTS') or 5)
WARMUP = int(os.environ.get('PERF_WARMUP') or 1)
TIMEOUT = 30

# key, column title, unit, threshold env name (None - not checked), description
METRICS = [
    ('client', 'Client', 'ms', 'PERF_JS_KEYS_CLIENT_MS', 'Full response time seen by HTTP client'),
    ('total', 'Total (PHP)', 'ms', 'PERF_JS_KEYS_TOTAL_MS', 'PHP time from request start to response'),
    ('boot', 'WP boot', 'ms', None, 'WP core load before CleanTalk (not ours, for reference)'),
    ('init', 'Plugin init', 'ms', 'PERF_JS_KEYS_INIT_MS', 'CleanTalk load + WP init + REST routing'),
    ('handler', 'Handler', 'ms', 'PERF_JS_KEYS_HANDLER_MS', 'js_keys__get handler itself'),
    ('sql', 'SQL', '', 'PERF_JS_KEYS_SQL', 'SQL queries for the whole request'),
    ('mem', 'Peak mem', 'MB', 'PERF_JS_KEYS_MEM_MB', 'PHP peak memory'),
]

TIMING_RE = re.compile(r'(\w+)\s*;\s*(?:dur=([\d.]+)|desc="?([\d.]+)"?)')


def threshold(env_name):
    if not env_name:
        return None
    raw = (os.environ.get(env_name) or '').strip()
    try:
        return float(raw) if raw else None
    except ValueError:
        print(f'::warning title=Perf js_keys__get::Variable {env_name}="{raw}" is not a number, ignored')
        return None


def parse_server_timing(header):
    result = {}
    for name, dur, desc in TIMING_RE.findall(header or ''):
        result[name] = float(dur or desc)
    return result


def do_request():
    req = urllib.request.Request(URL, data=b'', method='POST')
    start = time.perf_counter()
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
            body = resp.read().decode('utf-8', 'replace')
            status = resp.status
            header = resp.headers.get('Server-Timing', '')
    except urllib.error.HTTPError as e:
        body, status, header = e.read().decode('utf-8', 'replace'), e.code, e.headers.get('Server-Timing', '')
    except Exception as e:  # connection problems are reported, not raised
        return {'error': str(e), 'client': (time.perf_counter() - start) * 1000}

    row = parse_server_timing(header)
    row['client'] = (time.perf_counter() - start) * 1000
    row['status'] = status
    if status != 200 or '"js_key"' not in body:
        row['error'] = f'HTTP {status}, body: {" ".join(body[:200].split())}'
    elif not header:
        row['error'] = 'No Server-Timing header (is APBCT_SERVICE__PERF_METRICS_HEADER defined?)'
    return row


def fmt(value, unit):
    if value is None:
        return '—'
    if unit == '':
        return str(int(value))
    return f'{value:.2f}'


def main():
    sys.stdout.reconfigure(encoding='utf-8')

    for _ in range(WARMUP):
        do_request()

    rows = [do_request() for _ in range(REQUESTS)]
    limits = {key: threshold(env) for key, _, _, env, _ in METRICS}

    problems = []
    for i, row in enumerate(rows, 1):
        if 'error' in row:
            problems.append(f'Request #{i}: {row["error"]}')
        for key, title, unit, _, _ in METRICS:
            limit, value = limits[key], row.get(key)
            if limit is not None and value is not None and value > limit:
                problems.append(f'Request #{i}: {title} = {fmt(value, unit)} {unit} > threshold {fmt(limit, unit)} {unit}')

    head = '| # | ' + ' | '.join(f'{t}{", " + u if u else ""}' for _, t, u, _, _ in METRICS) + ' |'
    lines = [
        '## js_keys__get performance',
        '',
        f'Endpoint: `{URL}`, requests: {REQUESTS} (+{WARMUP} warm-up, not counted).',
        '',
        head,
        '|' + '---|' * (len(METRICS) + 1),
    ]
    for i, row in enumerate(rows, 1):
        cells = []
        for key, _, unit, _, _ in METRICS:
            value = row.get(key)
            mark = ' ⚠️' if limits[key] is not None and value is not None and value > limits[key] else ''
            cells.append(fmt(value, unit) + mark)
        if 'error' in row:
            cells[0] += ' ❌'
        lines.append(f'| {i} | ' + ' | '.join(cells) + ' |')
    lines.append('| **Threshold** | ' + ' | '.join(
        fmt(limits[key], unit) if limits[key] is not None else 'not set' for key, _, unit, _, _ in METRICS
    ) + ' |')
    lines += ['', '**Columns:**', '']
    lines += [f'- **{t}** — {d}' + (f' (var `{e}`)' if e else '') for _, t, _, e, d in METRICS]
    lines += ['', '### Result', '']
    lines += [f'- ⚠️ {p}' for p in problems] if problems else ['✅ All requests are within thresholds.']

    report = '\n'.join(lines) + '\n'
    print(report)
    summary_file = os.environ.get('GITHUB_STEP_SUMMARY')
    if summary_file:
        with open(summary_file, 'a', encoding='utf-8') as f:
            f.write(report)

    for p in problems:
        print(f'::warning title=Perf js_keys__get::{p}')


if __name__ == '__main__':
    main()
