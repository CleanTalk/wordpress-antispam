#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
from pathlib import Path

# Date part is format-agnostic: 'Sep 25 2026' and '25.09.2026' are both accepted.
VERSION_HEADER_RE = re.compile(r'^= (\d+(?:\.\d+)+) (\S.*?) =$')


def parse_blocks(text: str, keep_preamble_blank: bool) -> list[tuple[str | None, str]]:
    lines = text.splitlines(keepends=True)
    blocks: list[tuple[str | None, str]] = []
    current_header = None
    current_lines: list[str] = []

    for raw_line in lines:
        line = raw_line.rstrip('\n')
        if VERSION_HEADER_RE.match(line):
            if current_header is not None:
                blocks.append((current_header, ''.join(current_lines).rstrip('\n')))
            current_header = line
            current_lines = [raw_line]
        else:
            if current_header is None:
                if keep_preamble_blank or raw_line.strip():
                    blocks.append((None, raw_line.rstrip('\n')))
            else:
                current_lines.append(raw_line)

    if current_header is not None:
        blocks.append((current_header, ''.join(current_lines).rstrip('\n')))

    return blocks


def merge_blocks(existing_text: str, block_text: str, version: str, keep_preamble_blank: bool) -> str:
    blocks = parse_blocks(existing_text, keep_preamble_blank=keep_preamble_blank)
    block = block_text.rstrip('\n')
    block_lines = block.splitlines()
    new_entries = block_lines[1:]

    result_parts: list[str] = []
    inserted = False

    for header, body in blocks:
        if header is None:
            if body:
                result_parts.append(body.rstrip('\n'))
            continue

        match = VERSION_HEADER_RE.match(header)
        existing_version = match.group(1) if match else None

        if existing_version == version:
            rebuilt = '\n'.join([header] + new_entries).rstrip('\n')
            result_parts.append(rebuilt)
            inserted = True
        else:
            if not inserted:
                result_parts.append(block)
                inserted = True
            result_parts.append(body)

    if not inserted:
        result_parts.append(block)

    return '\n\n'.join(part.rstrip('\n') for part in result_parts if part != '')


def rewrite_readme(target: Path, block: str, version: str) -> None:
    text = target.read_text(encoding='utf-8')
    text, stable_replacements = re.subn(
        r'^Stable tag:\s*.*$',
        f'Stable tag: {version}',
        text,
        count=1,
        flags=re.MULTILINE,
    )
    if stable_replacements == 0:
        raise SystemExit('Stable tag not found for replacement')

    changelog_header = '== Changelog ==\n\n'
    header_pos = text.find(changelog_header)
    if header_pos == -1:
        raise SystemExit('Changelog section not found')

    content_start = header_pos + len(changelog_header)
    next_section_match = re.search(r'^== [^=].* ==\s*$', text[content_start:], flags=re.MULTILINE)
    content_end = content_start + next_section_match.start() if next_section_match else len(text)

    before = text[:content_start]
    changelog_content = text[content_start:content_end]
    after = text[content_end:]

    new_content = merge_blocks(changelog_content, block, version, keep_preamble_blank=True)
    if new_content:
        new_content += '\n\n'

    target.write_text(before + new_content + after.lstrip('\n'), encoding='utf-8')


def rewrite_changelog(target: Path, block: str, version: str) -> None:
    text = target.read_text(encoding='utf-8')
    new_text = merge_blocks(text, block, version, keep_preamble_blank=False)
    if new_text:
        new_text += '\n'
    target.write_text(new_text, encoding='utf-8')


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--mode', choices=['readme', 'changelog'], required=True)
    parser.add_argument('--file', required=True)
    parser.add_argument('--block-file', required=True)
    parser.add_argument('--version', required=True)
    args = parser.parse_args()

    target = Path(args.file)
    block = Path(args.block_file).read_text(encoding='utf-8')

    if args.mode == 'readme':
        rewrite_readme(target, block, args.version)
    else:
        rewrite_changelog(target, block, args.version)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
