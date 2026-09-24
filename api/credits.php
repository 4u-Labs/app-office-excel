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

$user = verifyAuthToken();
$userId = (int)$user['id'];
$pdo = Database::get();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT credits, email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();

    $email = strtolower($u['email'] ?? '');
    $isVip = in_array($email, ADMIN_EMAILS);

    jsonResponse([
        'success' => true,
        'credits' => $isVip ? ADMIN_CREDITS : (int)($u['credits'] ?? 0),
        'is_vip'  => $isVip
    ]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $cost = max(1, (int)($body['cost'] ?? 1));
    $tool = trim($body['tool'] ?? 'ExcelClone');

    $email = strtolower($user['email'] ?? '');
    $isVip = in_array($email, ADMIN_EMAILS);

    if (!$isVip) {
        $success = deductUserCredit($userId, $cost);
        if (!$success) {
            jsonError('Créditos insuficientes na sua conta 4U. Recarregue seus créditos para continuar.', 402);
        }
    }

    $stmt = $pdo->prepare('SELECT credits FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $remaining = $isVip ? ADMIN_CREDITS : (int)($stmt->fetch()['credits'] ?? 0);

    jsonResponse([
        'success'           => true,
        'credits_deducted'  => $isVip ? 0 : $cost,
        'credits_remaining' => $remaining,
        'tool'              => $tool
    ]);
}

jsonError('Método não permitido.', 405);
