(() => {
    'use strict';

    const app = document.getElementById('chat-app');
    if (!app) {
        return;
    }

    const endpoint = document.body.dataset.chatEndpoint || '../../processar.php';
    const csrfToken = app.dataset.csrf || '';
    const pollMs = Math.max(3000, Number(app.dataset.pollMs || 5000));

    const sidebar = document.getElementById('chat-sidebar');
    const main = document.getElementById('chat-main');
    const list = document.getElementById('chat-list');
    const search = document.getElementById('chat-search');
    const conversationCount = document.getElementById('chat-conversation-count');
    const emptyState = document.getElementById('chat-empty-state');
    const conversationView = document.getElementById('chat-conversation-view');
    const headerName = document.getElementById('chat-header-name');
    const headerStatus = document.getElementById('chat-header-status');
    const messagesEl = document.getElementById('chat-messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const sendButton = document.getElementById('chat-send-button');
    const errorEl = document.getElementById('chat-error');
    const composerHint = document.getElementById('chat-composer-hint');
    const backButton = document.getElementById('chat-back-button');
    const stateButton = document.getElementById('chat-state-button');
    const newButton = document.getElementById('chat-new-button');
    const emptyNewButton = document.getElementById('chat-empty-new-button');
    const newModal = document.getElementById('chat-new-modal');
    const newClose = document.getElementById('chat-new-close');
    const newCancel = document.getElementById('chat-new-cancel');
    const providerSearch = document.getElementById('chat-provider-search');
    const providerList = document.getElementById('chat-provider-list');
    const reviewButton = document.getElementById('chat-review-button');
    const reviewModal = document.getElementById('chat-review-modal');
    const reviewRecipient = document.getElementById('chat-review-recipient');
    const reviewStars = document.getElementById('chat-review-stars');
    const reviewError = document.getElementById('chat-review-error');
    const reviewSubmit = document.getElementById('chat-review-submit');
    const reviewCancel = document.getElementById('chat-review-cancel');
    const reviewClose = document.getElementById('chat-review-close');

    const state = {
        currentToken: String(app.dataset.conversationToken || ''),
        lastMessageId: Number(app.dataset.lastMessageId || 0),
        currentConversation: null,
        conversations: [],
        pollTimer: null,
        inboxTimer: null,
        inboxRefreshTimer: null,
        providerTimer: null,
        sending: false,
        startingProvider: false,
        changingState: false,
        reviewing: false,
        reviewNote: 0,
        viewVersion: 0
    };

    let pendingInitialQuery = app.dataset.initialQuery || '';

    const escapeHtml = (value) => String(value ?? '');
    const formatRating = (value) => Number(value).toFixed(1).replace('.', ',');

    async function post(action, values = {}) {
        const data = new FormData();
        data.set('acao', action);
        if (csrfToken !== '') {
            data.set('csrf_token', csrfToken);
        }
        Object.entries(values).forEach(([key, value]) => {
            data.set(key, value === null || value === undefined ? '' : String(value));
        });

        const response = await fetch(endpoint, {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const text = await response.text();
        let payload;

        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('O servidor retornou uma resposta inválida.');
        }

        if (!response.ok || !payload.sucesso) {
            throw new Error(payload.mensagem || 'Não foi possível concluir a ação.');
        }

        return payload;
    }

    function icon(className, label = '') {
        const element = document.createElement('i');
        element.className = className;
        element.setAttribute('aria-hidden', label === '' ? 'true' : 'false');
        if (label !== '') {
            element.setAttribute('aria-label', label);
        }
        return element;
    }

    function initialOf(name) {
        const trimmed = String(name ?? '').trim();
        if (trimmed === '') {
            return '?';
        }
        return [...trimmed][0].toLocaleUpperCase('pt-BR');
    }

    function avatar(name, extraClasses = '') {
        const element = document.createElement('span');
        element.className = `flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700 ${extraClasses}`.trim();
        element.textContent = initialOf(name);
        return element;
    }

    function normalize(value) {
        return String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim()
            .replace(/\s+/g, ' ');
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatListDate(value) {
        const date = parseDate(value);
        if (!date) {
            return '';
        }

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const target = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const diffDays = Math.round((today - target) / 86400000);

        if (diffDays === 0) {
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        if (diffDays === 1) {
            return 'Ontem';
        }
        if (date.getFullYear() === now.getFullYear()) {
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }
        return date.toLocaleDateString('pt-BR');
    }

    function formatTime(value) {
        const date = parseDate(value);
        if (!date) {
            return '';
        }
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function formatMoney(value, period) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const amount = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
        const labels = { hora: '/hora', dia: '/dia', servico: '/serviço' };
        return amount + (labels[period] || '');
    }

    function reputationText(value) {
        return value === null || value === undefined ? 'Sem avaliações' : `★ ${Number(value).toFixed(1).replace('.', ',')}`;
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }

    function clearError() {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function beginViewChange() {
        state.viewVersion += 1;
        return state.viewVersion;
    }

    function isCurrentView(version) {
        return version === state.viewVersion;
    }

    function setMobileView(showConversation) {
        if (showConversation) {
            sidebar.classList.add('hidden');
            main.classList.remove('hidden');
            main.classList.add('flex');
        } else {
            sidebar.classList.remove('hidden');
            main.classList.add('hidden');
            main.classList.remove('flex');
        }
    }

    function showConversationView(show) {
        emptyState.classList.toggle('hidden', show);
        conversationView.classList.toggle('hidden', !show);
    }

    function setComposerEnabled(enabled) {
        input.disabled = !enabled;
        sendButton.disabled = !enabled;
        composerHint.textContent = enabled
            ? 'Enter envia; Shift+Enter quebra a linha.'
            : 'Você não pode enviar mensagens nesta conversa.';
    }

    function setHeaderLoading() {
        headerName.textContent = 'Carregando...';
        headerStatus.textContent = 'Aguarde um instante.';
        if (reviewButton) {
            reviewButton.hidden = true;
        }
    }

    function renderHeader(conversation) {
        const name = conversation?.outroNome || 'Conversa';
        headerName.textContent = name;

        const avatarElement = document.getElementById('chat-header-avatar');
        if (avatarElement) {
            avatarElement.textContent = initialOf(name);
        }

        if (stateButton) {
            const conversationState = conversation?.estado || 'ativa';
            const closing = conversationState === 'encerrada';
            stateButton.hidden = conversationState === 'bot';
            stateButton.dataset.estado = conversationState;
            stateButton.textContent = closing ? 'Reabrir' : 'Encerrar';
            stateButton.setAttribute('aria-label', closing ? 'Reabrir conversa' : 'Encerrar conversa');
        }

        if (reviewButton) {
            reviewButton.hidden = !conversation?.podeAvaliar;
            const label = conversation?.papel === 'prestador' ? 'Avaliar cliente' : 'Avaliar prestador';
            reviewButton.setAttribute('aria-label', label);
            reviewButton.title = label;
        }

        if (conversation?.estado === 'encerrada') {
            const status = ['Conversa encerrada'];
            if (conversation?.avaliacao !== null && conversation?.avaliacao !== undefined) {
                status.push(`Você avaliou: ★ ${formatRating(conversation.avaliacao)}`);
            }
            if (conversation?.avaliacaoRecebida !== null && conversation?.avaliacaoRecebida !== undefined) {
                status.push(`Você recebeu: ★ ${formatRating(conversation.avaliacaoRecebida)}`);
            }
            headerStatus.textContent = status.join(' · ');
            return;
        }

        if (conversation?.bloqueada) {
            headerStatus.textContent = 'Envio bloqueado nesta conversa';
            return;
        }

        headerStatus.textContent = conversation?.outroReputacao !== null && conversation?.outroReputacao !== undefined
            ? `★ ${Number(conversation.outroReputacao).toFixed(1).replace('.', ',')} de reputação`
            : 'Conversa interna da Roppin';
    }

    function updateActiveItem() {
        list.querySelectorAll('[data-conversation-token]').forEach((item) => {
            const active = item.dataset.conversationToken !== '' && item.dataset.conversationToken === state.currentToken;
            item.classList.toggle('bg-red-50', active);
            item.classList.toggle('hover:bg-gray-50', !active);
        });
    }

    function applyConversationFilter() {
        const query = normalize(search.value);
        let visible = 0;

        list.querySelectorAll('[data-conversation-token]').forEach((item) => {
            const matches = query === '' || normalize(item.textContent).includes(query);
            item.hidden = !matches;
            if (matches) {
                visible += 1;
            }
        });

        list.querySelectorAll('.chat-filter-empty').forEach((element) => element.remove());
        if (query !== '' && visible === 0 && list.querySelector('[data-conversation-token]')) {
            const empty = document.createElement('div');
            empty.className = 'chat-filter-empty p-6 text-center text-sm text-gray-500';
            empty.textContent = 'Nenhuma conversa encontrada.';
            list.append(empty);
        }
    }

    function renderConversations(conversations) {
        state.conversations = conversations;
        list.replaceChildren();

        if (conversations.length === 0) {
            const empty = document.createElement('div');
            empty.id = 'chat-list-empty';
            empty.className = 'p-6 text-center text-sm text-gray-500';
            const emptyIcon = icon('fas fa-comments mb-3 text-2xl text-gray-300');
            const title = document.createElement('p');
            title.textContent = 'Nenhuma conversa ainda.';
            const hint = document.createElement('p');
            hint.className = 'mt-1 text-xs';
            hint.textContent = 'Quando você iniciar um contato, ela aparece aqui.';
            empty.append(emptyIcon, title, hint);
            list.append(empty);
        } else {
            conversations.forEach((conversation) => {
                const item = document.createElement('a');
                item.href = 'mensagens.php';
                item.dataset.conversationToken = String(conversation.token || '');
                item.className = `chat-conversation-item flex gap-3 border-b border-gray-100 px-4 py-3 transition-colors ${
                    item.dataset.conversationToken !== '' && item.dataset.conversationToken === state.currentToken
                        ? 'bg-red-50'
                        : 'hover:bg-gray-50'
                }`;

                const avatarElement = avatar(conversation.outroNome);
                const body = document.createElement('span');
                body.className = 'min-w-0 flex-1';

                const firstLine = document.createElement('span');
                firstLine.className = 'flex items-center justify-between gap-2';
                const name = document.createElement('span');
                name.className = 'truncate text-sm font-semibold text-gray-900';
                name.textContent = conversation.outroNome || 'Conversa';
                const time = document.createElement('span');
                time.className = 'shrink-0 text-[11px] text-gray-400';
                time.textContent = conversation.ultimaMensagemEmLabel || formatListDate(conversation.ultimaMensagemEm);
                firstLine.append(name, time);

                const secondLine = document.createElement('span');
                secondLine.className = 'mt-0.5 flex items-center justify-between gap-2';
                const preview = document.createElement('span');
                preview.className = 'truncate text-xs text-gray-500';
                preview.textContent = conversation.preview || 'Sem mensagens ainda';
                secondLine.append(preview);

                if (Number(conversation.naoLidas) > 0) {
                    const badge = document.createElement('span');
                    badge.className = 'inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-red-700 px-1.5 py-0.5 text-[10px] font-bold text-white';
                    badge.textContent = String(conversation.naoLidas);
                    secondLine.append(badge);
                }

                body.append(firstLine, secondLine);
                item.append(avatarElement, body);
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    openConversation(String(conversation.token || ''));
                });
                list.append(item);
            });
        }

        conversationCount.textContent = `${conversations.length} conversa(s)`;
        updateActiveItem();
        applyConversationFilter();
    }

    function createMessageElement(message) {
        const wrapper = document.createElement('div');
        wrapper.dataset.messageId = String(message.id);

        if (message.tipo === 'sistema') {
            wrapper.className = 'flex justify-center';
            const text = document.createElement('span');
            text.className = 'rounded-full bg-gray-200 px-3 py-1 text-xs text-gray-600';
            text.textContent = message.texto;
            wrapper.append(text);
            return wrapper;
        }

        const mine = Boolean(message.minha);
        wrapper.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;

        const bubble = document.createElement('div');
        bubble.className = `max-w-[85%] rounded-2xl px-4 py-2 shadow-sm ${
            mine
                ? 'rounded-br-sm bg-red-700 text-white'
                : (message.tipo === 'bot'
                    ? 'rounded-bl-sm border border-red-100 bg-white text-gray-800'
                    : 'rounded-bl-sm bg-white text-gray-800')
        }`;

        if (!mine) {
            const author = document.createElement('p');
            author.className = 'mb-1 text-[11px] font-semibold opacity-70';
            if (message.tipo === 'bot') {
                author.append(icon('fas fa-robot mr-1'));
            }
            author.append(document.createTextNode(message.autor || ''));
            bubble.append(author);
        }

        const text = document.createElement('p');
        text.className = 'whitespace-pre-wrap break-words text-sm';
        text.textContent = message.texto;
        bubble.append(text);

        if (message.metadados && message.metadados.tipo === 'sugestoes' && Array.isArray(message.metadados.prestadores)) {
            const suggestions = document.createElement('div');
            suggestions.className = 'mt-3 space-y-2';
            message.metadados.prestadores.forEach((provider) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'flex w-full items-center justify-between gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-left text-xs font-semibold text-red-800 hover:bg-red-100';
                const label = document.createElement('span');
                label.textContent = provider.nome || 'Profissional';
                const action = document.createElement('span');
                action.textContent = 'Conversar';
                button.append(label, action);
                button.addEventListener('click', () => startConversation(Number(provider.idUsuario)));
                suggestions.append(button);
            });
            bubble.append(suggestions);
        }

        const time = document.createElement('p');
        time.className = 'mt-1 text-right text-[10px] opacity-60';
        time.textContent = message.hora || formatTime(message.criadoEm);
        bubble.append(time);

        wrapper.append(bubble);
        return wrapper;
    }

    function renderMessages(messages, append = false) {
        if (!append) {
            messagesEl.replaceChildren();
        }

        messagesEl.querySelectorAll('#chat-messages-empty').forEach((element) => element.remove());

        const rendered = new Map();
        messagesEl.querySelectorAll('[data-message-id]').forEach((element) => {
            const id = Number(element.dataset.messageId);
            if (!rendered.has(id)) {
                rendered.set(id, []);
            }
            rendered.get(id).push(element);
        });

        messages.forEach((message) => {
            const messageId = Number(message.id);
            if (messageId > 0 && rendered.has(messageId)) {
                rendered.get(messageId).forEach((element) => element.remove());
                rendered.delete(messageId);
            }

            const element = createMessageElement(message);
            messagesEl.append(element);
            if (messageId > 0) {
                rendered.set(messageId, [element]);
            }
        });

        if (messagesEl.childElementCount === 0) {
            const empty = document.createElement('div');
            empty.id = 'chat-messages-empty';
            empty.className = 'py-10 text-center text-sm text-gray-500';
            empty.textContent = 'Nenhuma mensagem ainda. Diga olá!';
            messagesEl.append(empty);
        }

        scrollToBottom();
    }

    function focusConversation(token) {
        if (!token) {
            return;
        }
        post('chat_focar_conversa', { token }).catch(() => {
            // O foco só persiste a conversa aberta; uma falha não interrompe o chat.
        });
    }

    function blurConversation() {
        post('chat_limpar_foco').catch(() => {
            // Nada a fazer se a sessão já tiver expirado.
        });
    }

    function markRead(lastMessageId) {
        if (!state.currentToken || lastMessageId <= 0) {
            return;
        }

        post('chat_marcar_lida', {
            token: state.currentToken,
            ultima_id: lastMessageId
        }).catch(() => {
            // A confirmação de leitura não deve interromper o envio de mensagens.
        });
    }

    function refreshInboxSoon() {
        window.clearTimeout(state.inboxRefreshTimer);
        state.inboxRefreshTimer = window.setTimeout(refreshInbox, 500);
    }

    async function refreshInbox() {
        try {
            const result = await post('chat_conversas');
            renderConversations(result.conversas || []);
        } catch (error) {
            // O polling da inbox é silencioso para não poluir a conversa aberta.
        }
    }

    function stopPolling() {
        if (state.pollTimer !== null) {
            window.clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function startPolling() {
        stopPolling();
        if (!state.currentToken) {
            return;
        }
        state.pollTimer = window.setInterval(pollConversation, pollMs);
    }

    async function pollConversation() {
        if (!state.currentToken || document.hidden || state.sending || state.changingState) {
            return;
        }

        const version = state.viewVersion;
        const token = state.currentToken;

        try {
            const result = await post('chat_conversa', {
                token,
                depois_id: state.lastMessageId
            });

            if (!isCurrentView(version) || token !== state.currentToken) {
                return;
            }

            if (result.conversa) {
                state.currentConversation = result.conversa;
                renderHeader(result.conversa);
                setComposerEnabled(Boolean(result.podeEnviar));
            }

            const messages = result.mensagens || [];
            const nextLastId = Number(result.ultimoId || 0);
            if (nextLastId > state.lastMessageId) {
                state.lastMessageId = nextLastId;
            }
            if (messages.length > 0) {
                renderMessages(messages, true);
                markRead(state.lastMessageId);
                refreshInboxSoon();
            }
        } catch (error) {
            // Mantém a conversa aberta e tenta novamente no próximo ciclo.
        }
    }

    async function openConversation(token) {
        if (!token) {
            return;
        }

        const version = beginViewChange();
        clearError();
        state.currentToken = token;
        state.lastMessageId = 0;
        state.currentConversation = null;
        showConversationView(true);
        setHeaderLoading();
        setMobileView(true);
        updateActiveItem();
        startPolling();
        focusConversation(token);

        try {
            const result = await post('chat_conversa', {
                token,
                depois_id: 0
            });

            if (!isCurrentView(version) || token !== state.currentToken) {
                return;
            }

            state.currentConversation = result.conversa;
            state.lastMessageId = Number(result.ultimoId || 0);
            renderHeader(result.conversa);
            renderMessages(result.mensagens || [], false);
            setComposerEnabled(Boolean(result.podeEnviar));
            markRead(state.lastMessageId);
            updateActiveItem();
            refreshInboxSoon();
            input.focus();
        } catch (error) {
            if (!isCurrentView(version)) {
                return;
            }
            showError(error.message);
            headerName.textContent = 'Conversa indisponível';
            headerStatus.textContent = 'Volte para a lista e tente novamente.';
            setComposerEnabled(false);
            stopPolling();
        }
    }

    function closeConversation() {
        beginViewChange();
        state.currentToken = '';
        state.lastMessageId = 0;
        state.currentConversation = null;
        stopPolling();
        showConversationView(false);
        setMobileView(false);
        updateActiveItem();
        clearError();
        blurConversation();
    }

    async function changeConversationState() {
        if (!state.currentToken || state.changingState || !stateButton || stateButton.hidden) {
            return;
        }

        const version = beginViewChange();
        const token = state.currentToken;
        const closing = stateButton.dataset.estado !== 'encerrada';
        state.changingState = true;
        stateButton.disabled = true;
        clearError();

        try {
            const result = await post(
                closing ? 'chat_encerrar_conversa' : 'chat_reabrir_conversa',
                { token }
            );

            if (!isCurrentView(version) || token !== state.currentToken) {
                return;
            }

            state.currentConversation = result.conversa;
            state.lastMessageId = Number(result.ultimoId || state.lastMessageId);
            renderHeader(result.conversa);
            renderMessages(result.mensagens || [], false);
            setComposerEnabled(Boolean(result.podeEnviar));
            refreshInboxSoon();
        } catch (error) {
            if (!isCurrentView(version)) {
                return;
            }
            showError(error.message);
        } finally {
            state.changingState = false;
            stateButton.disabled = false;
        }
    }

    function clearReviewError() {
        reviewError.textContent = '';
        reviewError.classList.add('hidden');
    }

    function renderReviewStars() {
        reviewStars.replaceChildren();
        for (let note = 1; note <= 5; note += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `rounded p-1 transition-colors ${note <= state.reviewNote ? 'text-amber-500' : 'text-gray-300 hover:text-amber-300'}`;
            button.setAttribute('aria-label', `${note} estrela${note > 1 ? 's' : ''}`);
            button.setAttribute('aria-pressed', note === state.reviewNote ? 'true' : 'false');
            button.innerHTML = '<i class="fas fa-star text-2xl" aria-hidden="true"></i>';
            button.addEventListener('click', () => {
                state.reviewNote = note;
                reviewSubmit.disabled = false;
                renderReviewStars();
            });
            reviewStars.append(button);
        }
    }

    function openReviewModal() {
        if (!state.currentToken || !state.currentConversation || !state.currentConversation.podeAvaliar) {
            return;
        }

        clearReviewError();
        state.reviewNote = 0;
        reviewSubmit.disabled = true;

        const isProvider = state.currentConversation.papel === 'prestador';
        const name = state.currentConversation.outroNome || 'participante';
        reviewRecipient.textContent = isProvider
            ? `Como foi ${name} como cliente?`
            : `Como foi o atendimento de ${name}?`;

        renderReviewStars();
        reviewModal.classList.remove('hidden');
        reviewModal.classList.add('flex');
        reviewModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeReviewModal() {
        reviewModal.classList.add('hidden');
        reviewModal.classList.remove('flex');
        reviewModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    async function submitReview() {
        if (!state.currentToken || state.reviewing || state.reviewNote < 1) {
            return;
        }

        const version = state.viewVersion;
        const token = state.currentToken;
        state.reviewing = true;
        reviewSubmit.disabled = true;
        clearReviewError();

        try {
            const result = await post('chat_avaliar', { token, nota: state.reviewNote });
            if (!isCurrentView(version) || token !== state.currentToken) {
                return;
            }
            state.currentConversation = result.conversa;
            state.lastMessageId = Number(result.ultimoId || state.lastMessageId);
            renderHeader(result.conversa);
            setComposerEnabled(Boolean(result.podeEnviar));
            closeReviewModal();
            refreshInboxSoon();
        } catch (error) {
            reviewError.textContent = error.message;
            reviewError.classList.remove('hidden');
            reviewSubmit.disabled = false;
        } finally {
            state.reviewing = false;
        }
    }

    async function sendMessage(event) {
        event.preventDefault();
        if (state.sending || !state.currentToken) {
            return;
        }

        const text = input.value.trim();
        if (text === '') {
            return;
        }

        const version = state.viewVersion;
        const token = state.currentToken;
        state.sending = true;
        clearError();
        sendButton.disabled = true;

        try {
            const result = await post('chat_enviar', {
                token,
                mensagem: text
            });

            if (!isCurrentView(version) || token !== state.currentToken) {
                return;
            }

            renderMessages([result.dados], true);
            const nextLastId = Number(result.ultimoId || 0);
            if (nextLastId > state.lastMessageId) {
                state.lastMessageId = nextLastId;
            }
            input.value = '';
            input.style.height = '';
            markRead(state.lastMessageId);
            refreshInboxSoon();
        } catch (error) {
            if (!isCurrentView(version)) {
                return;
            }
            showError(error.message);
        } finally {
            state.sending = false;
            sendButton.disabled = false;
            input.focus();
        }
    }

    function openNewModal() {
        const query = pendingInitialQuery;
        pendingInitialQuery = '';
        providerSearch.value = query;
        providerList.replaceChildren();
        const loading = document.createElement('p');
        loading.className = 'p-4 text-sm text-gray-500';
        loading.textContent = 'Carregando profissionais...';
        providerList.append(loading);
        newModal.classList.remove('hidden');
        newModal.classList.add('flex');
        newModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        providerSearch.focus();
        loadProviders(query);
    }

    function closeNewModal() {
        newModal.classList.add('hidden');
        newModal.classList.remove('flex');
        newModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function renderProviders(providers) {
        providerList.replaceChildren();

        if (!providers || providers.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'p-4 text-sm text-gray-500';
            empty.textContent = 'Nenhum profissional encontrado para essa busca.';
            providerList.append(empty);
            return;
        }

        providers.forEach((provider) => {
            const card = document.createElement('div');
            card.className = 'flex items-center gap-3 rounded-lg border border-gray-100 p-3';

            const details = document.createElement('div');
            details.className = 'min-w-0 flex-1';
            const name = document.createElement('p');
            name.className = 'truncate text-sm font-semibold text-gray-900';
            name.textContent = provider.nome || 'Profissional';
            const specialties = document.createElement('p');
            specialties.className = 'truncate text-xs text-gray-500';
            specialties.textContent = provider.especialidades || 'Especialidade não informada';
            const pricing = document.createElement('p');
            pricing.className = 'mt-0.5 text-xs text-gray-500';
            const price = formatMoney(provider.valor, provider.periodo_cobranca);
            pricing.textContent = [price, reputationText(provider.reputacao)].filter(Boolean).join(' · ');
            details.append(name, specialties, pricing);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'shrink-0 rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60';
            button.textContent = 'Conversar';
            button.addEventListener('click', () => startConversation(Number(provider.idUsuario), button));

            card.append(avatar(provider.nome), details, button);
            providerList.append(card);
        });
    }

    async function loadProviders(searchTerm) {
        try {
            const result = await post('chat_listar_prestadores', { busca: searchTerm });
            renderProviders(result.prestadores || []);
        } catch (error) {
            providerList.replaceChildren();
            const message = document.createElement('p');
            message.className = 'p-4 text-sm text-red-700';
            message.textContent = error.message;
            providerList.append(message);
        }
    }

    async function startConversation(providerId, button = null) {
        if (!providerId || state.startingProvider) {
            return;
        }

        state.startingProvider = true;
        if (button) {
            button.disabled = true;
            button.textContent = 'Abrindo...';
        }

        try {
            const result = await post('chat_iniciar_conversa', { prestador_id: providerId });
            closeNewModal();
            await refreshInbox();
            await openConversation(String(result.conversa.token || ''));
        } catch (error) {
            providerList.replaceChildren();
            const message = document.createElement('p');
            message.className = 'p-4 text-sm text-red-700';
            message.textContent = error.message;
            providerList.append(message);
        } finally {
            state.startingProvider = false;
            if (button) {
                button.disabled = false;
                button.textContent = 'Conversar';
            }
        }
    }

    function autoResizeInput() {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 128)}px`;
    }

    newButton.addEventListener('click', openNewModal);
    emptyNewButton.addEventListener('click', openNewModal);
    newClose.addEventListener('click', closeNewModal);
    newCancel.addEventListener('click', closeNewModal);
    newModal.addEventListener('click', (event) => {
        if (event.target === newModal) {
            closeNewModal();
        }
    });

    if (reviewButton) {
        reviewButton.addEventListener('click', openReviewModal);
    }
    reviewClose.addEventListener('click', closeReviewModal);
    reviewCancel.addEventListener('click', closeReviewModal);
    reviewSubmit.addEventListener('click', submitReview);
    reviewModal.addEventListener('click', (event) => {
        if (event.target === reviewModal) {
            closeReviewModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        if (!reviewModal.classList.contains('hidden')) {
            closeReviewModal();
        } else if (!newModal.classList.contains('hidden')) {
            closeNewModal();
        }
    });

    providerSearch.addEventListener('input', () => {
        window.clearTimeout(state.providerTimer);
        state.providerTimer = window.setTimeout(() => loadProviders(providerSearch.value.trim()), 300);
    });

    search.addEventListener('input', applyConversationFilter);
    form.addEventListener('submit', sendMessage);
    input.addEventListener('input', autoResizeInput);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    backButton.addEventListener('click', closeConversation);
    if (stateButton) {
        stateButton.addEventListener('click', changeConversationState);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshInbox();
            pollConversation();
        }
    });

    if (state.currentToken !== '') {
        showConversationView(true);
        setMobileView(true);
        updateActiveItem();
        scrollToBottom();
        startPolling();
    } else {
        showConversationView(false);
        setMobileView(false);
    }

    if (app.dataset.openNew === '1') {
        openNewModal();
    }

    refreshInbox();
    state.inboxTimer = window.setInterval(refreshInbox, 20000);
})();
