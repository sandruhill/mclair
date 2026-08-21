<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
cmsRequireRole(['admin', 'editor', 'author']);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex" />
</head>
<body>
<a href="/acesso/posts" style="position:fixed;top:16px;left:16px;z-index:999;font:600 .78rem/1 'Public Sans',system-ui,sans-serif;color:#6B7280;background:#fff;padding:8px 12px;border-radius:8px;border:1px solid #E5E7EB;text-decoration:none">← voltar ao painel</a>
<?php readfile(__DIR__ . '/manual-content.html'); ?>
</body>
</html>
