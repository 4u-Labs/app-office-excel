/**
 * ExcelClone — Google OAuth 2.0 & Gestão de Créditos Compartilhados do Ecossistema 4U.IA.BR
 * Compatível com KeepAi, FreePDF, WordClone, PointClone e todo o ecossistema 4U
 */

const GoogleAuth = {
    CLIENT_ID: '569266864432-pd09jbb5no9ekdhdr018fj643nopp817.apps.googleusercontent.com',
    ADMIN_EMAILS: ['fbr4g4@gmail.com', 'fb4g4@gmail.com'],
    STORAGE_USER: 'user_profile',
    STORAGE_CREDITS: 'user_credits',
    STORAGE_TOKEN: 'user_token',
    STORAGE_ROLE: 'user_role',
    DEFAULT_CREDITS: 10,
    ADMIN_CREDITS: 999999,

    tokenClient: null,
    currentUser: null,
    token: null,
    credits: 0,
    pendingAction: null,

    init() {
        this.loadStoredSession();
        this.initTokenClient();
        this.renderAuthUI();
        this.syncWithBackend();

        // Fechar dropdown de usuário ao clicar fora
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('userDropdownMenu');
            const avatarBtn = document.getElementById('userAvatarBtn');
            if (dropdown && !dropdown.classList.contains('hidden')) {
                if (avatarBtn && !avatarBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            }
        });
    },

    getLang() {
        return (typeof currentLang !== 'undefined' ? currentLang : (localStorage.getItem('office_lang') || 'pt'));
    },

    // 1. CARREGAR SESSÃO SALVA (Detecta login de qualquer app 4U no mesmo domínio)
    loadStoredSession() {
        try {
            const stored = localStorage.getItem(this.STORAGE_USER);
            const token = localStorage.getItem(this.STORAGE_TOKEN);
            if (stored) {
                this.currentUser = JSON.parse(stored);
                this.token = token;
                const storedCredits = localStorage.getItem(this.STORAGE_CREDITS);

                if (this.isAdmin()) {
                    this.credits = this.ADMIN_CREDITS;
                } else {
                    this.credits = storedCredits !== null ? parseInt(storedCredits, 10) : this.DEFAULT_CREDITS;
                }
            }
        } catch (e) {
            console.warn('[GoogleAuth] Erro ao carregar sessão local:', e);
            this.currentUser = null;
            this.token = null;
            this.credits = 0;
        }
    },

    // 2. INICIALIZAR CLIENTE DO GOOGLE (GSI)
    initTokenClient() {
        if (typeof google === 'undefined' || !google.accounts || !google.accounts.oauth2) {
            setTimeout(() => this.initTokenClient(), 400);
            return;
        }

        try {
            this.tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: this.CLIENT_ID,
                scope: 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email openid',
                callback: async (response) => {
                    if (response && response.access_token) {
                        await this.handleTokenResponse(response.access_token);
                    }
                },
                error_callback: (err) => {
                    console.error('[GoogleAuth] Erro no GSI:', err);
                }
            });
        } catch (e) {
            console.warn('[GoogleAuth] Erro ao inicializar initTokenClient:', e);
        }
    },

    // 3. FLUXO DE LOGIN
    signIn(onSuccess = null) {
        if (onSuccess) {
            this.pendingAction = onSuccess;
        }

        if (this.isLoggedIn()) {
            if (this.pendingAction) {
                const action = this.pendingAction;
                this.pendingAction = null;
                action();
            }
            return;
        }

        if (this.tokenClient) {
            this.tokenClient.requestAccessToken({ prompt: 'select_account' });
        } else {
            console.warn('[GoogleAuth] Cliente GSI ainda não carregado. Tentando novamente...');
            setTimeout(() => {
                if (this.tokenClient) {
                    this.tokenClient.requestAccessToken({ prompt: 'select_account' });
                } else {
                    alert(this.getLang() === 'en' ? 'Google services are loading, please try again in a few seconds.' : 'Os serviços do Google estão carregando. Tente novamente em instantes.');
                }
            }, 800);
        }
    },

    async handleTokenResponse(accessToken) {
        try {
            const userInfoRes = await fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
                headers: { Authorization: `Bearer ${accessToken}` }
            });

            if (!userInfoRes.ok) {
                throw new Error('Falha ao obter perfil do Google.');
            }

            const googleUser = await userInfoRes.json();

            const backendRes = await fetch('api/auth.php?action=google', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: googleUser.email,
                    name: googleUser.name || googleUser.given_name || 'Usuário 4U',
                    picture: googleUser.picture || ''
                })
            });

            const data = await backendRes.json();

            if (!data.success) {
                throw new Error(data.error || 'Erro na autenticação central.');
            }

            this.token = data.token;
            this.currentUser = {
                id: data.user.id,
                email: data.user.email,
                name: data.user.display_name || googleUser.name,
                givenName: googleUser.given_name || (data.user.display_name ? data.user.display_name.split(' ')[0] : 'Usuário'),
                picture: data.user.photo_url || googleUser.picture || '',
                isVip: data.user.is_vip
            };

            this.credits = data.user.credits;

            // Salva chaves unificadas compartilhadas do ecossistema 4U
            localStorage.setItem(this.STORAGE_USER, JSON.stringify(this.currentUser));
            localStorage.setItem(this.STORAGE_TOKEN, this.token);
            localStorage.setItem(this.STORAGE_CREDITS, String(this.credits));
            localStorage.setItem(this.STORAGE_ROLE, this.isAdmin() ? 'admin' : 'user');

            this.renderAuthUI();
            this.closeAuthModal();

            const isEn = this.getLang() === 'en';
            if (typeof updateStatus === 'function') {
                updateStatus(isEn ? `👋 Welcome, ${this.currentUser.givenName}! Connected.` : `👋 Olá, ${this.currentUser.givenName}! Conectado.`);
            }

            if (this.pendingAction) {
                const action = this.pendingAction;
                this.pendingAction = null;
                action();
            }
        } catch (e) {
            console.error('[GoogleAuth] Erro ao processar login:', e);
            alert('Erro no login Google: ' + e.message);
        }
    },

    // 4. SINCRONIZAR SALDO COM O BACKEND
    async syncWithBackend() {
        if (!this.token) return;
        try {
            const res = await fetch('api/auth.php?action=sync', {
                headers: { Authorization: `Bearer ${this.token}` }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.success && data.user) {
                    this.credits = data.user.credits;
                    localStorage.setItem(this.STORAGE_CREDITS, String(this.credits));
                    this.updateCreditsBadge();
                }
            }
        } catch (e) {
            console.warn('[GoogleAuth] Falha na sincronização em segundo plano:', e);
        }
    },

    // 5. LOGOUT
    async signOut() {
        try {
            if (this.token) {
                fetch('api/auth.php?action=logout', {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.token}` }
                }).catch(() => {});
            }
        } catch (e) {}

        this.currentUser = null;
        this.token = null;
        this.credits = 0;

        localStorage.removeItem(this.STORAGE_USER);
        localStorage.removeItem(this.STORAGE_TOKEN);
        localStorage.removeItem(this.STORAGE_CREDITS);
        localStorage.removeItem(this.STORAGE_ROLE);

        this.renderAuthUI();
        this.closeUserDropdown();

        const isEn = this.getLang() === 'en';
        if (typeof updateStatus === 'function') {
            updateStatus(isEn ? 'Signed out successfully.' : 'Você saiu da sua conta.');
        }
    },

    // 6. CHECAGEM DE STATUS & CRÉDITOS
    isLoggedIn() {
        return Boolean(this.currentUser && this.currentUser.email);
    },

    isAdmin() {
        if (!this.currentUser || !this.currentUser.email) return false;
        return this.ADMIN_EMAILS.includes(this.currentUser.email.toLowerCase().trim());
    },

    hasCredits(cost = 1) {
        if (this.isAdmin()) return true;
        return this.credits >= cost;
    },

    async consumeCredit(cost = 1, tool = 'ExcelClone') {
        if (this.isAdmin()) {
            this.updateCreditsBadge();
            return true;
        }

        try {
            const res = await fetch('api/credits.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${this.token}`
                },
                body: JSON.stringify({ cost, tool })
            });

            const data = await res.json();
            if (data.success) {
                this.credits = data.credits_remaining;
                localStorage.setItem(this.STORAGE_CREDITS, String(this.credits));
                this.updateCreditsBadge();
                return true;
            } else {
                this.openCreditsModal('insufficient');
                return false;
            }
        } catch (e) {
            this.credits = Math.max(0, this.credits - cost);
            localStorage.setItem(this.STORAGE_CREDITS, String(this.credits));
            this.updateCreditsBadge();
            return true;
        }
    },

    // 7. RENDERIZAÇÃO DA INTERFACE (HEADER)
    renderAuthUI() {
        const container = document.getElementById('googleAuthContainer');
        if (!container) return;

        const isEn = this.getLang() === 'en';

        if (!this.isLoggedIn()) {
            container.innerHTML = `
                <button onclick="GoogleAuth.signIn()" class="flex items-center gap-1.5 px-2.5 py-1 rounded bg-white hover:bg-gray-100 text-gray-800 font-bold text-xs shadow-sm border border-gray-300 transition-all cursor-pointer" title="${isEn ? 'Sign in with Google to sync 4U credits' : 'Entrar com Conta Google e sincronizar créditos 4U'}">
                    <svg viewBox="0 0 24 24" width="14" height="14">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>${isEn ? 'Google Login' : 'Entrar'}</span>
                </button>
            `;
        } else {
            const avatar = this.currentUser.picture || 'https://www.gravatar.com/avatar/?d=mp';
            const name = this.currentUser.givenName || this.currentUser.name || 'Conta 4U';
            const creditsText = this.isAdmin() ? '∞ VIP' : `${this.credits}`;

            container.innerHTML = `
                <div class="relative flex items-center gap-1.5">
                    <!-- Badge de Créditos do Ecossistema 4U -->
                    <button onclick="GoogleAuth.openCreditsModal()" class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-400 text-amber-200 font-extrabold text-xs shadow-sm hover:border-amber-300 transition-all cursor-pointer" title="${isEn ? 'Your credits across the 4U ecosystem' : 'Seus créditos válidos em todo o ecossistema 4U'}">
                        <span class="text-xs">💎</span>
                        <span id="creditsCountDisplay">${creditsText}</span>
                        <span class="text-[9px] uppercase font-bold tracking-wider">${isEn ? 'cr' : 'créd'}</span>
                    </button>

                    <!-- Botão Perfil Usuário -->
                    <button id="userAvatarBtn" onclick="GoogleAuth.toggleUserDropdown()" class="flex items-center gap-1.5 p-0.5 pl-1 pr-2 rounded-full bg-black/20 border border-white/30 hover:border-white transition-all cursor-pointer">
                        <img src="${avatar}" alt="${name}" class="w-5 h-5 rounded-full object-cover">
                        <span class="text-xs font-semibold max-w-[80px] truncate text-white">${name}</span>
                        <span class="text-[9px] text-white/70">▼</span>
                    </button>

                    <!-- Dropdown de Usuário -->
                    <div id="userDropdownMenu" class="hidden absolute right-0 top-9 w-60 bg-white text-gray-800 border border-gray-200 rounded-xl p-3 shadow-2xl z-50 text-left">
                        <div class="flex items-center gap-2.5 pb-2.5 border-b border-gray-200">
                            <img src="${avatar}" class="w-8 h-8 rounded-full border border-green-500/40 object-cover">
                            <div class="overflow-hidden">
                                <div class="text-xs font-bold text-gray-900 truncate">${this.currentUser.name}</div>
                                <div class="text-[10px] text-gray-500 truncate">${this.currentUser.email}</div>
                                <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded-full bg-green-500/10 text-green-700 text-[9px] font-extrabold uppercase">
                                    ${this.isAdmin() ? '⭐ Admin VIP' : 'Conta 4U'}
                                </span>
                            </div>
                        </div>

                        <div class="py-2 space-y-1">
                            <div class="flex items-center justify-between text-xs px-2 py-1 rounded bg-gray-100">
                                <span class="text-gray-500">${isEn ? '4U Balance:' : 'Saldo 4U:'}</span>
                                <span class="font-extrabold text-amber-600">${this.isAdmin() ? '∞ VIP' : `${this.credits} ${isEn ? 'credits' : 'créditos'}`}</span>
                            </div>
                            <a href="../index.php" class="flex items-center gap-2 px-2 py-1.5 rounded text-xs text-gray-700 hover:bg-gray-100 transition-colors">
                                <span>🏢</span>
                                <span>${isEn ? 'OfficeClone Suite' : 'Suíte OfficeClone'}</span>
                            </a>
                        </div>

                        <div class="pt-2 border-t border-gray-200">
                            <button onclick="GoogleAuth.signOut()" class="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-600 text-xs font-bold transition-all cursor-pointer">
                                <span>🚪</span>
                                <span>${isEn ? 'Sign Out' : 'Sair da Conta'}</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
    },

    updateCreditsBadge() {
        const display = document.getElementById('creditsCountDisplay');
        if (display) {
            display.textContent = this.isAdmin() ? '∞ VIP' : `${this.credits}`;
        }
    },

    toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdownMenu');
        if (dropdown) dropdown.classList.toggle('hidden');
    },

    closeUserDropdown() {
        const dropdown = document.getElementById('userDropdownMenu');
        if (dropdown) dropdown.classList.add('hidden');
    },

    // 8. MODAL DE AVISO: LOGIN GOOGLE OBRIGATÓRIO PARA IA / RECURSOS
    openAuthModal(featureName = 'ExcelClone') {
        let modal = document.getElementById('googleAuthPromptModal');
        const isEn = this.getLang() === 'en';

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'googleAuthPromptModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm select-none';
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div class="bg-white border border-gray-300 rounded-2xl p-6 max-w-md w-full shadow-2xl relative text-left">
                <button onclick="GoogleAuth.closeAuthModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 font-bold text-sm">✕</button>
                <div class="w-12 h-12 rounded-2xl bg-green-600/10 text-green-700 flex items-center justify-center text-xl mb-3">
                    📊
                </div>
                <h3 class="text-base font-extrabold text-gray-900 mb-1">
                    ${isEn ? 'Sign in with Google' : 'Faça login com sua conta Google'}
                </h3>
                <p class="text-xs text-gray-600 mb-4">
                    ${isEn 
                        ? `Connect your Google account to sync formulas, features, and credits in <strong>${featureName}</strong>. You get <strong>10 free credits</strong> to use in any 4U app!` 
                        : `Conecte sua conta Google para sincronizar recursos e créditos no <strong>${featureName}</strong>. Novos usuários ganham <strong>10 créditos gratuitos</strong> para usar em qualquer app da 4U!`}
                </p>
                <div class="space-y-2">
                    <button onclick="GoogleAuth.signIn()" class="w-full flex items-center justify-center gap-3 py-2.5 px-4 bg-green-700 hover:bg-green-800 text-white rounded-xl text-xs font-bold shadow-lg transition-all cursor-pointer">
                        <svg viewBox="0 0 24 24" width="16" height="16">
                            <path fill="#ffffff" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9v-2h2v2zm0-4H9V7h2v5z"/>
                        </svg>
                        <span>${isEn ? 'Sign in with Google' : 'Entrar com Conta Google'}</span>
                    </button>
                    <button onclick="GoogleAuth.closeAuthModal()" class="w-full py-2 text-center text-xs text-gray-500 hover:text-gray-700">
                        ${isEn ? 'Cancel' : 'Cancelar'}
                    </button>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    },

    closeAuthModal() {
        const modal = document.getElementById('googleAuthPromptModal');
        if (modal) modal.classList.add('hidden');
    },

    // 9. MODAL DE CRÉDITOS & RECARGA
    openCreditsModal(type = 'info') {
        let modal = document.getElementById('creditsInfoModal');
        const isEn = this.getLang() === 'en';

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'creditsInfoModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm select-none';
            document.body.appendChild(modal);
        }

        const isInsuf = (type === 'insufficient');
        const creditsText = this.isAdmin() ? '∞ VIP' : `${this.credits}`;

        modal.innerHTML = `
            <div class="bg-white border border-gray-300 rounded-2xl p-6 max-w-md w-full shadow-2xl relative text-left">
                <button onclick="GoogleAuth.closeCreditsModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 font-bold text-sm">✕</button>
                
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl ${isInsuf ? 'bg-rose-500/10 text-rose-500' : 'bg-amber-500/10 text-amber-500'} flex items-center justify-center text-xl">
                        ${isInsuf ? '⚠️' : '💎'}
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-gray-900">
                            ${isInsuf ? (isEn ? 'Insufficient Credits' : 'Créditos Insuficientes') : (isEn ? '4U Ecosystem Credits' : 'Créditos do Ecossistema 4U')}
                        </h3>
                        <p class="text-xs text-gray-500">
                            ${isEn ? 'Valid across ExcelClone, WordClone, FreePDF, KeepAi and more' : 'Válidos no ExcelClone, WordClone, FreePDF, KeepAi e todo o 4U'}
                        </p>
                    </div>
                </div>

                <div class="bg-gray-100 p-3.5 rounded-xl border border-gray-200 mb-4 flex items-center justify-between">
                    <span class="text-xs text-gray-600">${isEn ? 'Current Balance:' : 'Seu Saldo Atual:'}</span>
                    <span class="text-sm font-extrabold text-amber-600">${creditsText} ${isEn ? 'credits' : 'créditos'}</span>
                </div>

                <p class="text-xs text-gray-600 mb-4 leading-relaxed">
                    ${isEn 
                        ? 'Unified credits can be used in any application within the 4U.IA.BR ecosystem.' 
                        : 'Os créditos unificados são compartilhados e válidos em todos os aplicativos do ecossistema 4U.IA.BR.'}
                </p>

                <div class="space-y-2">
                    <a href="https://4u.ia.br/app/office/keepai/" target="_blank" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-600 hover:to-yellow-700 text-white rounded-xl text-xs font-bold shadow-md transition-all">
                        <span>💎 ${isEn ? 'Get More Credits (KeepAi)' : 'Adquirir Mais Créditos (KeepAi)'}</span>
                    </a>
                    <button onclick="GoogleAuth.closeCreditsModal()" class="w-full py-2 text-center text-xs text-gray-500 hover:text-gray-700">
                        ${isEn ? 'Close' : 'Fechar'}
                    </button>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    },

    closeCreditsModal() {
        const modal = document.getElementById('creditsInfoModal');
        if (modal) modal.classList.add('hidden');
    }
};

window.GoogleAuth = GoogleAuth;
