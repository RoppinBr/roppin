<?php
require_once __DIR__ . '/../scripts/auth_session.php';
startRoppinSession();

if (!isset($_SESSION['usuario_logado']) || !($_SESSION['usuario_logado'] instanceof User)) {
    header('Location: ../../index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$usuario = $_SESSION['usuario_logado'];
$csrfToken = $_SESSION['csrf_token'];
$nome = htmlspecialchars((string) $usuario->getNome(), ENT_QUOTES, 'UTF-8');
$cpf = htmlspecialchars((string) $usuario->getCpf(), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars((string) $usuario->getEmail(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha conta | Roppin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 text-gray-800">
    <header class="bg-red-700 text-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4">
            <a href="../../index.php" class="flex items-center gap-3" aria-label="Voltar para a Roppin">
                <img src="../../images/roppin_Logo_circulo.png" alt="Roppin" class="h-10 w-10 rounded-full object-cover">
                <span class="text-lg font-bold">Minha conta</span>
            </a>
            <a href="../../index.php" class="rounded px-3 py-2 text-sm font-semibold hover:bg-red-600">Voltar ao início</a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-5 py-10">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-red-700">Perfil</p>
            <h1 class="mt-1 text-3xl font-bold">Minha conta</h1>
        </div>

        <div id="account-tabs" role="tablist" aria-label="Seções da conta" class="mb-8 flex border-b border-gray-300">
            <button id="tab-account" type="button" role="tab" aria-selected="true" aria-controls="panel-account" tabindex="0" class="border-b-2 border-red-700 px-4 py-3 text-sm font-semibold text-red-700 transition-colors">Dados da conta</button>
            <button id="tab-provider" type="button" role="tab" aria-selected="false" aria-controls="panel-provider" tabindex="-1" class="border-b-2 border-transparent px-4 py-3 text-sm font-semibold text-gray-500 transition-colors hover:text-gray-800">Cadastro de prestador</button>
        </div>

        <section id="panel-account" role="tabpanel" aria-labelledby="tab-account" tabindex="0">
            <div class="mb-6">
                <h2 class="text-2xl font-bold">Editar seus dados</h2>
                <p class="mt-2 text-gray-600">Atualize as informações vinculadas à sua conta.</p>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <form id="account-form" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div>
                        <label for="nome" class="mb-1 block text-sm font-semibold">Nome completo</label>
                        <input id="nome" name="nome" type="text" value="<?php echo $nome; ?>" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>

                    <div>
                        <label for="cpf" class="mb-1 block text-sm font-semibold">CPF ou CNPJ</label>
                        <input id="cpf" name="cpf" type="text" value="<?php echo $cpf; ?>" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-semibold">E-mail</label>
                        <input id="email" name="email" type="email" value="<?php echo $email; ?>" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>

                    <details class="rounded border border-gray-200">
                        <summary class="cursor-pointer px-4 py-3 font-semibold text-red-700">Alterar senha</summary>
                        <div class="space-y-4 border-t border-gray-200 p-4">
                            <p class="text-sm text-gray-600">A senha precisa ter pelo menos 8 caracteres. Deixe os campos vazios para manter a senha atual.</p>
                            <div>
                                <label for="senha" class="mb-1 block text-sm font-semibold">Nova senha</label>
                                <input id="senha" name="senha" type="password" minlength="8" autocomplete="new-password" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                            </div>
                            <div>
                                <label for="confirmar-senha" class="mb-1 block text-sm font-semibold">Confirmar nova senha</label>
                                <input id="confirmar-senha" name="confirmar_senha" type="password" minlength="8" autocomplete="new-password" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                            </div>
                        </div>
                    </details>

                    <button type="submit" class="rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Salvar alterações</button>
                </form>
            </section>

            <section class="mt-8 border-t border-gray-300 pt-6">
                <h3 class="text-lg font-bold text-gray-900">Excluir conta</h3>
                <p class="mt-1 text-sm text-gray-600">A exclusão é permanente e encerra sua sessão. Será necessário confirmar sua senha atual.</p>
                <form id="delete-form" class="mt-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="rounded border border-red-700 px-4 py-2 font-semibold text-red-700 hover:bg-red-50">Excluir minha conta</button>
                </form>
            </section>
        </section>

        <section id="panel-provider" role="tabpanel" aria-labelledby="tab-provider" tabindex="0" hidden>
            <div class="mb-6">
                <h2 class="text-2xl font-bold">Cadastro de prestador</h2>
                <p class="mt-2 text-gray-600">Configure seu perfil de atendimento.</p>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <form id="provider-form" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="valor-hora" class="mb-1 block text-sm font-semibold">Valor cobrado (R$)</label>
                            <input id="valor-hora" name="valor" type="number" min="0.01" max="99999999.99" step="0.01" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                        </div>
                        <div>
                            <label for="periodo-cobranca" class="mb-1 block text-sm font-semibold">Período de cobrança</label>
                            <select id="periodo-cobranca" name="periodo_cobranca" required class="w-full rounded border border-gray-300 bg-white px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                                <option value="hora">Por hora</option>
                                <option value="dia">Por dia</option>
                                <option value="servico">Por serviço</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-5">
                        <h3 class="text-lg font-bold">Especialidade</h3>
                        <div id="provider-specialty-pills" class="mt-3 flex flex-wrap gap-2"></div>
                        <button id="edit-provider-specialties" type="button" class="mt-4 rounded border border-red-700 px-4 py-2 font-semibold text-red-700 hover:bg-red-50">Adicionar especialidades</button>
                    </div>

                    <p id="provider-message" class="hidden text-sm" role="status" aria-live="polite"></p>
                    <button id="provider-submit" type="submit" class="rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Cadastrar como prestador</button>
                </form>
            </section>
        </section>
    </main>

    <div id="provider-specialty-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
        <section role="dialog" aria-modal="true" aria-labelledby="provider-specialty-modal-title" class="flex max-h-[90vh] w-full max-w-xl flex-col rounded-lg bg-white shadow-xl">
            <header class="border-b border-gray-200 p-5 sm:p-6">
                <h2 id="provider-specialty-modal-title" class="text-xl font-bold">Editar especialidades</h2>
                <label for="provider-specialty-filter" class="sr-only">Buscar especialidade</label>
                <input id="provider-specialty-filter" type="search" autocomplete="off" placeholder="Buscar especialidade" class="mt-4 w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">
                <ul id="provider-specialty-options" class="divide-y divide-gray-100 rounded border border-gray-200" aria-label="Especialidades"></ul>
                <p id="provider-specialty-modal-message" class="mt-3 hidden text-sm" role="status" aria-live="polite"></p>
            </div>
            <footer class="flex justify-end gap-3 border-t border-gray-200 p-5 sm:px-6">
                <button id="cancel-provider-specialties" type="button" class="rounded border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button id="save-provider-specialties" type="button" class="rounded bg-red-700 px-4 py-2 font-bold text-white hover:bg-red-800">Salvar especialidades</button>
            </footer>
        </section>
    </div>

    <div id="confirmation-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
        <section role="dialog" aria-modal="true" aria-labelledby="confirmation-title" aria-describedby="confirmation-description" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h2 id="confirmation-title" class="text-xl font-bold text-gray-900"></h2>
            <p id="confirmation-description" class="mt-2 text-sm text-gray-600"></p>

            <div id="delete-password-fields" class="mt-5 hidden">
                <label for="delete-password" class="mb-1 block text-sm font-semibold">Senha atual</label>
                <input id="delete-password" type="password" autocomplete="current-password" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
            </div>

            <p id="confirmation-message" class="mt-4 hidden text-sm text-red-700" role="alert"></p>

            <div class="mt-6 flex justify-end gap-3">
                <button id="cancel-confirmation" type="button" class="rounded border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button id="confirm-account-action" type="button" class="rounded bg-red-700 px-4 py-2 font-bold text-white hover:bg-red-800">Confirmar</button>
            </div>
        </section>
    </div>

    <script>
        function mostrarMensagem(mensagem, sucesso) {
            const aviso = document.createElement('div');
            aviso.className = `fixed bottom-5 right-5 z-50 max-w-sm rounded px-5 py-3 text-white shadow-lg ${sucesso ? 'bg-green-700' : 'bg-red-700'}`;
            aviso.textContent = mensagem;
            document.body.appendChild(aviso);
            setTimeout(() => aviso.remove(), 4000);
        }

        const notificacao = sessionStorage.getItem('notificacao_pos_reload');
        if (notificacao) {
            const dados = JSON.parse(notificacao);
            mostrarMensagem(dados.mensagem, dados.sucesso);
            sessionStorage.removeItem('notificacao_pos_reload');
        }

        const accountForm = document.getElementById('account-form');
        const deleteForm = document.getElementById('delete-form');
        const providerForm = document.getElementById('provider-form');
        const providerSubmit = document.getElementById('provider-submit');
        const providerMessage = document.getElementById('provider-message');
        const providerRateInput = document.getElementById('valor-hora');
        const providerBillingPeriod = document.getElementById('periodo-cobranca');
        const providerSpecialtyPills = document.getElementById('provider-specialty-pills');
        const editProviderSpecialties = document.getElementById('edit-provider-specialties');
        const providerSpecialtyModal = document.getElementById('provider-specialty-modal');
        const providerSpecialtyFilter = document.getElementById('provider-specialty-filter');
        const providerSpecialtyOptions = document.getElementById('provider-specialty-options');
        const providerSpecialtyModalMessage = document.getElementById('provider-specialty-modal-message');
        const cancelProviderSpecialties = document.getElementById('cancel-provider-specialties');
        const saveProviderSpecialties = document.getElementById('save-provider-specialties');
        const accountTabs = Array.from(document.querySelectorAll('#account-tabs [role="tab"]'));
        const accountPanels = accountTabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));
        const confirmationModal = document.getElementById('confirmation-modal');
        const confirmationTitle = document.getElementById('confirmation-title');
        const confirmationDescription = document.getElementById('confirmation-description');
        const confirmationMessage = document.getElementById('confirmation-message');
        const deletePasswordFields = document.getElementById('delete-password-fields');
        const deletePassword = document.getElementById('delete-password');
        const cancelConfirmation = document.getElementById('cancel-confirmation');
        const confirmAccountAction = document.getElementById('confirm-account-action');
        let pendingAction = null;
        let submitting = false;
        let allSpecialties = [];
        let savedSpecialtyIds = [];
        let draftSpecialtyIds = new Set();
        let draftNewSpecialties = [];
        let savingSpecialties = false;
        let providerRegistered = false;

        function activateTab(index, moveFocus = false) {
            accountTabs.forEach((tab, tabIndex) => {
                const selected = tabIndex === index;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                tab.classList.toggle('border-red-700', selected);
                tab.classList.toggle('text-red-700', selected);
                tab.classList.toggle('border-transparent', !selected);
                tab.classList.toggle('text-gray-500', !selected);
                accountPanels[tabIndex].hidden = !selected;
            });

            if (moveFocus) {
                accountTabs[index].focus();
            }
        }

        accountTabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activateTab(index));
            tab.addEventListener('keydown', (event) => {
                let nextIndex = index;
                if (event.key === 'ArrowRight') {
                    nextIndex = (index + 1) % accountTabs.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (index - 1 + accountTabs.length) % accountTabs.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = accountTabs.length - 1;
                } else {
                    return;
                }

                event.preventDefault();
                activateTab(nextIndex, true);
            });
        });

        const requestedTab = new URLSearchParams(window.location.search).get('aba');
        if (requestedTab === 'prestador') {
            const providerTabIndex = accountTabs.findIndex((tab) => tab.id === 'tab-provider');
            if (providerTabIndex !== -1) {
                activateTab(providerTabIndex);
            }
        }

        function setInlineMessage(element, message, success = false) {
            element.textContent = message;
            element.classList.remove('hidden', 'text-red-700', 'text-green-700');
            element.classList.add(success ? 'text-green-700' : 'text-red-700');
        }

        async function postAction(action, values = {}) {
            const data = new FormData();
            data.set('acao', action);
            Object.entries(values).forEach(([key, value]) => data.set(key, String(value)));
            const response = await fetch('../../processar.php', { method: 'POST', body: data });
            return response.json();
        }

        function normalizeSpecialtyName(name) {
            return name.trim().toLocaleLowerCase('pt-BR');
        }

        function showProviderSpecialtyModalMessage(message, success = false) {
            providerSpecialtyModalMessage.textContent = message;
            providerSpecialtyModalMessage.classList.remove('hidden', 'text-red-700', 'text-green-700');
            providerSpecialtyModalMessage.classList.add(success ? 'text-green-700' : 'text-red-700');
        }

        async function loadSpecialties() {
            try {
                const result = await postAction('listar_especialidades');
                if (!result.sucesso) {
                    throw new Error(result.mensagem);
                }

                allSpecialties = result.especialidades.slice().sort((first, second) => first.nomeEspecialidade.localeCompare(second.nomeEspecialidade, 'pt-BR'));
                renderProviderSpecialtyPills();
            } catch (error) {
                setInlineMessage(providerMessage, error.message || 'Não foi possível carregar as especialidades.');
            }
        }

        function renderProviderSpecialtyPills() {
            providerSpecialtyPills.replaceChildren();
            const selected = allSpecialties.filter((specialty) => savedSpecialtyIds.includes(Number(specialty.idEspecialidade)));

            if (selected.length === 0) {
                const emptyState = document.createElement('span');
                emptyState.className = 'text-sm text-gray-500';
                emptyState.textContent = 'Nenhuma especialidade cadastrada';
                providerSpecialtyPills.append(emptyState);
                editProviderSpecialties.textContent = 'Adicionar especialidades';
                return;
            }

            selected.forEach((specialty) => {
                const pill = document.createElement('span');
                pill.className = 'inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-800';
                pill.textContent = specialty.nomeEspecialidade;
                providerSpecialtyPills.append(pill);
            });
            editProviderSpecialties.textContent = 'Editar especialidades';
        }

        async function loadProviderProfile() {
            try {
                const result = await postAction('perfil_prestador');
                if (!result.sucesso) {
                    throw new Error(result.mensagem);
                }

                providerRegistered = result.cadastrado;
                providerSubmit.textContent = providerRegistered ? 'Salvar alterações' : 'Cadastrar como prestador';
                if (result.cadastrado) {
                    providerRateInput.value = result.prestador.valor;
                    providerBillingPeriod.value = result.prestador.periodo_cobranca || 'hora';
                    savedSpecialtyIds = result.especialidades.map((specialty) => Number(specialty.idEspecialidade));
                    renderProviderSpecialtyPills();
                } else {
                    providerRateInput.value = '';
                    providerBillingPeriod.value = 'hora';
                    savedSpecialtyIds = [];
                    renderProviderSpecialtyPills();
                }
            } catch (error) {
                setInlineMessage(providerMessage, error.message || 'Não foi possível carregar o cadastro de prestador.');
            }
        }

        async function registerProvider() {
            try {
                const result = await postAction('cadastrar_prestador', {
                    csrf_token: providerForm.elements.csrf_token.value,
                    valor: providerRateInput.value,
                    periodo_cobranca: providerBillingPeriod.value,
                    especialidade_ids: JSON.stringify(savedSpecialtyIds)
                });
                setInlineMessage(providerMessage, result.mensagem, result.sucesso);
                if (!result.sucesso) {
                    return false;
                }

                await loadProviderProfile();
                await loadSpecialties();
                return true;
            } catch (error) {
                setInlineMessage(providerMessage, 'Não foi possível concluir o cadastro de prestador.');
                return false;
            }
        }

        function renderSpecialtyOptions() {
            const filter = normalizeSpecialtyName(providerSpecialtyFilter.value);
            const selected = allSpecialties.filter((specialty) => draftSpecialtyIds.has(Number(specialty.idEspecialidade)));
            const pending = draftNewSpecialties.slice().sort((first, second) => first.localeCompare(second, 'pt-BR'));
            const matches = allSpecialties.filter((specialty) =>
                !draftSpecialtyIds.has(Number(specialty.idEspecialidade)) &&
                normalizeSpecialtyName(specialty.nomeEspecialidade).includes(filter)
            );

            providerSpecialtyOptions.replaceChildren();
            const renderOption = (name, id, isSelected, isNew) => {
                const item = document.createElement('li');
                const option = document.createElement('button');
                option.type = 'button';
                option.setAttribute('aria-pressed', String(isSelected));
                option.className = `flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm transition-colors ${isSelected ? 'bg-red-50 font-semibold text-red-800' : 'text-gray-700 hover:bg-gray-50'}`;

                const label = document.createElement('span');
                label.textContent = name;
                const state = document.createElement('span');
                state.className = 'shrink-0 text-xs';
                state.textContent = isNew ? 'Nova · Remover' : (isSelected ? 'Selecionada' : 'Selecionar');
                option.append(label, state);
                option.addEventListener('click', () => {
                    if (isNew) {
                        draftNewSpecialties = draftNewSpecialties.filter((pendingName) => normalizeSpecialtyName(pendingName) !== normalizeSpecialtyName(name));
                    } else if (draftSpecialtyIds.has(Number(id))) {
                        draftSpecialtyIds.delete(Number(id));
                    } else {
                        draftSpecialtyIds.add(Number(id));
                    }
                    renderSpecialtyOptions();
                });
                item.append(option);
                providerSpecialtyOptions.append(item);
            };

            selected.forEach((specialty) => renderOption(specialty.nomeEspecialidade, specialty.idEspecialidade, true, false));
            pending.forEach((name) => renderOption(name, null, true, true));
            matches.forEach((specialty) => renderOption(specialty.nomeEspecialidade, specialty.idEspecialidade, false, false));

            if (providerSpecialtyOptions.childElementCount === 0) {
                const empty = document.createElement('li');
                empty.className = 'px-4 py-3 text-sm text-gray-500';
                empty.textContent = 'Nenhuma especialidade corresponde à busca.';
                providerSpecialtyOptions.append(empty);
            }

            const addItem = document.createElement('li');
            const addButton = document.createElement('button');
            addButton.id = 'add-filtered-specialty';
            addButton.type = 'button';
            addButton.className = 'w-full px-4 py-3 text-left text-sm font-semibold text-red-700 hover:bg-red-50';
            addButton.textContent = 'Adicionar especialidade';
            addItem.append(addButton);
            providerSpecialtyOptions.append(addItem);
        }

        function openProviderSpecialtyModal() {
            draftSpecialtyIds = new Set(savedSpecialtyIds);
            draftNewSpecialties = [];
            providerSpecialtyFilter.value = '';
            providerSpecialtyModalMessage.classList.add('hidden');
            renderSpecialtyOptions();
            providerSpecialtyModal.classList.remove('hidden');
            providerSpecialtyModal.classList.add('flex');
            providerSpecialtyModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            providerSpecialtyFilter.focus();
        }

        function closeProviderSpecialtyModal(force = false) {
            if (savingSpecialties && !force) {
                return;
            }
            providerSpecialtyModal.classList.add('hidden');
            providerSpecialtyModal.classList.remove('flex');
            providerSpecialtyModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        }

        providerSpecialtyFilter.addEventListener('input', renderSpecialtyOptions);
        editProviderSpecialties.addEventListener('click', openProviderSpecialtyModal);
        cancelProviderSpecialties.addEventListener('click', closeProviderSpecialtyModal);
        providerSpecialtyModal.addEventListener('click', (event) => {
            if (event.target === providerSpecialtyModal) {
                closeProviderSpecialtyModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !providerSpecialtyModal.classList.contains('hidden')) {
                closeProviderSpecialtyModal();
            }
        });

        function addFilteredSpecialtyToDraft() {
            const name = providerSpecialtyFilter.value.trim();
            const length = [...name].length;
            if (name === '' || length > 100) {
                showProviderSpecialtyModalMessage('Digite uma especialidade com até 100 caracteres.');
                return;
            }

            const existing = allSpecialties.find((specialty) => normalizeSpecialtyName(specialty.nomeEspecialidade) === normalizeSpecialtyName(name));
            if (existing) {
                draftSpecialtyIds.add(Number(existing.idEspecialidade));
            } else if (!draftNewSpecialties.some((pendingName) => normalizeSpecialtyName(pendingName) === normalizeSpecialtyName(name))) {
                draftNewSpecialties.push(name);
            }

            providerSpecialtyFilter.value = '';
            providerSpecialtyModalMessage.classList.add('hidden');
            renderSpecialtyOptions();
        }

        providerSpecialtyOptions.addEventListener('click', (event) => {
            if (event.target.closest('#add-filtered-specialty')) {
                addFilteredSpecialtyToDraft();
            }
        });

        saveProviderSpecialties.addEventListener('click', async () => {
            if (savingSpecialties) {
                return;
            }

            savingSpecialties = true;
            saveProviderSpecialties.disabled = true;
            try {
                const result = providerRegistered
                    ? await postAction('salvar_especialidades_prestador', {
                        csrf_token: providerForm.elements.csrf_token.value,
                        especialidade_ids: JSON.stringify([...draftSpecialtyIds]),
                        novas_especialidades: JSON.stringify(draftNewSpecialties)
                    })
                    : await postAction('criar_especialidades_rascunho', {
                        csrf_token: providerForm.elements.csrf_token.value,
                        novas_especialidades: JSON.stringify(draftNewSpecialties)
                    });

                if (!result.sucesso) {
                    showProviderSpecialtyModalMessage(result.mensagem);
                    return;
                }

                savedSpecialtyIds = providerRegistered
                    ? result.especialidades.map((specialty) => Number(specialty.idEspecialidade))
                    : [...new Set([
                        ...draftSpecialtyIds,
                        ...result.especialidades.map((specialty) => Number(specialty.idEspecialidade))
                    ])];
                await loadSpecialties();
                renderProviderSpecialtyPills();
                setInlineMessage(
                    providerMessage,
                    providerRegistered ? result.mensagem : 'Especialidades guardadas. Conclua o cadastro para vinculá-las ao perfil.',
                    true
                );
                savingSpecialties = false;
                closeProviderSpecialtyModal(true);
            } catch (error) {
                showProviderSpecialtyModalMessage('Não foi possível salvar as especialidades. Tente novamente.');
            } finally {
                savingSpecialties = false;
                saveProviderSpecialties.disabled = false;
            }
        });

        providerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await registerProvider();
        });

        loadSpecialties();
        loadProviderProfile();

        function openConfirmation(action) {
            pendingAction = action;
            const deleting = action === 'delete';
            confirmationTitle.textContent = deleting ? 'Excluir conta?' : 'Confirmar alterações?';
            confirmationDescription.textContent = deleting
                ? 'Essa ação é permanente. Confirme sua senha atual para excluir a conta.'
                : 'Deseja salvar as alterações feitas nos dados da sua conta?';
            confirmAccountAction.textContent = deleting ? 'Excluir conta' : 'Salvar alterações';
            confirmAccountAction.classList.toggle('bg-red-700', deleting);
            confirmAccountAction.classList.toggle('hover:bg-red-800', deleting);
            confirmAccountAction.classList.toggle('bg-gray-900', !deleting);
            confirmAccountAction.classList.toggle('hover:bg-gray-800', !deleting);
            deletePasswordFields.classList.toggle('hidden', !deleting);
            deletePassword.value = '';
            confirmationMessage.textContent = '';
            confirmationMessage.classList.add('hidden');
            confirmationModal.classList.remove('hidden');
            confirmationModal.classList.add('flex');
            confirmationModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            if (deleting) {
                deletePassword.focus();
            } else {
                confirmAccountAction.focus();
            }
        }

        function closeConfirmation() {
            if (submitting) {
                return;
            }
            confirmationModal.classList.add('hidden');
            confirmationModal.classList.remove('flex');
            confirmationModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
            pendingAction = null;
        }

        function showConfirmationMessage(message) {
            confirmationMessage.textContent = message;
            confirmationMessage.classList.remove('hidden');
        }

        accountForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const senha = accountForm.elements.senha.value;
            const confirmacaoSenha = accountForm.elements.confirmar_senha.value;

            if (senha !== '' && senha.length < 8) {
                mostrarMensagem('A nova senha precisa ter pelo menos 8 caracteres.', false);
                return;
            }

            if (senha !== confirmacaoSenha) {
                mostrarMensagem('A confirmação da nova senha não confere.', false);
                return;
            }

            openConfirmation('update');
        });

        deleteForm.addEventListener('submit', (event) => {
            event.preventDefault();
            openConfirmation('delete');
        });

        cancelConfirmation.addEventListener('click', closeConfirmation);
        confirmationModal.addEventListener('click', (event) => {
            if (event.target === confirmationModal) {
                closeConfirmation();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !confirmationModal.classList.contains('hidden')) {
                closeConfirmation();
            }
        });

        confirmAccountAction.addEventListener('click', async () => {
            if (!pendingAction || submitting) {
                return;
            }

            const deleting = pendingAction === 'delete';
            if (deleting && deletePassword.value === '') {
                showConfirmationMessage('Informe sua senha atual para excluir a conta.');
                deletePassword.focus();
                return;
            }

            const dados = new FormData(deleting ? deleteForm : accountForm);
            dados.set('acao', deleting ? 'excluir_usuario' : 'atualizar_usuario');
            if (deleting) {
                dados.set('senha_atual', deletePassword.value);
            }

            submitting = true;
            confirmAccountAction.disabled = true;
            cancelConfirmation.disabled = true;
            confirmAccountAction.classList.add('cursor-not-allowed', 'opacity-60');
            try {
                const resposta = await fetch('../../processar.php', { method: 'POST', body: dados });
                const resultado = await resposta.json();
                if (!resultado.sucesso) {
                    showConfirmationMessage(resultado.mensagem);
                    return;
                }

                sessionStorage.setItem('notificacao_pos_reload', JSON.stringify(resultado));
                if (deleting) {
                    window.location.href = '../../index.php';
                } else {
                    window.location.reload();
                }
            } catch (erro) {
                showConfirmationMessage('Não foi possível concluir a solicitação. Tente novamente.');
            } finally {
                submitting = false;
                confirmAccountAction.disabled = false;
                cancelConfirmation.disabled = false;
                confirmAccountAction.classList.remove('cursor-not-allowed', 'opacity-60');
            }
        });
    </script>
</body>
</html>