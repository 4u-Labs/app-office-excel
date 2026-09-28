<?php
declare(strict_types=1);

/**
 * ExcelClone — Backend de Inteligência Artificial (OpenAI gpt-4o-mini)
 * 1. Prompt-to-Formula: Geração e correção de fórmulas por linguagem natural
 * 2. Prompt-to-Sheet: Geração de planilhas completas com dados e fórmulas reais
 * 3. Data Analyst Copilot: Diagnóstico, insights e recomendação de gráficos
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido. Utilize POST.', 405);
}

// Autenticação Obrigatória no Ecossistema 4U
$user = verifyAuthToken();
$userId = (int)$user['id'];
$userEmail = strtolower($user['email'] ?? '');
$isVip = in_array($userEmail, ADMIN_EMAILS);

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['action'])) {
    jsonError('Requisição inválida. O parâmetro "action" é obrigatório.', 400);
}

$action = trim($data['action']);

// Dedução de Crédito 4U
if (!$isVip) {
    $deducted = deductUserCredit($userId, 1);
    if (!$deducted) {
        jsonError('Créditos insuficientes na sua conta 4U. Recarregue seus créditos para usar o Assistente IA.', 402);
    }
}

$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
if (empty($apiKey)) {
    jsonError('Chave da OpenAI não configurada no servidor.', 500);
}

try {
    switch ($action) {
        case 'formula':
            handleFormulaGeneration($data, $apiKey);
            break;

        case 'fix_formula':
            handleFormulaFix($data, $apiKey);
            break;

        case 'generate_sheet':
            handleSheetGeneration($data, $apiKey);
            break;

        case 'analyze':
            handleDataAnalysis($data, $apiKey);
            break;

        default:
            jsonError('Ação desconhecida: ' . htmlspecialchars($action), 400);
            break;
    }
} catch (Throwable $e) {
    error_log('[ExcelClone AI] Erro: ' . $e->getMessage());
    jsonError('Erro no Assistente IA: ' . $e->getMessage(), 500);
}

/**
 * Chama a API da OpenAI com retorno em JSON
 */
function callOpenAiChat(array $messages, string $apiKey, float $temperature = 0.3, int $maxTokens = 2000): array
{
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => OPENAI_MODEL,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object']
        ]),
        CURLOPT_TIMEOUT => 45
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        throw new Exception("Falha de conexão com a OpenAI: {$curlErr}");
    }

    if ($httpCode !== 200) {
        $errJson = json_decode($response ?: '', true);
        $msg = $errJson['error']['message'] ?? "HTTP {$httpCode}";
        throw new Exception("Erro da OpenAI ({$httpCode}): {$msg}");
    }

    $resArr = json_decode($response ?: '', true);
    $content = $resArr['choices'][0]['message']['content'] ?? '{}';
    return json_decode($content, true) ?: [];
}

/**
 * Detecta se o idioma solicitado é inglês ou se o prompt está em inglês
 */
function resolveAiLanguage(string $prompt, string $requestedLang): string
{
    $req = strtolower(trim($requestedLang));
    if ($req === 'en') return 'en';
    if ($req === 'pt') return 'pt';
    
    // Pedido explícito na mensagem do usuário
    if (preg_match('/(em ingl[eê]s|in english|vers[aã]o em ingl[eê]s|english version|translate to english)/ui', $prompt)) {
        return 'en';
    }
    if (preg_match('/(em portugu[eê]s|in portuguese|vers[aã]o em portugu[eê]s)/ui', $prompt)) {
        return 'pt';
    }

    // Auto-detecção por padrões em inglês no prompt
    if (preg_match('/\b(sheet for|budget|invoice|income|revenue|sales|pipeline|forecast|report|inventory|payroll|kpi|cash flow|expenses|balance)\b/i', $prompt)) {
        if (!preg_match('/\b(para|com|gerar|planilha|orçamento|receita|despesa|vendas|estoque)\b/i', $prompt)) {
            return 'en';
        }
    }
    return 'pt';
}

/**
 * 1. Gerador de Fórmulas por Linguagem Natural
 */
