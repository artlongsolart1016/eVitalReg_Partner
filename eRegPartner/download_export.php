<?php ob_start(); // ← MUST be line 1 char 1 — buffers ALL output (BOM, whitespace, PHP notices from includes)
/**
 * download_export.php
 * ─────────────────────────────────────────────────────────────────────
 * Streams xlsx to browser then DELETES it from the server.
 *
 * WHY ob_start() ON LINE 1:
 *   config.php / SecurityHelper.php may have trailing whitespace, a
 *   closing ?> tag, a BOM, or PHP notices that get emitted BEFORE our
 *   binary headers are sent.  Even a single stray byte prepended to the
 *   xlsx (a ZIP file) breaks the ZIP header → Excel shows
 *   "file format or file extension is not valid".
 *   ob_start() captures every byte from line 1.
 *   We call ob_end_clean() right before echo to flush nothing but the
 *   pure binary content.
 *
 * Security:
 *   ✅ Session + requireLogin check
 *   ✅ basename()  — strips any path from the ?file= param
 *   ✅ realpath()  — must resolve to inside exports/ (no traversal)
 *   ✅ .xlsx only
 *   ✅ File deleted from server right after read (before headers sent)
 */

session_start();

require_once 'config/config.php';
require_once 'classes/SecurityHelper.php';
SecurityHelper::requireLogin();

// ── Validate ?file= param ──────────────────────────────────────────────────
$raw = trim($_GET['file'] ?? '');

if ($raw === '') {
    while (ob_get_level()) ob_end_clean();
    http_response_code(400); exit('No file specified.');
}

$filename = basename($raw);   // strip any directory component

if (!preg_match('/\.xlsx$/i', $filename)) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(403); exit('Invalid file type.');
}

// ── Resolve & guard path ──────────────────────────────────────────────────
$exportDir    = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'exports');
$realFilepath = realpath($exportDir . DIRECTORY_SEPARATOR . $filename);

if (!$exportDir || !$realFilepath) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(404); exit('File not found.');
}

// Prevent symlink escape — file must be directly inside exports/
if (strpos($realFilepath, $exportDir . DIRECTORY_SEPARATOR) !== 0) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(403); exit('Access denied.');
}

if (!is_file($realFilepath)) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(404); exit('File not found or already downloaded.');
}

// ── Read entire file into RAM ─────────────────────────────────────────────
$fileData = file_get_contents($realFilepath);
if ($fileData === false) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500); exit('Could not read file.');
}
$fileSize = strlen($fileData);

// ── Delete from server NOW (data is safely in RAM) ───────────────────────
@unlink($realFilepath);

// ── PURGE output buffer — zero stray bytes before binary stream ──────────
while (ob_get_level()) ob_end_clean();

// ── Stream clean binary to browser ───────────────────────────────────────
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $fileSize);
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

echo $fileData;
exit;