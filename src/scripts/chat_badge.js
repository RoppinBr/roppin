(() => {
    'use strict';

    const badges = Array.from(document.querySelectorAll('[data-chat-unread-badge]'));
    if (badges.length === 0) {
        return;
    }

    const endpoint = document.body.dataset.chatEndpoint || 'processar.php';
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';
    const baseTitle = document.title;

    function applyTotal(total) {
        const value = Number(total) || 0;
        badges.forEach((badge) => {
            badge.textContent = value > 99 ? '99+' : String(value);
            badge.classList.toggle('hidden', value === 0);
            badge.setAttribute('aria-label', `${value} mensagem(ns) não lida(s)`);
        });
        document.title = value > 0 ? `(${value}) ${baseTitle}` : baseTitle;
    }

    async function refresh() {
        if (document.hidden) {
            return;
        }

        const data = new FormData();
        data.set('acao', 'chat_nao_lidas');
        if (csrfToken !== '') {
            data.set('csrf_token', csrfToken);
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (payload.sucesso) {
                applyTotal(payload.total);
            }
        } catch (error) {
            // O badge é informativo; falhas de rede não devem afetar a página.
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refresh();
        }
    });

    refresh();
    window.setInterval(refresh, 30000);
})();