function handleFormulaGeneration(array $data, string $apiKey): void
{
    $prompt = trim($data['prompt'] ?? '');
    $targetCell = trim($data['targetCell'] ?? 'A1');
    $context = $data['context'] ?? []; // { activeHeaders: [...], sampleRows: [...] }
    $lang = resolveAiLanguage($prompt, trim($data['lang'] ?? ''));

    if (empty($prompt)) {
        jsonError($lang === 'en' ? 'Please provide the formula description.' : 'Informe a descrição do cálculo desejado.');
    }

    if ($lang === 'en') {
        $system = "You are the leading expert in Microsoft Excel and spreadsheet formulas.
Your mission is to convert the user's description into an exact Excel formula.
Permitted formulas: SUM, AVERAGE, COUNT, MAX, MIN, IF, VLOOKUP, CONCATENATE, COUNTIF, SUMIF, SUMIFS, PRODUCT, ROUND.
The formula must always begin with '='. Use comma ',' as argument separator for English/international standard.
Return ONLY valid JSON in this format:
{
  \"formula\": \"=SUM(B2:B10)\",
  \"explanation\": \"Sums all numeric values in range B2 through B10.\",
  \"alternatives\": [\"=SUBTOTAL(9, B2:B10)\"]
}";
        $userMsg = "Target Cell: {$targetCell}\n"
                 . "User Request: {$prompt}\n"
                 . "Sheet Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n"
                 . "Response Language: English";
    } else {
        $system = "Você é o maior especialista em Microsoft Excel e fórmulas de planilhas.
Sua missão é converter a descrição do usuário em uma fórmula exata do Excel.
Fórmulas permitidas: SOMA/SUM, MÉDIA/AVERAGE, CONT.NÚM/COUNT, MÁXIMO/MAX, MÍNIMO/MIN, SE/IF, PROCV/VLOOKUP, CONCATENAR/CONCATENATE, CONT.SE/COUNTIF, SOMASE/SUMIF, SOMASES/SUMIFS, MULT/PRODUCT, ARRED/ROUND.
A fórmula deve sempre iniciar com '='. Use ponto e vírgula ';' como separador de argumentos conforme padrão brasileiro ou vírgula se em inglês.
Sempre retorne APENAS um JSON válido no formato:
{
  \"formula\": \"=SOMA(B2:B10)\",
  \"explanation\": \"Soma todos os valores numéricos do intervalo de B2 até B10.\",
  \"alternatives\": [\"=SUBTOTAL(9; B2:B10)\"]
}";
        $userMsg = "Célula Alvo: {$targetCell}\n"
                 . "Instrução do usuário: {$prompt}\n"
                 . "Contexto da Planilha: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n"
                 . "Idioma de resposta: Português";
    }

    $result = callOpenAiChat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userMsg]
    ], $apiKey, 0.2, 500);

    jsonResponse([
        'success' => true,
        'action' => 'formula',
        'formula' => $result['formula'] ?? ($lang === 'en' ? '=SUM()' : '=SOMA()'),
        'explanation' => $result['explanation'] ?? '',
        'alternatives' => $result['alternatives'] ?? []
    ]);
}

/**
 * 2. Corretor de Fórmulas com Erro
 */
function handleFormulaFix(array $data, string $apiKey): void
{
    $formula = trim($data['formula'] ?? '');
    $targetCell = trim($data['targetCell'] ?? 'A1');
    $errorType = trim($data['errorType'] ?? '');
    $lang = resolveAiLanguage($formula . ' ' . $errorType, trim($data['lang'] ?? ''));

    if ($lang === 'en') {
        $system = "You are an expert in diagnosing and fixing Excel formula errors (#VALUE!, #N/A, #REF!, syntax errors).
Identify the issue and provide the corrected formula with a clear explanation in English.
Return ONLY valid JSON:
{
  \"fixedFormula\": \"=IF(B2>0, B2*0.1, 0)\",
  \"diagnosis\": \"Missing closing parenthesis and unclosed logical condition.\",
  \"tip\": \"Always verify if referenced columns contain valid numbers.\"
}";
        $userMsg = "Cell: {$targetCell}\nFormula with Error: {$formula}\nReported Error: {$errorType}";
    } else {
        $system = "Você é um especialista em corrigir erros de fórmulas no Excel (#VALOR!, #N/D, #REF!, erro de sintaxe).
Identifique o problema e forneça a fórmula corrigida e uma explicação clara do que foi consertado.
Retorne um JSON:
{
  \"fixedFormula\": \"=SE(B2>0; B2*0,1; 0)\",
  \"diagnosis\": \"Havia parênteses abertos sem fechamento e falta de condição alternativa.\",
  \"tip\": \"Sempre verifique se a coluna referenciada contém números.\"
}";
        $userMsg = "Célula: {$targetCell}\nFórmula com Erro: {$formula}\nErro reportado: {$errorType}";
    }

    $result = callOpenAiChat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userMsg]
    ], $apiKey, 0.2, 500);

    jsonResponse([
        'success' => true,
        'action' => 'fix_formula',
        'fixedFormula' => $result['fixedFormula'] ?? $formula,
        'diagnosis' => $result['diagnosis'] ?? ($lang === 'en' ? 'Formula successfully reviewed.' : 'Fórmula revisada com sucesso.'),
        'tip' => $result['tip'] ?? ''
    ]);
}

