<?php
declare(strict_types=1);

header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
session_name('livraison_client');
session_start(['cookie_secure' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);

function fail(int $status, string $message): void {
    http_response_code($status);
    exit($message);
}
function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$gallery = $_GET['g'] ?? '';
if (!is_string($gallery) || !preg_match('/\A[a-f0-9]{32}\z/', $gallery)) {
    fail(404, 'Galerie indisponible.');
}
$root = dirname(__DIR__) . '/.photographie-livraisons';
$dir = $root . '/' . $gallery;
$manifest = is_readable($dir . '/gallery.json')
    ? json_decode((string) file_get_contents($dir . '/gallery.json'), true) : null;
if (!is_array($manifest) || $manifest['expires'] <= time()) {
    fail(404, 'Galerie indisponible ou expirée.');
}
$url = '?g=' . $gallery;
$ownerView = ($_GET['vue'] ?? '') === 'photographe';
if ($ownerView) {
    $url .= '&vue=photographe';
}
$role = $ownerView ? 'photographe' : 'client';
$accessHash = $manifest[$ownerView ? 'owner_hash' : 'token_hash'] ?? '';
$token = $_GET['access'] ?? null;
if ($token !== null) {
    if (!is_string($token) || !hash_equals($accessHash, hash('sha256', $token))) {
        fail(404, 'Galerie indisponible.');
    }
    session_regenerate_id(true);
    $_SESSION['galleries'][$gallery][$role] = $accessHash;
    header('Location: ' . $url, true, 303);
    exit;
}
if ($accessHash === '' || !hash_equals($accessHash, $_SESSION['galleries'][$gallery][$role] ?? '')) {
    fail(404, 'Ouvrez le lien de livraison fourni par votre photographe.');
}
$photos = $manifest['photos'];
$photo = $_GET['photo'] ?? null;
if ($photo !== null) {
    if (!is_string($photo) || !preg_match('/\A[0-9]+\z/', $photo) || !isset($photos[$photo])) {
        fail(404, 'Photo indisponible.');
    }
    $path = $dir . '/photos/' . $photo . '.jpg';
    if (!is_file($path)) {
        fail(404, 'Photo indisponible.');
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($path));
    session_write_close();
    readfile($path);
    exit;
}

$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($ownerView) {
        fail(405, 'La vue photographe est en lecture seule.');
    }
    $csrf = $_POST['csrf'] ?? null;
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        fail(403, 'Rechargez la galerie avant de réessayer.');
    }
    $notes = $_POST['notes'] ?? [];
    if (!is_array($notes) || count($notes) > count($photos)) {
        fail(400, 'Demandes invalides.');
    }
    $requests = [];
    foreach ($notes as $id => $note) {
        if (!isset($photos[$id]) || !is_string($note) || strlen($note) > 8000) {
            fail(400, 'Une demande est invalide ou trop longue.');
        }
        if (trim($note) !== '') {
            $requests[] = ['photo' => $photos[$id], 'demande' => trim($note)];
        }
    }
    if (!$requests) {
        fail(400, 'Ajoutez une demande sous au moins une photo.');
    }
    $record = json_encode(['date' => gmdate('c'), 'demandes' => $requests],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (file_put_contents($dir . '/retouches.jsonl', $record . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        fail(500, 'Enregistrement impossible. Réessayez dans un instant.');
    }
    $_SESSION['sent'][$gallery] = true;
    header('Location: ' . $url, true, 303);
    exit;
}
$sent = !empty($_SESSION['sent'][$gallery]);
unset($_SESSION['sent'][$gallery]);
$byPhoto = [];
if ($ownerView && is_file($dir . '/retouches.jsonl')) {
    $log = fopen($dir . '/retouches.jsonl', 'rb');
    if ($log === false || !flock($log, LOCK_SH)) {
        fail(500, 'Impossible de lire les retouches.');
    }
    while (($line = fgets($log)) !== false) {
        $entry = json_decode($line, true);
        foreach ($entry['demandes'] ?? [] as $request) {
            $byPhoto[$request['photo']][] = $request['demande'];
        }
    }
    fclose($log);
}
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Votre galerie privée</title>
<style>
body{margin:0;background:#f2f0e9;color:#222;font:16px system-ui,sans-serif}
main{max-width:1100px;margin:auto;padding:clamp(16px,4vw,48px)}
h1,h2{overflow-wrap:anywhere} h2{font-size:1rem}
.notes{overflow-wrap:anywhere;white-space:pre-wrap}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:20px}
article{min-width:0;background:white;padding:14px;border-radius:8px}
img{display:block;width:100%;aspect-ratio:3/2;object-fit:contain;background:#eee}
textarea{display:block;box-sizing:border-box;width:100%;font:inherit;margin-top:8px;padding:8px;resize:vertical}
button{margin-top:20px;font:inherit;background:#222;color:white;border:0;border-radius:6px;padding:12px 18px}
</style></head><body><main>
<p>Photographie immobilière Québec · Galerie privée</p>
<h1><?= esc($manifest['title']) ?></h1>
<?php if ($ownerView): ?>
<p>Vue photographe · Demandes de retouche reçues</p>
<?php else: ?>
<p>Ouvrez une photo pour la voir en grand ou écrivez une demande de retouche sous la photo concernée.</p>
<?php if ($sent): ?><p role="status">Tes demandes de retouche ont été enregistrées.</p><?php endif; ?>
<form method="post" action="<?= esc($url) ?>">
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
<?php endif; ?>
<div class="grid">
<?php foreach ($photos as $id => $name): $image = esc($url . '&photo=' . $id); ?>
<article>
<a href="<?= $image ?>" target="_blank" rel="noopener"><img src="<?= $image ?>" alt="<?= esc($name) ?>" loading="lazy"></a>
<h2><?= esc($name) ?></h2>
<?php if ($ownerView): ?>
<?php foreach ($byPhoto[$name] ?? [] as $note): ?>
<p class="notes"><?= esc($note) ?></p>
<?php endforeach; ?>
<?php if (empty($byPhoto[$name])): ?><p>Aucune demande pour cette photo.</p><?php endif; ?>
<?php else: ?>
<label>Retouche demandée<textarea rows="3" maxlength="1500" name="notes[<?= (int) $id ?>]"></textarea></label>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php if (!$ownerView): ?>
<button type="submit">Envoyer mes demandes de retouche</button></form>
<?php endif; ?>
</main></body></html>
