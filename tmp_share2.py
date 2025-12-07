from pathlib import Path
lines=Path('index.php').read_text(encoding='utf-8').splitlines()
for i in range(320, 420):
    print(f'{i+1}: {lines[i]}')
