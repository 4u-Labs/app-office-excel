/**
 * ExcelClone — Módulo de Inteligência Artificial Frontend
 * 1. Prompt-to-Formula: Assistente de Fórmulas Inteligentes
 * 2. Prompt-to-Sheet: Gerador de Planilhas Completas
 * 3. Data Analyst Copilot: Insights, Auditoria e Recomendação de Gráficos
 */

const ExcelAi = {
    isProcessing: false,

    // Obtém o token do usuário autenticado (SSO 4U)
    getAuthToken() {
        if (window.GoogleAuth && window.GoogleAuth.token) {
            return window.GoogleAuth.token;
        }
        if (window.GoogleAuth && window.GoogleAuth.currentUser && window.GoogleAuth.currentUser.token) {
            return window.GoogleAuth.currentUser.token;
        }
        const storedToken = localStorage.getItem('user_token');
        if (storedToken && !storedToken.startsWith('{')) {
            return storedToken;
        }
        try {
            const stored = localStorage.getItem('user_profile') || localStorage.getItem('excelclone_user_profile');
            if (stored) {
                const u = JSON.parse(stored);
                if (u.token) return u.token;
            }
        } catch (e) {}
        return '';
    },

    handleAuthError() {
        if (window.GoogleAuth && typeof GoogleAuth.signOut === 'function') {
            GoogleAuth.signOut();
        }
        if (window.GoogleAuth && typeof GoogleAuth.openAuthModal === 'function') {
            GoogleAuth.openAuthModal(currentLang === 'en' ? 'Session expired. Please reconnect your Google account.' : 'Sua sessão expirou. Conecte sua Conta Google novamente.');
        } else {
            alert(currentLang === 'en' ? 'Session expired. Please log in with Google again.' : 'Sua sessão expirou. Faça login novamente.');
        }
    },

    // Garante que o usuário está logado e possui créditos
    ensureAuth(actionName, callback) {
        if (!window.GoogleAuth || !GoogleAuth.isLoggedIn()) {
            if (window.GoogleAuth && typeof GoogleAuth.openAuthModal === 'function') {
                GoogleAuth.openAuthModal(actionName || 'Assistente IA', callback);
            } else {
                alert('Faça login com sua Conta Google para usar a Inteligência Artificial.');
            }
            return false;
        }

        if (typeof GoogleAuth.hasCredits === 'function' && !GoogleAuth.hasCredits()) {
            if (typeof GoogleAuth.showCreditsDepletedModal === 'function') {
                GoogleAuth.showCreditsDepletedModal();
            } else {
                alert('Créditos 4U esgotados. Recarregue seus créditos para continuar.');
            }
            return false;
        }

        return true;
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 1. PROMPT-TO-FORMULA
    // ──────────────────────────────────────────────────────────────────────────
    openFormulaModal() {
        const cellId = selectedCell ? selectedCell.dataset.id : 'A1';
        const modal = document.getElementById('aiFormulaModal');
        if (!modal) return;

        document.getElementById('aiFormulaTargetCell').textContent = cellId;
        const input = document.getElementById('aiFormulaPromptInput');
        if (input) {
            input.value = '';
            setTimeout(() => input.focus(), 150);
        }

        // Esconde resultado anterior
        const resultBox = document.getElementById('aiFormulaResultBox');
        if (resultBox) resultBox.classList.add('hidden');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    closeFormulaModal() {
        const modal = document.getElementById('aiFormulaModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },

    async runGenerateFormula() {
        const promptInput = document.getElementById('aiFormulaPromptInput');
        const promptText = promptInput ? promptInput.value.trim() : '';
        if (!promptText) {
            alert(currentLang === 'en' ? 'Please describe the formula you want.' : 'Descreva o que deseja calcular.');
            return;
        }

        if (!this.ensureAuth('Gerador de Fórmulas com IA', () => this.runGenerateFormula())) {
            return;
        }

        const cellId = selectedCell ? selectedCell.dataset.id : 'A1';
        const token = this.getAuthToken();

        // Extrai contexto dos cabeçalhos da planilha atual
        const sheet = sheets[currentSheet];
        const headers = [];
        for (let c = 0; c < 10; c++) {
            const cId = `${getColName(c)}1`;
            const val = sheet.data[cId]?.value || sheet.data[cId]?.display;
            if (val) headers.push(`${getColName(c)}: ${val}`);
        }

        this.setLoader('aiFormulaLoader', true);
        const resultBox = document.getElementById('aiFormulaResultBox');
        if (resultBox) resultBox.classList.add('hidden');

        try {
            const resp = await fetch('api/ai.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    action: 'formula',
                    prompt: promptText,
                    targetCell: cellId,
                    context: { activeHeaders: headers },
                    lang: currentLang
                })
            });

            if (resp.status === 401) {
                this.handleAuthError();
                return;
            }

            const res = await resp.json();
            if (res && res.error && res.error.includes('Token')) {
                this.handleAuthError();
                return;
            }
            if (!res.success) {
                throw new Error(res.error || 'Erro ao gerar fórmula');
            }

            // Desconta crédito visualmente
            if (window.GoogleAuth && typeof GoogleAuth.consumeCredit === 'function') {
                GoogleAuth.consumeCredit(1);
            }

            // Exibe resultado
            document.getElementById('aiFormulaOutput').textContent = res.formula;
            document.getElementById('aiFormulaExplanation').textContent = res.explanation || '';
            if (resultBox) resultBox.classList.remove('hidden');

        } catch (err) {
            alert('Erro: ' + err.message);
        } finally {
            this.setLoader('aiFormulaLoader', false);
        }
    },

    applyFormulaToCell() {
        const formulaEl = document.getElementById('aiFormulaOutput');
        const formula = formulaEl ? formulaEl.textContent.trim() : '';
        if (!formula) return;

        if (selectedCell) {
            selectedCell.textContent = formula;
            saveCell(selectedCell);
            const formulaBar = document.getElementById('formulaBar');
            if (formulaBar) formulaBar.value = formula;
        }

        this.closeFormulaModal();
        updateStatus(currentLang === 'en' ? `✓ Formula ${formula} applied!` : `✓ Fórmula ${formula} inserida com sucesso!`);
    },

    setFormulaChip(text) {
        const input = document.getElementById('aiFormulaPromptInput');
        if (input) {
            input.value = text;
            input.focus();
        }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 2. PROMPT-TO-SHEET (GERADOR DE PLANILHAS COMPLETAS)
    // ──────────────────────────────────────────────────────────────────────────
    openGenerateSheetModal() {
        const modal = document.getElementById('aiSheetModal');
        if (!modal) return;

        const input = document.getElementById('aiSheetPromptInput');
        if (input) {
            input.value = '';
            setTimeout(() => input.focus(), 150);
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    closeGenerateSheetModal() {
        const modal = document.getElementById('aiSheetModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },

    setSheetChip(text) {
        const input = document.getElementById('aiSheetPromptInput');
        if (input) {
            input.value = text;
            input.focus();
        }
    },

    async runGenerateSheet() {
        const promptInput = document.getElementById('aiSheetPromptInput');
        const promptText = promptInput ? promptInput.value.trim() : '';
        if (!promptText) {
            alert(currentLang === 'en' ? 'Please describe the spreadsheet you want.' : 'Descreva a planilha que deseja criar.');
            return;
        }

        if (!this.ensureAuth('Gerador de Planilhas com IA', () => this.runGenerateSheet())) {
            return;
        }

        const token = this.getAuthToken();
        const mode = document.getElementById('aiSheetTargetMode')?.value || 'new';

        this.setLoader('aiSheetLoader', true);

        try {
            const resp = await fetch('api/ai.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    action: 'generate_sheet',
                    prompt: promptText,
                    numRows: 8,
                    lang: currentLang
                })
            });

            if (resp.status === 401) {
                this.handleAuthError();
                return;
            }

            const res = await resp.json();
            if (res && res.error && res.error.includes('Token')) {
                this.handleAuthError();
                return;
            }
            if (!res.success || !res.sheet) {
                throw new Error(res.error || 'Erro ao gerar planilha.');
            }

            // Desconta crédito visualmente
            if (window.GoogleAuth && typeof GoogleAuth.consumeCredit === 'function') {
                GoogleAuth.consumeCredit(1);
            }

            this.applyGeneratedSheet(res.sheet, mode);
            this.closeGenerateSheetModal();

        } catch (err) {
            alert('Erro: ' + err.message);
        } finally {
            this.setLoader('aiSheetLoader', false);
        }
    },

    applyGeneratedSheet(sheetData, mode = 'new') {
        let sheetIndex = currentSheet;

        if (mode === 'new') {
            // Cria nova aba
            addSheet();
            sheetIndex = sheets.length - 1;
            switchSheet(sheetIndex);
        }

        const sheet = sheets[sheetIndex];
        if (sheetData.sheetName) {
            sheet.name = (typeof getUniqueSheetName === 'function')
                ? getUniqueSheetName(sheetData.sheetName, sheetIndex)
                : sheetData.sheetName.substring(0, 25);
            renderSheetTabs();
        }

        saveUndo();

        const headerColor = sheetData.headerColor || '#107c41';
        const columns = sheetData.columns || [];
        const rows = sheetData.data || [];
        const totals = sheetData.totals || [];

        // 1. Cabeçalhos (Linha 1 -> row index 0)
        columns.forEach((colObj, cIdx) => {
            const colLetter = colObj.col || colObj.colLetter || getColName(cIdx);
            const cellId = `${colLetter}1`;
            const cNumber = getColIndex(colLetter);

            sheet.data[cellId] = {
                value: colObj.header,
                display: colObj.header
            };
            sheet.formats[cellId] = {
                bold: true,
                color: '#ffffff',
                backgroundColor: headerColor,
                textAlign: (colObj.type === 'currency' || colObj.type === 'number' || colObj.format === 'currency' || colObj.format === 'number') ? 'right' : 'left'
            };

            if (colObj.width) {
                sheet.colWidths[cNumber] = Math.max(80, Math.min(260, colObj.width));
            }
        });

        // 2. Linhas de Dados (Linha 2..N -> row index 1..)
        rows.forEach((rowArr, rIdx) => {
            const rowNumber = rIdx + 2; // começa em 2
            rowArr.forEach((val, cIdx) => {
                if (cIdx >= columns.length) return; // NUNCA transbordar além das colunas declaradas
                const colObj = columns[cIdx];
                const colLetter = colObj?.col || colObj?.colLetter || getColName(cIdx);
                const cellId = `${colLetter}${rowNumber}`;
                const colType = colObj?.type || colObj?.format || 'text';

                let numFormat = null;
                if (colType === 'currency') numFormat = 'currency';
                else if (colType === 'percent') numFormat = 'percent';
                else if (colType === 'number') numFormat = 'number';

                const strVal = String(val);
                if (strVal.startsWith('=')) {
                    const result = evaluateFormula(strVal, cellId);
                    sheet.data[cellId] = {
                        formula: strVal,
                        value: result,
                        display: formatValue(result, numFormat)
                    };
                } else {
                    const cleanNum = (typeof parseNumericValue === 'function') ? parseNumericValue(val) : NaN;
                    const finalVal = (!isNaN(cleanNum) && (colType === 'currency' || colType === 'number' || colType === 'percent'))
                        ? cleanNum
                        : val;
                    sheet.data[cellId] = {
                        value: finalVal,
                        display: numFormat ? formatValue(finalVal, numFormat) : String(val)
                    };
                }

                sheet.formats[cellId] = {
                    numberFormat: numFormat,
                    textAlign: colType === 'currency' || colType === 'number' || colType === 'percent' ? 'right' : 'left',
                    backgroundColor: rIdx % 2 === 1 ? 'rgba(0,0,0,0.03)' : ''
                };
            });
        });

        // 3. Linha de Totais / Fórmulas de Fechamento (se houver)
        if (totals.length > 0) {
            const totalRowNum = rows.length + 2;
            totals.forEach((totVal, cIdx) => {
                if (cIdx >= columns.length) return; // NUNCA ultrapassa o número de colunas declaradas
                const colObj = columns[cIdx];
                const colLetter = colObj?.col || colObj?.colLetter || getColName(cIdx);
                const cellId = `${colLetter}${totalRowNum}`;
                const colType = colObj?.type || colObj?.format || 'text';

                const strTot = String(totVal);
                let numFormat = colType === 'currency' ? 'currency' : (colType === 'percent' ? 'percent' : (colType === 'number' ? 'number' : null));
                if (strTot.startsWith('=')) {
                    const result = evaluateFormula(strTot, cellId);
                    sheet.data[cellId] = {
                        formula: strTot,
                        value: result,
                        display: formatValue(result, numFormat)
                    };
                } else if (strTot !== '-' && strTot !== '') {
                    const cleanNum = (typeof parseNumericValue === 'function') ? parseNumericValue(totVal) : NaN;
                    const finalVal = (!isNaN(cleanNum) && (colType === 'currency' || colType === 'number' || colType === 'percent'))
                        ? cleanNum
                        : totVal;
                    sheet.data[cellId] = {
                        value: finalVal,
                        display: numFormat ? formatValue(finalVal, numFormat) : String(totVal)
                    };
                }

                sheet.formats[cellId] = {
                    bold: true,
                    border: true,
                    numberFormat: numFormat,
                    textAlign: colType === 'currency' || colType === 'number' || colType === 'percent' ? 'right' : 'left',
                    backgroundColor: 'rgba(16, 124, 65, 0.12)'
                };
            });
        }

        // Atualiza fórmulas interdependentes
        if (typeof updateDependentCells === 'function') {
            updateDependentCells();
        }

        // Recria os cabeçalhos para aplicar larguras e recarrega dados
        createHeaders();
        createCells();
        saveToLocalStorage();

        updateStatus(currentLang === 'en'
            ? `🚀 Spreadsheet "${sheet.name}" generated with AI!`
            : `🚀 Planilha "${sheet.name}" criada com sucesso pela IA!`
        );
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 3. DATA ANALYST COPILOT (ANÁLISE & INSIGHTS)
    // ──────────────────────────────────────────────────────────────────────────
    openAnalyzeModal() {
        const modal = document.getElementById('aiAnalyzeModal');
        if (!modal) return;

        // Prévia dos dados detectados
        const sheet = sheets[currentSheet];
        let filledCount = 0;
        for (let r = 0; r < 50; r++) {
            for (let c = 0; c < 26; c++) {
                const id = `${getColName(c)}${r + 1}`;
                if (sheet.data[id]?.value !== undefined && sheet.data[id]?.value !== '' && sheet.data[id]?.value !== null) {
                    filledCount++;
                }
            }
        }

        const countEl = document.getElementById('aiAnalyzeDataCount');
        if (countEl) {
            countEl.textContent = currentLang === 'en'
                ? `Detected ~${filledCount} filled cells in active sheet "${sheet.name}".`
                : `Detectadas ~${filledCount} células preenchidas na aba ativa "${sheet.name}".`;
        }

        const resultBox = document.getElementById('aiAnalyzeResultBox');
        if (resultBox) resultBox.classList.add('hidden');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    closeAnalyzeModal() {
        const modal = document.getElementById('aiAnalyzeModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },

    async runDataAnalysis() {
        if (!this.ensureAuth('Análise de Dados com IA', () => this.runDataAnalysis())) {
            return;
        }

        const token = this.getAuthToken();
        const sheet = sheets[currentSheet];

        // 1. Encontrar o limite real de linhas e colunas usadas (bounding box)
        let maxCol = -1;
        let maxRow = -1;
        for (let r = 0; r < 60; r++) {
            for (let c = 0; c < 26; c++) {
                const id = `${getColName(c)}${r + 1}`;
                const d = sheet.data[id];
                if (d && d.value !== undefined && d.value !== '' && d.value !== null) {
                    if (c > maxCol) maxCol = c;
                    if (r > maxRow) maxRow = r;
                }
            }
        }

        if (maxCol === -1 || maxRow < 1) {
            alert(currentLang === 'en'
                ? 'Please add some rows of data to the sheet before analyzing.'
                : 'Adicione algumas linhas de dados na planilha antes de analisar.'
            );
            return;
        }

        // 2. Extrai estritamente a matriz delimitada até maxCol e maxRow sem colunas vazias extras
        const tableData = [];
        for (let r = 0; r <= maxRow; r++) {
            const rowArr = [];
            let hasAny = false;
            for (let c = 0; c <= maxCol; c++) {
                const id = `${getColName(c)}${r + 1}`;
                const cellObj = sheet.data[id];
                let val = '';
                if (cellObj) {
                    // Para análise com IA, preferir o valor numérico para cálculos matemáticos precisos
                    if (typeof cellObj.value === 'number') {
                        val = cellObj.value;
                    } else if (cellObj.value !== undefined && cellObj.value !== null && cellObj.value !== '') {
                        val = cellObj.value;
                    } else if (cellObj.display) {
                        val = cellObj.display;
                    }
                }
                if (val !== '') hasAny = true;
                rowArr.push(val);
            }
            if (hasAny) tableData.push(rowArr);
        }

        if (tableData.length < 2) {
            alert(currentLang === 'en'
                ? 'Please add some rows of data to the sheet before analyzing.'
                : 'Adicione algumas linhas de dados na planilha antes de analisar.'
            );
            return;
        }

        this.setLoader('aiAnalyzeLoader', true);
        const resultBox = document.getElementById('aiAnalyzeResultBox');
        if (resultBox) resultBox.classList.add('hidden');

        try {
            const resp = await fetch('api/ai.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({
                    action: 'analyze',
                    tableData: tableData,
                    lang: currentLang
                })
            });

            if (resp.status === 401) {
                this.handleAuthError();
                return;
            }

            const res = await resp.json();
            if (res && res.error && res.error.includes('Token')) {
                this.handleAuthError();
                return;
            }
            if (!res.success || !res.analysis) {
                throw new Error(res.error || 'Erro ao analisar dados');
            }

            // Desconta crédito visualmente
            if (window.GoogleAuth && typeof GoogleAuth.consumeCredit === 'function') {
                GoogleAuth.consumeCredit(1);
            }

            this.renderAnalysisResults(res.analysis);
            if (resultBox) resultBox.classList.remove('hidden');

        } catch (err) {
            alert('Erro: ' + err.message);
        } finally {
            this.setLoader('aiAnalyzeLoader', false);
        }
    },

    renderAnalysisResults(an) {
        this.lastAnalysis = an;
        document.getElementById('aiAnalyzeSummary').textContent = an.summary || '';

        const insightsList = document.getElementById('aiAnalyzeInsightsList');
        if (insightsList) {
            insightsList.innerHTML = (an.keyInsights || []).map(item => `
                <li class="flex items-start gap-2">
                    <span class="text-emerald-500 font-bold">✓</span>
                    <span>${item}</span>
                </li>
            `).join('');
        }

        const anomaliesBox = document.getElementById('aiAnalyzeAnomaliesBox');
        const anomaliesList = document.getElementById('aiAnalyzeAnomaliesList');
        if (an.anomalies && an.anomalies.length > 0) {
            if (anomaliesList) {
                anomaliesList.innerHTML = an.anomalies.map(item => `
                    <li class="flex items-start gap-2 text-amber-700 dark:text-amber-300">
                        <span>⚠️</span>
                        <span>${item}</span>
                    </li>
                `).join('');
            }
            if (anomaliesBox) anomaliesBox.classList.remove('hidden');
        } else {
            if (anomaliesBox) anomaliesBox.classList.add('hidden');
        }

        // Gráfico Recomendado
        const chartBox = document.getElementById('aiAnalyzeChartRecBox');
        if (an.chartRecommendation && chartBox) {
            document.getElementById('aiAnalyzeChartTitle').textContent = an.chartRecommendation.title || 'Gráfico Sugerido';
            document.getElementById('aiAnalyzeChartReason').textContent = an.chartRecommendation.reason || '';
            chartBox.classList.remove('hidden');
        }
    },

    openRecommendedChart() {
        this.closeAnalyzeModal();
        const rec = this.lastAnalysis?.chartRecommendation;
        const type = rec?.type || 'bar';
        const title = rec?.title || (currentLang === 'en' ? 'Data Analysis Chart' : 'Gráfico de Análise');
        let range = rec?.range;
        if (!range || !range.includes(':')) {
            range = typeof detectDataRange === 'function' ? detectDataRange() : 'A1:B10';
        }
        if (typeof showChartModal === 'function') {
            showChartModal(range, type, title);
        }
    },

    setLoader(loaderId, isLoading) {
        const el = document.getElementById(loaderId);
        if (!el) return;
        if (isLoading) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        } else {
            el.classList.add('hidden');
            el.classList.remove('flex');
        }
    }
};

window.ExcelAi = ExcelAi;
