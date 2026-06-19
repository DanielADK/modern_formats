<?php

if (!defined('PHPWG_ROOT_PATH')) exit('Hacking attempt!');

// Reads metadata back out of a converted WebP for the picture page, since Piwigo's
// own readers are JPEG-only (exif_read_data / getimagesize+APP13).
final class ModernFormats_WebpMetadataReader
{
    /**
     * Canonical IPTC-IIM (2#dataset) <-> XMP correspondence, shared with the
     * converter (which writes the XMP) and used here to read it back. `iptc` is
     * the ExifTool IPTC tag name; `ns`/`prop` is the XMP property (`prop` matches
     * the actual XML local-name, case-sensitive).
     *
     * @var array<string,array{iptc: string, ns: string, prop: string}>
     */
    public const IPTC_XMP = [
        '2#005' => ['iptc' => 'ObjectName', 'ns' => 'dc', 'prop' => 'title'],
        '2#080' => ['iptc' => 'By-line', 'ns' => 'dc', 'prop' => 'creator'],
        '2#085' => ['iptc' => 'By-lineTitle', 'ns' => 'photoshop', 'prop' => 'AuthorsPosition'],
        '2#120' => ['iptc' => 'Caption-Abstract', 'ns' => 'dc', 'prop' => 'description'],
        '2#122' => ['iptc' => 'Writer-Editor', 'ns' => 'photoshop', 'prop' => 'CaptionWriter'],
        '2#025' => ['iptc' => 'Keywords', 'ns' => 'dc', 'prop' => 'subject'],
        '2#090' => ['iptc' => 'City', 'ns' => 'photoshop', 'prop' => 'City'],
        '2#101' => ['iptc' => 'Country-PrimaryLocationName', 'ns' => 'photoshop', 'prop' => 'Country'],
        '2#100' => ['iptc' => 'Country-PrimaryLocationCode', 'ns' => 'iptcCore', 'prop' => 'CountryCode'],
        '2#116' => ['iptc' => 'CopyrightNotice', 'ns' => 'dc', 'prop' => 'rights'],
    ];
    // XMP namespace URIs, registered as XPath prefixes when reading IPTC.
    private const NS = [
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'photoshop' => 'http://ns.adobe.com/photoshop/1.0/',
        'iptcCore' => 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/',
    ];

    /**
     * EXIF in the shape exif_read_data() would return, or null when the WebP has no
     * readable EXIF chunk. The FILE section is rewritten to describe the WebP itself.
     *
     * @return null|array<array-key,mixed>
     */
    public static function exif(string $path): ?array
    {
        $chunk = self::chunk($path, 'EXIF');
        if (null === $chunk) {
            return null;
        }
        // Some writers prefix the APP1 "Exif\0\0" marker; the rest is raw TIFF.
        if (str_starts_with($chunk, "Exif\x00\x00")) {
            $chunk = substr($chunk, 6);
        }
        $magic = substr($chunk, 0, 4);
        if ("II\x2a\x00" !== $magic && "MM\x00\x2a" !== $magic) {
            return null; // not a TIFF stream exif_read_data() could parse
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mf_exif_');
        if (false === $tmp) {
            return null;
        }
        $exif = false;

        try {
            if (false === @file_put_contents($tmp, $chunk)) {
                return null;
            }
            $exif = @exif_read_data($tmp);
        } finally {
            @unlink($tmp);
        }
        if (!is_array($exif)) {
            return null;
        }

        // exif_read_data described the temp file; point every file-identity field back
        // at the WebP so 'FILE;FileSize' and flat 'FileName'/'MimeType'/... read right.
        $mtime = @filemtime($path);
        $size = @filesize($path);
        $orig_file = $exif['FILE'] ?? null;
        $sections = is_array($orig_file) && isset($orig_file['SectionsFound']) ? $orig_file['SectionsFound'] : '';
        $file = [
            'FileName' => basename($path),
            'FileDateTime' => false === $mtime ? 0 : $mtime,
            'FileSize' => false === $size ? 0 : $size,
            'FileType' => IMAGETYPE_WEBP,
            'MimeType' => 'image/webp',
            'SectionsFound' => $sections,
        ];
        $exif['FILE'] = $file;
        // exif_read_data also exposes these as flat top-level keys; overwrite the stale
        // temp-file values so a flat-configured EXIF field shows the WebP, not the temp.
        foreach (['FileName', 'FileDateTime', 'FileSize', 'FileType', 'MimeType'] as $k) {
            $exif[$k] = $file[$k];
        }

        return $exif;
    }

    /**
     * IPTC values keyed by IPTC-IIM dataset code (e.g. '2#080'), recovered from the
     * WebP's XMP chunk. Multi-valued fields are joined with ', '. Empty when none.
     *
     * @return array<string,string>
     */
    public static function iptc(string $path): array
    {
        if (!class_exists('DOMDocument')) {
            return []; // ext-dom absent: degrade quietly rather than break the page
        }
        $chunk = self::chunk($path, 'XMP ');
        if (null === $chunk) {
            return [];
        }
        // Strip the xpacket envelope (BOM + leading/trailing processing instructions);
        // what remains is the xmpmeta element whatever its namespace prefix.
        $xml = preg_replace(['/^\xEF\xBB\xBF/', '/<\?xpacket[^>]*\?>/'], '', $chunk);
        if (null === $xml) {
            return [];
        }
        $xml = trim($xml);
        // Legitimate XMP never carries a DOCTYPE; refusing it keeps entity expansion
        // off the table regardless of libxml defaults.
        if ('' === $xml || false !== stripos($xml, '<!DOCTYPE')) {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return [];
        }

        $xp = new DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $xp->registerNamespace($prefix, $uri);
        }

        $result = [];
        foreach (self::IPTC_XMP as $code => $m) {
            $value = self::readProp($xp, $m['ns'], $m['prop']);
            if (null !== $value && '' !== $value) {
                $result[$code] = $value;
            }
        }

        return $result;
    }

