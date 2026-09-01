<?php
declare(strict_types=1);

/*
 * SINOP natural voice proxy
 * -------------------------
 * Keeps ElevenLabs credentials on the server instead of inside the browser.
 *
 * Required environment variables:
 *   ELEVENLABS_API_KEY
 *   ELEVENLABS_VOICE_ID
 *
 * Optional per-personality voice IDs:
 *   SINOP_ELEVENLABS_VOICE_WARM
 *   SINOP_ELEVENLABS_VOICE_RELAXED
 *   SINOP_ELEVENLABS_VOICE_NEUTRAL
 *
 * Optional:
 *   ELEVENLABS_MODEL=eleven_flash_v2_5
 */


header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $apiKey = trim((string) getenv('ELEVENLABS_API_KEY'));
    $defaultVoice = trim((string) getenv('ELEVENLABS_VOICE_ID'));
    echo json_encode([
        'ok' => true,
        'configured' => $apiKey !== '' && $defaultVoice !== '',
        'provider' => 'ElevenLabs',
        'model' => getenv('ELEVENLABS_MODEL') ?: 'eleven_flash_v2_5',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$apiKey = trim((string) getenv('ELEVENLABS_API_KEY'));
if ($apiKey === '') {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ELEVENLABS_API_KEY is not configured on the server.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

$text = trim((string) ($data['text'] ?? ''));
if ($text === '') {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Text is required.']);
    exit;
}
if (mb_strlen($text) > 700) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Voice prompt is too long.']);
    exit;
}

$profile = strtolower(trim((string) ($data['profile'] ?? 'warm')));
if (!in_array($profile, ['warm', 'relaxed', 'neutral'], true)) {
    $profile = 'warm';
}

$profileEnv = [
    'warm' => 'SINOP_ELEVENLABS_VOICE_WARM',
    'relaxed' => 'SINOP_ELEVENLABS_VOICE_RELAXED',
    'neutral' => 'SINOP_ELEVENLABS_VOICE_NEUTRAL',
];

$voiceId = trim((string) getenv($profileEnv[$profile]));
if ($voiceId === '') {
    $voiceId = trim((string) getenv('ELEVENLABS_VOICE_ID'));
}
if ($voiceId === '') {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ELEVENLABS_VOICE_ID is not configured on the server.']);
    exit;
}

$model = trim((string) getenv('ELEVENLABS_MODEL'));
if ($model === '') {
    $model = 'eleven_flash_v2_5';
}

$profileSettings = [
    'warm' => [
        'stability' => 0.42,
        'similarity_boost' => 0.78,
        'style' => 0.18,
        'use_speaker_boost' => true,
        'speed' => 0.98,
    ],
    'relaxed' => [
        'stability' => 0.50,
        'similarity_boost' => 0.76,
        'style' => 0.10,
        'use_speaker_boost' => true,
        'speed' => 0.94,
    ],
    'neutral' => [
        'stability' => 0.58,
        'similarity_boost' => 0.75,
        'style' => 0.04,
        'use_speaker_boost' => true,
        'speed' => 1.00,
    ],
];

$payload = json_encode([
    'text' => $text,
    'model_id' => $model,
    'voice_settings' => $profileSettings[$profile],
], JSON_UNESCAPED_SLASHES);

$url = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voiceId) . '/stream?output_format=mp3_44100_128';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'xi-api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: audio/mpeg',
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 25,
]);

$response = curl_exec($ch);
if ($response === false) {
    $message = curl_error($ch);
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Voice provider connection failed: ' . $message]);
    exit;
}

$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$body = substr($response, $headerSize);
curl_close($ch);

if ($status < 200 || $status >= 300) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    $providerMessage = '';
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $providerMessage = (string) ($decoded['detail']['message'] ?? $decoded['detail'] ?? $decoded['message'] ?? '');
    }
    echo json_encode([
        'error' => $providerMessage !== '' ? $providerMessage : 'ElevenLabs returned HTTP ' . $status . '.',
    ]);
    exit;
}

header('Content-Type: ' . ($contentType ?: 'audio/mpeg'));
header('Content-Length: ' . strlen($body));
echo $body;
