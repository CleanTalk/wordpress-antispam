#!/usr/bin/env python3
from pathlib import Path
import argparse
import html
import os
import re
import sys


def escape_text(value: str) -> int:
    print(html.escape(value))
    return 0


def escape_file(path: str) -> int:
    print(html.escape(Path(path).read_text(encoding="utf-8")))
    return 0


def parse_subject(subject: str) -> int:
    match = re.match(r"^\{to_release:\s*(\d+)\}\s+(Fix|Upd|New)\.\s*(.+)$", subject)
    if not match:
        return 0
    print("\t".join(match.groups()))
    return 0


def parse_message() -> int:
    data = sys.stdin.read()
    subject, _, body = data.partition("\x1f")
    subject = subject.strip("\r\n")

    prefixes = os.environ.get("COMMIT_PREFIXES") or "Fix|Upd|New|Code"
    task_url_prefix = os.environ.get("TASK_URL_PREFIX") or "https://app.doboard.com/1/task/"

    subject_match = re.match(rf"^({prefixes})\.\s*(.+?)\s*$", subject)
    url_match = re.search(re.escape(task_url_prefix) + r"(\d+)", subject)

    if not subject_match and not url_match:
        return 0

    kind = subject_match.group(1) if subject_match else "Upd"
    text = subject.strip()
    task = url_match.group(1) if url_match else ""

    print("\t".join([kind, text, task]))
    return 0

def main() -> int:
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(dest="command", required=True)

    parser_escape_text = subparsers.add_parser("escape-text")
    parser_escape_text.add_argument("value")

    parser_escape_file = subparsers.add_parser("escape-file")
    parser_escape_file.add_argument("path")

    parser_parse_subject = subparsers.add_parser("parse-subject")
    parser_parse_subject.add_argument("subject")

    subparsers.add_parser("parse-message")

    args = parser.parse_args()

    if args.command == "escape-text":
        return escape_text(args.value)
    if args.command == "escape-file":
        return escape_file(args.path)
    if args.command == "parse-subject":
        return parse_subject(args.subject)
    if args.command == "parse-message":
        return parse_message()
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
