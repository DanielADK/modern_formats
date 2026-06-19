<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

// Picture-page handlers that restore EXIF/IPTC panels for converted WebP. Kept apart
// from events.inc.php so the front-end never loads the admin/upload includes.
require_once __DIR__.'/metadata_reader.class.php';

/**
 * Filter on Piwigo's 'format_exif_data'. get_exif_data() fires it when its native
 * exif_read_data() comes back empty — which it always does for WebP. We then supply
 * EXIF read from the WebP's embedded chunk, in the same shape exif_read_data() returns.
 * The filter ALSO fires with a real array on the JPEG success path, so a populated
 * $exif must be returned untouched.
 *
 * @param mixed $exif
 * @param mixed $filename
 *
 * @return mixed
 */
function modern_formats_format_exif_data($exif, $filename)
{
    // A populated array means the JPEG success path — leave it untouched.
    if (is_array($exif) && [] !== $exif) {
        return $exif;
    }
    if (!is_string($filename) || 1 !== preg_match('/\.webp$/i', $filename)) {
        return $exif;
    }

    return ModernFormats_WebpMetadataReader::exif($filename) ?? $exif;
}

/**
 * Hook on 'loc_end_picture'. Piwigo's get_iptc_data() is JPEG-only (APP13) with no
 * plugin seam, so for a converted WebP we append the IPTC panel ourselves, read from
 * the embedded XMP. Mirrors the gating around picture_metadata.inc.php in picture.php
 * (metadata toggle + $metadata_showable) and only acts on .webp originals.
 */
function modern_formats_picture_iptc(): void
{
    global $template, $picture, $conf, $lang;

    /** @var Template $template */
    /** @var array<string,mixed> $picture */
    /** @var array<string,mixed> $conf */
    /** @var array<string,mixed> $lang */
    if (!(bool) ($conf['show_iptc'] ?? false) || null === pwg_get_session_var('show_metadata')) {
        return;
    }
    $current = $picture['current'] ?? null;
    if (!is_array($current) || !isset($current['src_image']) || !($current['src_image'] instanceof SrcImage)) {
        return;
    }
    $src = $current['src_image'];

    // Only our converted WebP originals are relevant — bail before re-firing core's
    // get_element_metadata_available on every non-webp (JPEG/PNG/video) picture page.
    $path = $src->get_path();
    if (1 !== preg_match('/\.webp$/i', $path)) {
        return;
    }

    // Replicate core's $metadata_showable so the panel hides exactly when Piwigo's would.
    $showable = trigger_change(
        'get_element_metadata_available',
        ((bool) ($conf['show_exif'] ?? false) || (bool) ($conf['show_iptc'] ?? false)) && !$src->is_mimetype(),
        $current
    );
    if (!$showable) {
        return;
    }

    $iptc = ModernFormats_WebpMetadataReader::iptc($path);
    if ([] === $iptc) {
        return;
    }

    $mapping = is_array($conf['show_iptc_mapping'] ?? null) ? $conf['show_iptc_mapping'] : [];
    $allow_html = (bool) ($conf['allow_html_in_metadata'] ?? false);
    $lines = [];
    foreach ($mapping as $pwg_key => $code) {
        if (!is_string($code) || !isset($iptc[$code]) || '' === $iptc[$code]) {
            continue;
        }
        // get_exif_data strips tags for the EXIF panel; this path bypasses it, so do
        // the same here for XSS parity when HTML in metadata is disallowed.
        $value = $allow_html ? $iptc[$code] : strip_tags($iptc[$code]);
        $label = (isset($lang[$pwg_key]) && is_string($lang[$pwg_key])) ? $lang[$pwg_key] : (string) $pwg_key;
        $lines[$label] = $value;
    }
    if ([] === $lines) {
        return;
    }

    $template->append('metadata', ['TITLE' => l10n('IPTC Metadata'), 'lines' => $lines]);
}
