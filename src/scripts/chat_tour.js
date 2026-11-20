(() => {
    'use strict';

    const app = document.getElementById('chat-app');
    if (!app || app.dataset.tourEnabled !== '1') {
        return;
    }

    const COOKIE = 'roppin_chat_tutorial_visto';
    const forcar = new URLSearchParams(window.location.search).get('tour') === '1';
    const jaViu = document.cookie.split('; ').some((item) => item.startsWith(COOKIE + '='));
    if (jaViu && !forcar) {
        return;
    }

    const panel = document.getElementById('panel-messages');
    const newButton = document.getElementById('chat-new-button');
    const newCancel = document.getElementById('chat-new-cancel');
    const newModal = document.getElementById('chat-new-modal');
    const providerSearch = document.getElementById('chat-provider-search');
    const providerList = document.getElementById('chat-provider-list');
    const exemplo = app.dataset.demoQuery || 'Eletricista';

    const etapas = [
        {
            titulo: 'Bem-vindo às mensagens',
            texto: 'Em 4 passos você vê como iniciar e acompanhar uma conversa.',
            alvo: null,
            boasVindas: true
        },
        {
            titulo: '1. Suas conversas',
            texto: 'Aqui ficam suas conversas. Use a busca para encontrar uma pelo nome.',
            alvo: 'chat-sidebar'
        },
        {
            titulo: '2. Nova conversa',
            texto: 'Clique em “Nova” para iniciar uma nova conversa.',
            alvo: 'chat-new-button'
        },
        {
            titulo: '3. Busque um profissional',
            texto: 'Digite o nome do profissional que você procura. Deixamos “' + exemplo + '” preenchido como exemplo.',
            alvos: ['chat-provider-search', 'chat-provider-exemplo'],
            abrirModal: true,
            preencherBusca: true,
            posicaoFixa: 'inferior'
        },
        {
            titulo: '4. Acompanhe a conversa',
            texto: 'Escreva sua mensagem embaixo e acompanhe as respostas aqui.',
            alvo: 'chat-main',
            fecharModal: true,
            conversaExemplo: true
        }
    ];

    let iniciado = false;
    let indice = 0;
    let raiz;
    let holo;
    let card;
    let titulo;
    let texto;
    let progresso;
    let botaoVoltar;
    let botaoProximo;
    let botaoPular;
    let botaoFechar;
    let observerLista = null;

    function visivel(elemento) {
        if (!elemento) {
            return false;
        }
        const estilo = window.getComputedStyle(elemento);
        const rect = elemento.getBoundingClientRect();
        return estilo.display !== 'none' && estilo.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    }

    function posicionarCartao(rect) {
        const margem = 16;
        const distancia = 18;
        const larguraTela = window.innerWidth;
        const alturaTela = window.innerHeight;
        const etapa = etapas[indice];

        if (etapa && etapa.posicaoFixa === 'inferior') {
            const largura = Math.min(520, larguraTela - margem * 2);
            card.style.width = largura + 'px';
            const altura = card.offsetHeight;
            card.style.left = margem + 'px';
            card.style.top = Math.max(margem, alturaTela - altura - margem) + 'px';
            return;
        }

        const candidatos = [];

        [520, 360].forEach((larguraDesejada) => {
            const largura = Math.min(larguraDesejada, larguraTela - margem * 2);
            card.style.width = largura + 'px';
            const altura = card.offsetHeight;

            const posicoes = rect
                ? [
                    [rect.left + rect.width / 2 - largura / 2, rect.bottom + distancia],
                    [rect.left + rect.width / 2 - largura / 2, rect.top - distancia - altura],
                    [rect.right + distancia, rect.top + rect.height / 2 - altura / 2],
                    [rect.left - distancia - largura, rect.top + rect.height / 2 - altura / 2],
                    [margem, margem],
                    [larguraTela - largura - margem, margem],
                    [margem, alturaTela - altura - margem],
                    [larguraTela - largura - margem, alturaTela - altura - margem]
                ]
                : [[(larguraTela - largura) / 2, (alturaTela - altura) / 2]];

            posicoes.forEach((posicao) => {
                candidatos.push({ left: posicao[0], top: posicao[1], largura, altura });
            });
        });

        const pontuacao = (candidato) => {
            let sobreposicao = 0;
            if (rect) {
                const x = Math.max(0, Math.min(candidato.left + candidato.largura, rect.right) - Math.max(candidato.left, rect.left));
                const y = Math.max(0, Math.min(candidato.top + candidato.altura, rect.bottom) - Math.max(candidato.top, rect.top));
                sobreposicao = x * y;
            }

            const fora =
                Math.max(0, margem - candidato.left) +
                Math.max(0, candidato.left + candidato.largura - (larguraTela - margem)) +
                Math.max(0, margem - candidato.top) +
                Math.max(0, candidato.top + candidato.altura - (alturaTela - margem));

            const preferenciaTopo = rect && candidato.top > alturaTela / 2 ? 20000 : 0;
            return fora * 1000000 + sobreposicao + preferenciaTopo;
        };

        let melhor = candidatos[0];
        let melhorPontuacao = pontuacao(melhor);
        candidatos.slice(1).forEach((candidato) => {
            const atual = pontuacao(candidato);
            if (atual < melhorPontuacao) {
                melhor = candidato;
                melhorPontuacao = atual;
            }
        });

        card.style.width = melhor.largura + 'px';
        card.style.left = Math.max(margem, melhor.left) + 'px';
        card.style.top = Math.max(margem, melhor.top) + 'px';
    }

    function destacar(nome) {
        const nomes = nome === null || nome === undefined ? [] : (Array.isArray(nome) ? nome : [nome]);
        if (nomes.length === 0) {
            holo.style.display = 'block';
            holo.style.left = '0';
            holo.style.top = '0';
            holo.style.width = '100vw';
            holo.style.height = '100vh';
            holo.style.border = '0';
            holo.style.borderRadius = '0';
            holo.style.background = 'rgba(0,0,0,.68)';
            holo.style.boxShadow = 'none';
            posicionarCartao(null);
            return;
        }

        const retangulos = [];
        nomes.forEach((item) => {
            const candidatos = Array.from(document.querySelectorAll('[data-tour="' + item + '"]'));
            const alvo = candidatos.find(visivel) || candidatos[0];
            if (visivel(alvo)) {
                retangulos.push(alvo.getBoundingClientRect());
            }
        });

        if (retangulos.length === 0) {
            destacar(null);
            return;
        }

        const esquerda = Math.min(...retangulos.map((item) => item.left));
        const direita = Math.max(...retangulos.map((item) => item.right));
        const topo = Math.min(...retangulos.map((item) => item.top));
        const fundo = Math.max(...retangulos.map((item) => item.bottom));
        const rect = {
            left: esquerda,
            top: topo,
            right: direita,
            bottom: fundo,
            width: direita - esquerda,
            height: fundo - topo
        };
        const espaco = 8;
        holo.style.display = 'block';
        holo.style.left = Math.max(4, rect.left - espaco) + 'px';
        holo.style.top = Math.max(4, rect.top - espaco) + 'px';
        holo.style.width = rect.width + espaco * 2 + 'px';
        holo.style.height = rect.height + espaco * 2 + 'px';
        holo.style.border = '2px solid #fff';
        holo.style.borderRadius = '14px';
        holo.style.background = 'transparent';
        holo.style.boxShadow = '0 0 0 3px #b91c1c, 0 0 0 9999px rgba(0,0,0,.68)';
        posicionarCartao(rect);
    }

    function abrirModalExemplo() {
        if (newModal && newModal.classList.contains('hidden') && newButton) {
            newButton.click();
        }
    }

    function fecharModalExemplo() {
        if (newModal && !newModal.classList.contains('hidden') && newCancel) {
            newCancel.click();
        }
    }

    function preencherBuscaExemplo() {
        if (!providerSearch) {
            return;
        }
        providerSearch.value = exemplo;
        providerSearch.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function render() {
        const etapa = etapas[indice];
        titulo.textContent = etapa.titulo;
        texto.textContent = etapa.texto;
        const etapasNumeradas = etapas.filter((item) => !item.boasVindas);
        if (etapa.boasVindas) {
            progresso.textContent = 'Introdução';
        } else {
            const numero = etapas.slice(0, indice + 1).filter((item) => !item.boasVindas).length;
            progresso.textContent = 'Passo ' + numero + ' de ' + etapasNumeradas.length;
        }
        botaoVoltar.style.visibility = indice === 0 ? 'hidden' : 'visible';
        botaoProximo.textContent = indice === etapas.length - 1 ? 'Concluir' : 'Próximo';

        if (etapa.abrirModal) {
            abrirModalExemplo();
        }
        if (etapa.preencherBusca) {
            preencherBuscaExemplo();
        }
        if (typeof window.roppinChatConversaExemplo === 'function') {
            window.roppinChatConversaExemplo(Boolean(etapa.conversaExemplo));
        }

        window.setTimeout(() => destacar(etapa.alvos || etapa.alvo), 0);
    }

    function finalizar() {
        if (observerLista) {
            observerLista.disconnect();
        }
        if (typeof window.roppinChatEncerrarDemo === 'function') {
            window.roppinChatEncerrarDemo();
        }
        const seguro = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE + '=1; Path=/; Max-Age=31536000; SameSite=Lax' + seguro;
        fecharModalExemplo();
        raiz.remove();
        document.removeEventListener('keydown', aoTeclar);
        window.removeEventListener('resize', redesenhar);
    }

    function sairDaEtapa() {
        const etapa = etapas[indice];
        if (etapa.abrirModal || etapa.preencherBusca || etapa.fecharModal) {
            fecharModalExemplo();
        }
    }

    function proximo() {
        if (indice === etapas.length - 1) {
            finalizar();
            return;
        }
        sairDaEtapa();
        indice += 1;
        render();
    }

    function anterior() {
        if (indice === 0) {
            return;
        }
        sairDaEtapa();
        indice -= 1;
        render();
    }

    function aoTeclar(event) {
        if (event.key === 'Escape') {
            finalizar();
        } else if (event.key === 'ArrowRight') {
            proximo();
        } else if (event.key === 'ArrowLeft') {
            anterior();
        }
    }

    function redesenhar() {
        const etapa = etapas[indice];
        destacar(etapa.alvos || etapa.alvo);
    }

    function iniciar() {
        if (iniciado) {
            return;
        }
        iniciado = true;
        fecharModalExemplo();

        raiz = document.createElement('div');
        raiz.id = 'roppin-chat-tutorial';
        raiz.innerHTML = `
            <div data-chat-tour-fundo style="position:fixed;inset:0;z-index:1000"></div>
            <div data-chat-tour-holo style="position:fixed;z-index:1001;pointer-events:none;display:none;transition:all .25s ease"></div>
            <div data-chat-tour-card role="dialog" aria-modal="true" aria-labelledby="roppin-chat-tutorial-titulo" style="position:fixed;left:16px;top:16px;z-index:1002;width:calc(100% - 32px);max-width:520px;background:#fff;border-radius:16px;padding:20px;box-shadow:0 20px 50px rgba(0,0,0,.35);text-align:left">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px">
                    <span data-chat-tour-progresso style="font-size:12px;font-weight:800;color:#b91c1c;text-transform:uppercase;letter-spacing:.05em"></span>
                    <button type="button" data-chat-tour-fechar aria-label="Fechar tutorial" style="border:0;background:transparent;color:#6b7280;font-size:22px;line-height:1;cursor:pointer">&times;</button>
                </div>
                <h2 id="roppin-chat-tutorial-titulo" data-chat-tour-titulo style="font-size:20px;font-weight:800;color:#111827;margin:0 0 8px"></h2>
                <p data-chat-tour-texto style="font-size:14px;line-height:1.5;color:#4b5563;margin:0 0 16px"></p>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                    <button type="button" data-chat-tour-voltar style="border:1px solid #d1d5db;background:#fff;color:#374151;border-radius:9999px;padding:8px 16px;font-size:14px;font-weight:700;cursor:pointer">Voltar</button>
                    <div style="display:flex;align-items:center;gap:8px">
                        <button type="button" data-chat-tour-pular style="border:0;background:transparent;color:#6b7280;font-size:14px;font-weight:600;cursor:pointer">Pular</button>
                        <button type="button" data-chat-tour-proximo style="border:0;background:#b91c1c;color:#fff;border-radius:9999px;padding:8px 20px;font-size:14px;font-weight:700;cursor:pointer">Próximo</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(raiz);

        holo = raiz.querySelector('[data-chat-tour-holo]');
        card = raiz.querySelector('[data-chat-tour-card]');
        titulo = raiz.querySelector('[data-chat-tour-titulo]');
        texto = raiz.querySelector('[data-chat-tour-texto]');
        progresso = raiz.querySelector('[data-chat-tour-progresso]');
        botaoVoltar = raiz.querySelector('[data-chat-tour-voltar]');
        botaoProximo = raiz.querySelector('[data-chat-tour-proximo]');
        botaoPular = raiz.querySelector('[data-chat-tour-pular]');
        botaoFechar = raiz.querySelector('[data-chat-tour-fechar]');

        if (providerList) {
            observerLista = new MutationObserver(() => {
                const etapa = etapas[indice];
                if (etapa && (etapa.alvo === 'chat-provider-search' || (Array.isArray(etapa.alvos) && etapa.alvos.includes('chat-provider-exemplo')))) {
                    destacar(etapa.alvos || etapa.alvo);
                }
            });
            observerLista.observe(providerList, { childList: true, subtree: true });
        }

        botaoProximo.addEventListener('click', proximo);
        botaoVoltar.addEventListener('click', anterior);
        botaoPular.addEventListener('click', finalizar);
        botaoFechar.addEventListener('click', finalizar);
        document.addEventListener('keydown', aoTeclar);
        window.addEventListener('resize', redesenhar);
        render();
    }

    if (!panel || !panel.hidden) {
        iniciar();
        return;
    }

    const observer = new MutationObserver(() => {
        if (!panel.hidden) {
            observer.disconnect();
            iniciar();
        }
    });
    observer.observe(panel, { attributes: true, attributeFilter: ['hidden'] });
})();
