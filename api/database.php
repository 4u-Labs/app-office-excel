<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');

            self::bootstrap($pdo);
            self::$instance = $pdo;
        }

        return self::$instance;
    }

    private static function bootstrap(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                username       TEXT UNIQUE NOT NULL,
                email          TEXT,
                password_hash  TEXT NOT NULL,
                display_name   TEXT,
                photo_url      TEXT,
                credits        INTEGER NOT NULL DEFAULT 10,
                token          TEXT,
                created_at     TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id        INTEGER NOT NULL,
                mp_payment_id  TEXT,
                package_label  TEXT,
                amount_brl     REAL NOT NULL DEFAULT 0,
                credits_added  INTEGER NOT NULL DEFAULT 0,
                status         TEXT NOT NULL DEFAULT 'approved',
                created_at     TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
    }
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400): never
{
    jsonResponse(['success' => false, 'error' => $message], $status);
}

function verifyAuthToken(): array
{
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token   = trim(str_replace('Bearer ', '', $auth));

    if (empty($token)) {
        jsonError('Não autorizado: Token não fornecido.', 401);
    }

    $pdo = Database::get();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonError('Não autorizado: Token inválido ou expirado.', 401);
    }

    // Dev VIP: Garante créditos ilimitados
    $email = strtolower($user['email'] ?? '');
    if (in_array($email, ADMIN_EMAILS)) {
        if ((int)$user['credits'] < ADMIN_CREDITS) {
            $pdo->prepare('UPDATE users SET credits = ?, updated_at = datetime("now") WHERE id = ?')
                ->execute([ADMIN_CREDITS, $user['id']]);
            $user['credits'] = ADMIN_CREDITS;
        }
    }

    return $user;
}

function deductUserCredit(int $userId, int $cost = 1): bool
{
    $pdo = Database::get();
    $stmt = $pdo->prepare('SELECT email, credits FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return false;

    $email = strtolower($user['email'] ?? '');
    if (in_array($email, ADMIN_EMAILS)) {
        return true; // Admins / VIPs nunca perdem créditos
    }

    $current = (int)$user['credits'];
    if ($current < $cost) {
        return false;
    }

    $new = $current - $cost;
    $pdo->prepare('UPDATE users SET credits = ?, updated_at = datetime("now") WHERE id = ?')
        ->execute([$new, $userId]);

    return true;
}
