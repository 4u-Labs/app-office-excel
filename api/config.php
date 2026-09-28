<?php
declare(strict_types=1);

/**
 * ExcelClone — Configuração Central & Integração Ecossistema 4U
 * Banco de dados SQLite centralizado compartilhado com KeepAi, FreePDF e todo o 4U.IA.BR
 */

// Caminho do Banco SQLite compartilhado do ecossistema 4U
$sharedDb = realpath(__DIR__ . '/../../keepai/database/keepai.db');
if (!$sharedDb || !file_exists($sharedDb)) {
    $sharedDb = __DIR__ . '/../../keepai/database/keepai.db';
}
define('DB_PATH', $sharedDb);

// Google OAuth 2.0 Oficial 4U.IA.BR
define('GOOGLE_CLIENT_ID', '569266864432-pd09jbb5no9ekdhdr018fj643nopp817.apps.googleusercontent.com');

// Administradores e VIPs com créditos infinitos
define('ADMIN_EMAILS', ['fbr4g4@gmail.com', 'fb4g4@gmail.com']);
define('DEFAULT_CREDITS', 10);
define('ADMIN_CREDITS', 999999);

// Chave da OpenAI para o ExcelClone IA
if (!defined('OPENAI_API_KEY')) {
    $apiKey = getenv('OPENAI_API_KEY') ?: '';
    if (!$apiKey && file_exists(__DIR__ . '/api_key.local.php')) {
        $apiKey = include __DIR__ . '/api_key.local.php';
    }
    if (!$apiKey && file_exists(__DIR__ . '/../../word/api/config.php')) {
        $wordCfg = file_get_contents(__DIR__ . '/../../word/api/config.php');
        if (preg_match("/define\('OPENAI_API_KEY',\s*'([^']+)'\)/", $wordCfg, $m)) {
            $apiKey = $m[1];
        }
    }
    define('OPENAI_API_KEY', $apiKey);
}
define('OPENAI_MODEL', 'gpt-4o-mini');
