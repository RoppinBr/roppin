<?php
require_once __DIR__ . '/ChatException.php';
require_once __DIR__ . '/ChatCryptoService.php';
require_once __DIR__ . '/../repositories/ConversationRepository.php';
require_once __DIR__ . '/../repositories/MessageRepository.php';
require_once __DIR__ . '/../repositories/ReviewRepository.php';

class ChatService
{
    private ConversationRepository $conversations;
    private MessageRepository $messages;
    private ReviewRepository $reviews;
    private ChatCryptoService $crypto;

    public function __construct(
        ConversationRepository $conversations,
        MessageRepository $messages,
        ChatCryptoService $crypto,
        ReviewRepository $reviews
    ) {
        $this->conversations = $conversations;
        $this->messages = $messages;
        $this->crypto = $crypto;
        $this->reviews = $reviews;
    }

    public function listProviders(int $userId, string $search = '', ?int $specialtyId = null): array
    {
        $rows = $this->conversations->listProviders($userId);
        $needle = $this->normalize($search);
        $providers = [];

        foreach ($rows as $row) {
            $specialtyIds = $this->parseIds($row['especialidadeIds'] ?? '');

            if ($specialtyId !== null && $specialtyId > 0 && !in_array($specialtyId, $specialtyIds, true)) {
                continue;
            }

            if ($needle !== '') {
                $haystack = $this->normalize(($row['nome'] ?? '') . ' ' . ($row['especialidades'] ?? ''));
                if (!str_contains($haystack, $needle)) {
                    continue;
                }
            }

            $providers[] = [
                'idUsuario' => (int) $row['idUsuario'],
                'nome' => (string) ($row['nome'] ?? 'Prestador'),
                'valor' => $row['valor'] !== null ? (float) $row['valor'] : null,
                'periodo_cobranca' => (string) ($row['periodo_cobranca'] ?? 'servico'),
                'reputacao' => $row['media_reputacao'] !== null ? (float) $row['media_reputacao'] : null,
                'especialidades' => (string) ($row['especialidades'] ?? ''),
                'especialidade_ids' => $specialtyIds,
                'idEspecialidade' => $specialtyIds[0] ?? null
            ];
        }

        return array_slice($providers, 0, 50);
    }

    public function startConversation(
        int $userId,
        int $providerId,
        string $firstMessage = '',
        ?int $specialtyId = null
    ): array {
        if ($providerId <= 0 || $providerId === $userId) {
            throw new ChatException('Selecione um profissional válido.', 400);
        }

        $provider = $this->conversations->findProvider($providerId);
        if (!$provider) {
            throw new ChatException('Profissional não encontrado.', 404);
        }

        if ($this->conversations->isBlocked($userId, $providerId)) {
            throw new ChatException('Não é possível iniciar uma conversa com este usuário.', 403);
        }

        $specialtyIds = $this->parseIds($provider['especialidadeIds'] ?? '');
        if ($specialtyId !== null && $specialtyId > 0 && !in_array($specialtyId, $specialtyIds, true)) {
            throw new ChatException('O profissional não oferece essa especialidade.', 400);
        }
        if ($specialtyId === null || $specialtyId <= 0) {
            $specialtyId = $specialtyIds[0] ?? null;
        }

        $existing = $this->conversations->findActiveByPair($userId, $providerId);
        $created = false;

        if ($existing) {
            if (($existing['estado'] ?? '') !== 'ativa') {
                throw new ChatException('Esta conversa ainda está com o assistente.', 409);
            }
            $conversationId = (int) $existing['idConversa'];
        } else {
            $conversationId = $this->conversations->create($userId, $providerId, $specialtyId);
            $created = true;
        }

        $firstMessage = trim($firstMessage);
        if ($firstMessage !== '') {
            $this->sendMessage($userId, $conversationId, $firstMessage);
        } elseif ($created) {
            $this->createSystemMessage($conversationId, 'Conversa iniciada.');
        }

        $data = $this->getConversation($userId, $conversationId);

        return [
            'conversa' => $data['conversa'],
            'mensagens' => $data['mensagens'],
            'ultimoId' => $data['ultimoId'],
            'criada' => $created
        ];
    }

