# Modern Formats (Piwigo plugin)

Automatically converts newly uploaded JPEG/PNG photos to **WebP**, with a configurable
quality (default 80) and a button to **bulk-convert** existing photos.

## How it works

On upload (`loc_end_add_uploaded_file`) the original is transcoded to WebP and becomes the
stored original; Piwigo then generates WebP derivatives natively (requires Piwigo 14+).
Bulk conversion runs as a chunked, resumable AJAX loop over a web-service method.

## Requirements

- Piwigo 14+
- PHP 8.2+ with **GD built with WebP** or the **Imagick** extension with WebP support

## Settings

Admin → Plugins → Modern Formats → Settings: WebP quality, which formats to convert,
auto-convert on upload, and whether to keep a backup of originals.

## Originals & backups

The original JPEG/PNG is replaced by the WebP. With "Keep a backup" (default) the original
is moved to `_data/modern_formats_backup/` (web-access denied, kept on uninstall). Note that
"download original" returns the WebP after conversion; embedded EXIF may be dropped (Piwigo
keeps photo metadata in its database).

## Install (from source)

```bash
./build.sh   # produces modern_formats.zip
```
Upload via Admin → Plugins → Manage → Add, or extract into `plugins/modern_formats/`.

## Translations

Available in 21 languages. Corrections and new translations are welcome.

## License

WTFPL — see [LICENSE](LICENSE).