    // Streams the RIFF container and returns the payload of the first chunk with the
    // given FourCC, or null. fseeks past the (large) image payload so only the matching
    // metadata chunk is read into memory, never the whole file.
    private static function chunk(string $path, string $fourcc): ?string
    {
        $fh = @fopen($path, 'r');
        if (false === $fh) {
            return null;
        }

        try {
            $hdr = fread($fh, 12);
            if (!is_string($hdr) || 12 !== strlen($hdr) || !str_starts_with($hdr, 'RIFF') || 'WEBP' !== substr($hdr, 8, 4)) {
                return null;
            }
            while (true) {
                $head = fread($fh, 8);
                if (!is_string($head) || 8 !== strlen($head)) {
                    return null;
                }
                $cc = substr($head, 0, 4);
                $u = unpack('V', substr($head, 4, 4));
                if (false === $u || !isset($u[1]) || !is_int($u[1]) || $u[1] < 0) {
                    return null;
                }
                $sz = $u[1];
                if ($cc === $fourcc) {
                    if (0 === $sz) {
                        return '';
                    }
                    $body = fread($fh, $sz);

                    return is_string($body) && strlen($body) === $sz ? $body : null;
                }
                // Skip payload + the pad byte RIFF adds to keep chunks even-sized.
                if (0 !== fseek($fh, $sz + ($sz & 1), SEEK_CUR)) {
                    return null;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    // Reads one XMP property as a scalar, handling the attribute form, the simple
    // element form, and rdf:Alt/Seq/Bag containers (joined with ', ').
    private static function readProp(DOMXPath $xp, string $ns, string $local): ?string
    {
        $attr = $xp->query("//rdf:Description/@{$ns}:{$local}");
        if (false !== $attr && $attr->length > 0) {
            $node = $attr->item(0);
            if (null !== $node) {
                $v = trim((string) $node->nodeValue);

                return '' === $v ? null : $v;
            }
        }

        $els = $xp->query("//{$ns}:{$local}");
        if (false === $els || 0 === $els->length) {
            return null;
        }
        $el = $els->item(0);
        if (!$el instanceof DOMNode) {
            return null;
        }

        $lis = $xp->query('.//rdf:li', $el);
        if (false !== $lis && $lis->length > 0) {
            $vals = [];
            foreach ($lis as $li) {
                if (!$li instanceof DOMNode) {
                    continue;
                }
                $t = trim($li->textContent);
                if ('' !== $t) {
                    $vals[] = $t;
                }
            }

            return [] === $vals ? null : implode(', ', $vals);
        }

        $t = trim($el->textContent);

        return '' === $t ? null : $t;
    }
}
