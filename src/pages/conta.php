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
        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-red-700">Perfil</p>
            <h1 class="mt-1 text-3xl font-bold">Editar seus dados</h1>
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

                <div>
                    <label for="senha" class="mb-1 block text-sm font-semibold">Nova senha <span class="font-normal text-gray-500">(opcional)</span></label>
                    <input id="senha" name="senha" type="password" autocomplete="new-password" placeholder="Deixe em branco para manter a senha atual" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                </div>

                <button type="submit" class="rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Salvar alterações</button>
            </form>
        </section>

        <section class="mt-8 border-t border-gray-300 pt-6">
            <h2 class="text-lg font-bold text-gray-900">Excluir conta</h2>
            <p class="mt-1 text-sm text-gray-600">A exclusão é permanente e encerra sua sessão.</p>
            <form id="delete-form" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="rounded border border-red-700 px-4 py-2 font-semibold text-red-700 hover:bg-red-50">Excluir minha conta</button>
            </form>
        </section>
    </main>

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

        document.getElementById('account-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const dados = new FormData(event.currentTarget);
            dados.set('acao', 'atualizar_usuario');

            try {
                const resposta = await fetch('../../processar.php', { method: 'POST', body: dados });
                const resultado = await resposta.json();
                if (!resultado.sucesso) {
                    mostrarMensagem(resultado.mensagem, false);
                    return;
                }

                sessionStorage.setItem('notificacao_pos_reload', JSON.stringify(resultado));
                window.location.reload();
            } catch (erro) {
                mostrarMensagem('Não foi possível salvar as alterações.', false);
            }
        });

        document.getElementById('delete-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm('Tem certeza que deseja excluir sua conta? Esta ação não pode ser desfeita.')) {
                return;
            }

            const dados = new FormData(event.currentTarget);
            dados.set('acao', 'excluir_usuario');

            try {
                const resposta = await fetch('../../processar.php', { method: 'POST', body: dados });
                const resultado = await resposta.json();
                if (!resultado.sucesso) {
                    mostrarMensagem(resultado.mensagem, false);
                    return;
                }

                sessionStorage.setItem('notificacao_pos_reload', JSON.stringify(resultado));
                window.location.href = '../../index.php';
            } catch (erro) {
                mostrarMensagem('Não foi possível excluir a conta.', false);
            }
        });
    </script>
</body>
</html>