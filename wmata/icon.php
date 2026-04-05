<?php
/**
 * wmata/icon.php
 * Serves .icon files from wmata/assets/icons/ with correct MIME type.
 *
 * Usage:  /wmata/icon?name=metro
 *         /wmata/icon?name=line-rd
 *
 * The .icon files are PNG or SVG images stored with a custom extension.
 * This script reads the magic bytes to detect the actual format.
 */

require_once __DIR__ . '/../shared/config.php';

// Validate name: only alphanumeric, hyphens, underscores allowed
$name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    exit('Bad request');
}

$path = __DIR__ . '/assets/icons/' . $name . '.icon';

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Icon not found');
}

// Detect image format from magic bytes
$fh     = fopen($path, 'rb');
$header = fread($fh, 8);
fclose($fh);

// PNG: 8-byte signature
if (str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
    $mime = 'image/png';
// JPEG: FF D8 FF
} elseif (str_starts_with($header, "\xFF\xD8\xFF")) {
    $mime = 'image/jpeg';
// GIF
} elseif (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
    $mime = 'image/gif';
// WebP: RIFF....WEBP
} elseif (str_starts_with($header, 'RIFF') && substr($header, 4, 4) === 'WEBP') {
    $mime = 'image/webp';
// SVG: starts with '<' (XML/SVG) — also catches <?xml and <svg
} elseif (ltrim($header)[0] === '<') {
    $mime = 'image/svg+xml';
} else {
    // Fallback: check file content for SVG declaration
    $chunk = file_get_contents($path, false, null, 0, 256);
    $mime  = (str_contains($chunk, '<svg') || str_contains($chunk, '<?xml'))
           ? 'image/svg+xml'
           : 'application/octet-stream';
}

$size  = filesize($path);
$mtime = filemtime($path);
$etag  = '"' . md5($name . $mtime) . '"';

// Conditional GET support
if (
    isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
    trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag
) {
    http_response_code(304);
    exit;
}

header('Content-Type: '  . $mime);
header('Content-Length: ' . $size);
header('ETag: '           . $etag);
header('Cache-Control: public, max-age=604800, stale-while-revalidate=86400');
header('Last-Modified: '  . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

readfile($path);
