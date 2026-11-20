<?php
require_once __DIR__ . '/ChatException.php';

class ChatRateLimitService
{
    public function check(string $action): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $limit = $this->limitFor($action);
        if ($limit <= 0) {
            return;
        }

        $window = $action === 'nova_conversa' ? 86400 : 60;
        $now = time();
        $timestamps = $_SESSION['chat_rate_limit'][$action] ?? [];
        $timestamps = array_values(array_filter(
            is_array($timestamps) ? $timestamps : [],
            static fn($timestamp): bool => is_numeric($timestamp) && (int) $timestamp > $now - $window
        ));

        if (count($timestamps) >= $limit) {
            $_SESSION['chat_rate_limit'][$action] = $timestamps;
            $mensagem = $action === 'nova_conversa'
                ? 'Você iniciou muitas conversas. Tente novamente mais tarde.'
                : 'Você enviou muitas mensagens. Aguarde um momento.';
            throw new ChatException($mensagem, 429);
        }

        $timestamps[] = $now;
        $_SESSION['chat_rate_limit'][$action] = $timestamps;
    }

    private function limitFor(string $action): int
    {
        $variavel = $action === 'nova_conversa'
            ? 'CHAT_MAX_NEW_CONVERSATIONS_PER_DAY'
            : 'CHAT_MAX_MESSAGES_PER_MINUTE';

        $valor = getenv($variavel);
        return $valor === false ? ($action === 'nova_conversa' ? 10 : 30) : max(0, (int) $valor);
    }
}
