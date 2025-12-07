from pathlib import Path
lines=Path('index.php').read_text(encoding='utf-8').splitlines()
for i in range(1, 150):
    print(f'{i:04}: {lines[i-1]}')
