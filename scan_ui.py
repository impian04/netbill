import glob, os, re

views = glob.glob('resources/views/**/*.blade.php', recursive=True)
component_files = [os.path.abspath(f) for f in glob.glob('resources/views/components/**/*.blade.php', recursive=True)]
layout_files = [os.path.abspath(f) for f in glob.glob('resources/views/layouts/**/*.blade.php', recursive=True)]

page_views = [f for f in views if os.path.abspath(f) not in component_files and os.path.abspath(f) not in layout_files]

print('=== AUDIT RAPOR VIEW ===')

for pv in sorted(page_views):
    with open(pv, 'r', encoding='utf-8') as f:
        content = f.read()
        lines = content.split('\n')
        
    issues = []
    
    # 1. Container ad-hoc
    for i, line in enumerate(lines[:20], 1):
        if re.search(r'class=["\'][^"\']*(container-fluid|container\b)', line):
            issues.append(f'L{i}: adhoc container [{line.strip()}]')
            
    # 2. Raw buttons
    btn_matches = re.findall(r'class=["\'][^"\']*\b(btn-(?:primary|secondary|success|danger|warning|info|light|dark|outline-\w+))\b', content)
    if btn_matches:
        issues.append(f'Raw btn classes: {len(btn_matches)} occurrences: {set(btn_matches)}')
        
    # 3. Raw badges
    badge_matches = re.findall(r'class=["\'][^"\']*\b(badge\s+bg-\w+|bg-\w+\s+badge)\b', content)
    if badge_matches:
        issues.append(f'Raw badge classes: {len(badge_matches)} occurrences: {set(badge_matches)}')
        
    # 4. Icons
    icon_count = len(re.findall(r'<i\s+class=["\']bi\s', content))
    if icon_count > 0:
        issues.append(f'{icon_count} icons')
        
    if issues:
        rel_path = os.path.relpath(pv).replace('\\', '/')
        print(f'{rel_path}:')
        for iss in issues:
            print(f'   - {iss}')
