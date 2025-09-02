<?php
// HATA AYIKLAMA AKTİF
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "HATA: [$errno] $errstr in $errfile on line $errline\n";
    return false;
});

// Varsayılan değerler
$defaultBaseUrl   = 'https://m.prectv55.sbs';
$defaultSuffix    = '4F5A9C3D9A86FA54EACEDDD635185/64f9535b-bd2e-4483-b234-89060b1e631c/';
$defaultUserAgent = 'Dart/3.7 (dart:io)';
$defaultReferer   = 'https://twitter.com/';
$pageCount        = 4;

$sourceUrlRaw = 'https://raw.githubusercontent.com/kerimmkirac/cs-kerim/refs/heads/master/RecTV/src/main/kotlin/com/keyiflerolsun/RecTV.kt';
$proxyUrl     = 'https://api.codetabs.com/v1/proxy/?quest=' . urlencode($sourceUrlRaw);

// Güncel değerlerin tutulacağı değişkenler
$baseUrl   = $defaultBaseUrl;
$suffix    = $defaultSuffix;
$userAgent = $defaultUserAgent;
$referer   = $defaultReferer;

// Github içeriğini çek
function fetchGithubContent($sourceUrlRaw, $proxyUrl) {
    $githubContent = @file_get_contents($sourceUrlRaw);
    if ($githubContent !== FALSE) return $githubContent;
    $githubContentProxy = @file_get_contents($proxyUrl);
    if ($githubContentProxy !== FALSE) return $githubContentProxy;
    return FALSE;
}

$githubContent = fetchGithubContent($sourceUrlRaw, $proxyUrl);

// Regex ile güncel değerleri çıkar
if ($githubContent !== FALSE) {
    if (preg_match('/override\s+var\s+mainUrl\s*=\s*"([^"]+)"/', $githubContent, $m)) {
        $baseUrl = $m[1];
    }
    if (preg_match('/private\s+val\s+swKey\s*=\s*"([^"]+)"/', $githubContent, $m)) {
        $suffix = $m[1];
    }
    if (preg_match('/user-agent"\s*to\s*"([^"]+)"/', $githubContent, $m)) {
        $userAgent = $m[1];
    }
    if (preg_match('/Referer"\s*to\s*"([^"]+)"/', $githubContent, $m)) {
        $referer = $m[1];
    }
}

// Base URL testi
function isBaseUrlWorking($baseUrl, $suffix, $userAgent) {
    $testUrl = $baseUrl . '/api/channel/by/filtres/0/0/0/' . $suffix;
    $opts = [
        'http' => [
            'header' => "User-Agent: $userAgent\r\n"
        ]
    ];
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($testUrl, false, $ctx);
    return $response !== FALSE;
}

// Geçersizse varsayılanlara dön
if (!isBaseUrlWorking($baseUrl, $suffix, $userAgent)) {
    $baseUrl = $defaultBaseUrl;
    $suffix  = $defaultSuffix;
}

// M3U oluştur
$m3uContent = "#EXTM3U\n";

// API çağrıları için header ayarları
$options = [
    'http' => [
        'header' => "User-Agent: $userAgent\r\nReferer: $referer\r\n"
    ]
];
$context = stream_context_create($options);

// CANLI YAYINLAR
for ($page = 0; $page < $pageCount; $page++) {
    $apiUrl = $baseUrl . "/api/channel/by/filtres/0/0/$page/" . $suffix;
    $response = @file_get_contents($apiUrl, false, $context);
    if ($response === FALSE) continue;

    $data = json_decode($response, true);
    if (!is_array($data)) continue;

    foreach ($data as $content) {
        if (!isset($content['sources']) || !is_array($content['sources'])) continue;

        foreach ($content['sources'] as $source) {
            if (($source['type'] ?? '') !== 'm3u8' || !isset($source['url'])) continue;

            $title      = $content['title'] ?? 'Bilinmeyen';
            $image      = isset($content['image']) && strpos($content['image'], 'http') === 0
                            ? $content['image']
                            : $baseUrl . '/' . ltrim($content['image'] ?? '', '/');
            $categories = isset($content['categories']) && is_array($content['categories'])
                            ? implode(", ", array_column($content['categories'], 'title'))
                            : 'Genel';

            $m3uContent .= "#EXTINF:-1 tvg-id=\"{$content['id']}\" tvg-name=\"$title\" tvg-logo=\"$image\" group-title=\"$categories\", $title\n";
            $m3uContent .= "#EXTVLCOPT:http-user-agent=googleusercontent\n";
            $m3uContent .= "#EXTVLCOPT:http-referrer=https://twitter.com/\n";
            $m3uContent .= "{$source['url']}\n";
        }
    }
}

// Dosyaya yaz
$outputFile = 'canli_tv.m3u';
$result = file_put_contents($outputFile, $m3uContent);

if ($result === false) {
    echo "HATA: $outputFile dosyasına yazılamadı.\n";
    exit(1);
}

echo "✅ M3U dosyası başarıyla oluşturuldu: $outputFile\n";
