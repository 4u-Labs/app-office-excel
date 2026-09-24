<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'GET' ? 'sync' : 'google');

if ($method === 'POST') {
    if ($action === 'google') {
        handleGoogleAuth();
    } elseif ($action === 'logout') {
        handleLogout();
    } else {
        jsonError('Ação inválida.', 400);
    }
} elseif ($method === 'GET') {
    handleSync();
} else {
    jsonError('Método não permitido.', 405);
}

function handleGoogleAuth(): never
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = strtolower(trim($body['email'] ?? ''));
    $name = trim($body['name'] ?? '');
    $picture = trim($body['picture'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('E-mail do Google inválido.');
    }

    $pdo = Database::get();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $token = bin2hex(random_bytes(32));
    $isVip = in_array($email, ADMIN_EMAILS);

    if ($user) {
        $credits = $isVip ? ADMIN_CREDITS : (int)$user['credits'];
        $displayName = $user['display_name'] ?: ($name ?: explode('@', $email)[0]);
        $photoUrl = $picture ?: ($user['photo_url'] ?? '');
        
        $pdo->prepare('UPDATE users SET token = ?, credits = ?, display_name = ?, photo_url = ?, updated_at = datetime("now") WHERE id = ?')
            ->execute([$token, $credits, $displayName, $photoUrl, $user['id']]);
        $id = (int)$user['id'];
    } else {
        $fakeHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $displayName = $name ?: explode('@', $email)[0];
        $credits = $isVip ? ADMIN_CREDITS : DEFAULT_CREDITS;
        
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, email, display_name, photo_url, token, credits) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$email, $fakeHash, $email, $displayName, $picture, $token, $credits]);
        $id = (int)$pdo->lastInsertId();
    }

    jsonResponse([
        'success' => true,
        'token'   => $token,
        'user'    => [
            'id'           => $id,
            'email'        => $email,
            'credits'      => $credits,
            'display_name' => $displayName,
            'photo_url'    => $picture ?: ($user['photo_url'] ?? ''),
            'is_vip'       => $isVip
        ]
    ]);
}

function handleSync(): never
{
    $user = verifyAuthToken();
    $email = strtolower($user['email'] ?? '');
    $isVip = in_array($email, ADMIN_EMAILS);

    jsonResponse([
        'success' => true,
        'user'    => [
            'id'           => (int)$user['id'],
            'email'        => $user['email'],
            'credits'      => (int)$user['credits'],
            'display_name' => $user['display_name'] ?? explode('@', $user['email'])[0],
            'photo_url'    => $user['photo_url'] ?? '',
            'is_vip'       => $isVip
        ]
    ]);
}

function handleLogout(): never
{
    try {
        $user = verifyAuthToken();
        $pdo = Database::get();
        $pdo->prepare('UPDATE users SET token = NULL WHERE id = ?')->execute([$user['id']]);
    } catch (Exception $e) {}

    jsonResponse(['success' => true, 'message' => 'Desconectado com sucesso.']);
}