/**
 * 3. Gerador de Planilha Completa por Prompt (Prompt-to-Sheet)
 */
function handleSheetGeneration(array $data, string $apiKey): void
{
    $prompt = trim($data['prompt'] ?? '');
    $numRows = max(4, min(20, (int)($data['numRows'] ?? 8)));
    $lang = resolveAiLanguage($prompt, trim($data['lang'] ?? ''));

    if (empty($prompt)) {
        jsonError($lang === 'en' ? 'Please describe the spreadsheet you want to generate.' : 'Descreva o objetivo da planilha a ser gerada.');
    }

    if ($lang === 'en') {
        $system = "You are a senior business analyst and Excel spreadsheet architect.
Create a complete, professional, realistic spreadsheet in English based on the user's prompt.
Strict Rules:
1. 'sheetName': short, clean tab name in English (e.g., 'Cash Flow', 'Marketing Budget', 'Sales Q1').
2. 'columns': array of objects for each column:
   - 'col': column letter ('A', 'B', 'C'...).
   - 'header': column header name in English.
   - 'type': 'text', 'currency', 'number', 'percent', or 'date'.
   - 'width': recommended width in pixels (e.g., 180 for text, 130 for values).
3. 'data': array with {$numRows} rows of coherent, realistic sample data.
   - Numeric and monetary values must ALWAYS be pure numbers (e.g., 15000, 2000, 150.50, NEVER formatted strings with '$', commas, or currency symbols).
   - The 'data' array rows must contain EXACTLY the same number of elements as 'columns'.
4. 'totals': total/summary row array containing EXACTLY the same number of elements as 'columns'.
   - If a column has no total, use '-' or ''.
   - For monetary/numerical columns, use standard Excel formulas in English like '=SUM(C2:C9)'.
   - NEVER add extra columns to 'totals'.
5. 'headerColor': elegant background color (e.g., '#107c41', '#1e40af', '#0f766e', '#4f46e5') with text color '#ffffff'.
All texts, headers, and formulas MUST be in English.
Return ONLY valid JSON in this format:
{
  \"sheetName\": \"Monthly Cash Flow\",
  \"title\": \"Q1 Executive Financial Model\",
  \"headerColor\": \"#107c41\",
  \"columns\": [
    {\"col\": \"A\", \"header\": \"Item / Description\", \"type\": \"text\", \"width\": 180},
    {\"col\": \"B\", \"header\": \"Category\", \"type\": \"text\", \"width\": 130},
    {\"col\": \"C\", \"header\": \"Amount (USD)\", \"type\": \"currency\", \"width\": 130}
  ],
  \"data\": [
    [\"Product Revenue\", \"Inflow\", 25000],
    [\"Office Lease\", \"Fixed Expense\", 3200]
  ],
  \"totals\": [\"TOTAL\", \"-\", \"=SUM(C2:C3)\"]
}";
        $userMsg = "User Request: {$prompt}\nTarget row count: {$numRows}\nLanguage: English";
    } else {
        $system = "Você é um analista de negócios e arquiteto de planilhas financeiras e operacionais no Excel.
Crie uma planilha completa, profissional e realista baseada no prompt do usuário em Português.
Regras:
1. 'sheetName': nome curto e limpo para a aba (ex: 'Fluxo de Caixa', 'Orçamento Reforma', 'Vendas 2026').
2. 'columns': array de objetos para cada coluna:
   - 'col': letra da coluna ('A', 'B', 'C'...).
   - 'header': nome do cabeçalho da coluna.
   - 'type': 'text', 'currency', 'number', 'percent' ou 'date'.
   - 'width': largura recomendada em pixels (ex: 180 para texto, 130 para valores).
3. 'data': array com {$numRows} linhas de dados de exemplo coerentes e realistas.
   - Valores numéricos e monetários nos dados devem ser SEMPRE números puros (ex: 15000, 2000, 150.50 e NUNCA strings com 'R$', pontos ou vírgulas).
   - O array 'data' deve conter EXATAMENTE a mesma quantidade de elementos por linha que a quantidade de colunas em 'columns'.
4. 'totals': array de totalização/fechamento contendo EXATAMENTE a mesma quantidade de elementos que 'columns' (nem mais, nem menos!).
   - Se uma coluna não tiver total, use '-' ou ''.
   - Para colunas de valores, use fórmulas REAIS do Excel como '=SOMA(C2:C9)'.
   - NUNCA adicione colunas extras em 'totals' que não existam em 'columns'.
5. 'headerColor': cor de fundo elegante para cabeçalho (ex: '#107c41', '#1e40af', '#0f766e', '#4f46e5') e cor de texto '#ffffff'.
Retorne APENAS um JSON no formato:
{
  \"sheetName\": \"Nome da Aba\",
  \"title\": \"Título Executivo da Planilha\",
  \"headerColor\": \"#107c41\",
  \"columns\": [
    {\"col\": \"A\", \"header\": \"Item / Descrição\", \"type\": \"text\", \"width\": 180},
    {\"col\": \"B\", \"header\": \"Categoria\", \"type\": \"text\", \"width\": 130},
    {\"col\": \"C\", \"header\": \"Valor\", \"type\": \"currency\", \"width\": 130}
  ],
  \"data\": [
    [\"Venda de Produtos\", \"Entrada\", 15000],
    [\"Aluguel\", \"Custo Fixo\", 2000]
  ],
  \"totals\": [\"TOTAL GERAL\", \"-\", \"=SOMA(C2:C3)\"]
}";
        $userMsg = "Prompt do Usuário: {$prompt}\n"
                 . "Quantidade aproximada de linhas: {$numRows}\n"
                 . "Idioma: Português do Brasil";
    }

    $result = callOpenAiChat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userMsg]
    ], $apiKey, 0.3, 2000);

    jsonResponse([
        'success' => true,
        'action' => 'generate_sheet',
        'sheet' => $result
    ]);
}

/**
 * 4. Analisador de Dados & Insights / Recomendador de Gráficos
 */
function handleDataAnalysis(array $data, string $apiKey): void
{
    $tableData = $data['tableData'] ?? [];
    $lang = resolveAiLanguage(json_encode($tableData), trim($data['lang'] ?? ''));

    if (empty($tableData) || !is_array($tableData)) {
        jsonError($lang === 'en' ? 'No data selected for analysis.' : 'Nenhum dado selecionado para análise.');
    }

    if ($lang === 'en') {
        $system = "You are a Senior Data Scientist and Financial Auditor.
Analyze the extracted tabular data from ExcelClone.
Provide in English:
1. 'summary': Clear executive summary of what the data represents in 2 to 3 concise sentences.
2. 'keyInsights': Array of 3 to 5 most relevant insights, trends, percentage shares, highest and lowest values.
3. 'anomalies': Real business anomalies or mathematical discrepancies (e.g. expenses exceeding revenues). Return empty array [] if data is healthy.
4. 'chartRecommendation':
   - 'type': 'bar', 'line', or 'pie'
   - 'title': Chart title in English
   - 'labelColumn': column name for X-axis / categories
   - 'valueColumn': column name with numerical metrics
   - 'range': suggested table range excluding total rows (e.g. 'A1:C9')
   - 'reason': Why this chart is recommended.
Return ONLY valid JSON with this structure.";
        $userMsg = "Table Data: " . json_encode($tableData, JSON_UNESCAPED_UNICODE) . "\nLanguage: English";
    } else {
        $system = "Você é um Cientista de Dados e Auditor Financeiro sênior.
Analise os dados extraídos de uma planilha do ExcelClone.
Forneça:
1. 'summary': Resumo executivo claro do que os dados representam em 2 a 3 frases.
2. 'keyInsights': Lista com 3 a 5 descobertas mais relevantes, tendências, percentuais de participação, maiores e menores valores.
3. 'anomalies': Possíveis inconsistências de negócio REAIS ou divergências matemáticas (ex: despesas superando entradas, déficits).
   - NÃO critique formatação monetária (ex: R$) nem células vazias normais em cabeçalhos. Se os dados estiverem consistentes e saudáveis, retorne lista vazia [].
4. 'chartRecommendation':
   - 'type': 'bar', 'line' ou 'pie'
   - 'title': Título do gráfico
   - 'labelColumn': nome da coluna para eixo X ou fatias
   - 'valueColumn': nome da coluna com métricas numéricas
   - 'range': intervalo sugerido da tabela excluindo linhas de totais (ex: 'A1:C9')
   - 'reason': Por que esse gráfico é o mais indicado.
Retorne APENAS um JSON válido com esta estrutura.";
        $userMsg = "Dados da Tabela: " . json_encode($tableData, JSON_UNESCAPED_UNICODE) . "\n"
                 . "Idioma: Português";
    }

    $result = callOpenAiChat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userMsg]
    ], $apiKey, 0.3, 1200);

    jsonResponse([
        'success' => true,
        'action' => 'analyze',
        'analysis' => $result
    ]);
}
