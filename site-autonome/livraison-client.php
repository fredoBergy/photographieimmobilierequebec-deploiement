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
$manifestPath = $dir . '/gallery.json';
$manifest = is_readable($manifestPath)
    ? json_decode((string) file_get_contents($manifestPath), true) : null;
if (!is_array($manifest) || min($manifest['expires'], filemtime($manifestPath) + 5 * 86400) <= time()) {
    fail(404, 'Galerie indisponible ou expirée.');
}
$url = '?g=' . $gallery;
$ownerView = ($_GET['vue'] ?? '') === 'photographe';
if ($ownerView) {
    $url .= '&vue=photographe';
}
$role = $ownerView ? 'photographe' : 'client';
$accessHash = $manifest[$ownerView ? 'owner_hash' : 'token_hash'] ?? '';
$notificationToken = isset($manifest['owner_hash'])
    ? hash_hmac('sha256', 'retouches-notification:' . $gallery, $manifest['owner_hash']) : '';
$token = $_GET['access'] ?? null;
$notification = $_GET['notification'] ?? null;
if ($token !== null || $notification !== null) {
    $linkValid = is_string($token) && hash_equals($accessHash, hash('sha256', $token));
    $notificationValid = $ownerView && $notificationToken !== '' && is_string($notification)
        && hash_equals($notificationToken, $notification);
    if (!$linkValid && !$notificationValid) {
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
$paired = !empty($manifest['pairs']);
$cards = [];
foreach ($manifest['pairs'] ?? [] as $pair) {
    if (count($pair) === 2 && isset($photos[$pair[0]], $photos[$pair[1]])) {
        $cards[$pair[1]] = ['Avant' => $pair[0], 'Après' => $pair[1]];
    }
}
if (!$paired) {
    foreach ($photos as $id => $name) {
        $cards[$id] = ['' => $id];
    }
}
$photo = $_GET['photo'] ?? null;
if ($photo !== null) {
    if (!is_string($photo) || !preg_match('/\A[0-9]+\z/', $photo) || !isset($photos[$photo])) {
        fail(404, 'Photo indisponible.');
    }
    $path = $dir . '/photos/' . $photo . '.jpg';
    if (!is_file($path)) {
        fail(404, 'Photo indisponible.');
    }
    session_write_close();
    if (!function_exists('imagecreatefromjpeg') || !($image = @imagecreatefromjpeg($path))) {
        fail(503, 'Image protégée temporairement indisponible.');
    }
    if (($_GET['comparaison'] ?? '') === '1') {
        if (count($cards[$photo] ?? []) !== 2) {
            fail(404, 'Comparaison indisponible.');
        }
        $before = @imagecreatefromjpeg($dir . '/photos/' . $cards[$photo]['Avant'] . '.jpg');
        if (!$before) {
            fail(503, 'Comparaison temporairement indisponible.');
        }
        $canvasWidth = min(1600, max(imagesx($before), imagesx($image)));
        $beforeHeight = max(1, (int) round(imagesy($before) * $canvasWidth / imagesx($before)));
        $afterHeight = max(1, (int) round(imagesy($image) * $canvasWidth / imagesx($image)));
        $band = 34;
        $canvas = imagecreatetruecolor($canvasWidth, $beforeHeight + $afterHeight + 2 * $band);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 28, 28, 28));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, 5, 12, 9, 'AVANT', $white);
        imagecopyresampled($canvas, $before, 0, $band, 0, 0, $canvasWidth, $beforeHeight, imagesx($before), imagesy($before));
        imagestring($canvas, 5, 12, $beforeHeight + $band + 9, 'APRES', $white);
        imagecopyresampled($canvas, $image, 0, $beforeHeight + 2 * $band, 0, 0, $canvasWidth, $afterHeight, imagesx($image), imagesy($image));
        imagedestroy($before);
        imagedestroy($image);
        $image = $canvas;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $mark = 'PHOTOGRAPHIE IMMOBILIERE QUEBEC';
    $font = 5;
    $markWidth = imagefontwidth($font) * strlen($mark);
    $markHeight = imagefontheight($font);
    $stamp = imagecreatetruecolor($markWidth + 2, $markHeight + 2);
    imagealphablending($stamp, false);
    imagesavealpha($stamp, true);
    imagefill($stamp, 0, 0, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
    imagestring($stamp, $font, 1, 1, $mark, imagecolorallocatealpha($stamp, 0, 0, 0, 107));
    imagestring($stamp, $font, 0, 0, $mark, imagecolorallocatealpha($stamp, 255, 255, 255, 100));
    $diagonal = imagerotate($stamp, 28, imagecolorallocatealpha($stamp, 0, 0, 0, 127));
    $labelWidth = max(1, (int) ($width * 0.32));
    $labelHeight = max(1, (int) ($labelWidth * imagesy($diagonal) / imagesx($diagonal)));
    $stepX = max(1, (int) ($labelWidth * 1.22));
    $stepY = max(1, (int) ($labelHeight * 1.07));
    imagealphablending($image, true);
    for ($row = 0, $y = -(int) ($labelHeight / 2); $y < $height; $row++, $y += $stepY) {
        for ($x = -(int) ($labelWidth / 3) - ($row % 2) * (int) ($stepX / 2); $x < $width; $x += $stepX) {
            imagecopyresized($image, $diagonal, $x, $y, 0, 0,
                $labelWidth, $labelHeight, imagesx($diagonal), imagesy($diagonal));
        }
    }
    header('Content-Type: image/jpeg');
    imagejpeg($image, null, 90);
    exit;
}

$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));
$logPath = $dir . '/retouches.jsonl';
$draftPath = $dir . '/retouches-brouillon.json';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
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
    foreach ($notes as $id => $note) {
        if (!isset($cards[$id]) || !is_string($note) || strlen($note) > 8000) {
            fail(400, 'Une demande est invalide ou trop longue.');
        }
    }
    $action = $_POST['action'] ?? 'envoyer';
    if ($action === 'enregistrer') {
        $draft = json_encode($notes, JSON_INVALID_UTF8_SUBSTITUTE);
        $temp = tempnam($dir, '.brouillon-');
        if ($draft === false || $temp === false || file_put_contents($temp, $draft) !== strlen($draft)
            || !rename($temp, $draftPath)) {
            fail(500, 'Impossible d’enregistrer le brouillon.');
        }
        $_SESSION['saved'][$gallery] = true;
        header('Location: ' . $url, true, 303);
        exit;
    }
    if ($action !== 'envoyer') {
        fail(400, 'Action inconnue.');
    }
}
$byPhoto = [];
$currentNotes = [];
if ($isPost || is_file($logPath)) {
    $log = fopen($logPath, $isPost ? 'c+' : 'rb');
    if ($log === false || !flock($log, $isPost ? LOCK_EX : LOCK_SH)) {
        fail(500, 'Impossible de lire les retouches.');
    }
    while (($line = fgets($log)) !== false) {
        $entry = json_decode($line, true);
        foreach ($entry['demandes'] ?? [] as $request) {
            $byPhoto[$request['photo']][] = $request['demande'];
            $currentNotes[$request['photo']] = $request['demande'];
        }
    }
    if ($isPost) {
        $requests = [];
        foreach ($notes as $id => $note) {
            $note = trim($note);
            if ($note !== ($currentNotes[$photos[$id]] ?? '')) {
                $requests[] = ['photo' => $photos[$id], 'demande' => $note];
            }
        }
        if ($requests) {
            $record = json_encode(['date' => gmdate('c'), 'demandes' => $requests],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($record === false || fseek($log, 0, SEEK_END) !== 0
                || fwrite($log, $record . PHP_EOL) !== strlen($record . PHP_EOL) || !fflush($log)) {
                fail(500, 'Enregistrement impossible. Réessayez dans un instant.');
            }
        }
    }
    fclose($log);
}
if ($isPost) {
    if (is_file($draftPath)) {
        unlink($draftPath);
    }
    $_SESSION['sent'][$gallery] = true;
    if (($requests || !empty($_SESSION['retryNotice'][$gallery])) && $notificationToken !== '') {
        $recipient = 'fbergeron@agence2t3m.com';
        $sender = 'fbergeron@photographie-immobiliere-quebec.com';
        $link = 'https://photographie-immobiliere-quebec.com/livraison-client.php?g=' . $gallery
            . '&vue=photographe&notification=' . $notificationToken;
        $subject = '=?UTF-8?B?' . base64_encode('Demandes de retouche · Galerie privée') . '?=';
        $body = "Des demandes de retouche ont été envoyées pour la galerie suivante.\r\n\r\n"
            . $manifest['title'] . "\r\n\r\nOuvrir la vue photographe :\r\n" . $link . "\r\n";
        $headers = "From: Photographie immobiliere Quebec <$sender>\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64";
        $accepted = function_exists('mail') && @mail($recipient, $subject,
            chunk_split(base64_encode($body), 76, "\r\n"), $headers, '-f' . $sender);
        $_SESSION['retryNotice'][$gallery] = !$accepted;
        error_log('Retouches notification ' . ($accepted ? 'accepted' : 'failed') . ': ' . $gallery);
    }
    header('Location: ' . $url, true, 303);
    exit;
}
$sent = !empty($_SESSION['sent'][$gallery]);
unset($_SESSION['sent'][$gallery]);
$saved = !empty($_SESSION['saved'][$gallery]);
unset($_SESSION['saved'][$gallery]);
$draftNotes = !$ownerView && is_readable($draftPath)
    ? json_decode((string) file_get_contents($draftPath), true) : [];
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
.pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
figure{margin:0;min-width:0}figcaption{margin-top:6px;font-weight:600}
img{display:block;width:100%;aspect-ratio:3/2;object-fit:contain;background:#eee}
textarea{display:block;box-sizing:border-box;width:100%;font:inherit;margin-top:8px;padding:8px;resize:vertical}
button{margin-top:20px;font:inherit;background:#222;color:white;border:0;border-radius:6px;padding:12px 18px}
</style></head><body><main>
<p>Photographie immobilière Québec · Galerie privée</p>
<h1><?= esc($manifest['title']) ?></h1>
<p><strong>Ce lien deviendra inactif après 5 jours.</strong></p>
<?php if ($ownerView): ?>
<p>Vue photographe · Demandes de retouche reçues</p>
<?php else: ?>
<p>Ouvrez une photo pour la voir en grand ou écrivez une demande de retouche sous la photo concernée.</p>
<?php if ($sent): ?><p role="status">Tes demandes de retouche ont été enregistrées.</p><?php endif; ?>
<?php if ($sent && !empty($_SESSION['retryNotice'][$gallery])): ?>
<p role="status">Le courriel n’a pas pu être envoyé. Clique de nouveau sur Envoyer au photographe pour réessayer.</p>
<?php endif; ?>
<?php if ($saved): ?><p role="status">Brouillon enregistré. Tu peux rouvrir ce lien pour continuer.</p><?php endif; ?>
<form method="post" action="<?= esc($url) ?>">
<input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
<?php endif; ?>
<div class="grid">
<?php foreach ($cards as $id => $versions): $name = $photos[$id]; ?>
<article>
<div class="<?= $paired ? 'pair' : '' ?>">
<?php foreach ($versions as $label => $versionId): $image = esc($url . '&photo=' . $versionId); ?>
<?php $zoom = $paired ? esc($url . '&photo=' . $id . '&comparaison=1') : $image; ?>
<figure><a href="<?= $zoom ?>" target="_blank" rel="noopener"<?= $paired ? ' aria-label="Ouvrir la comparaison Avant et Après"' : '' ?>><img src="<?= $image ?>" alt="<?= esc($label ?: $name) ?>" loading="lazy"></a>
<?php if ($label !== ''): ?><figcaption><?= esc($label) ?></figcaption><?php endif; ?>
</figure>
<?php endforeach; ?>
</div>
<h2><?= esc($name) ?></h2>
<?php if ($ownerView): ?>
<?php foreach (array_reverse($byPhoto[$name] ?? []) as $index => $note): ?>
<p class="notes"><strong><?= $index === 0 ? 'Demande actuelle' : 'Version précédente' ?></strong><br><?= $note === '' ? 'Demande retirée.' : esc($note) ?></p>
<?php endforeach; ?>
<?php if (empty($byPhoto[$name])): ?><p>Aucune demande pour cette photo.</p><?php endif; ?>
<?php else: ?>
<label>Retouche demandée<textarea rows="3" maxlength="1500" name="notes[<?= (int) $id ?>]"><?= "\n" . esc($draftNotes[$id] ?? $currentNotes[$name] ?? '') ?></textarea></label>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php if (!$ownerView): ?>
<button type="submit" name="action" value="enregistrer">Enregistrer</button>
<button type="submit" name="action" value="envoyer">Envoyer au photographe</button></form>
<?php endif; ?>
</main></body></html>
