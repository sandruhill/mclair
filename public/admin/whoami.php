<?php
// Lightweight session check used by the public site to decide whether to
// show the floating "editar" button. Deliberately does NOT require auth.php
// (which redirects to the login page) -- an anonymous visitor should just
// get {loggedIn:false}, not a login form.
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

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