    public function listConversations(int $userId): array
    {
        $rows = $this->conversations->listForUser($userId);

        return array_map(fn(array $row): array => $this->formatConversation($row, $userId), $rows);
    }

    public function resolveConversationToken(int $userId, string $tokenPublico): int
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $tokenPublico)) {
            throw new ChatException('Conversa inválida.', 400);
        }

        $row = $this->conversations->findForUserByToken($tokenPublico, $userId);
        if (!$row) {
            throw new ChatException('Conversa não encontrada.', 404);
        }

        return (int) $row['idConversa'];
    }

    public function getConversationByToken(int $userId, string $tokenPublico): array
    {
        return $this->getConversation($userId, $this->resolveConversationToken($userId, $tokenPublico));
    }

    public function getConversation(int $userId, int $conversationId, int $afterId = 0): array
    {
        if ($conversationId <= 0) {
            throw new ChatException('Conversa inválida.', 400);
        }

        $row = $this->conversations->findForUser($conversationId, $userId);
        if (!$row) {
            throw new ChatException('Conversa não encontrada.', 404);
        }

        $conversation = $this->formatConversation($row, $userId);
        $rows = $this->messages->listByConversation($conversationId, max(0, $afterId));
        $messages = array_map(
            fn(array $message): array => $this->formatMessage($message, $conversation, $userId),
            $rows
        );

        $lastReadId = (int) ($row['ultimaMensagemLida'] ?? 0);
        $unread = $this->messages->countUnread($conversationId, $userId, $lastReadId);
        $blocked = $row['outroId'] !== null
            && $this->conversations->isBlocked($userId, (int) $row['outroId']);

        $conversation['podeEnviar'] = $conversation['podeEnviar'] && !$blocked;
        $conversation['bloqueada'] = $blocked;
        $conversation['naoLidas'] = $unread;

        return [
            'conversa' => $conversation,
            'mensagens' => $messages,
            'ultimoId' => $this->messages->latestId($conversationId),
            'naoLidas' => $unread,
            'podeEnviar' => $conversation['podeEnviar']
        ];
    }

    public function sendMessage(int $userId, int $conversationId, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new ChatException('Digite uma mensagem antes de enviar.', 400);
        }

        if ($this->textLength($text) > 2000) {
            throw new ChatException('A mensagem deve ter no máximo 2000 caracteres.', 400);
        }

        $row = $this->conversations->findForUser($conversationId, $userId);
        if (!$row) {
            throw new ChatException('Conversa não encontrada.', 404);
        }

        if (($row['estado'] ?? '') !== 'ativa') {
            throw new ChatException('Esta conversa não está disponível para envio.', 409);
        }

        $other = $this->conversations->findOtherParticipant($conversationId, $userId);
        if (!$other) {
            throw new ChatException('Não foi possível identificar o destinatário.', 409);
        }

        if ($this->conversations->isBlocked($userId, (int) $other['idUsuario'])) {
            throw new ChatException('O envio está bloqueado nesta conversa.', 403);
        }

        $now = $this->now();
        $encrypted = $this->crypto->encrypt($text);
        $messageId = $this->messages->create(
            $conversationId,
            $userId,
            (int) $other['idUsuario'],
            'usuario',
            $encrypted,
            ChatCryptoService::ALGORITHM,
            $now
        );

        $preview = $this->textSlice($text, 120);
        $this->conversations->touchLastMessage(
            $conversationId,
            $this->crypto->encryptPreview($preview),
            $now
        );
        $this->messages->markRead($conversationId, $userId, $messageId);

        return [
            'mensagem' => [
                'id' => $messageId,
                'texto' => $text,
                'minha' => true,
                'tipo' => 'usuario',
                'autor' => 'Você',
                'criadoEm' => $now,
                'hora' => substr($now, 11, 5),
                'metadados' => null
            ],
            'ultimoId' => $messageId
        ];
    }

    public function setConversationState(int $userId, int $conversationId, string $nextState): array
    {
        if (!in_array($nextState, ['ativa', 'encerrada'], true)) {
            throw new ChatException('Estado de conversa inválido.', 400);
        }

        $row = $this->conversations->findForUser($conversationId, $userId);
        if (!$row) {
            throw new ChatException('Conversa não encontrada.', 404);
        }

        if ((string) ($row['estado'] ?? '') === $nextState) {
            throw new ChatException('A conversa já está nesse estado.', 409);
        }

        if (!$this->conversations->setState($conversationId, $nextState)) {
            throw new ChatException('Não foi possível atualizar a conversa.', 409);
        }

        $this->createSystemMessage(
            $conversationId,
            $nextState === 'encerrada' ? 'Conversa encerrada.' : 'Conversa reaberta.'
        );

        return $this->getConversation($userId, $conversationId);
    }

    public function rateConversation(int $userId, int $conversationId, float $note): array
    {
        if ($note < 1 || $note > 5 || floor($note) != $note) {
            throw new ChatException('Escolha uma nota de 1 a 5 estrelas.', 400);
        }

        $row = $this->conversations->findForUser($conversationId, $userId);
        if (!$row) {
            throw new ChatException('Conversa não encontrada.', 404);
        }

        if (($row['estado'] ?? '') !== 'encerrada') {
            throw new ChatException('A conversa precisa estar encerrada para ser avaliada.', 409);
        }

        $other = $this->conversations->findOtherParticipant($conversationId, $userId);
        if (!$other) {
            throw new ChatException('Não foi possível identificar o outro participante.', 409);
        }

        $reviewedId = (int) $other['idUsuario'];
        if ($this->reviews->existsForReviewer($conversationId, $userId)) {
            throw new ChatException('Você já avaliou esta conversa.', 409);
        }

        $now = $this->now();
        $this->reviews->create(
            $conversationId,
            $userId,
            $reviewedId,
            $note,
            substr($now, 0, 10),
            substr($now, 11, 8)
        );
        $this->reviews->recalculateReputation($reviewedId);

        return $this->getConversation($userId, $conversationId);
    }

    public function markRead(int $userId, int $conversationId, int $lastMessageId = 0): array
    {
        $row = $this->conversations->findForUser($conversationId, $userId);
        if (!$row) {
            throw new ChatException('Conversa não encontrada.', 404);
        }

        if ($lastMessageId <= 0) {
            $lastMessageId = $this->messages->latestId($conversationId);
        }

        $this->messages->markRead($conversationId, $userId, $lastMessageId);

        return [
            'ultimaMensagemLida' => $lastMessageId,
            'naoLidas' => 0
        ];
    }

    public function unreadCount(int $userId): int
    {
        return $this->messages->unreadCountForUser($userId);
    }

    private function createSystemMessage(int $conversationId, string $text): void
    {
        $now = $this->now();
        $encrypted = $this->crypto->encrypt($text);
        $this->messages->create(
            $conversationId,
            null,
            null,
            'sistema',
            $encrypted,
            ChatCryptoService::ALGORITHM,
            $now
        );
        $this->conversations->touchLastMessage(
            $conversationId,
            $this->crypto->encryptPreview($this->textSlice($text, 120)),
            $now
        );
    }

    private function formatConversation(array $row, int $userId): array
    {
        $otherId = isset($row['outroId']) && $row['outroId'] !== null ? (int) $row['outroId'] : null;
        $otherName = $row['outroNome'] ?? null;

        if ($otherName === null || $otherName === '') {
            $otherName = ($row['estado'] ?? '') === 'bot' ? 'Assistente Roppin' : 'Conversa';
        }

        $lastAt = $row['ultimaMensagemEm'] ?? $row['criadaEm'] ?? null;
        $reviews = ['minhaNota' => null, 'notaRecebida' => null];

        if (($row['estado'] ?? '') === 'encerrada' && $otherId !== null) {
            $reviews = $this->reviews->findForPair((int) $row['idConversa'], $userId, $otherId);
        }

        return [
            'token' => (string) ($row['tokenPublico'] ?? ''),
            'estado' => (string) ($row['estado'] ?? 'ativa'),
            'papel' => $row['papel'] ?? null,
            'assunto' => $row['assunto'] ?? null,
            'outroId' => $otherId,
            'outroNome' => (string) $otherName,
            'outroReputacao' => ($row['outroReputacao'] ?? null) !== null ? (float) $row['outroReputacao'] : null,
            'preview' => $this->crypto->tryDecryptPreview($row['ultimaMensagemPreview'] ?? null),
            'naoLidas' => (int) ($row['naoLidas'] ?? 0),
            'ultimaMensagemEm' => $lastAt,
            'ultimaMensagemEmLabel' => $this->formatListDate($lastAt),
            'criadaEm' => $row['criadaEm'] ?? null,
            'podeEnviar' => ($row['estado'] ?? '') === 'ativa',
            'avaliacao' => $reviews['minhaNota'] !== null ? (float) $reviews['minhaNota'] : null,
            'avaliacaoRecebida' => $reviews['notaRecebida'] !== null ? (float) $reviews['notaRecebida'] : null,
            'podeAvaliar' => ($row['estado'] ?? '') === 'encerrada'
                && $otherId !== null
                && $reviews['minhaNota'] === null
        ];
    }

    private function formatMessage(array $row, array $conversation, int $userId): array
    {
        $senderId = $row['idRemetente'] !== null ? (int) $row['idRemetente'] : null;
        $mine = $senderId !== null && $senderId === $userId;
        $type = (string) ($row['tipoRemetente'] ?? 'usuario');
        $text = $this->crypto->tryDecrypt(
            $row['textoMensagem'] ?? '',
            (string) ($row['criptografia'] ?? ChatCryptoService::LEGACY)
        );

        $metadata = null;
        if (!empty($row['metadados'])) {
            $decoded = json_decode((string) $row['metadados'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        if ($type === 'sistema') {
            $author = 'Sistema';
        } elseif ($type === 'bot') {
            $author = 'Assistente Roppin';
        } elseif ($mine) {
            $author = 'Você';
        } else {
            $author = (string) ($conversation['outroNome'] ?? 'Participante');
        }

        $createdAt = $row['criadoEm'] ?? null;

        return [
            'id' => (int) $row['idMensagem'],
            'texto' => $text,
            'minha' => $mine,
            'tipo' => $type,
            'autor' => $author,
            'criadoEm' => $createdAt,
            'hora' => $this->formatTime($createdAt),
            'metadados' => $metadata
        ];
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
    }

    private function formatListDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        $today = new DateTimeImmutable('today');
        $messageDay = new DateTimeImmutable(date('Y-m-d', $timestamp));

        if ($messageDay == $today) {
            return date('H:i', $timestamp);
        }

        $yesterday = $today->modify('-1 day');
        if ($messageDay == $yesterday) {
            return 'Ontem';
        }

        return $messageDay->format('Y') === $today->format('Y')
            ? $messageDay->format('d/m')
            : $messageDay->format('d/m/Y');
    }

    private function formatTime(?string $date): string
    {
        if ($date === null || strlen($date) < 16) {
            return '';
        }

        return substr($date, 11, 5);
    }

    private function textLength(string $text): int
    {
        $length = preg_match_all('/./us', $text);
        return $length === false ? strlen($text) : $length;
    }

    private function textSlice(string $text, int $limit): string
    {
        if (preg_match('/^.{0,' . max(0, $limit) . '}/us', $text, $matches) === 1) {
            return $matches[0];
        }

        return substr($text, 0, $limit);
    }

    private function normalize(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }

        $text = preg_replace('/[^a-z0-9]+/', ' ', strtolower($text)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function parseIds(?string $ids): array
    {
        if ($ids === null || $ids === '') {
            return [];
        }

        $parsed = [];
        foreach (explode(',', $ids) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $parsed[] = $id;
            }
        }

        return array_values(array_unique($parsed));
    }
}
