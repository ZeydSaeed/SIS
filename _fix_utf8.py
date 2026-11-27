from pathlib import Path

p = Path(r"d:\Projects\sis\resources\js\components\enrollments\enrollment-list.tsx")
raw = p.read_bytes()
fixed = (
    raw.replace(bytes([0x97]), b"-")
    .replace(bytes([0x96]), b"-")
    .replace(bytes([0x91]), b"'")
    .replace(bytes([0x92]), b"'")
    .replace(bytes([0x93]), b'"')
    .replace(bytes([0x94]), b'"')
    .replace(bytes([0x85]), b"...")
)
fixed.decode("utf-8")
p.write_bytes(fixed)
print("ok", len(fixed))
