import os, re

VIEWS_DIR = 'resources/views'
WHITELIST = {
    'resources/views/components/btn.blade.php',
    'resources/views/components/btn-icon.blade.php',
    'resources/views/components/status-badge.blade.php',
    'resources/views/layouts/partials/sidebar.blade.php',
    'resources/views/layouts/partials/topbar.blade.php',
}

btn_replacements = [
    # Submit / Simpan / Tambah / Buat / Kirim / Terapkan / Masuk
    (re.compile(r'<button\s+([^>]*?)type="submit"([^>]*?)class="[^"]*\bbtn-primary\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn type="submit" variant="primary" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-primary\b[^"]*"([^>]*?)type="submit"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn type="submit" variant="primary" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)type="button"([^>]*?)class="[^"]*\bbtn-primary\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="primary" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-primary\b[^"]*"([^>]*?)type="button"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="primary" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-primary\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="primary" \1 \2>\3</x-btn>'),

    # Neutral: btn-outline-secondary
    (re.compile(r'<button\s+([^>]*?)type="button"([^>]*?)class="[^"]*\bbtn-outline-secondary\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="neutral" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-outline-secondary\b[^"]*"([^>]*?)type="button"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="neutral" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)type="reset"([^>]*?)class="[^"]*\bbtn-outline-secondary\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn type="reset" variant="neutral" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-outline-secondary\b[^"]*"([^>]*?)type="reset"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn type="reset" variant="neutral" \1 \2 \3>\4</x-btn>'),
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-outline-secondary\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="neutral" \1 \2>\3</x-btn>'),
    (re.compile(r'<a\s+([^>]*?)class="[^"]*\bbtn-outline-secondary\b[^"]*"([^>]*?)>(.*?)</a>', re.DOTALL),
     r'<x-btn variant="neutral" \1 \2>\3</x-btn>'),

    # Danger: btn-danger / btn-outline-danger
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-(outline-)?danger\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="danger" \1 \3>\4</x-btn>'),

    # Approve / Success: btn-success / btn-outline-success
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-(outline-)?success\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="approve" \1 \3>\4</x-btn>'),
    (re.compile(r'<a\s+([^>]*?)class="[^"]*\bbtn-(outline-)?success\b[^"]*"([^>]*?)>(.*?)</a>', re.DOTALL),
     r'<x-btn variant="approve" \1 \3>\4</x-btn>'),

    # Warning: btn-warning / btn-outline-warning
    (re.compile(r'<button\s+([^>]*?)class="[^"]*\bbtn-(outline-)?warning\b[^"]*"([^>]*?)>(.*?)</button>', re.DOTALL),
     r'<x-btn variant="warning" \1 \3>\4</x-btn>'),

    # Export / Primary link: <a class="btn btn-outline-primary" ...>
    (re.compile(r'<a\s+([^>]*?)class="[^"]*\bbtn-outline-primary\b[^"]*"([^>]*?)>(.*?)</a>', re.DOTALL),
     r'<x-btn variant="export" \1 \2>\3</x-btn>'),
    (re.compile(r'<a\s+([^>]*?)class="[^"]*\bbtn-primary\b[^"]*"([^>]*?)>(.*?)</a>', re.DOTALL),
     r'<x-btn variant="primary" \1 \2>\3</x-btn>'),
]

for root, dirs, files in os.walk(VIEWS_DIR):
    for f in files:
        if not f.endswith('.blade.php'): continue
        filepath = os.path.join(root, f).replace('\\', '/')
        if filepath in WHITELIST: continue
        
        with open(filepath, 'r', encoding='utf-8') as fh:
            content = fh.read()
        
        orig = content
        for pat, repl in btn_replacements:
            content = pat.sub(repl, content)
        
        if content != orig:
            with open(filepath, 'w', encoding='utf-8') as fh:
                fh.write(content)
            print(f"Converted buttons in {filepath}")

