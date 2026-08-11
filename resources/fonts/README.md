# Server-rendered design fonts

The composed-design renderer never downloads fonts at runtime. It resolves these optional project-local files first:

- `Cattie-Script.ttf`
- `Cattie-Serif-Bold.ttf`
- `Cattie-Sans-Bold.ttf`

If they are absent, it uses offline platform fonts (Segoe Script, Georgia Bold, and Arial Bold on Windows; DejaVu equivalents on Linux). Any font binaries added here must be redistributable and must include their licence in this directory.
