import os
import re

views_dir = 'resources/views'
raw_offcanvas = []
comp_offcanvas = []

for root, _, files in os.walk(views_dir):
    for f in files:
        if not f.endswith('.blade.php'):
            continue
        path = os.path.join(root, f).replace('\\', '/')
        if path == 'resources/views/components/offcanvas-form.blade.php':
            continue
        with open(path, 'r', encoding='utf-8') as fh:
            content = fh.read()

        # Raw offcanvas divs
        matches_raw = re.finditer(r'<div\b[^>]*class=["\'][^"\']*\boffcanvas\b[^"\']*["\'][^>]*>', content)
        for m in matches_raw:
            raw_offcanvas.append((path, m.group(0)))

        # x-offcanvas-form components
        matches_comp = re.finditer(r'<x-offcanvas-form\b([^>]*)>', content)
        for m in matches_comp:
            comp_offcanvas.append((path, m.group(0)))

print("=== RAW OFFCANVAS DIVS (HARUS DIGANTI MENJADI x-offcanvas-form) ===")
for p, tag in raw_offcanvas:
    print(f"{p} -> {tag}")

print("\n=== COMPONENT x-offcanvas-form ===")
for p, tag in comp_offcanvas:
    print(f"{p} -> {tag}")

