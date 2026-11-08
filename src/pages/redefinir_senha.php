<?php
$token = trim($_GET['token'] ?? '');
$tokenValido = preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
$tokenEscapado = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Redefinir senha | Roppin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 text-gray-800">
    <main class="mx-auto flex min-h-screen max-w-lg items-center px-5 py-10">
        <section class="w-full rounded-lg bg-white p-6 shadow-sm sm:p-8">
            <a href="../../index.php" class="mb-8 inline-flex items-center gap-3 text-red-700" aria-label="Voltar para a Roppin">
                <img src="../../images/roppin_Logo_circulo.png" alt="" class="h-10 w-10 rounded-full object-cover">
                <span class="text-lg font-bold">Roppin</span>
            </a>

            <?php if ($tokenValido): ?>
                <h1 class="text-2xl font-bold">Criar nova senha</h1>
                <p class="mt-2 text-sm text-gray-600">Escolha uma senha com pelo menos oito caracteres.</p>

                <form id="reset-form" class="mt-6 space-y-4">
                    <input type="hidden" name="token" value="<?php echo $tokenEscapado; ?>">
                    <div>
                        <label for="senha" class="mb-1 block text-sm font-semibold">Nova senha</label>
                        <input id="senha" name="senha" type="password" minlength="8" autocomplete="new-password" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                    <div>
                        <label for="confirmar-senha" class="mb-1 block text-sm font-semibold">Confirme a nova senha</label>
                        <input id="confirmar-senha" type="password" minlength="8" autocomplete="new-password" required class="w-full rounded border border-gray-300 px-3 py-2 focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
                    </div>
                    <button type="submit" class="w-full rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Salvar nova senha</button>
                </form>
                <p id="reset-message" class="mt-4 text-sm" role="status" aria-live="polite"></p>
            <?php else: ?>
                <h1 class="text-2xl font-bold">Link inválido</h1>
                <p class="mt-2 text-gray-600">Solicite uma nova recuperação de senha pela página inicial.</p>
                <a href="../../index.php" class="mt-6 inline-block rounded bg-red-700 px-5 py-3 font-bold text-white hover:bg-red-800">Voltar à página inicial</a>
            <?php endif; ?>
        </section>
    </main>

    <?php if ($tokenValido): ?>
        <script>
            const resetForm = document.getElementById('reset-form');
            const resetMessage = document.getElementById('reset-message');

            resetForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const senha = document.getElementById('senha').value;
                const confirmarSenha = document.getElementById('confirmar-senha').value;

                if (senha !== confirmarSenha) {
                    resetMessage.textContent = 'As senhas não conferem.';
                    resetMessage.className = 'mt-4 text-sm text-red-700';
                    return;
                }

                const dados = new FormData(resetForm);
                dados.set('acao', 'redefinir_senha');
                try {
                    const resposta = await fetch('../../processar.php', { method: 'POST', body: dados });
                    const resultado = await resposta.json();
                    if (!resultado.sucesso) {
                        resetMessage.textContent = resultado.mensagem;
                        resetMessage.className = 'mt-4 text-sm text-red-700';
                        return;
                    }

                    sessionStorage.setItem('notificacao_pos_reload', JSON.stringify(resultado));
                    window.location.href = '../../index.php';
                } catch (error) {
                    resetMessage.textContent = 'Não foi possível redefinir a senha agora.';
                    resetMessage.className = 'mt-4 text-sm text-red-700';
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>