<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../scripts/config.php';
require_once __DIR__ . '/../services/ChatException.php';
require_once __DIR__ . '/../services/ChatService.php';

class ChatController
{
    private ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    public function providers(array $input = []): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $search = trim((string) ($input['busca'] ?? ''));
            $specialtyId = (int) ($input['especialidade_id'] ?? 0);
            $providers = $this->chat->listProviders(
                (int) $user->getId(),
                $search,
                $specialtyId > 0 ? $specialtyId : null
            );

            return new ControllerResponse([
                'sucesso' => true,
                'prestadores' => $providers
            ]);
        });
    }

    public function start(array $input): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $specialtyId = (int) ($input['especialidade_id'] ?? 0);
            $data = $this->chat->startConversation(
                (int) $user->getId(),
                (int) ($input['prestador_id'] ?? 0),
                (string) ($input['mensagem'] ?? ''),
                $specialtyId > 0 ? $specialtyId : null
            );

            $_SESSION['chat_conversa_token'] = (string) ($data['conversa']['token'] ?? '');

            return new ControllerResponse([
                'sucesso' => true,
                'mensagem' => 'Conversa iniciada.',
                'conversa' => $data['conversa'],
                'mensagens' => $data['mensagens'],
                'ultimoId' => $data['ultimoId'],
                'criada' => $data['criada']
            ]);
        });
    }

    public function conversations(array $input = []): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return $this->handle(function () use ($user): ControllerResponse {
            $conversations = $this->chat->listConversations((int) $user->getId());
            $unread = array_sum(array_map(
                static fn(array $conversation): int => (int) ($conversation['naoLidas'] ?? 0),
                $conversations
            ));

            return new ControllerResponse([
                'sucesso' => true,
                'conversas' => $conversations,
                'totalNaoLidas' => $unread
            ]);
        });
    }

    public function conversation(array $input = []): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $conversationId = $this->conversationIdFromToken((int) $user->getId(), $input);
            $data = $this->chat->getConversation(
                (int) $user->getId(),
                $conversationId,
                (int) ($input['depois_id'] ?? 0)
            );

            return new ControllerResponse([
                'sucesso' => true,
                'conversa' => $data['conversa'],
                'mensagens' => $data['mensagens'],
                'ultimoId' => $data['ultimoId'],
                'naoLidas' => $data['naoLidas'],
                'podeEnviar' => $data['podeEnviar']
            ]);
        });
    }

    public function send(array $input): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $data = $this->chat->sendMessage(
                (int) $user->getId(),
                $this->conversationIdFromToken((int) $user->getId(), $input),
                (string) ($input['mensagem'] ?? '')
            );

            return new ControllerResponse([
                'sucesso' => true,
                'mensagem' => 'Mensagem enviada.',
                'dados' => $data['mensagem'],
                'ultimoId' => $data['ultimoId']
            ]);
        });
    }

    public function close(array $input): ControllerResponse
    {
        return $this->changeState($input, 'encerrada');
    }

    public function reopen(array $input): ControllerResponse
    {
        return $this->changeState($input, 'ativa');
    }

    private function changeState(array $input, string $estado): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        return $this->handle(function () use ($user, $input, $estado): ControllerResponse {
            $data = $this->chat->setConversationState(
                (int) $user->getId(),
                $this->conversationIdFromToken((int) $user->getId(), $input),
                $estado
            );

            return new ControllerResponse([
                'sucesso' => true,
                'mensagem' => $estado === 'encerrada' ? 'Conversa encerrada.' : 'Conversa reaberta.',
                'conversa' => $data['conversa'],
                'mensagens' => $data['mensagens'],
                'ultimoId' => $data['ultimoId'],
                'podeEnviar' => $data['podeEnviar']
            ]);
        });
    }

    public function rate(array $input): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $data = $this->chat->rateConversation(
                (int) $user->getId(),
                $this->conversationIdFromToken((int) $user->getId(), $input),
                (float) ($input['nota'] ?? 0)
            );

            return new ControllerResponse([
                'sucesso' => true,
                'mensagem' => 'Avaliação registrada.',
                'conversa' => $data['conversa'],
                'ultimoId' => $data['ultimoId'],
                'podeEnviar' => $data['podeEnviar']
            ]);
        });
    }

    public function read(array $input): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $data = $this->chat->markRead(
                (int) $user->getId(),
                $this->conversationIdFromToken((int) $user->getId(), $input),
                (int) ($input['ultima_id'] ?? 0)
            );

            return new ControllerResponse([
                'sucesso' => true,
                'ultimaMensagemLida' => $data['ultimaMensagemLida'],
                'naoLidas' => $data['naoLidas']
            ]);
        });
    }

    public function unread(array $input = []): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        return $this->handle(function () use ($user): ControllerResponse {
            return new ControllerResponse([
                'sucesso' => true,
                'total' => $this->chat->unreadCount((int) $user->getId())
            ]);
        });
    }

    public function focus(array $input): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if ($response = $this->disabledResponse()) {
            return $response;
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        return $this->handle(function () use ($user, $input): ControllerResponse {
            $tokenPublico = trim((string) ($input['token'] ?? ''));
            $this->conversationIdFromToken((int) $user->getId(), $input);
            $_SESSION['chat_conversa_token'] = $tokenPublico;

            return new ControllerResponse(['sucesso' => true]);
        });
    }

    public function blur(array $input): ControllerResponse
    {
        $user = $this->authenticatedUser();
        if (!$user) {
            return $this->unauthorized();
        }

        if (!$this->validCsrfToken((string) ($input['csrf_token'] ?? ''))) {
            return $this->csrfError();
        }

        unset($_SESSION['chat_conversa_token']);

        return new ControllerResponse(['sucesso' => true]);
    }

    private function conversationIdFromToken(int $userId, array $input): int
    {
        return $this->chat->resolveConversationToken($userId, trim((string) ($input['token'] ?? '')));
    }

    private function handle(callable $callback): ControllerResponse
    {
        try {
            return $callback();
        } catch (ChatException $e) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            error_log('ChatController -> ' . $e->getMessage());
            return new ControllerResponse(
                ['sucesso' => false, 'mensagem' => 'Não foi possível processar a solicitação do chat.'],
                500
            );
        }
    }

    private function authenticatedUser(): User | false
    {
        $user = $_SESSION['usuario_logado'] ?? null;
        return $user instanceof User ? $user : false;
    }

    private function unauthorized(): ControllerResponse
    {
        return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
    }

    private function disabledResponse(): ?ControllerResponse
    {
        if (chatEnabled()) {
            return null;
        }

        return new ControllerResponse(
            ['sucesso' => false, 'mensagem' => 'O chat está temporariamente desativado.'],
            503
        );
    }

    private function csrfError(): ControllerResponse
    {
        return new ControllerResponse(
            ['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'],
            403
        );
    }

    private function validCsrfToken(string $received): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return $sessionToken !== '' && hash_equals($sessionToken, $received);
    }
}
