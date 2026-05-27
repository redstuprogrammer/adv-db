import re, subprocess, tempfile, os
from pathlib import Path
p = Path('services.php')
text = p.read_text(encoding='utf-8', errors='replace')
pattern = re.compile(r'<\?(?:php)?|\?>')
ms = list(pattern.finditer(text))
print('tokens', len(ms))
for i in range(0, len(ms), 2):
    if i+1 >= len(ms):
        print('unmatched start at', i)
        break
    openm = ms[i]
    closem = ms[i+1]
    block = text[openm.end():closem.start()]
    with tempfile.NamedTemporaryFile('w', delete=False, suffix='.php', encoding='utf-8') as tmp:
        tmp.write('<?php\n' + block + '\n?>')
        tmpname = tmp.name
    proc = subprocess.run(['D:\\XAMPP\\php\\php.exe', '-l', tmpname], capture_output=True, text=True)
    if proc.returncode != 0:
        print('BLOCK', i//2+1, 'ERROR')
        print(proc.stderr.strip())
        print(block[:1000])
        os.unlink(tmpname)
        break
    os.unlink(tmpname)
