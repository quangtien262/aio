"""Compress manifest preview images without overwriting their source files.

Requires Pillow with WebP support. Run from any directory:
    python scripts/optimize-theme-previews.py --apply

Without --apply, reports the candidates without writing files. Existing SVGs,
small images and already optimized images are left unchanged. Original images
remain available for stored URLs and for future recompression.
"""

import argparse
import io
import json
import re
from pathlib import Path

from PIL import Image, ImageOps, features


ROOT = Path(__file__).resolve().parents[1]
PREVIEWS = ROOT / "public" / "theme-previews"
SUFFIX = "-optimized.webp"


def optimize(source: Path, max_width: int, quality: int) -> tuple[bytes, tuple]:
    with Image.open(source) as image:
        image = ImageOps.exif_transpose(image)
        # Fully opaque screenshots do not need an alpha channel.
        if image.mode == "RGBA" and image.getchannel("A").getextrema() == (255, 255):
            image = image.convert("RGB")
        elif image.mode not in ("RGB", "RGBA"):
            image = image.convert("RGBA" if "transparency" in image.info else "RGB")
        image.thumbnail((max_width, 16383), Image.Resampling.LANCZOS)
        buffer = io.BytesIO()
        options = {"quality": quality, "method": 6, "exact": True}
        if image.info.get("icc_profile"):
            options["icc_profile"] = image.info["icc_profile"]
        image.save(buffer, "WEBP", **options)
        data = buffer.getvalue()
        with Image.open(io.BytesIO(data)) as encoded:
            encoded.load()
            if encoded.size != image.size or encoded.format != "WEBP":
                raise ValueError(f"Invalid encoded image: {source}")
        return data, image.size


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--max-width", type=int, default=1600)
    parser.add_argument("--quality", type=int, default=90)
    args = parser.parse_args()
    if not 1 <= args.max_width <= 16383 or not 1 <= args.quality <= 100:
        parser.error("max-width must be 1..16383 and quality must be 1..100")
    if not features.check("webp"):
        parser.error("Pillow must have WebP support")

    converted = {}
    missing = []
    changed_manifests = 0
    for manifest in sorted((ROOT / "themes").glob("*/theme.json")):
        raw = manifest.read_bytes()
        text = raw.decode("utf-8-sig")
        payload = json.loads(text)
        replacements = {}
        for kind in ("thumbnail", "cover"):
            name = payload.get("preview", {}).get(kind)
            if not isinstance(name, str) or not name:
                continue
            source = PREVIEWS / manifest.parent.name / name
            if not source.is_file():
                missing.append({"theme": manifest.parent.name, "kind": kind, "name": name})
                continue
            if (source.suffix.lower() not in (".png", ".jpg", ".jpeg", ".webp")
                    or name.endswith(SUFFIX) or source.stat().st_size < 256 * 1024):
                continue
            if source not in converted:
                output = source.with_name(source.stem + SUFFIX)
                if not args.apply:
                    print(f"candidate: {source.relative_to(ROOT)} ({source.stat().st_size:,} bytes)")
                    converted[source] = None
                    continue
                data, dimensions = optimize(source, args.max_width, args.quality)
                if len(data) >= source.stat().st_size:
                    converted[source] = None
                    continue
                # Do not overwrite a file created independently of this tool.
                if output.exists() and output.read_bytes() != data:
                    raise FileExistsError(f"Output already exists with different content: {output}")
                if not output.exists():
                    output.write_bytes(data)
                converted[source] = {
                    "source": source.relative_to(ROOT).as_posix(),
                    "output": output.relative_to(ROOT).as_posix(),
                    "before_bytes": source.stat().st_size,
                    "after_bytes": len(data),
                    "dimensions": dimensions,
                }
                print(f"optimized: {manifest.parent.name}/{name} -> {len(data):,} bytes", flush=True)
            record = converted[source]
            if record:
                replacements[kind] = Path(record["output"]).name

        if replacements:
            # Edit only the preview object; preserve manifest formatting and BOM.
            match = re.search(r'"preview"\s*:\s*\{[^}]*\}', text)
            if match is None:
                raise ValueError(f"Cannot locate preview object: {manifest}")
            preview = match.group()
            for kind, name in replacements.items():
                preview, count = re.subn(
                    rf'("{kind}"\s*:\s*)"[^"\\]*"',
                    lambda item: item.group(1) + json.dumps(name, ensure_ascii=False),
                    preview,
                )
                if count != 1:
                    raise ValueError(f"Cannot replace preview.{kind}: {manifest}")
            updated = text[:match.start()] + preview + text[match.end():]
            json.loads(updated)
            bom = b"\xef\xbb\xbf" if raw.startswith(b"\xef\xbb\xbf") else b""
            manifest.write_bytes(bom + updated.encode("utf-8"))
            changed_manifests += 1

    records = [item for item in converted.values() if item]
    report = {
        "applied": args.apply,
        "max_width": args.max_width,
        "quality": args.quality,
        "changed_manifests": changed_manifests,
        "converted_files": len(records),
        "before_bytes": sum(item["before_bytes"] for item in records),
        "after_bytes": sum(item["after_bytes"] for item in records),
        "preexisting_missing_references": missing,
        "images": records,
    }
    if args.apply:
        report_path = ROOT / ".tmp" / "theme-preview-optimization.json"
        report_path.parent.mkdir(exist_ok=True)
        report_path.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps({key: value for key, value in report.items() if key != "images"}, indent=2))


if __name__ == "__main__":
    main()
