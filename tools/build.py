#!/usr/bin/env python3
"""Build the installable OpenCart packages.

    python tools/build.py           copy shared/*.php into both versions, write dist/*.ocmod.zip
    python tools/build.py --check   fail when a copied library differs from shared/ (CI)

dist/opencart-3/satelit-opencart3.ocmod.zip   OpenCart 3.0.x: upload/ tree
dist/opencart-4/satelit.ocmod.zip             OpenCart 4.x: the name is the extension code
"""
import sys
import time
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SHARED = ROOT / "shared"
TARGETS = [
    ROOT / "opencart-3" / "upload" / "system" / "library",
    ROOT / "opencart-4" / "satelit" / "system" / "library",
]


def sync(check):
    bad = []
    for lib in sorted(SHARED.glob("*.php")):
        for target in TARGETS:
            dest = target / lib.name
            if dest.exists() and dest.read_bytes() == lib.read_bytes():
                continue
            if check:
                bad.append(str(dest.relative_to(ROOT)))
            else:
                target.mkdir(parents=True, exist_ok=True)
                dest.write_bytes(lib.read_bytes())
    return bad


def pack(src, zip_path, prefix=""):
    zip_path.parent.mkdir(parents=True, exist_ok=True)
    files = sorted(p for p in src.rglob("*") if p.is_file())
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as z:
        for f in files:
            # Real modification times: OpenCart extracts them as they are, and PHP's
            # opcache only reloads an upgraded file when its mtime changed.
            stamp = time.localtime(max(f.stat().st_mtime, 315532800))[:6]
            info = zipfile.ZipInfo(prefix + f.relative_to(src).as_posix(), stamp)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            z.writestr(info, f.read_bytes())
    print(f"{zip_path.relative_to(ROOT)}  {len(files)} files, {zip_path.stat().st_size} bytes")


def main():
    check = "--check" in sys.argv
    bad = sync(check)
    if bad:
        print("out of date (run python tools/build.py):", *bad, sep="\n  ")
        return 1
    if check:
        print("libraries in sync")
        return 0
    pack(ROOT / "opencart-3" / "upload", ROOT / "dist" / "opencart-3" / "satelit-opencart3.ocmod.zip", "upload/")
    pack(ROOT / "opencart-4" / "satelit", ROOT / "dist" / "opencart-4" / "satelit.ocmod.zip")
    return 0


if __name__ == "__main__":
    sys.exit(main())
