<?php
// Lightweight session check used by the public site to decide whether to
// show the floating "editar" button. Deliberately does NOT require auth.php
// (which redirects to the login page) -- an anonymous visitor should just
// get {loggedIn:false}, not a login form.
header('Content-Type: application/json');
header('Cache-Control: no-store');

// No session cookie at all -> anonymous. Bail before session_start() so this
// endpoint never mints stray sessions (or cookies with default params) for
// every public-site visitor that pings it.
if (empty($_COOKIE[session_name()])) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

// Mirror auth.php's cookie params so a re-issued cookie keeps the same
// path/secure/httponly attributes instead of PHP's defaults.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/acesso/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['cms_user_id'])) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

require_once __DIR__ . '/db.php';
$stmt = cmsDb()->prepare('SELECT role FROM cmstest_users WHERE id = ?');
$stmt->execute([$_SESSION['cms_user_id']]);
$role = $stmt->fetchColumn();

if (!$role) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

echo json_encode(['loggedIn' => true, 'role' => $role]);
