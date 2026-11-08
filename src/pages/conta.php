<?php
require_once __DIR__ . '/../models/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
                <p class="mt-2 text-gray-600">Configure em que áreas você consegue trabalhar.</p>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <form id="provider-form" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                        <label for="provider-specialty" class="mb-1 block text-sm font-semibold">Especialidade</label>
                        <select id="provider-specialty" name="idEspecialidade" required class="w-full rounded border border-gray-300 bg-white px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                            <option value="">Selecione uma especialidade cadastrada</option>
                        </select>
                    </div>
                    <div>
                        <label for="valor-hora" class="mb-1 block text-sm font-semibold">Valor por hora (R$)</label>
                        <input id="valor-hora" name="valor_hora" type="number" min="0.01" max="99999999.99" step="0.01" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                    <p id="provider-message" class="hidden text-sm" role="status" aria-live="polite"></p>
                    <button id="provider-submit" type="submit" disabled class="rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50">Cadastrar como prestador</button>
                </form>
            </section>
        </section>

        <section id="panel-specialties" role="tabpanel" aria-labelledby="tab-specialties" tabindex="0" hidden>
            <div class="mb-6">
                <h2 class="text-2xl font-bold">Gerenciar especialidades</h2>
                <p class="mt-2 text-gray-600">As especialidades cadastradas aparecem no formulário de prestador.</p>
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                <form id="specialty-form" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="idEspecialidade" value="">
                    <div>
                        <label for="specialty-name" class="mb-1 block text-sm font-semibold">Nome da especialidade</label>
                        <input id="specialty-name" name="nomeEspecialidade" type="text" maxlength="100" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button id="specialty-submit" type="submit" class="rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Cadastrar especialidade</button>
                        <button id="specialty-cancel-edit" type="button" class="hidden rounded border border-gray-300 px-4 py-3 font-semibold text-gray-700 hover:bg-gray-50">Cancelar edição</button>
                    </div>
                    <p id="specialty-message" class="hidden text-sm" role="status" aria-live="polite"></p>
                </form>
            </section>

            <ul id="specialty-list" class="mt-6 divide-y divide-gray-200 rounded-lg bg-white shadow-sm" aria-live="polite"></ul>
        </section>
    </main>

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
        const specialtyForm = document.getElementById('specialty-form');
        const specialtyList = document.getElementById('specialty-list');
        const specialtyMessage = document.getElementById('specialty-message');
        const specialtySubmit = document.getElementById('specialty-submit');
        const specialtyCancelEdit = document.getElementById('specialty-cancel-edit');
        const providerForm = document.getElementById('provider-form');
        const providerSpecialty = document.getElementById('provider-specialty');
        const providerSubmit = document.getElementById('provider-submit');
        const providerMessage = document.getElementById('provider-message');
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

        function setInlineMessage(element, message, success = false) {
            element.textContent = message;
            element.classList.remove('hidden', 'text-red-700', 'text-green-700');
            element.classList.add(success ? 'text-green-700' : 'text-red-700');
        }

        function resetSpecialtyForm() {
            specialtyForm.reset();
            specialtyForm.elements.idEspecialidade.value = '';
            specialtySubmit.textContent = 'Cadastrar especialidade';
            specialtyCancelEdit.classList.add('hidden');
        }

        async function sendFormAction(form, action) {
            const data = new FormData(form);
            data.set('acao', action);
            const response = await fetch('../../processar.php', { method: 'POST', body: data });
            return response.json();
        }

        function createSpecialtyRow(specialty) {
            const row = document.createElement('li');
            row.className = 'flex flex-wrap items-center justify-between gap-3 px-5 py-4';

            const label = document.createElement('span');
            label.className = 'font-medium text-gray-800';
            label.textContent = `#${specialty.idEspecialidade} · ${specialty.nomeEspecialidade}`;

            const actions = document.createElement('div');
            actions.className = 'flex items-center gap-2';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'rounded px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50';
            editButton.textContent = 'Editar';
            editButton.addEventListener('click', () => {
                specialtyForm.elements.idEspecialidade.value = specialty.idEspecialidade;
                specialtyForm.elements.nomeEspecialidade.value = specialty.nomeEspecialidade;
                specialtySubmit.textContent = 'Salvar especialidade';
                specialtyCancelEdit.classList.remove('hidden');
                specialtyForm.elements.nomeEspecialidade.focus();
            });

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'rounded px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50';
            deleteButton.textContent = 'Excluir';
            deleteButton.addEventListener('click', async () => {
                if (!window.confirm(`Excluir a especialidade "${specialty.nomeEspecialidade}"?`)) {
                    return;
                }

                const data = new FormData(specialtyForm);
                data.set('acao', 'excluir_especialidade');
                data.set('idEspecialidade', specialty.idEspecialidade);

                try {
                    const response = await fetch('../../processar.php', { method: 'POST', body: data });
                    const result = await response.json();
                    setInlineMessage(specialtyMessage, result.mensagem, result.sucesso);
                    if (result.sucesso) {
                        await loadSpecialties();
                    }
                } catch (error) {
                    setInlineMessage(specialtyMessage, 'Não foi possível excluir a especialidade.');
                }
            });

            actions.append(editButton, deleteButton);
            row.append(label, actions);
            return row;
        }

        async function loadSpecialties() {
            const data = new FormData();
            data.set('acao', 'listar_especialidades');

            try {
                const response = await fetch('../../processar.php', { method: 'POST', body: data });
                const result = await response.json();
                if (!result.sucesso) {
                    setInlineMessage(specialtyMessage, result.mensagem);
                    return;
                }

                specialtyList.replaceChildren();
                providerSpecialty.replaceChildren();
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Selecione uma especialidade cadastrada';
                providerSpecialty.append(emptyOption);

                result.especialidades.forEach((specialty) => {
                    specialtyList.append(createSpecialtyRow(specialty));
                    const option = document.createElement('option');
                    option.value = specialty.idEspecialidade;
                    option.textContent = `#${specialty.idEspecialidade} · ${specialty.nomeEspecialidade}`;
                    providerSpecialty.append(option);
                });

                providerSubmit.disabled = result.especialidades.length === 0;
                if (result.especialidades.length === 0) {
                    const emptyState = document.createElement('li');
                    emptyState.className = 'px-5 py-4 text-sm text-gray-600';
                    emptyState.textContent = 'Nenhuma especialidade cadastrada.';
                    specialtyList.append(emptyState);
                }
            } catch (error) {
                setInlineMessage(specialtyMessage, 'Não foi possível carregar as especialidades.');
            }
        }

        specialtyForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const updating = specialtyForm.elements.idEspecialidade.value !== '';

            try {
                const result = await sendFormAction(specialtyForm, updating ? 'atualizar_especialidade' : 'criar_especialidade');
                setInlineMessage(specialtyMessage, result.mensagem, result.sucesso);
                if (result.sucesso) {
                    resetSpecialtyForm();
                    await loadSpecialties();
                }
            } catch (error) {
                setInlineMessage(specialtyMessage, 'Não foi possível salvar a especialidade.');
            }
        });

        specialtyCancelEdit.addEventListener('click', resetSpecialtyForm);

        providerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const result = await sendFormAction(providerForm, 'cadastrar_prestador');
                setInlineMessage(providerMessage, result.mensagem, result.sucesso);
                if (result.sucesso) {
                    providerSubmit.disabled = true;
                }
            } catch (error) {
                setInlineMessage(providerMessage, 'Não foi possível concluir o cadastro de prestador.');
            }
        });

        loadSpecialties();

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