<?php ob_start(); // ← MUST be line 1 char 1 — prevents PHP warnings corrupting JSON
/**
 * api_preflight.php
 * ─────────────────────────────────────────────────────────────────────
 * Server-side CURL ping proxy for sakatamalaybalay.com
 * Called by the viewer JS before every send to verify connectivity.
 *
 * WHY SERVER-SIDE:
 *   Browser fetch() to an external domain triggers CORS → blocked.
 *   PHP CURL bypasses CORS and checks from the server's network.
 *   This also means if the SERVER can't reach sakatamalaybalay.com,
 *   we catch it early before the actual send attempt.
 *
 * Usage:
 *   GET api_preflight.php?check=apache   → checks https://sakatamalaybalay.com
 *   GET api_preflight.php?check=api      → checks https://sakatamalaybalay.com/api/lcr/dmslcr004.php?test=ping
 */

session_start();

require_once 'config/config.php';
require_once 'classes/SecurityHelper.php';
SecurityHelper::requireLogin();

header('Content-Type: application/json');

$check   = $_GET['check'] ?? 'apache';
$timeout = 10;  // seconds to wait

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT,        $timeout);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT,      'LONART-Preflight/1.0');

if ($check === 'apache') {
    // Just check if Apache responds at all (HEAD request, fast)
    curl_setopt($ch, CURLOPT_URL,           'https://sakatamalaybalay.com');
    curl_setopt($ch, CURLOPT_NOBODY,        true);  // HEAD only — no body download
} else {
    // Check the actual API ping endpoint
    curl_setopt($ch, CURLOPT_URL, 'https://sakatamalaybalay.com/api/lcr/dmslcr004.php?test=ping');
}

$body      = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

// ── Discard any buffered output ─────────────────────────────────────
while (ob_get_level()) ob_end_clean();

if ($curlErrno || $curlError) {
    // Map common errno to friendly message
    $friendly = match(true) {
        $curlErrno === 6   => 'Could not resolve host (DNS failure). Server domain unreachable.',
        $curlErrno === 7   => 'Failed to connect. Server may be down or firewall blocking.',
        $curlErrno === 28  => 'Connection timed out after ' . $timeout . 's.',
        $curlErrno === 35  => 'SSL handshake failed.',
        default            => $curlError ?: 'Unknown CURL error #' . $curlErrno,
    };

    echo json_encode([
        'reachable'   => false,
        'http_code'   => 0,
        'curl_errno'  => $curlErrno,
        'error'       => $friendly,
        'raw_error'   => $curlError,
    ]);
} else {
    // 200/401/403/500 all mean the server IS reachable (just auth may differ)
    // For the ping endpoint, 401 = "Authentication required" which is CORRECT/expected
    $reachable = $httpCode >= 200 && $httpCode < 600;

    $apiStatus = null;
    if ($check === 'api' && $body) {
        $decoded = @json_decode($body, true);
        if ($decoded) $apiStatus = $decoded['status'] ?? null;
    }

    echo json_encode([
        'reachable'   => $reachable,
        'http_code'   => $httpCode,
        'api_status'  => $apiStatus,  // "error" = auth required = API is UP
        'error'       => null,
    ]);
}
exit;