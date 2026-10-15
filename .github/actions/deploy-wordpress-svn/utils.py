#!/usr/bin/env python3
from pathlib import Path
import argparse
import html
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


def main() -> int:
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(dest="command", required=True)

    parser_escape_text = subparsers.add_parser("escape-text")
    parser_escape_text.add_argument("value")

    parser_escape_file = subparsers.add_parser("escape-file")
    parser_escape_file.add_argument("path")

    parser_parse_subject = subparsers.add_parser("parse-subject")
    parser_parse_subject.add_argument("subject")

    args = parser.parse_args()

    if args.command == "escape-text":
        return escape_text(args.value)
    if args.command == "escape-file":
        return escape_file(args.path)
    if args.command == "parse-subject":
        return parse_subject(args.subject)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
