"""Remove an artwork background locally for Cattie's private composition pipeline."""

from __future__ import annotations

import argparse
from pathlib import Path

from PIL import Image
from rembg import new_session, remove


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--model", default="isnet-general-use")
    args = parser.parse_args()

    source = Path(args.input).resolve(strict=True)
    destination = Path(args.output).resolve()
    if source == destination:
        raise ValueError("Input and output paths must differ.")

    with Image.open(source) as image:
        image.verify()

    session = new_session(args.model)
    result = remove(source.read_bytes(), session=session, force_return_bytes=True)
    destination.write_bytes(result)

    with Image.open(destination) as image:
        image.verify()
        if image.format != "PNG" or "A" not in image.mode:
            raise ValueError("Background removal did not produce an alpha PNG.")


if __name__ == "__main__":
    main()
