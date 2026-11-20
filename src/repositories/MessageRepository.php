<?php
require_once __DIR__ . '/BaseRepository.php';

class MessageRepository extends BaseRepository
{
    public function create(
        int $conversationId,
        ?int $senderId,
        ?int $recipientId,
        string $senderType,
        string $encryptedText,
        string $algorithm,
        string $createdAt
    ): int {
        $date = substr($createdAt, 0, 10);
        $time = substr($createdAt, 11, 8);

        $statement = $this->db->prepare(
            "INSERT INTO Mensagem
                (idConversa, tipoRemetente, canal, criptografia, textoMensagem, metadados, criadoEm, data, hora, idRemetente, idDestinatario)
             VALUES (?, ?, 'site', ?, ?, NULL, ?, ?, ?, ?, ?)"
        );
        $statement->bind_param(
            'issssssii',
            $conversationId,
            $senderType,
            $algorithm,
            $encryptedText,
            $createdAt,
            $date,
            $time,
            $senderId,
            $recipientId
        );
        $statement->execute();

        return (int) $statement->insert_id;
    }

    public function listByConversation(int $conversationId, int $afterId = 0, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $fields = 'idMensagem, idConversa, tipoRemetente, canal, criptografia, textoMensagem, metadados, criadoEm, idRemetente, idDestinatario';

        if ($afterId > 0) {
            $statement = $this->db->prepare(
                "SELECT {$fields}
                 FROM Mensagem
                 WHERE idConversa = ? AND idMensagem > ?
                 ORDER BY idMensagem ASC
                 LIMIT {$limit}"
            );
            $statement->bind_param('ii', $conversationId, $afterId);
            $statement->execute();

            return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $statement = $this->db->prepare(
            "SELECT {$fields}
             FROM Mensagem
             WHERE idConversa = ?
             ORDER BY idMensagem DESC
             LIMIT {$limit}"
        );
        $statement->bind_param('i', $conversationId);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_reverse($rows);
    }

    public function latestId(int $conversationId): int
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(MAX(idMensagem), 0) AS ultimoId FROM Mensagem WHERE idConversa = ?'
        );
        $statement->bind_param('i', $conversationId);
        $row = $this->fetchOne($statement);

        return (int) ($row['ultimoId'] ?? 0);
    }

    public function countUnread(int $conversationId, int $userId, int $lastReadId): int
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM Mensagem
             WHERE idConversa = ?
               AND idMensagem > ?
               AND tipoRemetente <> 'sistema'
               AND (idRemetente IS NULL OR idRemetente <> ?)"
        );
        $statement->bind_param('iii', $conversationId, $lastReadId, $userId);
        $row = $this->fetchOne($statement);

        return (int) ($row['total'] ?? 0);
    }

    public function markRead(int $conversationId, int $userId, int $lastMessageId): void
    {
        if ($lastMessageId <= 0) {
            return;
        }

        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $statement = $this->db->prepare(
            'UPDATE ConversaParticipante
             SET ultimaMensagemLida = GREATEST(COALESCE(ultimaMensagemLida, 0), ?),
                 lidaEm = ?
             WHERE idConversa = ? AND idUsuario = ?'
        );
        $statement->bind_param('isii', $lastMessageId, $now, $conversationId, $userId);
        $statement->execute();
    }

    public function unreadCountForUser(int $userId): int
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM Mensagem m
             JOIN ConversaParticipante cp
               ON cp.idConversa = m.idConversa AND cp.idUsuario = ?
             WHERE m.idMensagem > COALESCE(cp.ultimaMensagemLida, 0)
               AND m.tipoRemetente <> 'sistema'
               AND (m.idRemetente IS NULL OR m.idRemetente <> ?)"
        );
        $statement->bind_param('ii', $userId, $userId);
        $row = $this->fetchOne($statement);

        return (int) ($row['total'] ?? 0);
    }
}
