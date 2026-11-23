<?php
declare(strict_types=1);

// Fixed recipient and envelope sender: this endpoint cannot act as an open relay.
const CONTACT_RECIPIENT = 'info@photographie-immobiliere-quebec.com';
const CONTACT_ORIGIN = 'https://photographie-immobiliere-quebec.com';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

function respond(int $status, bool $ok, string $message, array $extra = []): void {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (PHP_VERSION_ID < 80200) {
    respond(503, false, 'Le service d’envoi doit être mis à jour. Appelez au 581-443-7718.');
}

function field(string $name, int $maximum): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value) || strlen($value) > $maximum || !preg_match('//u', $value)) {
        respond(422, false, 'Un champ est invalide ou trop long.');
    }
    return trim($value);
}

function encoded_header(string $value): string {
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function consume_rate_limit(): void {
    // Store only timestamps and hashed IPs, outside the website, never messages.
    $directory = PHP_SAPI === 'cli' && getenv('CONTACT_TEST_STORAGE')
        ? getenv('CONTACT_TEST_STORAGE') : dirname(__DIR__) . '/.photographie-contact';
    if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
        respond(503, false, 'Envoi temporairement indisponible. Appelez au 581-443-7718.');
    }
    $handle = @fopen($directory . '/limits.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        respond(503, false, 'Envoi temporairement indisponible. Réessayez plus tard.');
    }
    @chmod($directory . '/limits.json', 0600);
    $now = time();
    $data = json_decode(stream_get_contents($handle), true) ?: ['clients' => [], 'global' => []];
    foreach ($data['clients'] as $key => $times) {
        $data['clients'][$key] = array_values(array_filter($times, static function ($t) use ($now) {
            return $t > $now - 3600;
        }));
        if (!$data['clients'][$key]) {
            unset($data['clients'][$key]);
        }
    }
    $data['global'] = array_values(array_filter($data['global'], static function ($t) use ($now) {
        return $t > $now - 3600;
    }));
    // REMOTE_ADDR is server-provided; untrusted forwarding headers are ignored.
    $key = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $times = $data['clients'][$key] ?? [];
    if (count($times) >= 3 || ($times && $now - max($times) < 60) || count($data['global']) >= 30) {
        flock($handle, LOCK_UN);
        fclose($handle);
        header('Retry-After: 60');
        respond(429, false, 'Trop de demandes rapprochées. Réessayez plus tard ou appelez au 581-443-7718.');
    }
    $data['clients'][$key][] = $now;
    $data['global'][] = $now;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    respond(405, false, 'Méthode non autorisée.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 24000) {
    respond(413, false, 'Votre message est trop volumineux.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if (($origin !== '' && $origin !== CONTACT_ORIGIN) || $fetchSite === 'cross-site') {
    respond(403, false, 'Origine non autorisée.');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('photographie_contact');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true,
                          'httponly' => true, 'samesite' => 'Strict']);
if (!session_start()) {
    respond(503, false, 'Envoi temporairement indisponible. Réessayez plus tard.');
}

if ($method === 'GET') {
    if (($_GET['action'] ?? '') !== 'token') {
        header('Allow: GET, POST');
        respond(405, false, 'Utilisez le formulaire de la page Contact.');
    }
    if (!isset($_SESSION['csrf']) || time() - (int) ($_SESSION['issued'] ?? 0) > 7200) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['issued'] = time();
    }
    respond(200, true, '', ['token' => $_SESSION['csrf']]);
}

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)
    || time() - (int) ($_SESSION['issued'] ?? 0) > 7200) {
    respond(403, false, 'La session a expiré. Rechargez la page avant de réessayer.');
}
if (field('website', 500) !== '') {
    respond(200, true, 'Merci, votre demande a été prise en compte.');
}
$name = field('nom', 100);
$email = field('email', 254);
$subject = field('objet', 150);
$message = field('message', 8000);
if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || preg_match('/[\r\n\x00-\x1f\x7f]/', $name . $email . $subject)) {
    respond(422, false, 'Vérifiez votre nom, votre courriel et votre message.');
}
if (time() - (int) $_SESSION['issued'] < 2) {
    respond(429, false, 'Veuillez patienter quelques secondes avant d’envoyer.');
}
if (!function_exists('mail')) {
    respond(503, false, 'Envoi indisponible. Appelez au 581-443-7718.');
}
consume_rate_limit();
$reference = bin2hex(random_bytes(8));
$timestamp = (new DateTimeImmutable('now', new DateTimeZone('America/Toronto')))->format('Y-m-d H:i:s T');
$body = "Demande depuis le formulaire de Photographie immobilière Québec\r\n"
    . "Page : " . CONTACT_ORIGIN . "/contact.html\r\n"
    . "Destinataire : " . CONTACT_RECIPIENT . "\r\n"
    . "Référence : " . $reference . "\r\nDate : " . $timestamp . "\r\n\r\n"
    . "Nom : " . $name . "\r\nCourriel du visiteur : " . $email . "\r\n"
    . "Objet : " . ($subject !== '' ? $subject : 'Demande de renseignements') . "\r\n\r\n"
    . str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $message);
$headers = [
    'From' => encoded_header('Site web — Photographie immobilière') . ' <' . CONTACT_RECIPIENT . '>',
    'Reply-To' => $email,
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => 'base64',
    'Message-ID' => '<contact-' . $reference . '@photographie-immobiliere-quebec.com>',
    'X-Contact-Reference' => $reference,
];
$sent = @mail(CONTACT_RECIPIENT,
    encoded_header('[Site web] ' . ($subject !== '' ? $subject : 'Demande de renseignements')),
    chunk_split(base64_encode($body), 76, "\r\n"), $headers, '-f' . CONTACT_RECIPIENT);
if (!$sent) {
    error_log('Contact web failed: ' . $reference);
    respond(503, false, 'L’envoi a échoué. Réessayez plus tard ou appelez au 581-443-7718.');
}
// SMTP acceptance isn't a guarantee of final delivery; receipt must also be tested.
error_log('Contact web accepted: ' . $reference);
unset($_SESSION['csrf'], $_SESSION['issued']);
respond(200, true, 'Merci ! Votre demande a été transmise. Nous vous répondrons dès que possible.',
        ['reference' => $reference]);
