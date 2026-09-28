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
    $accessToken = trim($body['access_token'] ?? '');

    // Validação de token Google no servidor para evitar falsificação de e-mail / privilégios
    if (!empty($accessToken)) {
        $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $googleRes = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $googleRes) {
            $googleData = json_decode($googleRes, true);
            if (!empty($googleData['email'])) {
                $email = strtolower(trim($googleData['email']));
                $name = trim($googleData['name'] ?? ($googleData['given_name'] ?? $name));
                $picture = trim($googleData['picture'] ?? $picture);
            }
        } else {
            jsonError('Token de autenticação do Google inválido ou expirado.', 401);
        }
    }

    $adminList = defined('ADMIN_EMAILS') ? ADMIN_EMAILS : ['fbr4g4@gmail.com', 'fb4g4@gmail.com'];
    if (in_array($email, $adminList) && empty($accessToken)) {
        jsonError('Autenticação segura obrigatória para contas administrativas.', 403);
    }


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
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token   = trim(str_replace('Bearer ', '', $auth));

    if (!empty($token)) {
        try {
            $pdo = Database::get();
            $pdo->prepare('UPDATE users SET token = NULL WHERE token = ?')->execute([$token]);
        } catch (Throwable $e) {}
    }

    jsonResponse(['success' => true, 'message' => 'Desconectado com sucesso.']);
}
