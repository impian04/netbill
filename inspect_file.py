import sys, re

filepath = sys.argv[1]
with open(filepath, 'r', encoding='utf-8') as f:
    lines = f.readlines()

raw_btn = re.compile(r'\bbtn-(primary|secondary|success|danger|warning|info|light|dark|outline-[a-z]+)\b')
raw_badge = re.compile(r'\bbadge\s+bg-[a-z-]+\b')
icon = re.compile(r'<i\s+class="[^"]*\bbi\s+bi-[^"]*"[^>]*>')

for idx, line in enumerate(lines, 1):
    b = raw_btn.findall(line)
    bd = raw_badge.findall(line)
    ic = icon.findall(line)
    if b or bd or ic:
        print(f"L{idx}: btn={b} badge={bd} icon={ic}")
        print(f"   {line.strip()[:100]}")

