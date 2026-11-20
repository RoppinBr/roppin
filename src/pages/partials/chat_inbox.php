<?php
$initialOf = static function (string $name): string {
    $name = trim($name);
    if ($name !== '' && preg_match('/^./us', $name, $match) === 1) {
        return strtoupper($match[0]);
    }
    return '?';
};

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$selectedConversation = $selectedConversation ?? null;
$messages = $messages ?? [];
$conversations = $conversations ?? [];
$selectedToken = (string) ($selectedToken ?? '');
$lastMessageId = (int) ($lastMessageId ?? 0);
$csrfToken = (string) ($csrfToken ?? '');
$userId = (int) ($userId ?? 0);
$pollMs = (int) ($pollMs ?? 5000);
$openNew = (bool) ($openNew ?? false);
$embedded = (bool) ($embedded ?? false);
$initialQuery = trim((string) ($initialQuery ?? ''));
$ehConvidado = (bool) ($ehConvidado ?? false);
$chatTour = (bool) ($chatTour ?? false);
$selectedName = $selectedConversation['outroNome'] ?? '';
$selectedInitial = $initialOf($selectedName !== '' ? $selectedName : 'Conversa');
$selectedReputation = $selectedConversation['outroReputacao'] ?? null;
$selectedState = $selectedConversation['estado'] ?? 'ativa';
$selectedCanSend = (bool) ($selectedConversation['podeEnviar'] ?? false);
?>
<div
    id="chat-app"
    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
    data-csrf="<?php echo $escape($csrfToken); ?>"
    data-user-id="<?php echo $userId; ?>"
    data-conversation-token="<?php echo $escape($selectedToken); ?>"
    data-last-message-id="<?php echo $lastMessageId; ?>"
    data-open-new="<?php echo $openNew ? '1' : '0'; ?>"
    data-initial-query="<?php echo $escape($initialQuery); ?>"
    data-poll-ms="<?php echo $pollMs; ?>"
    data-guest="<?php echo $ehConvidado ? '1' : '0'; ?>"
    data-tour-enabled="<?php echo $chatTour ? '1' : '0'; ?>"
    data-demo-provider="<?php echo $chatTour ? '1' : '0'; ?>"
    data-demo-query="Eletricista"
