import hashlib
import sys
import zipfile
from pathlib import Path, PurePosixPath


def build_dir_manifest(source_dir: str, output_file: str) -> None:
    root = Path(source_dir).resolve()
    out = Path(output_file)
    entries = []
    for path in root.rglob('*'):
        if path.is_file():
            rel = path.relative_to(root)
            digest = hashlib.sha256(path.read_bytes()).hexdigest()
            entries.append((str(rel).replace('\\', '/'), digest))
    entries.sort()
    out.write_text(''.join(f"{digest}  {rel}\n" for rel, digest in entries), encoding='utf-8')


def extract_zip_root(zip_file: str) -> str:
    with zipfile.ZipFile(zip_file) as zf:
        names = [n for n in zf.namelist() if not n.endswith('/')]
        if not names:
            raise SystemExit('ZIP archive is empty')
        roots = {PurePosixPath(n).parts[0] for n in names if PurePosixPath(n).parts}
        if len(roots) != 1:
            raise SystemExit(f'ZIP must contain exactly one top-level directory, found: {sorted(roots)}')
        return next(iter(roots))


def build_zip_manifest(zip_file: str, output_file: str) -> None:
    out = Path(output_file)
    entries = []
    with zipfile.ZipFile(zip_file) as zf:
        names = [n for n in zf.namelist() if not n.endswith('/')]
        if not names:
            raise SystemExit('ZIP archive is empty')
        roots = {PurePosixPath(n).parts[0] for n in names if PurePosixPath(n).parts}
        if len(roots) != 1:
            raise SystemExit(f'ZIP must contain exactly one top-level directory, found: {sorted(roots)}')
        for name in names:
            p = PurePosixPath(name)
            rel = PurePosixPath(*p.parts[1:])
            if not rel.parts:
                continue
            digest = hashlib.sha256(zf.read(name)).hexdigest()
            entries.append((str(rel), digest))
    entries.sort()
    out.write_text(''.join(f"{digest}  {rel}\n" for rel, digest in entries), encoding='utf-8')


def main() -> int:
    if len(sys.argv) < 2:
        raise SystemExit('Usage: manifest_tools.py <dir-manifest|zip-root|zip-manifest> ...')
    command = sys.argv[1]
    if command == 'dir-manifest' and len(sys.argv) == 4:
        build_dir_manifest(sys.argv[2], sys.argv[3])
        return 0
    if command == 'zip-root' and len(sys.argv) == 3:
        print(extract_zip_root(sys.argv[2]))
        return 0
    if command == 'zip-manifest' and len(sys.argv) == 4:
        build_zip_manifest(sys.argv[2], sys.argv[3])
        return 0
    raise SystemExit('Invalid arguments')


if __name__ == '__main__':
    raise SystemExit(main())
