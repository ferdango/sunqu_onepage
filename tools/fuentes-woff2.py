#!/usr/bin/env python3
"""Genera fonts/*.woff2 desde los TTF de Aeonik (mismo diseño, ~50 % menos peso). Requiere fonttools y brotli."""
import glob, os
from fontTools.ttLib import TTFont

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
for ttf in sorted(glob.glob(os.path.join(ROOT, 'fonts', '*.ttf'))):
    out = ttf[:-4] + '.woff2'
    f = TTFont(ttf)
    f.flavor = 'woff2'
    f.save(out)
    print(f"✓ {os.path.basename(out)}  {os.path.getsize(ttf)//1024} KB → {os.path.getsize(out)//1024} KB")
