<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
$config = require __DIR__.'/config.php';
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
if (!$https && !$local && PHP_SAPI !== 'cli') { header('Location: https://touchinparis.touchroosters91.com/competition/', true, 302); exit; }
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('TIPCompetition');
session_set_cookie_params(['lifetime'=>0,'path'=>'/competition/','secure'=>$https,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) unset($_SESSION['authenticated']);
if (!empty($_SESSION['authenticated'])) $_SESSION['last_seen']=time();
function csrfValid(): bool { return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
function authenticated(): bool { return !empty($_SESSION['authenticated']); }
function requireAccess(): void { if(!authenticated()) { http_response_code(401); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>'Session expirée. Reconnectez-vous.']); exit; } }
function esc($value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function tryLogin(string $username,string $password,array $config): string {
    $file=__DIR__.'/attempts.php'; $handle=fopen($file,'c+');
    if(!$handle || !flock($handle,LOCK_EX)) return 'Connexion temporairement indisponible.';
    $raw=stream_get_contents($handle); $prefix="<?php exit; ?>\n";
    $data=json_decode(substr($raw,strlen($prefix)),true) ?: [];
    $now=time(); $data=array_filter($data,fn($entry)=>($entry['until']??0)>$now);
    $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'local'));
    $entry=$data[$key]??['count'=>0,'until'=>$now+300];
    if($entry['count']>=8) $error='Trop de tentatives. Réessayez dans cinq minutes.';
    elseif(hash_equals($config['username'],$username) && password_verify($password,$config['password_hash'])) {
        unset($data[$key]); session_regenerate_id(true); $_SESSION['authenticated']=true; $_SESSION['last_seen']=$now; $_SESSION['csrf']=bin2hex(random_bytes(32)); $error='';
    } else { $entry['count']++; $data[$key]=$entry; $error='Identifiant ou mot de passe incorrect.'; }
    rewind($handle); ftruncate($handle,0); fwrite($handle,$prefix.json_encode($data)); fflush($handle); flock($handle,LOCK_UN); fclose($handle);
    return $error;
}