>
    <div class="flex <?php echo $embedded ? 'h-[calc(100vh-24rem)] min-h-[480px]' : 'h-[calc(100vh-11rem)] min-h-[540px]'; ?>">
        <aside id="chat-sidebar" data-tour="chat-sidebar" class="flex w-full flex-col border-r border-gray-200 md:flex md:w-80 lg:w-96">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-4">
                <div class="min-w-0">
                    <h1 class="text-lg font-bold">Conversas</h1>
                    <p id="chat-conversation-count" class="text-xs text-gray-500"><?php echo count($conversations); ?> conversa(s)</p>
                </div>
                <button id="chat-new-button" data-tour="chat-new-button" type="button" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Nova</span>
                </button>
            </div>

            <div class="border-b border-gray-200 p-3">
                <label for="chat-search" class="sr-only">Buscar conversa</label>
                <input id="chat-search" type="search" autocomplete="off" placeholder="Buscar conversa" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
            </div>

            <div id="chat-list" class="flex-1 overflow-y-auto">
                <?php if ($conversations === []): ?>
                    <div id="chat-list-empty" class="p-6 text-center text-sm text-gray-500">
                        <i class="fas fa-comments mb-3 text-2xl text-gray-300" aria-hidden="true"></i>
                        <p>Nenhuma conversa ainda.</p>
                        <p class="mt-1 text-xs">Quando você iniciar um contato, ela aparece aqui.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <?php
                        $conversationToken = (string) ($conversation['token'] ?? '');
                        $conversationName = (string) $conversation['outroNome'];
                        $conversationInitial = $initialOf($conversationName);
                        $conversationUnread = (int) ($conversation['naoLidas'] ?? 0);
                        $isActive = $conversationToken !== '' && $conversationToken === $selectedToken;
                        ?>
                        <a
                            href="mensagens.php"
                            data-conversation-token="<?php echo $escape($conversationToken); ?>"
                            class="chat-conversation-item flex gap-3 border-b border-gray-100 px-4 py-3 transition-colors <?php echo $isActive ? 'bg-red-50' : 'hover:bg-gray-50'; ?>"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700"><?php echo $escape($conversationInitial); ?></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-gray-900"><?php echo $escape($conversationName); ?></span>
                                    <span class="shrink-0 text-[11px] text-gray-400"><?php echo $escape((string) ($conversation['ultimaMensagemEmLabel'] ?? '')); ?></span>
                                </span>
                                <span class="mt-0.5 flex items-center justify-between gap-2">
                                    <span class="truncate text-xs text-gray-500"><?php echo $escape($conversation['preview'] !== '' ? $conversation['preview'] : 'Sem mensagens ainda'); ?></span>
                                    <?php if ($conversationUnread > 0): ?>
                                        <span class="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-red-700 px-1.5 py-0.5 text-[10px] font-bold text-white"><?php echo $conversationUnread; ?></span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section id="chat-main" data-tour="chat-main" class="hidden min-w-0 flex-1 flex-col md:flex">
            <div id="chat-empty-state" class="flex flex-1 flex-col items-center justify-center p-8 text-center <?php echo $selectedConversation ? 'hidden' : ''; ?>">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-2xl text-red-700">
                    <i class="fas fa-comments" aria-hidden="true"></i>
                </div>
                <h2 class="text-xl font-bold">Selecione uma conversa</h2>
                <p class="mt-2 max-w-sm text-sm text-gray-500">Escolha uma conversa na lista ou inicie um novo contato com um profissional.</p>
                <button id="chat-empty-new-button" type="button" class="mt-5 rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800">Nova conversa</button>
            </div>

            <div id="chat-conversation-view" class="flex min-h-0 flex-1 flex-col <?php echo $selectedConversation ? '' : 'hidden'; ?>">
                <header class="flex items-center gap-3 border-b border-gray-200 p-4">
                    <button id="chat-back-button" type="button" class="inline-flex h-9 shrink-0 items-center gap-2 rounded-full px-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 lg:px-3" aria-label="Voltar para a lista de conversas">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <span class="hidden lg:inline">Conversas</span>
                    </button>
                    <div id="chat-header-avatar" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-bold text-red-700"><?php echo $escape($selectedInitial); ?></div>
                    <div class="min-w-0 flex-1">
                        <h2 id="chat-header-name" class="truncate text-base font-bold"><?php echo $escape($selectedName !== '' ? $selectedName : 'Conversa'); ?></h2>
                        <p id="chat-header-status" class="truncate text-xs text-gray-500">
                            <?php if ($selectedReputation !== null): ?>
                                ★ <?php echo number_format((float) $selectedReputation, 1, ',', ''); ?> de reputação
                            <?php elseif ($selectedState === 'encerrada'): ?>
                                Conversa encerrada
                            <?php else: ?>
                                Conversa interna da Roppin
                            <?php endif; ?>
                        </p>
                    </div>
                    <button id="chat-state-button" type="button" class="shrink-0 rounded-lg border border-red-700 px-2.5 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 sm:px-3" data-estado="<?php echo $escape($selectedState); ?>" aria-label="<?php echo $selectedState === 'encerrada' ? 'Reabrir conversa' : 'Encerrar conversa'; ?>" <?php echo $selectedState === 'bot' ? 'hidden' : ''; ?>>
                        <?php echo $selectedState === 'encerrada' ? 'Reabrir' : 'Encerrar'; ?>
                    </button>
                    <button id="chat-review-button" type="button" class="shrink-0 rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60 sm:px-3" aria-label="Avaliar atendimento" hidden>
                        <i class="fas fa-star mr-1" aria-hidden="true"></i><span class="hidden sm:inline">Avaliar</span>
                    </button>
                </header>

                <div id="chat-messages" class="flex-1 space-y-3 overflow-y-auto bg-gray-50 p-4" aria-live="polite" aria-relevant="additions">
                    <?php if ($messages === []): ?>
                        <div id="chat-messages-empty" class="py-10 text-center text-sm text-gray-500">Nenhuma mensagem ainda. Diga olá!</div>
                    <?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $isMine = (bool) ($message['minha'] ?? false);
                        $isSystem = ($message['tipo'] ?? 'usuario') === 'sistema';
                        $isBot = ($message['tipo'] ?? 'usuario') === 'bot';
                        ?>
                        <?php if ($isSystem): ?>
                            <div class="flex justify-center" data-message-id="<?php echo (int) $message['id']; ?>">
                                <span class="rounded-full bg-gray-200 px-3 py-1 text-xs text-gray-600"><?php echo $escape((string) $message['texto']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="flex <?php echo $isMine ? 'justify-end' : 'justify-start'; ?>" data-message-id="<?php echo (int) $message['id']; ?>">
                                <div class="max-w-[85%] rounded-2xl px-4 py-2 shadow-sm <?php echo $isMine ? 'rounded-br-sm bg-red-700 text-white' : ($isBot ? 'rounded-bl-sm border border-red-100 bg-white text-gray-800' : 'rounded-bl-sm bg-white text-gray-800'); ?>">
                                    <?php if (!$isMine): ?>
                                        <p class="mb-1 text-[11px] font-semibold opacity-70">
                                            <?php if ($isBot): ?><i class="fas fa-robot mr-1" aria-hidden="true"></i><?php endif; ?>
                                            <?php echo $escape((string) ($message['autor'] ?? '')); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="whitespace-pre-wrap break-words text-sm"><?php echo $escape((string) $message['texto']); ?></p>
                                    <p class="mt-1 text-right text-[10px] opacity-60"><?php echo $escape((string) ($message['hora'] ?? '')); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-gray-200 p-3">
                    <form id="chat-form" class="flex items-end gap-2">
                        <label for="chat-input" class="sr-only">Mensagem</label>
                        <textarea id="chat-input" rows="1" maxlength="2000" placeholder="Escreva uma mensagem..." class="max-h-32 min-h-[42px] flex-1 resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200" <?php echo $selectedCanSend ? '' : 'disabled'; ?>></textarea>
                        <button id="chat-send-button" type="submit" class="inline-flex h-[42px] items-center justify-center rounded-lg bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60" <?php echo $selectedCanSend ? '' : 'disabled'; ?>>
                            <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Enviar</span>
                        </button>
                    </form>
                    <p id="chat-error" class="mt-2 hidden text-sm text-red-700" role="alert"></p>
                    <p id="chat-composer-hint" class="mt-1 text-[11px] text-gray-400">Enter envia; Shift+Enter quebra a linha.</p>
                </div>
            </div>
        </section>
    </div>
</div>

<div id="chat-new-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <section role="dialog" aria-modal="true" aria-labelledby="chat-new-title" class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-xl bg-white shadow-xl">
        <header class="border-b border-gray-200 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="chat-new-title" class="text-xl font-bold">Nova conversa</h2>
                    <p class="mt-1 text-sm text-gray-600">Escolha um profissional para conversar.</p>
                </div>
                <button id="chat-new-close" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" aria-label="Fechar">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <label for="chat-provider-search" class="sr-only">Buscar profissional</label>
            <input id="chat-provider-search" data-tour="chat-provider-search" type="search" autocomplete="off" placeholder="Buscar por nome ou especialidade" class="mt-4 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-200">
        </header>
        <div id="chat-provider-list" class="min-h-0 flex-1 overflow-y-auto p-3">
            <p class="p-4 text-sm text-gray-500">Carregando profissionais...</p>
        </div>
        <footer class="border-t border-gray-200 p-4 text-right">
            <button id="chat-new-cancel" type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Fechar</button>
        </footer>
    </section>
</div>

<div id="chat-review-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/60 p-4" aria-hidden="true">
    <section role="dialog" aria-modal="true" aria-labelledby="chat-review-title" class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="chat-review-title" class="text-xl font-bold">Avaliar atendimento</h2>
                <p id="chat-review-recipient" class="mt-1 text-sm text-gray-600">Como foi o atendimento?</p>
            </div>
            <button id="chat-review-close" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" aria-label="Fechar">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div id="chat-review-stars" class="mt-5 flex items-center justify-center gap-2" role="group" aria-label="Nota de 1 a 5 estrelas"></div>
        <p id="chat-review-error" class="mt-3 hidden text-sm text-red-700" role="alert"></p>
        <footer class="mt-6 flex justify-end gap-2">
            <button id="chat-review-cancel" type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button id="chat-review-submit" type="button" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-white hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60" disabled>Enviar avaliação</button>
        </footer>
    </section>
</div>
