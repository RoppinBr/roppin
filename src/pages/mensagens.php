<?php
require_once __DIR__ . '/../scripts/auth_session.php';
startRoppinSession();

if (!chatEnabled()) {
    header('Location: ../../index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../scripts/conexao.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/ChatGuestSessionRepository.php';
require_once __DIR__ . '/../scripts/chat_guest.php';
require_once __DIR__ . '/../repositories/ConversationRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/ReviewRepository.php';
require_once __DIR__ . '/../services/ChatCryptoService.php';
require_once __DIR__ . '/../services/ChatService.php';

$db = getConexao();
if (!$db) {
    header('Location: ../../index.php');
    exit;
}

$users = new UserRepository($db);
$guestSessions = new ChatGuestSessionRepository($db);
$usuario = chatEnsureGuest($users, $guestSessions);
if (!$usuario) {
    header('Location: ../../index.php');
    exit;
}

$userId = (int) $usuario->getId();
$ehConvidado = $usuario->isGuest();
$csrfToken = $_SESSION['csrf_token'];
$selectedToken = trim((string) ($_SESSION['chat_conversa_token'] ?? ''));
$openNew = ($_GET['novo'] ?? '') === '1';
$initialQuery = trim((string) ($_GET['q'] ?? ''));
$chatTourForced = ($_GET['tour'] ?? '') === '1';
$chatTourSeen = isset($_COOKIE['roppin_chat_tutorial_visto']);
$chatTour = $chatTourForced || !$chatTourSeen;
$conversations = [];
$selectedConversation = null;
$messages = [];
$lastMessageId = 0;
$chatError = null;
$pollMs = (int) (getenv('CHAT_POLL_INTERVAL_MS') ?: 5000);
$pollMs = max(3000, min(15000, $pollMs));

try {
    $chat = new ChatService(
        new ConversationRepository($db),
        new MessageRepository($db),
        new ChatCryptoService(),
        new ReviewRepository($db)
    );

    $conversations = $chat->listConversations($userId);

    if ($selectedToken !== '') {
        try {
            $data = $chat->getConversationByToken($userId, $selectedToken);
            $selectedConversation = $data['conversa'];
            $messages = $data['mensagens'];
            $lastMessageId = $data['ultimoId'];
            $chat->markRead($userId, $chat->resolveConversationToken($userId, $selectedToken), $lastMessageId);
            $conversations = $chat->listConversations($userId);
        } catch (ChatException $e) {
            $chatError = $e->getMessage();
            $selectedToken = '';
            unset($_SESSION['chat_conversa_token']);
        }
    }
} catch (Throwable $e) {
    error_log('mensagens.php -> ' . $e->getMessage());
    $chatError = $e instanceof ChatException
        ? $e->getMessage()
        : 'Não foi possível carregar as mensagens agora.';
}

$unreadTotal = array_sum(array_map(
    static fn(array $conversation): int => (int) ($conversation['naoLidas'] ?? 0),
    $conversations
));
$temRespostaPrestador = false;
foreach ($messages as $mensagem) {
    if (empty($mensagem['minha']) && ($mensagem['tipo'] ?? '') === 'usuario') {
        $temRespostaPrestador = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas mensagens | Roppin</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 text-gray-800" data-chat-endpoint="../../processar.php">
    <header class="bg-red-700 text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <a href="../../index.php" class="shrink-0 rounded-full" aria-label="Voltar para a Roppin" title="Voltar para a Roppin">
                    <img src="../../images/roppin_Logo_circulo.png" alt="Roppin" class="h-10 w-10 rounded-full object-cover">
                </a>
                <a href="mensagens.php" class="min-w-0 truncate text-lg font-bold hover:text-red-100" aria-label="Ir para a lista de mensagens" title="Ir para a lista de mensagens">Minhas mensagens</a>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <?php if ($unreadTotal > 0): ?>
                    <span class="hidden rounded-full bg-white/20 px-3 py-1 text-xs font-bold sm:inline"><?php echo $unreadTotal; ?> não lida(s)</span>
                <?php endif; ?>
                <?php if ($ehConvidado): ?>
                    <a href="../../index.php" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-red-600">Entrar</a>
                <?php else: ?>
                    <a href="conta.php?aba=mensagens" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-red-600">Minha conta</a>
                <?php endif; ?>
                <a href="../../index.php" class="hidden rounded-lg px-3 py-2 text-sm font-semibold hover:bg-red-600 sm:inline">Voltar ao início</a>
            </div>
        </div>
    </header>

    <?php if ($ehConvidado): ?>
        <section
            id="chat-guest-banner"
            class="border-b border-amber-200 bg-amber-50 <?php echo $temRespostaPrestador ? '' : 'hidden'; ?>"
            data-guest="1"
        >
            <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-amber-900"><i class="fas fa-comments mr-2" aria-hidden="true"></i>Conversando como visitante</p>
                    <p class="mt-0.5 text-xs text-amber-800">Suas mensagens já estão salvas. Quer salvar esta conversa e receber respostas por e-mail?</p>
                </div>
                <form id="chat-guest-form" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input type="hidden" name="acao" value="chat_vincular_email">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <label for="chat-guest-nome" class="sr-only">Seu nome</label>
                    <input id="chat-guest-nome" name="nome" type="text" autocomplete="name" maxlength="120" required placeholder="Seu nome" class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200 sm:w-40">
                    <label for="chat-guest-email" class="sr-only">Seu e-mail</label>
                    <input id="chat-guest-email" name="email" type="email" autocomplete="email" required placeholder="seu@email.com" class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200 sm:w-56">
                    <button type="submit" class="shrink-0 rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">Salvar e receber link</button>
                </form>
            </div>
            <p id="chat-guest-status" class="mx-auto max-w-6xl px-4 pb-2 text-xs sm:px-6" role="status" aria-live="polite"></p>
        </section>
    <?php endif; ?>

    <main class="mx-auto max-w-6xl px-3 py-4 sm:px-6 sm:py-6">
        <?php if ($chatError !== null): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <i class="fas fa-circle-exclamation mr-2" aria-hidden="true"></i>
                <?php echo htmlspecialchars($chatError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/partials/chat_inbox.php'; ?>
    </main>

    <?php $chatJsVersion = is_file(__DIR__ . '/../scripts/chat.js') ? filemtime(__DIR__ . '/../scripts/chat.js') : 0; ?>
    <script src="../scripts/chat.js?v=<?php echo $chatJsVersion; ?>" defer></script>
    <?php $chatTourJsVersion = is_file(__DIR__ . '/../scripts/chat_tour.js') ? filemtime(__DIR__ . '/../scripts/chat_tour.js') : 0; ?>
    <script src="../scripts/chat_tour.js?v=<?php echo $chatTourJsVersion; ?>" defer></script>
    <?php if ($ehConvidado): ?>
        <script>
            (() => {
                const form = document.getElementById('chat-guest-form');
                const status = document.getElementById('chat-guest-status');
                if (!form) {
                    return;
                }

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    status.textContent = 'Enviando link...';
                    status.className = 'mx-auto max-w-6xl px-4 pb-2 text-xs text-amber-800 sm:px-6';
                    const button = form.querySelector('button[type="submit"]');
                    button.disabled = true;

                    try {
                        const response = await fetch('../../processar.php', {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const result = await response.json();
                        status.textContent = result.mensagem || 'Não foi possível enviar o link agora.';
                        status.className = `mx-auto max-w-6xl px-4 pb-2 text-xs sm:px-6 ${result.sucesso ? 'text-green-700' : 'text-red-700'}`;
                        if (result.sucesso) {
                            form.reset();
                        }
                    } catch (error) {
                        status.textContent = 'Não foi possível enviar o link agora.';
                        status.className = 'mx-auto max-w-6xl px-4 pb-2 text-xs text-red-700 sm:px-6';
                    } finally {
                        button.disabled = false;
                    }
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
