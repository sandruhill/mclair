<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

// Simple media library: lists whatever's already in uploads/ (the same
// directory upload.php saves to), newest first. No DB table -- the
// filesystem already is the source of truth and there's nothing else
// worth tracking (uploader, alt text, etc) yet.
$dir = __DIR__ . '/../uploads';
$files = [];
if (is_dir($dir)) {
    foreach (scandir($dir) as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . '/' . $name;
        if (!is_file($path)) continue;
        if (!preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', $name)) continue;
        $files[] = ['url' => '/uploads/' . $name, 'mtime' => filemtime($path)];
    }
}
usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

echo json_encode(array_map(fn($f) => ['url' => $f['url']], $files));
