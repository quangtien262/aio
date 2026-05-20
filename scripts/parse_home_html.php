<?php
$html = file_get_contents('http://127.0.0.1:8000/vi');
if ($html === false) { echo "fail"; exit(1); }
// Normalize
$normalized = preg_replace('/\s+/', ' ', $html);
$title = null;
if (preg_match('/<title>(.*?)<\/title>/i', $normalized, $m)) { $title = strip_tags($m[1]); }
$logoText = null;
if (preg_match('/<a[^>]*class="[^"]*th-logo[^"]*"[^>]*>(.*?)<\/a>/i', $normalized, $m)) {
    $logoHtml = $m[1];
    if (preg_match('/<strong>(.*?)<\/strong>/i', $logoHtml, $mm)) { $logoText = strip_tags($mm[1]); }
}
echo json_encode(['title' => $title, 'logoText' => $logoText], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
