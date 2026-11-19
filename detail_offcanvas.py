import os
import re

files_with_offcanvas = []
views_dir = 'resources/views'

for root, _, files in os.walk(views_dir):
    for f in files:
        if not f.endswith('.blade.php'):
            continue
        p = os.path.join(root, f).replace('\\', '/')
        if p == 'resources/views/components/offcanvas-form.blade.php':
            continue
        with open(p, 'r', encoding='utf-8') as fh:
            text = fh.read()
        
        # Check raw offcanvas
        raw_matches = re.findall(r'<div\b[^>]*class=["\'][^"\']*\boffcanvas\b[^"\']*["\'][^>]*>', text)
        comp_matches = re.findall(r'<x-offcanvas-form\b([^>]*)>', text)
        
        if raw_matches or comp_matches:
            files_with_offcanvas.append((p, raw_matches, comp_matches))

print(f"Total files with offcanvas: {len(files_with_offcanvas)}")
for p, raw, comp in files_with_offcanvas:
    print(f"\nFILE: {p}")
    for r in raw:
        # extract id
        id_m = re.search(r'id=["\']([^"\']+)["\']', r)
        id_val = id_m.group(1) if id_m else 'NO_ID'
        print(f"  [RAW] id={id_val} :: {r[:120]}")
    for c in comp:
        id_m = re.search(r'id=["\']([^"\']+)["\']', c)
        id_val = id_m.group(1) if id_m else 'NO_ID'
        print(f"  [COMP] id={id_val} :: {c[:120]}")

