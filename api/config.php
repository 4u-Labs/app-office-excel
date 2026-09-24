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
