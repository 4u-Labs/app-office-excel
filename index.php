<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
$assetVersion = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExcelClone - Planilhas Online</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/google-auth.js?v=<?= $assetVersion ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f3f4f6;
            --bg-toolbar: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #d1d5db;
            --header-bg: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
            --cell-selected: #e6f2eb;
            --cell-range: #cce5d8;
        }
        
        .dark-mode {
            --bg-primary: #1f2937;
            --bg-secondary: #374151;
            --bg-toolbar: #374151;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --border-color: #4b5563;
            --header-bg: linear-gradient(180deg, #374151 0%, #4b5563 100%);
            --cell-selected: #065f46;
            --cell-range: #047857;
        }
        
        body {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .cell {
            min-width: 100px;
            height: 24px;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 2px 4px;
            outline: none;
            font-size: 12px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            position: relative;
        }
        
        .cell:focus {
            outline: 2px solid #217346;
            outline-offset: -1px;
            z-index: 10;
            position: relative;
        }
        
        .cell.selected {
            background-color: var(--cell-selected) !important;
        }
        
        .cell.range-selected {
            background-color: var(--cell-range) !important;
        }
        
        .cell.has-comment::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-top: 6px solid #ff6b6b;
        }
        
        .cell .fill-handle {
            position: absolute;
            right: -3px;
            bottom: -3px;
            width: 6px;
            height: 6px;
            background: #217346;
            cursor: crosshair;
            z-index: 20;
            display: none;
        }
        
        .cell.selected .fill-handle {
            display: block;
        }
        
        .header-cell {
            background: var(--header-bg);
            font-weight: 500;
            text-align: center;
            min-width: 100px;
            height: 24px;
            border-right: 1px solid #c0c0c0;
            border-bottom: 1px solid #c0c0c0;
            font-size: 12px;
            user-select: none;
            cursor: pointer;
            position: relative;
            color: var(--text-primary);
        }
        
        .header-cell:hover {
            background: linear-gradient(180deg, #e9ecef 0%, #dee2e6 100%);
        }
        
        .header-cell .resize-handle {
            position: absolute;
            right: 0;
            top: 0;
            width: 5px;
            height: 100%;
            cursor: col-resize;
            background: transparent;
        }
        
        .header-cell .resize-handle:hover {
            background: #217346;
        }
        
        .header-cell .filter-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 8px;
            cursor: pointer;
            display: none;
        }
        
        .header-cell:hover .filter-btn,
        .header-cell.filtered .filter-btn {
            display: block;
        }
        
        .row-header {
            background: var(--header-bg);
            min-width: 50px;
            width: 50px;
            text-align: center;
            font-weight: 500;
            border-right: 1px solid #c0c0c0;
            border-bottom: 1px solid #c0c0c0;
            font-size: 12px;
            user-select: none;
            cursor: pointer;
            color: var(--text-primary);
        }
        
        .row-header:hover {
            background: linear-gradient(90deg, #e9ecef 0%, #dee2e6 100%);
        }
        
        .row-header.hidden-row,
        .header-cell.hidden-col {
            background: #fee2e2 !important;
        }
        
        .spreadsheet-container {
            overflow: auto;
            height: calc(100vh - 220px);
            background: var(--bg-primary);
        }
        
        .toolbar-btn {
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            transition: background 0.15s;
            color: var(--text-primary);
        }
        
        .toolbar-btn:hover {
            background-color: rgba(0,0,0,0.1);
        }
        
        .toolbar-btn.active {
            background-color: rgba(0,0,0,0.2);
        }
        
        .sheet-tab {
            padding: 6px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-bottom: none;
            cursor: pointer;
            font-size: 12px;
            border-radius: 4px 4px 0 0;
            margin-right: 2px;
            color: var(--text-primary);
        }
        
        .sheet-tab.active {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--bg-primary);
            margin-bottom: -1px;
        }
        
        .context-menu, .modal {
            position: fixed;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .context-menu {
            min-width: 180px;
        }
        
        .context-menu-item {
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        
        .context-menu-item:hover {
            background-color: rgba(0,0,0,0.05);
        }
        
        .context-menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 4px 0;
        }
        
        .modal {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            min-width: 400px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .comment-tooltip {
            position: absolute;
            background: #fffbcc;
            border: 1px solid #e6d88a;
            padding: 8px;
            border-radius: 4px;
            font-size: 12px;
            max-width: 200px;
            z-index: 100;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
            color: #333;
        }
        
        input[type="color"] {
            -webkit-appearance: none;
            width: 24px;
            height: 24px;
            border: none;
            cursor: pointer;
        }
        
        input[type="color"]::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        
        input[type="color"]::-webkit-color-swatch {
            border: 1px solid var(--border-color);
            border-radius: 2px;
        }
        
        .frozen-row {
            position: sticky;
            top: 24px;
            z-index: 15;
            background: var(--bg-primary);
        }
        
        .frozen-col {
            position: sticky;
            left: 50px;
            z-index: 15;
            background: var(--bg-primary);
        }
        
        .filter-dropdown {
            position: absolute;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            padding: 10px;
            min-width: 200px;
        }
        
        @media print {
            .no-print { display: none !important; }
            .spreadsheet-container { height: auto !important; overflow: visible !important; }
            .cell { border: 1px solid #000 !important; }
        }
        
        .toolbar-section {
            background: var(--bg-toolbar);
            border-bottom: 1px solid var(--border-color);
        }
        
        select, input[type="text"] {
            background: var(--bg-primary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        /* Rodapé Estilo Premium 4U.IA.BR */
        .footer-clean { position: relative; padding: 2rem 0; color: #4b5563; }
        .footer-link-group { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 0.5rem; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 500; }
        .footer-dot { width: 3px; height: 3px; border-radius: 50%; background: rgba(0, 0, 0, 0.1); }
        .footer-a { transition: all 0.2s; text-decoration: none; color: inherit; }
        .footer-a:hover { color: #217346; opacity: 1; }
    </style>
</head>
<body class="overflow-hidden">
    <!-- Top Bar -->
    <div class="bg-[#107c41] text-white px-4 py-2 flex items-center gap-4 no-print border-b border-green-800">
        <a href="../index.php" title="Voltar ao OfficeClone®" class="px-2.5 py-1 bg-[#0e6032] hover:bg-black/30 text-white font-bold rounded flex items-center gap-1.5 text-xs transition-colors shadow-sm">
            <span>←</span> <span data-i18n="back_office">OfficeClone®</span>
        </a>
        <div class="h-4 w-px bg-white/30 mx-1"></div>
        <div class="flex items-center gap-2">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21.17 3H7.83A1.83 1.83 0 006 4.83v14.34A1.83 1.83 0 007.83 21h13.34A1.83 1.83 0 0023 19.17V4.83A1.83 1.83 0 0021.17 3zM12 17.5H8v-2h4v2zm0-3H8v-2h4v2zm0-3H8v-2h4v2zm5 6h-4v-2h4v2zm0-3h-4v-2h4v2zm0-3h-4v-2h4v2z"/>
                <path d="M1 4.5l5-1.5v18l-5-1.5z" opacity="0.8"/>
            </svg>
            <span class="font-semibold">ExcelClone</span>
        </div>
        <input type="text" id="fileName" value="Pasta1" class="bg-transparent border-b border-white/50 px-2 py-1 text-sm focus:outline-none focus:border-white">
        <div class="flex-1"></div>
        <div class="flex items-center gap-2 text-sm">
            <button onclick="showFindReplace()" class="hover:bg-white/20 px-3 py-1 rounded" data-i18n="btn_find" data-i18n-title="tip_find" title="Buscar (Ctrl+F)">🔍 Buscar</button>
            <button onclick="showChartModal()" class="hover:bg-white/20 px-3 py-1 rounded" data-i18n="btn_chart">📊 Gráfico</button>
            <button onclick="importCSV()" class="hover:bg-white/20 px-3 py-1 rounded" data-i18n="btn_import">📁 Importar</button>
            <button onclick="exportCSV()" class="hover:bg-white/20 px-3 py-1 rounded" data-i18n="btn_export">💾 Exportar</button>
            <button onclick="window.print()" class="hover:bg-white/20 px-3 py-1 rounded" data-i18n="btn_print">🖨️ Imprimir</button>
            <button onclick="toggleDarkMode()" class="hover:bg-white/20 px-3 py-1 rounded" id="darkModeBtn" data-i18n="btn_dark_mode">🌙 Modo Escuro</button>
            
            <div class="h-4 w-px bg-white/30 mx-1"></div>
            
            <!-- Dual Language Toggle (PT / EN) -->
            <div class="inline-flex rounded-md p-0.5 bg-black/25 border border-white/20 shadow-inner">
                <button type="button" onclick="setExcelLanguage('pt')" id="langBtnPt" class="px-2 py-0.5 text-xs font-bold rounded transition-all bg-white text-green-900 shadow-sm cursor-pointer">
                    🇧🇷 PT
                </button>
                <button type="button" onclick="setExcelLanguage('en')" id="langBtnEn" class="px-2 py-0.5 text-xs font-bold rounded transition-all text-white/90 hover:text-white hover:bg-white/10 cursor-pointer">
                    🇺🇸 EN
                </button>
            </div>

            <!-- Google Auth & 4U Credits -->
            <div id="googleAuthContainer" class="flex items-center"></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar-section px-2 py-1 no-print">
        <div class="flex items-center gap-1 flex-wrap">
            <!-- Undo/Redo -->
            <button onclick="undo()" class="toolbar-btn" data-i18n-title="tip_undo" title="Desfazer (Ctrl+Z)">↶</button>
            <button onclick="redo()" class="toolbar-btn" data-i18n-title="tip_redo" title="Refazer (Ctrl+Y)">↷</button>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Copy/Paste -->
            <button onclick="copyCell()" class="toolbar-btn" data-i18n-title="tip_copy" title="Copiar (Ctrl+C)">📋</button>
            <button onclick="cutCell()" class="toolbar-btn" data-i18n-title="tip_cut" title="Recortar (Ctrl+X)">✂️</button>
            <button onclick="pasteCell()" class="toolbar-btn" data-i18n-title="tip_paste" title="Colar (Ctrl+V)">📄</button>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Font -->
            <select id="fontFamily" onchange="applyFormat('fontFamily', this.value)" class="border rounded px-2 py-1 text-sm w-28">
                <option value="Arial">Arial</option>
                <option value="Calibri">Calibri</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Courier New">Courier New</option>
                <option value="Georgia">Georgia</option>
                <option value="Verdana">Verdana</option>
            </select>
            
            <select id="fontSize" onchange="applyFormat('fontSize', this.value)" class="border rounded px-2 py-1 text-sm w-14">
                <option value="8">8</option>
                <option value="10">10</option>
                <option value="12" selected>12</option>
                <option value="14">14</option>
                <option value="16">16</option>
                <option value="18">18</option>
                <option value="24">24</option>
                <option value="36">36</option>
            </select>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Format buttons -->
            <button id="boldBtn" onclick="applyFormat('bold')" class="toolbar-btn font-bold" data-i18n-title="tip_bold" title="Negrito (Ctrl+B)">B</button>
            <button id="italicBtn" onclick="applyFormat('italic')" class="toolbar-btn italic" data-i18n-title="tip_italic" title="Itálico (Ctrl+I)">I</button>
            <button id="underlineBtn" onclick="applyFormat('underline')" class="toolbar-btn underline" data-i18n-title="tip_underline" title="Sublinhado (Ctrl+U)">U</button>
            <button id="strikeBtn" onclick="applyFormat('strike')" class="toolbar-btn line-through" data-i18n-title="tip_strike" title="Tachado">S</button>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Colors -->
            <label class="toolbar-btn flex items-center gap-1 cursor-pointer" data-i18n-title="tip_text_color" title="Cor do texto">
                <span class="text-sm">A</span>
                <input type="color" id="textColor" value="#000000" onchange="applyFormat('color', this.value)">
            </label>
            <label class="toolbar-btn flex items-center gap-1 cursor-pointer" data-i18n-title="tip_bg_color" title="Cor de fundo">
                <span>🎨</span>
                <input type="color" id="bgColor" value="#ffffff" onchange="applyFormat('backgroundColor', this.value)">
            </label>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Alignment -->
            <button onclick="applyFormat('textAlign', 'left')" class="toolbar-btn" data-i18n-title="tip_align_left" title="Esquerda">⬅</button>
            <button onclick="applyFormat('textAlign', 'center')" class="toolbar-btn" data-i18n-title="tip_align_center" title="Centro">⬌</button>
            <button onclick="applyFormat('textAlign', 'right')" class="toolbar-btn" data-i18n-title="tip_align_right" title="Direita">➡</button>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Number formats -->
            <select id="numberFormat" onchange="applyFormat('numberFormat', this.value)" class="border rounded px-2 py-1 text-sm">
                <option value="general" data-i18n="opt_general">Geral</option>
                <option value="number" data-i18n="opt_number">Número</option>
                <option value="currency" data-i18n="opt_currency">Moeda (R$)</option>
                <option value="percent" data-i18n="opt_percent">Porcentagem</option>
                <option value="date" data-i18n="opt_date">Data</option>
            </select>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Additional features -->
            <button onclick="showConditionalFormat()" class="toolbar-btn text-sm" data-i18n="btn_cond" data-i18n-title="tip_cond" title="Formatação Condicional">🎯 Cond.</button>
            <button onclick="addComment()" class="toolbar-btn text-sm" data-i18n-title="tip_comment" title="Adicionar Comentário">💬</button>
            <button onclick="toggleFreeze()" class="toolbar-btn text-sm" data-i18n-title="tip_freeze" title="Congelar Painéis">❄️</button>
            <button onclick="sortAsc()" class="toolbar-btn text-sm" data-i18n-title="tip_sort_asc" title="Ordenar A-Z">↑AZ</button>
            <button onclick="sortDesc()" class="toolbar-btn text-sm" data-i18n-title="tip_sort_desc" title="Ordenar Z-A">↓ZA</button>
            <button onclick="toggleFilter()" class="toolbar-btn text-sm" data-i18n-title="tip_filter" title="Filtrar">🔽</button>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <!-- Functions -->
            <select id="quickFunctions" onchange="insertQuickFunction(this.value); this.value='';" class="border rounded px-2 py-1 text-sm">
                <option value="" data-i18n="opt_functions_prompt">Funções...</option>
                <option value="SUM">SOMA / SUM</option>
                <option value="AVERAGE">MÉDIA / AVERAGE</option>
                <option value="COUNT">CONT.NÚM / COUNT</option>
                <option value="MAX">MÁXIMO / MAX</option>
                <option value="MIN">MÍNIMO / MIN</option>
                <option value="IF">SE / IF</option>
                <option value="VLOOKUP">PROCV / VLOOKUP</option>
                <option value="CONCATENATE">CONCATENAR / CONCATENATE</option>
                <option value="COUNTIF">CONT.SE / COUNTIF</option>
                <option value="SUMIF">SOMASE / SUMIF</option>
            </select>
            
            <div class="w-px h-6 bg-gray-300 mx-1"></div>
            
            <button onclick="saveToLocalStorage()" class="toolbar-btn text-sm" data-i18n="btn_save" data-i18n-title="tip_save" title="Salvar no navegador">💾 Salvar</button>
            <button onclick="loadFromLocalStorage()" class="toolbar-btn text-sm" data-i18n="btn_load" data-i18n-title="tip_load" title="Carregar do navegador">📂 Carregar</button>
        </div>
    </div>

    <!-- Formula Bar -->
    <div class="toolbar-section px-2 py-1 flex items-center gap-2 no-print">
        <div id="cellReference" class="w-20 text-center border rounded px-2 py-1 text-sm font-medium">A1</div>
        <div class="text-gray-400">fx</div>
        <input type="text" id="formulaBar" class="flex-1 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-[#217346]" placeholder="Digite um valor ou fórmula" data-i18n-placeholder="formula_placeholder">
    </div>

    <!-- Spreadsheet -->
    <div class="spreadsheet-container" id="spreadsheetContainer">
        <table id="spreadsheet" class="border-collapse">
            <thead id="headerRow"></thead>
            <tbody id="dataBody"></tbody>
        </table>
    </div>

    <!-- Sheet Tabs -->
    <div class="bg-gray-200 border-t border-gray-300 px-2 py-1 flex items-center gap-2 no-print" style="background: var(--bg-secondary);">
        <button onclick="addSheet()" class="toolbar-btn text-lg" data-i18n-title="tip_add_sheet" title="Adicionar planilha">+</button>
        <div id="sheetTabs" class="flex items-end"></div>
        <div class="flex-1"></div>
        <div id="statusBar" class="text-sm" style="color: var(--text-secondary);" data-i18n="status_ready">Pronto</div>
    </div>

    <!-- Context Menu -->
    <div id="contextMenu" class="context-menu hidden">
        <div class="context-menu-item" onclick="cutCell()" data-i18n="ctx_cut">✂️ Recortar</div>
        <div class="context-menu-item" onclick="copyCell()" data-i18n="ctx_copy">📋 Copiar</div>
        <div class="context-menu-item" onclick="pasteCell()" data-i18n="ctx_paste">📄 Colar</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="insertRow()" data-i18n="ctx_insert_row">➕ Inserir linha</div>
        <div class="context-menu-item" onclick="insertColumn()" data-i18n="ctx_insert_col">➕ Inserir coluna</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="deleteRow()" data-i18n="ctx_delete_row">🗑️ Excluir linha</div>
        <div class="context-menu-item" onclick="deleteColumn()" data-i18n="ctx_delete_col">🗑️ Excluir coluna</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="hideRow()" data-i18n="ctx_hide_row">👁️ Ocultar linha</div>
        <div class="context-menu-item" onclick="hideColumn()" data-i18n="ctx_hide_col">👁️ Ocultar coluna</div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" onclick="addComment()" data-i18n="ctx_add_comment">💬 Adicionar comentário</div>
        <div class="context-menu-item" onclick="clearCell()" data-i18n="ctx_clear_content">🧹 Limpar conteúdo</div>
        <div class="context-menu-item" onclick="clearFormat()" data-i18n="ctx_clear_format">🎨 Limpar formatação</div>
    </div>

    <!-- Comment Tooltip -->
    <div id="commentTooltip" class="comment-tooltip hidden"></div>

    <!-- Hidden file input for CSV import -->
    <input type="file" id="csvFileInput" accept=".csv" class="hidden" onchange="handleCSVImport(event)">

    <script>
        // Internationalization / Bilingue (PT / EN)
        const excelI18n = {
            pt: {
                app_title: "ExcelClone - Planilhas Online",
                back_office: "OfficeClone®",
                default_file_name: "Pasta1",
                default_sheet_name: "Planilha1",
                btn_find: "🔍 Buscar",
                tip_find: "Buscar (Ctrl+F)",
                btn_chart: "📊 Gráfico",
                btn_import: "📁 Importar",
                btn_export: "💾 Exportar",
                btn_print: "🖨️ Imprimir",
                btn_dark_mode: "🌙 Modo Escuro",
                btn_light_mode: "☀️ Modo Claro",
                tip_undo: "Desfazer (Ctrl+Z)",
                tip_redo: "Refazer (Ctrl+Y)",
                tip_copy: "Copiar (Ctrl+C)",
                tip_cut: "Recortar (Ctrl+X)",
                tip_paste: "Colar (Ctrl+V)",
                tip_bold: "Negrito (Ctrl+B)",
                tip_italic: "Itálico (Ctrl+I)",
                tip_underline: "Sublinhado (Ctrl+U)",
                tip_strike: "Tachado",
                tip_text_color: "Cor do texto",
                tip_bg_color: "Cor de fundo",
                tip_align_left: "Esquerda",
                tip_align_center: "Centro",
                tip_align_right: "Direita",
                opt_general: "Geral",
                opt_number: "Número",
                opt_currency: "Moeda (R$)",
                opt_percent: "Porcentagem",
                opt_date: "Data",
                btn_cond: "🎯 Cond.",
                tip_cond: "Formatação Condicional",
                tip_comment: "Adicionar Comentário",
                tip_freeze: "Congelar Painéis",
                tip_sort_asc: "Ordenar A-Z",
                tip_sort_desc: "Ordenar Z-A",
                tip_filter: "Filtrar",
                opt_functions_prompt: "Funções...",
                btn_save: "💾 Salvar",
                tip_save: "Salvar no navegador",
                btn_load: "📂 Carregar",
                tip_load: "Carregar do navegador",
                formula_placeholder: "Digite um valor ou fórmula",
                tip_add_sheet: "Adicionar planilha",
                status_ready: "Pronto",
                ctx_cut: "✂️ Recortar",
                ctx_copy: "📋 Copiar",
                ctx_paste: "📄 Colar",
                ctx_insert_row: "➕ Inserir linha",
                ctx_insert_col: "➕ Inserir coluna",
                ctx_delete_row: "🗑️ Excluir linha",
                ctx_delete_col: "🗑️ Excluir coluna",
                ctx_hide_row: "👁️ Ocultar linha",
                ctx_hide_col: "👁️ Ocultar coluna",
                ctx_add_comment: "💬 Adicionar comentário",
                ctx_clear_content: "🧹 Limpar conteúdo",
                ctx_clear_format: "🎨 Limpar formatação",
                cf_title: "🎯 Formatação Condicional",
                cf_range: "Intervalo:",
                cf_condition: "Condição:",
                cf_greater: "Maior que",
                cf_less: "Menor que",
                cf_equal: "Igual a",
                cf_contains: "Contém",
                cf_empty: "Está vazio",
                cf_not_empty: "Não está vazio",
                cf_value: "Valor:",
                cf_bg_color: "Cor do fundo:",
                cf_text_color: "Cor do texto:",
                cf_cancel: "Cancelar",
                cf_apply: "Aplicar",
                cf_applied: "Formatação condicional aplicada",
                fr_title: "🔍 Buscar e Substituir",
                fr_find: "Buscar:",
                fr_replace: "Substituir por:",
                fr_match_case: "Diferenciar maiúsculas/minúsculas",
                fr_next: "Próximo",
                fr_replace_btn: "Substituir",
                fr_replace_all: "Substituir Tudo",
                fr_replaced_count: "substituições realizadas",
                chart_title_modal: "📊 Criar Gráfico",
                chart_data_range: "Intervalo de dados:",
                chart_type: "Tipo de gráfico:",
                chart_bar: "Barras",
                chart_line: "Linhas",
                chart_pie: "Pizza",
                chart_doughnut: "Rosca",
                chart_radar: "Radar",
                chart_input_title: "Título:",
                chart_default_title: "Meu Gráfico",
                chart_download: "Baixar PNG",
                prompt_sheet_name: "Nome da planilha:",
                prompt_comment: "Comentário:",
                status_filter_cleared: "Filtro limpo",
                status_filter_applied: "Filtro aplicado",
                status_copied: "Copiado para a área de transferência!",
                status_cut: "Recortado!",
                status_pasted: "Colado!",
                status_saved: "Salvo no navegador!",
                status_loaded: "Carregado do navegador!",
                stat_avg: "Média",
                stat_count: "Contagem",
                stat_sum: "Soma",
                footer_rights: "© 2026 4U.IA.BR - Todos os direitos reservados"
            },
            en: {
                app_title: "ExcelClone - Online Spreadsheets",
                back_office: "OfficeClone®",
                default_file_name: "Workbook1",
                default_sheet_name: "Sheet1",
                btn_find: "🔍 Find",
                tip_find: "Find (Ctrl+F)",
                btn_chart: "📊 Chart",
                btn_import: "📁 Import",
                btn_export: "💾 Export",
                btn_print: "🖨️ Print",
                btn_dark_mode: "🌙 Dark Mode",
                btn_light_mode: "☀️ Light Mode",
                tip_undo: "Undo (Ctrl+Z)",
                tip_redo: "Redo (Ctrl+Y)",
                tip_copy: "Copy (Ctrl+C)",
                tip_cut: "Cut (Ctrl+X)",
                tip_paste: "Paste (Ctrl+V)",
                tip_bold: "Bold (Ctrl+B)",
                tip_italic: "Italic (Ctrl+I)",
                tip_underline: "Underline (Ctrl+U)",
                tip_strike: "Strikethrough",
                tip_text_color: "Text color",
                tip_bg_color: "Fill color",
                tip_align_left: "Left",
                tip_align_center: "Center",
                tip_align_right: "Right",
                opt_general: "General",
                opt_number: "Number",
                opt_currency: "Currency ($)",
                opt_percent: "Percentage",
                opt_date: "Date",
                btn_cond: "🎯 Cond.",
                tip_cond: "Conditional Formatting",
                tip_comment: "Add Comment",
                tip_freeze: "Freeze Panes",
                tip_sort_asc: "Sort A-Z",
                tip_sort_desc: "Sort Z-A",
                tip_filter: "Filter",
                opt_functions_prompt: "Functions...",
                btn_save: "💾 Save",
                tip_save: "Save to browser",
                btn_load: "📂 Load",
                tip_load: "Load from browser",
                formula_placeholder: "Enter a value or formula",
                tip_add_sheet: "Add sheet",
                status_ready: "Ready",
                ctx_cut: "✂️ Cut",
                ctx_copy: "📋 Copy",
                ctx_paste: "📄 Paste",
                ctx_insert_row: "➕ Insert row",
                ctx_insert_col: "➕ Insert column",
                ctx_delete_row: "🗑️ Delete row",
                ctx_delete_col: "🗑️ Delete column",
                ctx_hide_row: "👁️ Hide row",
                ctx_hide_col: "👁️ Hide column",
                ctx_add_comment: "💬 Add comment",
                ctx_clear_content: "🧹 Clear content",
                ctx_clear_format: "🎨 Clear formatting",
                cf_title: "🎯 Conditional Formatting",
                cf_range: "Range:",
                cf_condition: "Condition:",
                cf_greater: "Greater than",
                cf_less: "Less than",
                cf_equal: "Equal to",
                cf_contains: "Contains",
                cf_empty: "Is empty",
                cf_not_empty: "Is not empty",
                cf_value: "Value:",
                cf_bg_color: "Background color:",
                cf_text_color: "Text color:",
                cf_cancel: "Cancel",
                cf_apply: "Apply",
                cf_applied: "Conditional formatting applied",
                fr_title: "🔍 Find and Replace",
                fr_find: "Find:",
                fr_replace: "Replace with:",
                fr_match_case: "Match case",
                fr_next: "Next",
                fr_replace_btn: "Replace",
                fr_replace_all: "Replace All",
                fr_replaced_count: "replacements made",
                chart_title_modal: "📊 Create Chart",
                chart_data_range: "Data range:",
                chart_type: "Chart type:",
                chart_bar: "Bar",
                chart_line: "Line",
                chart_pie: "Pie",
                chart_doughnut: "Doughnut",
                chart_radar: "Radar",
                chart_input_title: "Title:",
                chart_default_title: "My Chart",
                chart_download: "Download PNG",
                prompt_sheet_name: "Sheet name:",
                prompt_comment: "Comment:",
                status_filter_cleared: "Filter cleared",
                status_filter_applied: "Filter applied",
                status_copied: "Copied to clipboard!",
                status_cut: "Cut!",
                status_pasted: "Pasted!",
                status_saved: "Saved to browser!",
                status_loaded: "Loaded from browser!",
                stat_avg: "Average",
                stat_count: "Count",
                stat_sum: "Sum",
                footer_rights: "© 2026 4U.IA.BR - All rights reserved"
            }
        };

        let currentLang = 'pt';

        function detectInitialLanguage() {
            const saved = localStorage.getItem('office_lang');
            if (saved === 'en' || saved === 'pt') return saved;
            const nav = (navigator.language || navigator.userLanguage || '').toLowerCase();
            return nav.startsWith('pt') ? 'pt' : 'en';
        }

        function setExcelLanguage(lang) {
            currentLang = lang;
            localStorage.setItem('office_lang', lang);
            applyExcelTranslations(true);
        }

        function applyExcelTranslations(notify = false) {
            const t = excelI18n[currentLang] || excelI18n.pt;
            document.title = t.app_title;
            
            // Toggle buttons visual state
            const btnPt = document.getElementById('langBtnPt');
            const btnEn = document.getElementById('langBtnEn');
            if (btnPt && btnEn) {
                if (currentLang === 'pt') {
                    btnPt.className = 'px-2 py-0.5 text-xs font-bold rounded transition-all bg-white text-green-900 shadow-sm cursor-pointer';
                    btnEn.className = 'px-2 py-0.5 text-xs font-bold rounded transition-all text-white/90 hover:text-white hover:bg-white/10 cursor-pointer';
                } else {
                    btnPt.className = 'px-2 py-0.5 text-xs font-bold rounded transition-all text-white/90 hover:text-white hover:bg-white/10 cursor-pointer';
                    btnEn.className = 'px-2 py-0.5 text-xs font-bold rounded transition-all bg-white text-green-900 shadow-sm cursor-pointer';
                }
            }

            // Translate DOM elements with data-i18n
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (t[key]) {
                    el.textContent = t[key];
                }
            });

            // Translate attributes: title, placeholder
            document.querySelectorAll('[data-i18n-title]').forEach(el => {
                const key = el.getAttribute('data-i18n-title');
                if (t[key]) {
                    el.setAttribute('title', t[key]);
                }
            });

            document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                if (t[key]) {
                    el.setAttribute('placeholder', t[key]);
                }
            });

            // Dark mode button text
            const dmBtn = document.getElementById('darkModeBtn');
            if (dmBtn) {
                dmBtn.textContent = darkMode ? t.btn_light_mode : t.btn_dark_mode;
            }

            // Refresh GoogleAuth UI so any labels adjust
            if (window.GoogleAuth && typeof GoogleAuth.renderAuthUI === 'function') {
                GoogleAuth.renderAuthUI();
            }

            if (notify) {
                updateStatus(currentLang === 'en' ? '🇺🇸 Language set to English' : '🇧🇷 Idioma alterado para Português');
            }
        }

        // Configuration
        const ROWS = 100;
        const COLS = 26;
        
        // State
        let sheets = [{
            name: 'Planilha1',
            data: {},
            formats: {},
            comments: {},
            colWidths: {},
            rowHeights: {},
            hiddenRows: new Set(),
            hiddenCols: new Set(),
            frozenRow: 0,
            frozenCol: 0,
            conditionalFormats: [],
            filters: {}
        }];
        let currentSheet = 0;
        let selectedCell = null;
        let selectedRange = { start: null, end: null };
        let clipboard = null;
        let clipboardMode = null;
        let undoStack = [];
        let redoStack = [];
        let isSelecting = false;
        let isFilling = false;
        let fillStart = null;
        let isResizing = false;
        let resizeCol = null;
        let resizeStartX = 0;
        let darkMode = false;
        // Estado para seleção de intervalo em funções (ex: SOMA)
        let formulaEdit = null; // { funcName, baseCellId }
        let formulaEditRangeStart = null;
        
        // Initialize spreadsheet
        function init() {
            loadFromLocalStorage(true);
            currentLang = detectInitialLanguage();
            applyExcelTranslations(false);
            createHeaders();
            createCells();
            renderSheetTabs();
            selectCell(document.querySelector('.cell'));
            setupEventListeners();
            if (window.GoogleAuth && typeof GoogleAuth.init === 'function') {
                GoogleAuth.init();
            }
        }
        
        function getColName(index) {
            let name = '';
            while (index >= 0) {
                name = String.fromCharCode(65 + (index % 26)) + name;
                index = Math.floor(index / 26) - 1;
            }
            return name;
        }
        
        function getColIndex(name) {
            let index = 0;
            for (let i = 0; i < name.length; i++) {
                index = index * 26 + (name.charCodeAt(i) - 64);
            }
            return index - 1;
        }
        
        function createHeaders() {
            const headerRow = document.getElementById('headerRow');
            const sheet = sheets[currentSheet];
            let html = '<tr><th class="header-cell sticky top-0 left-0 z-20" style="background: var(--bg-secondary);"></th>';
            for (let i = 0; i < COLS; i++) {
                const width = sheet.colWidths[i] || 100;
                const hidden = sheet.hiddenCols?.has(i);
                html += `<th class="header-cell sticky top-0 z-10 ${hidden ? 'hidden-col' : ''}" data-col="${i}" style="min-width:${width}px; width:${width}px; ${hidden ? 'max-width:5px;min-width:5px;width:5px;overflow:hidden;' : ''}">
                    ${hidden ? '‹›' : getColName(i)}
                    <div class="resize-handle" onmousedown="startResize(event, ${i})"></div>
                    <span class="filter-btn" onclick="showFilterDropdown(event, ${i})">▼</span>
                </th>`;
            }
            html += '</tr>';
            headerRow.innerHTML = html;
        }
        
        function createCells() {
            const dataBody = document.getElementById('dataBody');
            const sheet = sheets[currentSheet];
            let html = '';
            
            for (let row = 0; row < ROWS; row++) {
                const hidden = sheet.hiddenRows?.has(row);
                const frozen = row < sheet.frozenRow;
                html += `<tr class="${hidden ? 'hidden' : ''}" style="${hidden ? 'display:none;' : ''}">
                    <td class="row-header sticky left-0 z-10 ${hidden ? 'hidden-row' : ''} ${frozen ? 'frozen-row' : ''}" data-row="${row}">${row + 1}</td>`;
                
                for (let col = 0; col < COLS; col++) {
                    const cellId = `${getColName(col)}${row + 1}`;
                    const colHidden = sheet.hiddenCols?.has(col);
                    const colFrozen = col < sheet.frozenCol;
                    const width = sheet.colWidths[col] || 100;
                    html += `<td class="cell ${colFrozen ? 'frozen-col' : ''}" data-row="${row}" data-col="${col}" data-id="${cellId}" contenteditable="true" style="min-width:${width}px; ${colHidden ? 'display:none;' : ''}">
                        <div class="fill-handle" onmousedown="startFill(event)"></div>
                    </td>`;
                }
                html += '</tr>';
            }
            dataBody.innerHTML = html;
            loadSheetData();
        }
        
        function loadSheetData() {
            const sheet = sheets[currentSheet];
            document.querySelectorAll('.cell').forEach(cell => {
                const id = cell.dataset.id;
                const data = sheet.data[id];
                const format = sheet.formats[id] || {};
                const hasComment = sheet.comments && sheet.comments[id];
                
                // Get just the text content (not the fill handle)
                const textContent = data?.display ?? data?.value ?? '';
                
                // Update only text, preserve fill handle
                const fillHandle = cell.querySelector('.fill-handle');
                cell.textContent = textContent;
                if (fillHandle) cell.appendChild(fillHandle);
                else {
                    const newHandle = document.createElement('div');
                    newHandle.className = 'fill-handle';
                    newHandle.onmousedown = (e) => startFill(e);
                    cell.appendChild(newHandle);
                }
                
                applyFormatToCell(cell, format);
                applyConditionalFormat(cell, textContent);
                
                if (hasComment) {
                    cell.classList.add('has-comment');
                } else {
                    cell.classList.remove('has-comment');
                }
            });
        }
        
        function applyFormatToCell(cell, format) {
            cell.style.fontFamily = format.fontFamily || '';
            cell.style.fontSize = format.fontSize ? format.fontSize + 'px' : '';
            cell.style.fontWeight = format.bold ? 'bold' : '';
            cell.style.fontStyle = format.italic ? 'italic' : '';
            cell.style.textDecoration = format.underline ? 'underline' : (format.strike ? 'line-through' : '');
            cell.style.color = format.color || '';
            if (format.backgroundColor) {
                cell.style.backgroundColor = format.backgroundColor;
            }
            cell.style.textAlign = format.textAlign || '';
            if (format.border) {
                cell.style.border = '1px solid #000';
            }
        }
        
        function applyConditionalFormat(cell, value) {
            const sheet = sheets[currentSheet];
            if (!sheet.conditionalFormats) return;
            
            const numValue = parseFloat(value);
            
            sheet.conditionalFormats.forEach(cf => {
                const cellCol = cell.dataset.col;
                const cellRow = cell.dataset.row;
                
                // Check if cell is in range
                if (cf.range) {
                    const [start, end] = cf.range.split(':');
                    const startCol = getColIndex(start.match(/[A-Z]+/i)[0].toUpperCase());
                    const startRow = parseInt(start.match(/\d+/)[0]) - 1;
                    const endCol = getColIndex(end.match(/[A-Z]+/i)[0].toUpperCase());
                    const endRow = parseInt(end.match(/\d+/)[0]) - 1;
                    
                    if (cellCol < startCol || cellCol > endCol || cellRow < startRow || cellRow > endRow) {
                        return;
                    }
                }
                
                let match = false;
                switch (cf.condition) {
                    case 'greater':
                        match = !isNaN(numValue) && numValue > cf.value;
                        break;
                    case 'less':
                        match = !isNaN(numValue) && numValue < cf.value;
                        break;
                    case 'equal':
                        match = value == cf.value;
                        break;
                    case 'contains':
                        match = String(value).toLowerCase().includes(String(cf.value).toLowerCase());
                        break;
                    case 'empty':
                        match = !value || value === '';
                        break;
                    case 'notEmpty':
                        match = value && value !== '';
                        break;
                }
                
                if (match) {
                    if (cf.bgColor) cell.style.backgroundColor = cf.bgColor;
                    if (cf.textColor) cell.style.color = cf.textColor;
                }
            });
        }
        
        function renderSheetTabs() {
            const tabsContainer = document.getElementById('sheetTabs');
            tabsContainer.innerHTML = '';
            
            sheets.forEach((sheet, index) => {
                const tab = document.createElement('div');
                tab.className = `sheet-tab ${index === currentSheet ? 'active' : ''}`;
                tab.dataset.sheet = index;
                tab.textContent = sheet.name;
                tab.onclick = () => switchSheet(index);
                tab.ondblclick = () => renameSheet(index);
                tab.oncontextmenu = (e) => {
                    e.preventDefault();
                    if (confirm(`Excluir "${sheet.name}"?`)) {
                        deleteSheet(index);
                    }
                };
                tabsContainer.appendChild(tab);
            });
        }
        
        function setupEventListeners() {
            const container = document.getElementById('spreadsheetContainer');
            const formulaBar = document.getElementById('formulaBar');
            
            // Cell events
            container.addEventListener('mousedown', (e) => {
                const cell = e.target.closest('.cell');

                // Modo de seleção de intervalo para funções (ex: SOMA)
                if (formulaEdit && selectedCell && selectedCell.dataset.id === formulaEdit.baseCellId && cell && !e.target.classList.contains('fill-handle')) {
                    isSelecting = true;
                    formulaEditRangeStart = cell;
                    updateFormulaRangeSelection(cell, cell);
                    e.preventDefault();
                    hideContextMenu();
                    return;
                }

                if (cell && !e.target.classList.contains('fill-handle')) {
                    if (e.shiftKey && selectedCell) {
                        selectRange(selectedCell, cell);
                    } else {
                        isSelecting = true;
                        selectCell(cell);
                        selectedRange = { start: cell, end: cell };
                    }
                }
                hideContextMenu();
            });
            
            container.addEventListener('mousemove', (e) => {
                if (isSelecting && !isFilling) {
                    const cell = e.target.closest('.cell');
                    if (cell) {
                        if (formulaEdit && formulaEditRangeStart) {
                            updateFormulaRangeSelection(formulaEditRangeStart, cell);
                        } else if (selectedRange.start) {
                            selectRange(selectedRange.start, cell);
                        }
                    }
                }
                
                if (isFilling && fillStart) {
                    const cell = e.target.closest('.cell');
                    if (cell) {
                        highlightFillRange(fillStart, cell);
                    }
                }
                
                if (isResizing && resizeCol !== null) {
                    const delta = e.clientX - resizeStartX;
                    const sheet = sheets[currentSheet];
                    const currentWidth = sheet.colWidths[resizeCol] || 100;
                    const newWidth = Math.max(30, currentWidth + delta);
                    sheet.colWidths[resizeCol] = newWidth;
                    
                    // Update header
                    const header = document.querySelector(`.header-cell[data-col="${resizeCol}"]`);
                    if (header) {
                        header.style.minWidth = newWidth + 'px';
                        header.style.width = newWidth + 'px';
                    }
                    
                    // Update cells
                    document.querySelectorAll(`.cell[data-col="${resizeCol}"]`).forEach(cell => {
                        cell.style.minWidth = newWidth + 'px';
                    });
                    
                    resizeStartX = e.clientX;
                }
                
                // Show comment tooltip
                const cell = e.target.closest('.cell');
                if (cell && cell.classList.contains('has-comment')) {
                    showCommentTooltip(cell, e);
                } else {
                    hideCommentTooltip();
                }
            });
            
            document.addEventListener('mouseup', (e) => {
                if (isFilling && fillStart) {
                    const cell = e.target.closest('.cell');
                    if (cell) {
                        performFill(fillStart, cell);
                    }
                }
                isSelecting = false;
                formulaEditRangeStart = null;
                isFilling = false;
                fillStart = null;
                isResizing = false;
                resizeCol = null;
            });
            
            container.addEventListener('dblclick', (e) => {
                const cell = e.target.closest('.cell');
                if (cell) {
                    cell.focus();
                    const sheet = sheets[currentSheet];
                    const data = sheet.data[cell.dataset.id];
                    if (data?.formula) {
                        // Set text without removing fill handle
                        const fillHandle = cell.querySelector('.fill-handle');
                        cell.textContent = data.formula;
                        if (fillHandle) cell.appendChild(fillHandle);
                    }
                }
            });
            
            container.addEventListener('input', (e) => {
                const cell = e.target.closest('.cell');
                if (cell) {
                    // Usa o mesmo método de captura de texto da célula
                    const text = cell.textContent;
                    formulaBar.value = text;
                }
            });
            
            container.addEventListener('keydown', handleCellKeydown);
            
            container.addEventListener('blur', (e) => {
                const cell = e.target.closest('.cell');
                if (cell) {
                    saveCell(cell);
                }
            }, true);
            
            // Formula bar events
            formulaBar.addEventListener('input', () => {
                if (selectedCell) {
                    const fillHandle = selectedCell.querySelector('.fill-handle');
                    selectedCell.textContent = formulaBar.value;
                    if (fillHandle) selectedCell.appendChild(fillHandle);
                }
            });
            
            formulaBar.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedCell) {
                        saveCell(selectedCell);
                        moveSelection(1, 0);
                    }
                }
            });
            
            // Context menu
            container.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                const cell = e.target.closest('.cell');
                if (cell) {
                    selectCell(cell);
                    showContextMenu(e.clientX, e.clientY);
                }
            });
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.context-menu')) hideContextMenu();
                if (!e.target.closest('.filter-dropdown') && !e.target.closest('.filter-btn')) {
                    document.querySelectorAll('.filter-dropdown').forEach(d => d.remove());
                }
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', handleGlobalKeydown);
        }
        
        function handleCellKeydown(e) {
            const cell = e.target.closest('.cell');
            if (!cell) return;
            
            // Enter/Tab apenas movem a seleção; o salvamento é feito no evento de blur
            if (e.key === 'Enter') {
                e.preventDefault();
                moveSelection(e.shiftKey ? -1 : 1, 0);
            } else if (e.key === 'Tab') {
                e.preventDefault();
                moveSelection(0, e.shiftKey ? -1 : 1);
            } else if (e.key === 'Escape') {
                const sheet = sheets[currentSheet];
                const data = sheet.data[cell.dataset.id];
                const fillHandle = cell.querySelector('.fill-handle');
                cell.textContent = data?.display || data?.value || '';
                if (fillHandle) cell.appendChild(fillHandle);
                cell.blur();
            } else if (document.activeElement !== cell || !cell.textContent) {
                if (e.key === 'ArrowUp') { e.preventDefault(); moveSelection(-1, 0); }
                else if (e.key === 'ArrowDown') { e.preventDefault(); moveSelection(1, 0); }
                else if (e.key === 'ArrowLeft') { e.preventDefault(); moveSelection(0, -1); }
                else if (e.key === 'ArrowRight') { e.preventDefault(); moveSelection(0, 1); }
            }
        }
        
        function handleGlobalKeydown(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key.toLowerCase()) {
                    case 'c': e.preventDefault(); copyCell(); break;
                    case 'x': e.preventDefault(); cutCell(); break;
                    case 'v': e.preventDefault(); pasteCell(); break;
                    case 'z': e.preventDefault(); undo(); break;
                    case 'y': e.preventDefault(); redo(); break;
                    case 'b': e.preventDefault(); applyFormat('bold'); break;
                    case 'i': e.preventDefault(); applyFormat('italic'); break;
                    case 'u': e.preventDefault(); applyFormat('underline'); break;
                    case 's': e.preventDefault(); saveToLocalStorage(); break;
                    case 'f': e.preventDefault(); showFindReplace(); break;
                }
            }
            
            if (e.key === 'Delete' && selectedCell && document.activeElement !== selectedCell) {
                clearCell();
            }
        }
        
        function selectCell(cell) {
            // Ao mudar de célula, sai do modo de edição de fórmula
            formulaEdit = null;
            formulaEditRangeStart = null;
            
            document.querySelectorAll('.cell.selected, .cell.range-selected').forEach(c => {
                c.classList.remove('selected', 'range-selected');
            });
            
            if (cell) {
                selectedCell = cell;
                cell.classList.add('selected');
                
                document.getElementById('cellReference').textContent = cell.dataset.id;
                
                const sheet = sheets[currentSheet];
                const data = sheet.data[cell.dataset.id];
                document.getElementById('formulaBar').value = data?.formula || data?.value || '';
                
                updateToolbarState(cell);
            }
        }
        
        function selectRange(start, end) {
            document.querySelectorAll('.cell.selected, .cell.range-selected').forEach(c => {
                c.classList.remove('selected', 'range-selected');
            });
            
            const startRow = Math.min(parseInt(start.dataset.row), parseInt(end.dataset.row));
            const endRow = Math.max(parseInt(start.dataset.row), parseInt(end.dataset.row));
            const startCol = Math.min(parseInt(start.dataset.col), parseInt(end.dataset.col));
            const endCol = Math.max(parseInt(start.dataset.col), parseInt(end.dataset.col));
            
            for (let row = startRow; row <= endRow; row++) {
                for (let col = startCol; col <= endCol; col++) {
                    const cell = document.querySelector(`.cell[data-row="${row}"][data-col="${col}"]`);
                    if (cell) cell.classList.add('range-selected');
                }
            }
            
            selectedRange = { start, end };
            start.classList.add('selected');
            
            document.getElementById('cellReference').textContent = 
                (startRow !== endRow || startCol !== endCol) ? 
                `${start.dataset.id}:${end.dataset.id}` : start.dataset.id;
            
            updateStatusBar();
        }

        // Atualiza seleção de intervalo dentro de uma fórmula (ex: =SUM(A1:A5))
        function updateFormulaRangeSelection(startCell, endCell) {
            const startRow = Math.min(parseInt(startCell.dataset.row), parseInt(endCell.dataset.row));
            const endRow = Math.max(parseInt(startCell.dataset.row), parseInt(endCell.dataset.row));
            const startCol = Math.min(parseInt(startCell.dataset.col), parseInt(endCell.dataset.col));
            const endCol = Math.max(parseInt(startCell.dataset.col), parseInt(endCell.dataset.col));

            const startRef = getColName(startCol) + (startRow + 1);
            const endRef = getColName(endCol) + (endRow + 1);
            const rangeRef = `${startRef}:${endRef}`;

            if (formulaEdit && selectedCell && selectedCell.dataset.id === formulaEdit.baseCellId) {
                const func = formulaEdit.funcName;
                const newFormula = `=${func}(${rangeRef})`;

                const fillHandle = selectedCell.querySelector('.fill-handle');
                selectedCell.textContent = newFormula;
                if (fillHandle) selectedCell.appendChild(fillHandle);
                document.getElementById('formulaBar').value = newFormula;
            }

            // Destacar intervalo na grade
            document.querySelectorAll('.cell.range-selected').forEach(c => c.classList.remove('range-selected'));
            for (let row = startRow; row <= endRow; row++) {
                for (let col = startCol; col <= endCol; col++) {
                    const cell = document.querySelector(`.cell[data-row="${row}"][data-col="${col}"]`);
                    if (cell) cell.classList.add('range-selected');
                }
            }
        }
        
        function moveSelection(rowDelta, colDelta) {
            if (!selectedCell) return;
            
            let newRow = parseInt(selectedCell.dataset.row) + rowDelta;
            let newCol = parseInt(selectedCell.dataset.col) + colDelta;
            
            const sheet = sheets[currentSheet];
            
            // Skip hidden rows/cols
            while (newRow >= 0 && newRow < ROWS && sheet.hiddenRows?.has(newRow)) {
                newRow += rowDelta || 1;
            }
            while (newCol >= 0 && newCol < COLS && sheet.hiddenCols?.has(newCol)) {
                newCol += colDelta || 1;
            }
            
            if (newRow >= 0 && newRow < ROWS && newCol >= 0 && newCol < COLS) {
                const newCell = document.querySelector(`.cell[data-row="${newRow}"][data-col="${newCol}"]`);
                if (newCell) {
                    // garante que a célula anterior seja "desfocada" e salva via evento de blur
                    newCell.focus();
                    selectCell(newCell);
                    newCell.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            }
        }
        
        function getCellText(cell) {
            // Usa todo o texto da célula (independente de elementos internos criados pelo navegador)
            // O manipulador de preenchimento não possui texto, então não interfere.
            return cell.textContent.trim();
        }
        
        function saveCell(cell) {
            const value = getCellText(cell);
            const cellId = cell.dataset.id;
            const sheet = sheets[currentSheet];
            
            saveUndo();
            
            const fillHandle = cell.querySelector('.fill-handle');
            
            if (value === '') {
                delete sheet.data[cellId];
                cell.textContent = '';
            } else if (value.startsWith('=')) {
                const result = evaluateFormula(value, cellId);
                sheet.data[cellId] = {
                    formula: value,
                    value: result,
                    display: formatValue(result, sheet.formats[cellId]?.numberFormat)
                };
                cell.textContent = sheet.data[cellId].display;
            } else {
                sheet.data[cellId] = {
                    value: value,
                    display: formatValue(value, sheet.formats[cellId]?.numberFormat)
                };
                cell.textContent = sheet.data[cellId].display;
            }
            
            if (fillHandle) cell.appendChild(fillHandle);
            else {
                const newHandle = document.createElement('div');
                newHandle.className = 'fill-handle';
                newHandle.onmousedown = (e) => startFill(e);
                cell.appendChild(newHandle);
            }
            
            applyConditionalFormat(cell, sheet.data[cellId]?.value || '');
            updateDependentCells();
            updateStatusBar();
        }
        
        function evaluateFormula(formula, currentCellId) {
            try {
                let expr = formula.substring(1).toUpperCase();
                const sheet = sheets[currentSheet];
                
                const functions = {
                    'SUM': (args) => args.flat().reduce((a, b) => a + (parseFloat(b) || 0), 0),
                    'AVERAGE': (args) => { const flat = args.flat().filter(v => !isNaN(parseFloat(v))); return flat.reduce((a, b) => a + parseFloat(b), 0) / flat.length; },
                    'COUNT': (args) => args.flat().filter(v => !isNaN(parseFloat(v))).length,
                    'COUNTA': (args) => args.flat().filter(v => v !== '' && v !== null && v !== undefined).length,
                    'MAX': (args) => Math.max(...args.flat().map(v => parseFloat(v) || -Infinity)),
                    'MIN': (args) => Math.min(...args.flat().map(v => parseFloat(v) || Infinity)),
                    'ABS': (args) => Math.abs(parseFloat(args[0]) || 0),
                    'SQRT': (args) => Math.sqrt(parseFloat(args[0]) || 0),
                    'POWER': (args) => Math.pow(parseFloat(args[0]) || 0, parseFloat(args[1]) || 1),
                    'ROUND': (args) => {
                        const num = parseFloat(args[0]) || 0;
                        const decimals = parseInt(args[1]) || 0;
                        return Math.round(num * Math.pow(10, decimals)) / Math.pow(10, decimals);
                    },
                    'IF': (args) => args[0] ? args[1] : args[2],
                    'CONCATENATE': (args) => args.flat().join(''),
                    'LEN': (args) => String(args[0] || '').length,
                    'UPPER': (args) => String(args[0] || '').toUpperCase(),
                    'LOWER': (args) => String(args[0] || '').toLowerCase(),
                    'TRIM': (args) => String(args[0] || '').trim(),
                    'LEFT': (args) => String(args[0] || '').substring(0, parseInt(args[1]) || 1),
                    'RIGHT': (args) => { const str = String(args[0] || ''); return str.substring(str.length - (parseInt(args[1]) || 1)); },
                    'MID': (args) => String(args[0] || '').substring((parseInt(args[1]) - 1) || 0, ((parseInt(args[1]) - 1) || 0) + (parseInt(args[2]) || 1)),
                    'NOW': () => new Date().toLocaleString('pt-BR'),
                    'TODAY': () => new Date().toLocaleDateString('pt-BR'),
                    'PI': () => Math.PI,
                    'RAND': () => Math.random(),
                    'SUMIF': (args) => {
                        const range = args[0];
                        const criteria = args[1];
                        const sumRange = args[2] || range;
                        let sum = 0;
                        if (Array.isArray(range)) {
                            range.forEach((val, i) => {
                                if (String(val).includes(String(criteria)) || val == criteria) {
                                    sum += parseFloat(Array.isArray(sumRange) ? sumRange[i] : sumRange) || 0;
                                }
                            });
                        }
                        return sum;
                    },
                    'COUNTIF': (args) => {
                        const range = args[0];
                        const criteria = args[1];
                        let count = 0;
                        if (Array.isArray(range)) {
                            range.forEach(val => {
                                if (String(val).includes(String(criteria)) || val == criteria) count++;
                            });
                        }
                        return count;
                    },
                    'VLOOKUP': (args) => {
                        const searchVal = args[0];
                        const range = args[1];
                        const colIndex = parseInt(args[2]) || 1;
                        // Simplified VLOOKUP
                        return searchVal;
                    }
                };
                
                // Aliases em Português para compatibilidade total
                functions['SOMA'] = functions['SUM'];
                functions['MEDIA'] = functions['AVERAGE'];
                functions['MÉDIA'] = functions['AVERAGE'];
                functions['CONT.NUM'] = functions['COUNT'];
                functions['CONT.NÚM'] = functions['COUNT'];
                functions['MAXIMO'] = functions['MAX'];
                functions['MÁXIMO'] = functions['MAX'];
                functions['MINIMO'] = functions['MIN'];
                functions['MÍNIMO'] = functions['MIN'];
                functions['SE'] = functions['IF'];
                functions['CONCATENAR'] = functions['CONCATENATE'];
                functions['CONT.SE'] = functions['COUNTIF'];
                functions['SOMASE'] = functions['SUMIF'];
                functions['PROCV'] = functions['VLOOKUP'];
                
                // Parse function calls
                const processFunctions = (expr) => {
                    let result = expr;
                    let changed = true;
                    let iterations = 0;
                    
                    while (changed && iterations < 10) {
                        changed = false;
                        iterations++;
                        
                        for (const [funcName, funcImpl] of Object.entries(functions)) {
                            const regex = new RegExp(`${funcName}\\(([^()]*?)\\)`, 'gi');
                            result = result.replace(regex, (match, argsStr) => {
                                changed = true;
                                const args = parseArguments(argsStr);
                                const values = args.map(arg => {
                                    arg = arg.trim();
                                    if (arg.includes(':')) {
                                        return getRangeValues(arg);
                                    } else if (/^[A-Z]+\d+$/i.test(arg)) {
                                        return getCellValue(arg);
                                    } else if (arg.startsWith('"') && arg.endsWith('"')) {
                                        return arg.slice(1, -1);
                                    }
                                    return isNaN(parseFloat(arg)) ? arg : parseFloat(arg);
                                });
                                return funcImpl(values);
                            });
                        }
                    }
                    return result;
                };
                
                expr = processFunctions(expr);
                
                // Replace remaining cell references
                expr = expr.replace(/\b([A-Z]+)(\d+)\b/gi, (match) => {
                    const value = getCellValue(match);
                    return isNaN(parseFloat(value)) ? `"${value}"` : value;
                });
                
                // Handle comparison operators
                expr = expr.replace(/<>/g, '!==').replace(/(?<![<>!])=/g, '===');
                
                const result = Function('"use strict"; return (' + expr + ')')();
                return result;
            } catch (e) {
                console.error('Formula error:', e);
                return '#ERROR!';
            }
        }
        
        function parseArguments(argsStr) {
            const args = [];
            let current = '';
            let depth = 0;
            let inString = false;
            
            for (let i = 0; i < argsStr.length; i++) {
                const char = argsStr[i];
                if (char === '"') inString = !inString;
                if (!inString) {
                    if (char === '(') depth++;
                    else if (char === ')') depth--;
                    else if (char === ';' || char === ',') {
                        if (depth === 0) {
                            args.push(current.trim());
                            current = '';
                            continue;
                        }
                    }
                }
                current += char;
            }
            if (current.trim()) args.push(current.trim());
            return args;
        }
        
        function getCellValue(cellId) {
            const sheet = sheets[currentSheet];
            const data = sheet.data[cellId.toUpperCase()];
            if (!data) return '';
            const val = data.value;
            return isNaN(parseFloat(val)) ? val : parseFloat(val);
        }
        
        function getRangeValues(range) {
            const [start, end] = range.split(':');
            const startCol = getColIndex(start.match(/[A-Z]+/i)[0].toUpperCase());
            const startRow = parseInt(start.match(/\d+/)[0]) - 1;
            const endCol = getColIndex(end.match(/[A-Z]+/i)[0].toUpperCase());
            const endRow = parseInt(end.match(/\d+/)[0]) - 1;
            
            const values = [];
            for (let row = Math.min(startRow, endRow); row <= Math.max(startRow, endRow); row++) {
                for (let col = Math.min(startCol, endCol); col <= Math.max(startCol, endCol); col++) {
                    values.push(getCellValue(getColName(col) + (row + 1)));
                }
            }
            return values;
        }
        
        function formatValue(value, format) {
            if (value === '' || value === null || value === undefined) return '';
            if (typeof value === 'string' && value.startsWith('#')) return value;
            
            const num = parseFloat(value);
            if (isNaN(num)) return value;
            
            switch (format) {
                case 'number': return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                case 'currency': return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                case 'percent': return (num * 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + '%';
                case 'date':
                    if (num > 0) {
                        const date = new Date((num - 25569) * 86400 * 1000);
                        return date.toLocaleDateString('pt-BR');
                    }
                    return value;
                default: return value;
            }
        }
        
        function updateDependentCells() {
            const sheet = sheets[currentSheet];
            for (const [cellId, data] of Object.entries(sheet.data)) {
                if (data.formula) {
                    const cell = document.querySelector(`.cell[data-id="${cellId}"]`);
                    if (cell) {
                        const result = evaluateFormula(data.formula, cellId);
                        data.value = result;
                        data.display = formatValue(result, sheet.formats[cellId]?.numberFormat);
                        const fillHandle = cell.querySelector('.fill-handle');
                        cell.textContent = data.display;
                        if (fillHandle) cell.appendChild(fillHandle);
                    }
                }
            }
        }
        
        function updateToolbarState(cell) {
            const sheet = sheets[currentSheet];
            const format = sheet.formats[cell.dataset.id] || {};
            
            document.getElementById('boldBtn')?.classList.toggle('active', format.bold);
            document.getElementById('italicBtn')?.classList.toggle('active', format.italic);
            document.getElementById('underlineBtn')?.classList.toggle('active', format.underline);
            document.getElementById('strikeBtn')?.classList.toggle('active', format.strike);
            
            document.getElementById('fontFamily').value = format.fontFamily || 'Arial';
            document.getElementById('fontSize').value = format.fontSize || '12';
            document.getElementById('textColor').value = format.color || '#000000';
            document.getElementById('bgColor').value = format.backgroundColor || '#ffffff';
            document.getElementById('numberFormat').value = format.numberFormat || 'general';
        }
        
        function applyFormat(type, value) {
            if (!selectedCell) return;
            saveUndo();
            
            const cells = getSelectedCells();
            const sheet = sheets[currentSheet];
            
            cells.forEach(cell => {
                const cellId = cell.dataset.id;
                if (!sheet.formats[cellId]) sheet.formats[cellId] = {};
                
                switch (type) {
                    case 'bold': sheet.formats[cellId].bold = !sheet.formats[cellId].bold; break;
                    case 'italic': sheet.formats[cellId].italic = !sheet.formats[cellId].italic; break;
                    case 'underline': sheet.formats[cellId].underline = !sheet.formats[cellId].underline; break;
                    case 'strike': sheet.formats[cellId].strike = !sheet.formats[cellId].strike; break;
                    case 'border': sheet.formats[cellId].border = !sheet.formats[cellId].border; break;
                    case 'numberFormat':
                        sheet.formats[cellId].numberFormat = value;
                        if (sheet.data[cellId]) {
                            sheet.data[cellId].display = formatValue(sheet.data[cellId].value, value);
                            const fillHandle = cell.querySelector('.fill-handle');
                            cell.textContent = sheet.data[cellId].display;
                            if (fillHandle) cell.appendChild(fillHandle);
                        }
                        break;
                    default:
                        sheet.formats[cellId][type] = value;
                }
                
                applyFormatToCell(cell, sheet.formats[cellId]);
            });
            
            updateToolbarState(selectedCell);
        }
        
        function getSelectedCells() {
            const selected = document.querySelectorAll('.cell.selected, .cell.range-selected');
            return selected.length > 0 ? Array.from(selected) : (selectedCell ? [selectedCell] : []);
        }
        
        // Clipboard operations
        function copyCell() {
            const cells = getSelectedCells();
            if (cells.length === 0) return;
            
            clipboard = cells.map(cell => ({
                id: cell.dataset.id,
                row: parseInt(cell.dataset.row),
                col: parseInt(cell.dataset.col),
                data: JSON.parse(JSON.stringify(sheets[currentSheet].data[cell.dataset.id] || {})),
                format: JSON.parse(JSON.stringify(sheets[currentSheet].formats[cell.dataset.id] || {}))
            }));
            clipboardMode = 'copy';
            updateStatus('Copiado!');
        }
        
        function cutCell() {
            copyCell();
            clipboardMode = 'cut';
            updateStatus('Recortado!');
        }
        
        function pasteCell() {
            if (!clipboard || !selectedCell) return;
            saveUndo();
            
            const targetRow = parseInt(selectedCell.dataset.row);
            const targetCol = parseInt(selectedCell.dataset.col);
            const minRow = Math.min(...clipboard.map(c => c.row));
            const minCol = Math.min(...clipboard.map(c => c.col));
            const sheet = sheets[currentSheet];
            
            clipboard.forEach(item => {
                const newRow = targetRow + (item.row - minRow);
                const newCol = targetCol + (item.col - minCol);
                
                if (newRow < ROWS && newCol < COLS) {
                    const newCellId = getColName(newCol) + (newRow + 1);
                    const cell = document.querySelector(`.cell[data-id="${newCellId}"]`);
                    
                    if (cell) {
                        if (Object.keys(item.data).length > 0) {
                            sheet.data[newCellId] = JSON.parse(JSON.stringify(item.data));
                            const fillHandle = cell.querySelector('.fill-handle');
                            cell.textContent = item.data.display || item.data.value || '';
                            if (fillHandle) cell.appendChild(fillHandle);
                        }
                        if (Object.keys(item.format).length > 0) {
                            sheet.formats[newCellId] = JSON.parse(JSON.stringify(item.format));
                            applyFormatToCell(cell, sheet.formats[newCellId]);
                        }
                    }
                }
            });
            
            if (clipboardMode === 'cut') {
                clipboard.forEach(item => {
                    const cell = document.querySelector(`.cell[data-id="${item.id}"]`);
                    if (cell) {
                        delete sheet.data[item.id];
                        delete sheet.formats[item.id];
                        const fillHandle = cell.querySelector('.fill-handle');
                        cell.textContent = '';
                        cell.removeAttribute('style');
                        if (fillHandle) cell.appendChild(fillHandle);
                    }
                });
                clipboard = null;
            }
            
            updateDependentCells();
            updateStatus('Colado!');
        }
        
        function clearCell() {
            if (!selectedCell) return;
            saveUndo();
            
            const cells = getSelectedCells();
            const sheet = sheets[currentSheet];
            
            cells.forEach(cell => {
                delete sheet.data[cell.dataset.id];
                const fillHandle = cell.querySelector('.fill-handle');
                cell.textContent = '';
                if (fillHandle) cell.appendChild(fillHandle);
            });
            
            updateDependentCells();
            hideContextMenu();
        }
        
        function clearFormat() {
            if (!selectedCell) return;
            saveUndo();
            
            const cells = getSelectedCells();
            const sheet = sheets[currentSheet];
            
            cells.forEach(cell => {
                delete sheet.formats[cell.dataset.id];
                cell.removeAttribute('style');
            });
            
            hideContextMenu();
        }
        
        // Row/Column operations
        function insertRow() {
            if (!selectedCell) return;
            saveUndo();
            
            const row = parseInt(selectedCell.dataset.row);
            const sheet = sheets[currentSheet];
            
            for (let r = ROWS - 1; r > row; r--) {
                for (let c = 0; c < COLS; c++) {
                    const oldId = getColName(c) + r;
                    const newId = getColName(c) + (r + 1);
                    if (sheet.data[oldId]) { sheet.data[newId] = sheet.data[oldId]; delete sheet.data[oldId]; }
                    if (sheet.formats[oldId]) { sheet.formats[newId] = sheet.formats[oldId]; delete sheet.formats[oldId]; }
                }
            }
            
            loadSheetData();
            hideContextMenu();
            updateStatus('Linha inserida');
        }
        
        function insertColumn() {
            if (!selectedCell) return;
            saveUndo();
            
            const col = parseInt(selectedCell.dataset.col);
            const sheet = sheets[currentSheet];
            
            for (let c = COLS - 1; c > col; c--) {
                for (let r = 1; r <= ROWS; r++) {
                    const oldId = getColName(c - 1) + r;
                    const newId = getColName(c) + r;
                    if (sheet.data[oldId]) { sheet.data[newId] = sheet.data[oldId]; delete sheet.data[oldId]; }
                    if (sheet.formats[oldId]) { sheet.formats[newId] = sheet.formats[oldId]; delete sheet.formats[oldId]; }
                }
            }
            
            loadSheetData();
            hideContextMenu();
            updateStatus('Coluna inserida');
        }
        
        function deleteRow() {
            if (!selectedCell) return;
            saveUndo();
            
            const row = parseInt(selectedCell.dataset.row) + 1;
            const sheet = sheets[currentSheet];
            
            for (let c = 0; c < COLS; c++) {
                delete sheet.data[getColName(c) + row];
                delete sheet.formats[getColName(c) + row];
            }
            
            for (let r = row; r < ROWS; r++) {
                for (let c = 0; c < COLS; c++) {
                    const oldId = getColName(c) + (r + 1);
                    const newId = getColName(c) + r;
                    if (sheet.data[oldId]) { sheet.data[newId] = sheet.data[oldId]; delete sheet.data[oldId]; }
                    if (sheet.formats[oldId]) { sheet.formats[newId] = sheet.formats[oldId]; delete sheet.formats[oldId]; }
                }
            }
            
            loadSheetData();
            hideContextMenu();
            updateStatus('Linha excluída');
        }
        
        function deleteColumn() {
            if (!selectedCell) return;
            saveUndo();
            
            const col = parseInt(selectedCell.dataset.col);
            const sheet = sheets[currentSheet];
            
            for (let r = 1; r <= ROWS; r++) {
                delete sheet.data[getColName(col) + r];
                delete sheet.formats[getColName(col) + r];
            }
            
            for (let c = col; c < COLS - 1; c++) {
                for (let r = 1; r <= ROWS; r++) {
                    const oldId = getColName(c + 1) + r;
                    const newId = getColName(c) + r;
                    if (sheet.data[oldId]) { sheet.data[newId] = sheet.data[oldId]; delete sheet.data[oldId]; }
                    if (sheet.formats[oldId]) { sheet.formats[newId] = sheet.formats[oldId]; delete sheet.formats[oldId]; }
                }
            }
            
            loadSheetData();
            hideContextMenu();
            updateStatus('Coluna excluída');
        }
        
        function hideRow() {
            if (!selectedCell) return;
            const sheet = sheets[currentSheet];
            if (!sheet.hiddenRows) sheet.hiddenRows = new Set();
            sheet.hiddenRows.add(parseInt(selectedCell.dataset.row));
            createCells();
            hideContextMenu();
        }
        
        function hideColumn() {
            if (!selectedCell) return;
            const sheet = sheets[currentSheet];
            if (!sheet.hiddenCols) sheet.hiddenCols = new Set();
            sheet.hiddenCols.add(parseInt(selectedCell.dataset.col));
            createHeaders();
            createCells();
            hideContextMenu();
        }
        
        // Context menu
        function showContextMenu(x, y) {
            const menu = document.getElementById('contextMenu');
            menu.style.left = Math.min(x, window.innerWidth - 200) + 'px';
            menu.style.top = Math.min(y, window.innerHeight - 300) + 'px';
            menu.classList.remove('hidden');
        }
        
        function hideContextMenu() {
            document.getElementById('contextMenu').classList.add('hidden');
        }
        
        // Undo/Redo
        function saveUndo() {
            const state = JSON.stringify(sheets.map(s => ({
                ...s,
                hiddenRows: Array.from(s.hiddenRows || []),
                hiddenCols: Array.from(s.hiddenCols || [])
            })));
            undoStack.push(state);
            if (undoStack.length > 50) undoStack.shift();
            redoStack = [];
        }
        
        function undo() {
            if (undoStack.length === 0) return;
            const current = JSON.stringify(sheets.map(s => ({
                ...s,
                hiddenRows: Array.from(s.hiddenRows || []),
                hiddenCols: Array.from(s.hiddenCols || [])
            })));
            redoStack.push(current);
            
            const state = JSON.parse(undoStack.pop());
            sheets = state.map(s => ({
                ...s,
                hiddenRows: new Set(s.hiddenRows || []),
                hiddenCols: new Set(s.hiddenCols || [])
            }));
            loadSheetData();
            updateStatus('Desfeito');
        }
        
        function redo() {
            if (redoStack.length === 0) return;
            const current = JSON.stringify(sheets.map(s => ({
                ...s,
                hiddenRows: Array.from(s.hiddenRows || []),
                hiddenCols: Array.from(s.hiddenCols || [])
            })));
            undoStack.push(current);
            
            const state = JSON.parse(redoStack.pop());
            sheets = state.map(s => ({
                ...s,
                hiddenRows: new Set(s.hiddenRows || []),
                hiddenCols: new Set(s.hiddenCols || [])
            }));
            loadSheetData();
            updateStatus('Refeito');
        }
        
        // Sheet management
        function addSheet() {
            const newIndex = sheets.length;
            const t = excelI18n[currentLang] || excelI18n.pt;
            const baseName = currentLang === 'en' ? 'Sheet' : 'Planilha';
            sheets.push({
                name: `${baseName}${newIndex + 1}`,
                data: {},
                formats: {},
                comments: {},
                colWidths: {},
                rowHeights: {},
                hiddenRows: new Set(),
                hiddenCols: new Set(),
                frozenRow: 0,
                frozenCol: 0,
                conditionalFormats: [],
                filters: {}
            });
            renderSheetTabs();
            switchSheet(newIndex);
        }
        
        function switchSheet(index) {
            currentSheet = index;
            renderSheetTabs();
            createHeaders();
            createCells();
            selectCell(document.querySelector('.cell'));
        }
        
        function renameSheet(index) {
            const t = excelI18n[currentLang] || excelI18n.pt;
            const newName = prompt(t.prompt_sheet_name, sheets[index].name);
            if (newName?.trim()) {
                sheets[index].name = newName.trim();
                renderSheetTabs();
            }
        }
        
        function deleteSheet(index) {
            if (sheets.length <= 1) return;
            sheets.splice(index, 1);
            if (currentSheet >= sheets.length) currentSheet = sheets.length - 1;
            renderSheetTabs();
            switchSheet(currentSheet);
        }
        
        // Fill handle
        function startFill(e) {
            e.preventDefault();
            e.stopPropagation();
            isFilling = true;
            fillStart = e.target.closest('.cell');
        }
        
        function highlightFillRange(start, end) {
            document.querySelectorAll('.cell.fill-highlight').forEach(c => c.classList.remove('fill-highlight'));
            
            const startRow = parseInt(start.dataset.row);
            const endRow = parseInt(end.dataset.row);
            const startCol = parseInt(start.dataset.col);
            const endCol = parseInt(end.dataset.col);
            
            const minRow = Math.min(startRow, endRow);
            const maxRow = Math.max(startRow, endRow);
            const minCol = Math.min(startCol, endCol);
            const maxCol = Math.max(startCol, endCol);
            
            for (let r = minRow; r <= maxRow; r++) {
                for (let c = minCol; c <= maxCol; c++) {
                    const cell = document.querySelector(`.cell[data-row="${r}"][data-col="${c}"]`);
                    if (cell) cell.classList.add('range-selected');
                }
            }
        }
        
        function performFill(start, end) {
            saveUndo();
            const sheet = sheets[currentSheet];
            
            const startRow = parseInt(start.dataset.row);
            const endRow = parseInt(end.dataset.row);
            const startCol = parseInt(start.dataset.col);
            const endCol = parseInt(end.dataset.col);
            
            const sourceData = sheet.data[start.dataset.id];
            const sourceFormat = sheet.formats[start.dataset.id];
            
            if (!sourceData) return;
            
            const minRow = Math.min(startRow, endRow);
            const maxRow = Math.max(startRow, endRow);
            const minCol = Math.min(startCol, endCol);
            const maxCol = Math.max(startCol, endCol);
            
            let sequence = 1;
            const numMatch = String(sourceData.value).match(/(\d+)$/);
            const baseNum = numMatch ? parseInt(numMatch[1]) : null;
            const baseText = numMatch ? String(sourceData.value).replace(/\d+$/, '') : String(sourceData.value);
            
            for (let r = minRow; r <= maxRow; r++) {
                for (let c = minCol; c <= maxCol; c++) {
                    if (r === startRow && c === startCol) continue;
                    
                    const cellId = getColName(c) + (r + 1);
                    const cell = document.querySelector(`.cell[data-id="${cellId}"]`);
                    
                    if (cell) {
                        let newValue;
                        if (sourceData.formula) {
                            // Adjust formula references
                            newValue = adjustFormulaReferences(sourceData.formula, r - startRow, c - startCol);
                            const result = evaluateFormula(newValue, cellId);
                            sheet.data[cellId] = { formula: newValue, value: result, display: formatValue(result, sourceFormat?.numberFormat) };
                        } else if (baseNum !== null) {
                            newValue = baseText + (baseNum + sequence);
                            sheet.data[cellId] = { value: newValue, display: formatValue(newValue, sourceFormat?.numberFormat) };
                            sequence++;
                        } else {
                            sheet.data[cellId] = JSON.parse(JSON.stringify(sourceData));
                        }
                        
                        if (sourceFormat) {
                            sheet.formats[cellId] = JSON.parse(JSON.stringify(sourceFormat));
                        }
                        
                        const fillHandle = cell.querySelector('.fill-handle');
                        cell.textContent = sheet.data[cellId].display || sheet.data[cellId].value;
                        if (fillHandle) cell.appendChild(fillHandle);
                        if (sourceFormat) applyFormatToCell(cell, sourceFormat);
                    }
                }
            }
            
            updateDependentCells();
        }
        
        function adjustFormulaReferences(formula, rowDelta, colDelta) {
            return formula.replace(/([A-Z]+)(\d+)/gi, (match, col, row) => {
                const newCol = getColName(getColIndex(col.toUpperCase()) + colDelta);
                const newRow = parseInt(row) + rowDelta;
                return newCol + newRow;
            });
        }
        
        // Column resize
        function startResize(e, col) {
            e.preventDefault();
            e.stopPropagation();
            isResizing = true;
            resizeCol = col;
            resizeStartX = e.clientX;
        }
        
        // Comments
        function addComment() {
            if (!selectedCell) return;
            const sheet = sheets[currentSheet];
            const cellId = selectedCell.dataset.id;
            
            const t = excelI18n[currentLang] || excelI18n.pt;
            const currentComment = sheet.comments?.[cellId] || '';
            const comment = prompt(t.prompt_comment, currentComment);
            
            if (comment !== null) {
                if (!sheet.comments) sheet.comments = {};
                if (comment.trim()) {
                    sheet.comments[cellId] = comment;
                    selectedCell.classList.add('has-comment');
                } else {
                    delete sheet.comments[cellId];
                    selectedCell.classList.remove('has-comment');
                }
            }
            hideContextMenu();
        }
        
        function showCommentTooltip(cell, e) {
            const sheet = sheets[currentSheet];
            const comment = sheet.comments?.[cell.dataset.id];
            if (!comment) return;
            
            const tooltip = document.getElementById('commentTooltip');
            tooltip.textContent = comment;
            tooltip.style.left = (e.clientX + 10) + 'px';
            tooltip.style.top = (e.clientY + 10) + 'px';
            tooltip.classList.remove('hidden');
        }
        
        function hideCommentTooltip() {
            document.getElementById('commentTooltip').classList.add('hidden');
        }
        
        // Freeze panes
        function toggleFreeze() {
            if (!selectedCell) return;
            const sheet = sheets[currentSheet];
            
            if (sheet.frozenRow || sheet.frozenCol) {
                sheet.frozenRow = 0;
                sheet.frozenCol = 0;
                updateStatus('Painéis descongelados');
            } else {
                sheet.frozenRow = parseInt(selectedCell.dataset.row);
                sheet.frozenCol = parseInt(selectedCell.dataset.col);
                updateStatus(`Painéis congelados em ${selectedCell.dataset.id}`);
            }
            
            createCells();
        }
        
        // Sorting
        function sortAsc() { sortColumn(true); }
        function sortDesc() { sortColumn(false); }
        
        function sortColumn(ascending) {
            if (!selectedCell) return;
            saveUndo();
            
            const col = parseInt(selectedCell.dataset.col);
            const sheet = sheets[currentSheet];
            
            // Collect all data in the column
            const rows = [];
            for (let r = 0; r < ROWS; r++) {
                const cellId = getColName(col) + (r + 1);
                rows.push({
                    row: r,
                    value: sheet.data[cellId]?.value ?? '',
                    numValue: parseFloat(sheet.data[cellId]?.value) || 0
                });
            }
            
            // Sort
            rows.sort((a, b) => {
                const aNum = !isNaN(parseFloat(a.value));
                const bNum = !isNaN(parseFloat(b.value));
                
                if (aNum && bNum) {
                    return ascending ? a.numValue - b.numValue : b.numValue - a.numValue;
                }
                return ascending ? 
                    String(a.value).localeCompare(String(b.value)) :
                    String(b.value).localeCompare(String(a.value));
            });
            
            // Rebuild data for all columns based on new row order
            const newData = {};
            const newFormats = {};
            const newComments = {};
            
            rows.forEach((item, newRow) => {
                for (let c = 0; c < COLS; c++) {
                    const oldId = getColName(c) + (item.row + 1);
                    const newId = getColName(c) + (newRow + 1);
                    
                    if (sheet.data[oldId]) newData[newId] = sheet.data[oldId];
                    if (sheet.formats[oldId]) newFormats[newId] = sheet.formats[oldId];
                    if (sheet.comments?.[oldId]) newComments[newId] = sheet.comments[oldId];
                }
            });
            
            sheet.data = newData;
            sheet.formats = newFormats;
            sheet.comments = newComments;
            
            loadSheetData();
            updateStatus(`Ordenado ${ascending ? 'A-Z' : 'Z-A'}`);
        }
        
        // Filtering
        function toggleFilter() {
            const headers = document.querySelectorAll('.header-cell');
            headers.forEach(h => h.classList.toggle('filtered'));
            updateStatus('Filtros ativados');
        }
        
        function showFilterDropdown(e, col) {
            e.stopPropagation();
            
            document.querySelectorAll('.filter-dropdown').forEach(d => d.remove());
            
            const sheet = sheets[currentSheet];
            const values = new Set();
            
            for (let r = 0; r < ROWS; r++) {
                const cellId = getColName(col) + (r + 1);
                const val = sheet.data[cellId]?.value;
                if (val) values.add(val);
            }
            
            const dropdown = document.createElement('div');
            dropdown.className = 'filter-dropdown';
            dropdown.innerHTML = `
                <div class="mb-2"><strong>Filtrar ${getColName(col)}</strong></div>
                <div class="mb-2">
                    <input type="text" placeholder="Buscar..." class="w-full border rounded px-2 py-1 text-sm" id="filterSearch">
                </div>
                <div style="max-height: 200px; overflow: auto;">
                    <label class="block text-sm"><input type="checkbox" checked onchange="toggleAllFilter(${col}, this.checked)"> (Selecionar tudo)</label>
                    ${Array.from(values).slice(0, 50).map(v => 
                        `<label class="block text-sm"><input type="checkbox" checked value="${v}" class="filter-value"> ${v}</label>`
                    ).join('')}
                </div>
                <div class="mt-2 flex gap-2">
                    <button onclick="applyFilter(${col})" class="bg-green-600 text-white px-3 py-1 rounded text-sm">OK</button>
                    <button onclick="clearFilter(${col})" class="bg-gray-300 px-3 py-1 rounded text-sm">Limpar</button>
                </div>
            `;
            
            dropdown.style.left = e.target.getBoundingClientRect().left + 'px';
            dropdown.style.top = e.target.getBoundingClientRect().bottom + 'px';
            
            document.body.appendChild(dropdown);
        }
        
        function toggleAllFilter(col, checked) {
            document.querySelectorAll('.filter-value').forEach(cb => cb.checked = checked);
        }
        
        function applyFilter(col) {
            const sheet = sheets[currentSheet];
            const checkedValues = new Set();
            
            document.querySelectorAll('.filter-value:checked').forEach(cb => {
                checkedValues.add(cb.value);
            });
            
            sheet.hiddenRows = new Set();
            
            for (let r = 0; r < ROWS; r++) {
                const cellId = getColName(col) + (r + 1);
                const val = sheet.data[cellId]?.value;
                if (val && !checkedValues.has(String(val))) {
                    sheet.hiddenRows.add(r);
                }
            }
            
            createCells();
            document.querySelectorAll('.filter-dropdown').forEach(d => d.remove());
            updateStatus('Filtro aplicado');
        }
        
        function clearFilter(col) {
            const sheet = sheets[currentSheet];
            sheet.hiddenRows = new Set();
            createCells();
            document.querySelectorAll('.filter-dropdown').forEach(d => d.remove());
            const t = excelI18n[currentLang] || excelI18n.pt;
            updateStatus(t.status_filter_cleared);
        }
        
        // Conditional formatting
        function showConditionalFormat() {
            const t = excelI18n[currentLang] || excelI18n.pt;
            const range = selectedRange.start && selectedRange.end ? 
                `${selectedRange.start.dataset.id}:${selectedRange.end.dataset.id}` : 
                selectedCell?.dataset.id || 'A1:A10';
            
            const html = `
                <div class="modal-overlay" onclick="closeModal()"></div>
                <div class="modal">
                    <h3 class="text-lg font-bold mb-4">${t.cf_title}</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm mb-1">${t.cf_range}</label>
                            <input type="text" id="cfRange" value="${range}" class="w-full border rounded px-2 py-1">
                        </div>
                        <div>
                            <label class="block text-sm mb-1">${t.cf_condition}</label>
                            <select id="cfCondition" class="w-full border rounded px-2 py-1">
                                <option value="greater">${t.cf_greater}</option>
                                <option value="less">${t.cf_less}</option>
                                <option value="equal">${t.cf_equal}</option>
                                <option value="contains">${t.cf_contains}</option>
                                <option value="empty">${t.cf_empty}</option>
                                <option value="notEmpty">${t.cf_not_empty}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">${t.cf_value}</label>
                            <input type="text" id="cfValue" class="w-full border rounded px-2 py-1">
                        </div>
                        <div class="flex gap-4">
                            <div>
                                <label class="block text-sm mb-1">${t.cf_bg_color}</label>
                                <input type="color" id="cfBgColor" value="#ffcccc">
                            </div>
                            <div>
                                <label class="block text-sm mb-1">${t.cf_text_color}</label>
                                <input type="color" id="cfTextColor" value="#cc0000">
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2 justify-end">
                        <button onclick="closeModal()" class="px-4 py-2 border rounded">${t.cf_cancel}</button>
                        <button onclick="addConditionalFormat()" class="px-4 py-2 bg-green-600 text-white rounded">${t.cf_apply}</button>
                    </div>
                </div>
            `;
            
            const modal = document.createElement('div');
            modal.id = 'modalContainer';
            modal.innerHTML = html;
            document.body.appendChild(modal);
        }
        
        function addConditionalFormat() {
            const sheet = sheets[currentSheet];
            if (!sheet.conditionalFormats) sheet.conditionalFormats = [];
            
            sheet.conditionalFormats.push({
                range: document.getElementById('cfRange').value,
                condition: document.getElementById('cfCondition').value,
                value: document.getElementById('cfValue').value,
                bgColor: document.getElementById('cfBgColor').value,
                textColor: document.getElementById('cfTextColor').value
            });
            
            loadSheetData();
            closeModal();
            const t = excelI18n[currentLang] || excelI18n.pt;
            updateStatus(t.cf_applied);
        }
        
        function closeModal() {
            document.getElementById('modalContainer')?.remove();
        }
        
        // Find and Replace
        function showFindReplace() {
            const t = excelI18n[currentLang] || excelI18n.pt;
            const html = `
                <div class="modal-overlay" onclick="closeModal()"></div>
                <div class="modal">
                    <h3 class="text-lg font-bold mb-4">${t.fr_title}</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm mb-1">${t.fr_find}</label>
                            <input type="text" id="findText" class="w-full border rounded px-2 py-1" autofocus>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">${t.fr_replace}</label>
                            <input type="text" id="replaceText" class="w-full border rounded px-2 py-1">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="matchCase">
                            <label for="matchCase" class="text-sm">${t.fr_match_case}</label>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2 justify-end">
                        <button onclick="findNext()" class="px-4 py-2 border rounded">${t.fr_next}</button>
                        <button onclick="replaceOne()" class="px-4 py-2 border rounded">${t.fr_replace_btn}</button>
                        <button onclick="replaceAll()" class="px-4 py-2 bg-green-600 text-white rounded">${t.fr_replace_all}</button>
                    </div>
                </div>
            `;
            
            const modal = document.createElement('div');
            modal.id = 'modalContainer';
            modal.innerHTML = html;
            document.body.appendChild(modal);
        }
        
        let findIndex = 0;
        let foundCells = [];
        
        function findNext() {
            const findText = document.getElementById('findText').value;
            const matchCase = document.getElementById('matchCase').checked;
            const sheet = sheets[currentSheet];
            
            foundCells = [];
            const searchText = matchCase ? findText : findText.toLowerCase();
            
            for (const [cellId, data] of Object.entries(sheet.data)) {
                const value = matchCase ? String(data.value) : String(data.value).toLowerCase();
                if (value.includes(searchText)) {
                    foundCells.push(cellId);
                }
            }
            
            if (foundCells.length > 0) {
                findIndex = (findIndex + 1) % foundCells.length;
                const cell = document.querySelector(`.cell[data-id="${foundCells[findIndex]}"]`);
                if (cell) {
                    selectCell(cell);
                    cell.scrollIntoView({ block: 'center', inline: 'center' });
                }
                updateStatus(`${findIndex + 1} / ${foundCells.length}`);
            } else {
                const t = excelI18n[currentLang] || excelI18n.pt;
                updateStatus(currentLang === 'en' ? 'Not found' : 'Não encontrado');
            }
        }
        
        function replaceOne() {
            if (foundCells.length === 0) findNext();
            if (foundCells.length === 0) return;
            
            saveUndo();
            const sheet = sheets[currentSheet];
            const cellId = foundCells[findIndex];
            const findText = document.getElementById('findText').value;
            const replaceText = document.getElementById('replaceText').value;
            
            if (sheet.data[cellId]) {
                sheet.data[cellId].value = String(sheet.data[cellId].value).replace(findText, replaceText);
                sheet.data[cellId].display = sheet.data[cellId].value;
            }
            
            loadSheetData();
            findNext();
        }
        
        function replaceAll() {
            const findText = document.getElementById('findText').value;
            const replaceText = document.getElementById('replaceText').value;
            const matchCase = document.getElementById('matchCase').checked;
            const sheet = sheets[currentSheet];
            
            saveUndo();
            let count = 0;
            
            for (const [cellId, data] of Object.entries(sheet.data)) {
                const regex = new RegExp(findText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), matchCase ? 'g' : 'gi');
                if (regex.test(String(data.value))) {
                    data.value = String(data.value).replace(regex, replaceText);
                    data.display = data.value;
                    count++;
                }
            }
            
            loadSheetData();
            const t = excelI18n[currentLang] || excelI18n.pt;
            updateStatus(`${count} ${t.fr_replaced_count}`);
        }
        
        // Chart
        function showChartModal() {
            const t = excelI18n[currentLang] || excelI18n.pt;
            const range = selectedRange.start && selectedRange.end ? 
                `${selectedRange.start.dataset.id}:${selectedRange.end.dataset.id}` : 'A1:B10';
            
            const html = `
                <div class="modal-overlay" onclick="closeModal()"></div>
                <div class="modal" style="min-width: 600px;">
                    <h3 class="text-lg font-bold mb-4">${t.chart_title_modal}</h3>
                    <div class="flex gap-4">
                        <div class="w-1/3 space-y-3">
                            <div>
                                <label class="block text-sm mb-1">${t.chart_data_range}</label>
                                <input type="text" id="chartRange" value="${range}" class="w-full border rounded px-2 py-1">
                            </div>
                            <div>
                                <label class="block text-sm mb-1">${t.chart_type}</label>
                                <select id="chartType" class="w-full border rounded px-2 py-1" onchange="previewChart()">
                                    <option value="bar">${t.chart_bar}</option>
                                    <option value="line">${t.chart_line}</option>
                                    <option value="pie">${t.chart_pie}</option>
                                    <option value="doughnut">${t.chart_doughnut}</option>
                                    <option value="radar">${t.chart_radar}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">${t.chart_input_title}</label>
                                <input type="text" id="chartTitle" value="${t.chart_default_title}" class="w-full border rounded px-2 py-1" onchange="previewChart()">
                            </div>
                        </div>
                        <div class="w-2/3">
                            <canvas id="chartPreview" width="350" height="250"></canvas>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2 justify-end">
                        <button onclick="closeModal()" class="px-4 py-2 border rounded">${t.cf_cancel}</button>
                        <button onclick="downloadChart()" class="px-4 py-2 bg-blue-600 text-white rounded">${t.chart_download}</button>
                    </div>
                </div>
            `;
            
            const modal = document.createElement('div');
            modal.id = 'modalContainer';
            modal.innerHTML = html;
            document.body.appendChild(modal);
            
            setTimeout(previewChart, 100);
        }
        
        let currentChart = null;
        
        function previewChart() {
            const range = document.getElementById('chartRange').value;
            const type = document.getElementById('chartType').value;
            const title = document.getElementById('chartTitle').value;
            
            const values = getRangeValues(range);
            const labels = values.map((_, i) => `Item ${i + 1}`);
            
            const ctx = document.getElementById('chartPreview').getContext('2d');
            
            if (currentChart) currentChart.destroy();
            
            const colors = [
                '#4CAF50', '#2196F3', '#FF9800', '#E91E63', '#9C27B0',
                '#00BCD4', '#FFEB3B', '#795548', '#607D8B', '#F44336'
            ];
            
            currentChart = new Chart(ctx, {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: title,
                        data: values.map(v => parseFloat(v) || 0),
                        backgroundColor: colors,
                        borderColor: type === 'line' ? '#2196F3' : colors,
                        borderWidth: 2,
                        fill: type === 'line' ? false : true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: title },
                        legend: { display: type === 'pie' || type === 'doughnut' }
                    }
                }
            });
        }
        
        function downloadChart() {
            const canvas = document.getElementById('chartPreview');
            const link = document.createElement('a');
            link.download = 'grafico.png';
            link.href = canvas.toDataURL();
            link.click();
            updateStatus('Gráfico baixado');
        }
        
        // CSV Import/Export
        function importCSV() {
            document.getElementById('csvFileInput').click();
        }
        
        function handleCSVImport(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = (e) => {
                saveUndo();
                const sheet = sheets[currentSheet];
                const lines = e.target.result.split('\n');
                
                lines.forEach((line, row) => {
                    const cells = line.split(/[,;]/);
                    cells.forEach((value, col) => {
                        if (value.trim()) {
                            const cellId = getColName(col) + (row + 1);
                            sheet.data[cellId] = { value: value.trim(), display: value.trim() };
                        }
                    });
                });
                
                loadSheetData();
                updateStatus('CSV importado');
            };
            reader.readAsText(file);
            event.target.value = '';
        }
        
        function exportCSV() {
            const sheet = sheets[currentSheet];
            let csv = '';
            
            for (let r = 0; r < ROWS; r++) {
                const row = [];
                let hasData = false;
                
                for (let c = 0; c < COLS; c++) {
                    const cellId = getColName(c) + (r + 1);
                    const value = sheet.data[cellId]?.value || '';
                    row.push(value);
                    if (value) hasData = true;
                }
                
                if (hasData) csv += row.join(';') + '\n';
            }
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = document.getElementById('fileName').value + '.csv';
            link.click();
            
            updateStatus('CSV exportado');
        }
        
        // Local Storage
        function saveToLocalStorage() {
            const data = {
                fileName: document.getElementById('fileName').value,
                sheets: sheets.map(s => ({
                    ...s,
                    hiddenRows: Array.from(s.hiddenRows || []),
                    hiddenCols: Array.from(s.hiddenCols || [])
                })),
                darkMode: darkMode
            };
            localStorage.setItem('excelCloneData', JSON.stringify(data));
            const t = excelI18n[currentLang] || excelI18n.pt;
            updateStatus(t.status_saved);
        }
        
        function loadFromLocalStorage(silent = false) {
            const saved = localStorage.getItem('excelCloneData');
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    document.getElementById('fileName').value = data.fileName || 'Pasta1';
                    sheets = data.sheets.map(s => ({
                        ...s,
                        hiddenRows: new Set(s.hiddenRows || []),
                        hiddenCols: new Set(s.hiddenCols || [])
                    }));
                    if (data.darkMode) toggleDarkMode();
                    if (!silent) {
                        renderSheetTabs();
                        createHeaders();
                        createCells();
                        const t = excelI18n[currentLang] || excelI18n.pt;
                        updateStatus(t.status_loaded);
                    }
                } catch (e) {
                    console.error('Error loading:', e);
                }
            }
        }
        
        // Dark Mode
        function toggleDarkMode() {
            darkMode = !darkMode;
            document.body.classList.toggle('dark-mode', darkMode);
            const t = excelI18n[currentLang] || excelI18n.pt;
            document.getElementById('darkModeBtn').textContent = darkMode ? t.btn_light_mode : t.btn_dark_mode;
        }
        
        // Quick function insert
        function insertQuickFunction(func) {
            if (!selectedCell || !func) return;

            // Define modo de edição de fórmula com seleção de intervalo
            formulaEdit = { funcName: func, baseCellId: selectedCell.dataset.id };
            formulaEditRangeStart = null;

            // Sugere intervalo automático: por padrão, a célula imediatamente acima
            const row = parseInt(selectedCell.dataset.row);
            const col = parseInt(selectedCell.dataset.col);
            let suggestedFormula = '';

            if (row > 0) {
                const startRef = getColName(col) + '1';
                const endRef = getColName(col) + (row);
                suggestedFormula = `=${func}(${startRef}:${endRef})`;
            } else {
                suggestedFormula = `=${func}()`;
            }

            const fillHandle = selectedCell.querySelector('.fill-handle');
            selectedCell.textContent = suggestedFormula;
            if (fillHandle) selectedCell.appendChild(fillHandle);
            document.getElementById('formulaBar').value = suggestedFormula;
            selectedCell.focus();
        }
        
        // Status
        function updateStatus(message) {
            document.getElementById('statusBar').textContent = message;
            setTimeout(() => updateStatusBar(), 3000);
        }
        
        function updateStatusBar() {
            const t = excelI18n[currentLang] || excelI18n.pt;
            const cells = getSelectedCells();
            if (cells.length > 1) {
                const values = cells.map(c => {
                    const data = sheets[currentSheet].data[c.dataset.id];
                    return parseFloat(data?.value);
                }).filter(v => !isNaN(v));
                
                if (values.length > 0) {
                    const sum = values.reduce((a, b) => a + b, 0);
                    const avg = sum / values.length;
                    document.getElementById('statusBar').textContent = 
                        `${t.stat_avg}: ${avg.toFixed(2)} | ${t.stat_count}: ${values.length} | ${t.stat_sum}: ${sum.toFixed(2)}`;
                    return;
                }
            }
            document.getElementById('statusBar').textContent = t.status_ready;
        }
        
        // Initialize
        init();
    </script>
    <footer class="footer-clean py-8 text-center text-gray-500/50">
        <p class="text-[10px] uppercase tracking-[0.2em] opacity-50" data-i18n="footer_rights">&copy; 2026 4U.IA.BR - Todos os direitos reservados</p>
    </footer>
</body>
</html>
