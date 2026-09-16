from PIL import Image
from pathlib import Path

src = Path(
    r"C:\Users\TWENTY F C\.cursor\projects\c-Users-TWENTY-F-C-config-herd-config-valet-Sites-sis\assets\c__Users_TWENTY_F_C_AppData_Roaming_Cursor_User_workspaceStorage_871ef36148498044d8fb78540b54b064_images_images-15b4a5a7-3960-4f5c-9fab-0050d5335e09.png"
)
public = Path(r"c:\Users\TWENTY F C\.config\herd\config\valet\Sites\sis\public")
assets = Path(r"c:\Users\TWENTY F C\.config\herd\config\valet\Sites\sis\assets\desktop")
assets.mkdir(parents=True, exist_ok=True)

img = Image.open(src).convert("RGBA")
# Keep original yellow background exactly as provided.
corner = img.getpixel((4, 4))
print("bg", corner)

img.convert("RGB").save(public / "sis-logo.jpg", quality=95, optimize=True)
# Also keep PNG source copy for lossless mark
img.save(public / "sis-logo.png", optimize=True)

bg = (corner[0], corner[1], corner[2], 255)
side = max(img.size)
canvas = Image.new("RGBA", (side, side), bg)
ox = (side - img.size[0]) // 2
oy = (side - img.size[1]) // 2
canvas.paste(img, (ox, oy), img)

master = canvas.resize((512, 512), Image.Resampling.LANCZOS)
master.save(public / "sis-mark.png", optimize=True)
master.save(assets / "sis-mark.png", optimize=True)
master.save(assets / "sis-hub.png", optimize=True)

for s in (32, 180, 192):
    resized = master.resize((s, s), Image.Resampling.LANCZOS)
    if s == 32:
        resized.save(public / "favicon-32.png", optimize=True)
    if s == 180:
        resized.save(public / "apple-touch-icon.png", optimize=True)
    if s == 192:
        resized.save(public / "sis-mark-192.png", optimize=True)

master.save(
    public / "favicon.ico",
    format="ICO",
    sizes=[(16, 16), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)],
)

(public / "favicon.svg").write_text(
    """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="SIS">
  <image href="/sis-mark.png" width="512" height="512" preserveAspectRatio="xMidYMid meet"/>
</svg>
""",
    encoding="utf-8",
)

print("ok", master.size, "bg_rgb", bg[:3])
