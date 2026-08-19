<?php
const GH_REPO_OWNER = 'sandruhill';
const GH_REPO_NAME = 'mclair';

function githubRequest(string $token, string $method, string $url, ?array $body = null): int {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: mclair-acesso',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

function githubUserExists(string $token, string $username): bool {
    $status = githubRequest($token, 'GET', 'https://api.github.com/users/' . rawurlencode($username));
    return $status === 200;
}

function isAlreadyCollaborator(string $token, string $username): bool {
    $url = 'https://api.github.com/repos/' . GH_REPO_OWNER . '/' . GH_REPO_NAME . '/collaborators/' . rawurlencode($username);
    $status = githubRequest($token, 'GET', $url);
    return $status === 204;
}

function addCollaborator(string $token, string $username): bool {
    $url = 'https://api.github.com/repos/' . GH_REPO_OWNER . '/' . GH_REPO_NAME . '/collaborators/' . rawurlencode($username);
    $status = githubRequest($token, 'PUT', $url, ['permission' => 'push']);
    return $status === 201 || $status === 204;
}
