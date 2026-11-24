<?php
declare(strict_types=1);

// Publisher credential is provisioned outside the document root.
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
function stop(int $code): void {
    http_response_code($code);
    exit;
}
$root = dirname(__DIR__) . '/.photographie-livraisons';
$key = $_SERVER['HTTP_X_GALLERY_PUBLISH_KEY'] ?? '';
$keyPath = $root . '/.publisher.sha256';
if (($_SERVER['HTTPS'] ?? '') !== 'on' || $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !preg_match('/\A[a-f0-9]{64}\z/', $key) || !is_readable($keyPath)
    || !hash_equals(trim((string) file_get_contents($keyPath)), hash('sha256', $key))) {
    stop(404);
}
umask(0077);
$id = $_GET['g'] ?? '';
$part = $_GET['part'] ?? '';
if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/', $id) || !is_string($part)) {
    stop(400);
}
$target = $root . '/' . $id;
$stage = $root . '/.https-' . $id;
$lock = fopen($root . '/.publisher.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX)) {
    stop(503);
}
if (is_dir($target)) {
    stop(409);
}
if (!is_dir($stage . '/photos') && !mkdir($stage . '/photos', 0700, true)) {
    stop(500);
}
$input = fopen('php://input', 'rb');
if (!$input) {
    stop(400);
}
if (preg_match('/\A[1-9][0-9]{0,2}\z/', $part) && (int) $part <= 500) {
    $payload = json_decode((string) stream_get_contents($input, 35 * 1024 * 1024), true);
    $image = is_string($payload['jpeg'] ?? null) ? base64_decode($payload['jpeg'], true) : false;
    if (!is_string($image) || strlen($image) > 25 * 1024 * 1024
        || (getimagesizefromstring($image)[2] ?? null) !== IMAGETYPE_JPEG) {
        stop(400);
    }
    if (file_put_contents($stage . '/photos/' . $part . '.jpg', $image) !== strlen($image)) {
        stop(500);
    }
    stop(204);
}
if ($part !== 'finaliser') {
    stop(400);
}
$manifest = json_decode((string) stream_get_contents($input, 256 * 1024), true);
if (!is_array($manifest) || !is_string($manifest['title'] ?? null)
    || !is_array($manifest['photos'] ?? null) || count($manifest['photos']) < 1
    || count($manifest['photos']) > 500) {
    stop(400);
}
foreach (['token_hash', 'owner_hash'] as $field) {
    if (!is_string($manifest[$field] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/', $manifest[$field])) {
        stop(400);
    }
}
foreach ($manifest['photos'] as $number => $name) {
    if (!is_int($number) || $number < 1 || $number > 500 || !is_string($name)
        || !is_file($stage . '/photos/' . $number . '.jpg')) {
        stop(400);
    }
}
$manifest['expires'] = time() + 5 * 86400;
if (file_put_contents($stage . '/gallery.json', json_encode($manifest, JSON_THROW_ON_ERROR)) === false
    || !rename($stage, $target)) {
    stop(500);
}
stop(204);
