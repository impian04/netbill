import os
import re

VIEWS_DIR = 'resources/views'
WHITELIST_FILES = {
    'resources/views/components/btn-icon.blade.php',
    'resources/views/layouts/partials/sidebar.blade.php',
}

# Icon pattern in blade
icon_pattern = re.compile(r'<i\s+class="[^"]*bi\s+bi-[^"]*"[^>]*>\s*</i>\s*')

# Process file by file
modified = []
for root, dirs, files in os.walk(VIEWS_DIR):
    for f in files:
        if not f.endswith('.blade.php'):
            continue
        filepath = os.path.join(root, f).replace('\\', '/')
        if filepath in WHITELIST_FILES:
            continue
        
        with open(filepath, 'r', encoding='utf-8') as fh:
            content = fh.read()
        
        # Check if there are icons
        if icon_pattern.search(content):
            # Remove icons
            new_content = icon_pattern.sub('', content)
            with open(filepath, 'w', encoding='utf-8') as fh:
                fh.write(new_content)
            modified.append(filepath)

print(f"Cleaned decorative icons from {len(modified)} files:")
for m in modified:
    print(f" - {m}")

