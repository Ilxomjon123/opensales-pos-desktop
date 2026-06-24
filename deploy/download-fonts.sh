#!/usr/bin/env bash
# Self-hosted Instrument Sans yuklab olish skripti.
# Bunny Fonts dan to'g'ridan-to'g'ri woff2 fayllarni public/fonts/ ga tushiradi.
set -euo pipefail

FONTS_DIR="$(cd "$(dirname "$0")/.." && pwd)/public/fonts"
mkdir -p "$FONTS_DIR"

# Bunny Fonts CDN (Google Fonts mirror) — yuqori ishonchli, CC litsenziya
BASE="https://fonts.bunny.net/instrument-sans/files"

FILES="
instrument-sans-latin-400-normal.woff2
instrument-sans-latin-500-normal.woff2
instrument-sans-latin-600-normal.woff2
"

for file in $FILES; do
    target="$FONTS_DIR/$file"
    if [ -f "$target" ]; then
        echo "✓ $file (mavjud)"
        continue
    fi
    echo "→ Yuklanmoqda: $file"
    curl -fsSL "$BASE/$file" -o "$target"
done

echo ""
echo "Yuklab olingan fontlar:"
ls -lh "$FONTS_DIR"
