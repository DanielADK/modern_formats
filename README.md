# Modern Formats (Piwigo plugin)

Automatically converts newly uploaded JPEG/PNG photos to **WebP**, with a configurable
quality (default 80) and a button to **bulk-convert** existing photos.

## How it works

On upload (`loc_end_add_uploaded_file`) the original is transcoded to WebP and becomes the
stored original; Piwigo then generates WebP derivatives natively (requires Piwigo 14+).
Bulk conversion runs as a chunked, resumable AJAX loop over a web-service method, and
can be scoped to a single album (and its sub-albums) for large galleries.

## Requirements

- Piwigo 14+
- PHP 8.2+ with **GD built with WebP** or the **Imagick** extension with WebP support
- Optional: **ExifTool** (or Imagick) to copy photo metadata into the WebP

## Settings

Admin → Plugins → Modern Formats → Settings: WebP quality, which formats to convert,
auto-convert on upload, whether to preserve photo metadata, and whether to keep a backup
of originals.

## Metadata

With "Preserve photo metadata" (default) the original's EXIF/IPTC/XMP is copied into the
converted WebP, using **ExifTool** when available, otherwise **Imagick**. Orientation is
not copied (rotation is baked into the pixels). On a host with neither ExifTool nor Imagick
(GD-only), metadata is not embedded; Piwigo still shows it from its own database unless you
re-run "Synchronize metadata" on the converted photos.

## Originals & backups

The original JPEG/PNG is replaced by the WebP. With "Keep a backup" (default) the original
is moved to `_data/modern_formats_backup/` (web-access denied, kept on uninstall).

## Development

```bash
composer check   # php-cs-fixer (dry-run) + phpstan (max) + phpunit
```

## Install (from source)

```bash
./build.sh   # produces modern_formats.zip
```
Upload via Admin → Plugins → Manage → Add, or extract into `plugins/modern_formats/`.

## Translations

Available in 21 languages. Corrections and new translations are welcome.

## License

WTFPL — see [LICENSE](LICENSE).
