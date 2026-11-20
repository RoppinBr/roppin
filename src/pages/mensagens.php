<?php
require_once __DIR__ . '/../scripts/auth_session.php';
startRoppinSession();

if (!isset($_SESSION['usuario_logado']) || !($_SESSION['usuario_logado'] instanceof User)) {
    header('Location: ../../index.php');
    exit;
}

if (!chatEnabled()) {
    header('Location: ../../index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../scripts/conexao.php';
require_once __DIR__ . '/../repositories/ConversationRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/ReviewRepository.php';
require_once __DIR__ . '/../services/ChatCryptoService.php';
require_once __DIR__ . '/../services/ChatService.php';

$usuario = $_SESSION['usuario_logado'];
$userId = (int) $usuario->getId();
$csrfToken = $_SESSION['csrf_token'];
$selectedToken = trim((string) ($_SESSION['chat_conversa_token'] ?? ''));
$openNew = ($_GET['novo'] ?? '') === '1';
$initialQuery = trim((string) ($_GET['q'] ?? ''));
$conversations = [];
$selectedConversation = null;
$messages = [];
$lastMessageId = 0;
$chatError = null;
$pollMs = (int) (getenv('CHAT_POLL_INTERVAL_MS') ?: 5000);
$pollMs = max(3000, min(15000, $pollMs));

try {
    $db = getConexao();
    if (!$db) {
        throw new ChatException('Não foi possível conectar ao banco de dados.', 503);
    }

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
                <a href="conta.php?aba=mensagens" class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-red-600">Minha conta</a>
                <a href="../../index.php" class="hidden rounded-lg px-3 py-2 text-sm font-semibold hover:bg-red-600 sm:inline">Voltar ao início</a>
            </div>
        </div>
    </header>

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
</body>
</html>
