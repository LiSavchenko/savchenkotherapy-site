<?php
// Принимает заявку из формы записи и пересылает её в Telegram.
// Токен бота и chat_id лежат в tg-config.php на уровень выше папки сайта
// (или в переменных окружения TG_TOKEN / TG_CHAT_ID), но не в репозитории.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function reply(int $code, bool $ok): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok]);
  exit;
}

function len(string $s): int {
  return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, false);

$cfgFile = dirname(__DIR__, 2) . '/tg-config.php';
$cfg = is_file($cfgFile) ? (require $cfgFile) : [];
$token = getenv('TG_TOKEN') ?: ($cfg['token'] ?? '');
$chat = getenv('TG_CHAT_ID') ?: ($cfg['chat_id'] ?? '');
if ($token === '' || $chat === '') {
  error_log('lead.php: не найдены TG_TOKEN / TG_CHAT_ID');
  reply(500, false);
}

// Ловушка для ботов: живой человек это поле не видит и не заполняет
if (trim((string)($_POST['website'] ?? '')) !== '') reply(200, true);

$name = trim((string)($_POST['name'] ?? ''));
$contact = trim((string)($_POST['contact'] ?? ''));
if ($name === '' || $contact === '' || empty($_POST['consent'])
    || len($name) > 100 || len($contact) > 200) {
  reply(400, false);
}

// Не больше 5 заявок в час с одного IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = sys_get_temp_dir() . '/lead-' . md5($ip);
$now = time();
$hits = is_file($rateFile) ? (json_decode((string)file_get_contents($rateFile), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t > $now - 3600));
if (count($hits) >= 5) reply(429, false);
$hits[] = $now;
@file_put_contents($rateFile, json_encode($hits), LOCK_EX);

$time = (new DateTime('now', new DateTimeZone('Asia/Tashkent')))->format('d.m.Y H:i');
$text = "Новая заявка с сайта\n\nИмя: $name\nКонтакт: $contact\n\n$time (Ташкент)";

$url = "https://api.telegram.org/bot$token/sendMessage";
$body = http_build_query(['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'true']);

if (function_exists('curl_init')) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
  ]);
  $res = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
} else {
  $res = @file_get_contents($url, false, stream_context_create(['http' => [
    'method' => 'POST',
    'header' => 'Content-Type: application/x-www-form-urlencoded',
    'content' => $body,
    'timeout' => 10,
    'ignore_errors' => true,
  ]]));
  $status = preg_match('{HTTP/\S+ (\d+)}', $http_response_header[0] ?? '', $m) ? (int)$m[1] : 0;
}

if ($status !== 200) {
  error_log('lead.php: Telegram ответил ' . $status . ' ' . substr((string)$res, 0, 300));
  reply(502, false);
}
reply(200, true);
