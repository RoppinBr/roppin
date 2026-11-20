<?php
require_once __DIR__ . '/BaseRepository.php';

class ConversationRepository extends BaseRepository
{
    public function findActiveByPair(int $clientId, int $providerId): array | false
    {
        $statement = $this->db->prepare(
            "SELECT * FROM Conversa
             WHERE idCliente = ? AND idPrestador = ? AND estado IN ('bot', 'ativa')
             ORDER BY idConversa DESC
             LIMIT 1"
        );
        $statement->bind_param('ii', $clientId, $providerId);

        return $this->fetchOne($statement);
    }

    public function create(int $clientId, int $providerId, ?int $specialtyId = null): int
    {
        $transactionStarted = false;

        try {
            $this->db->begin_transaction();
            $transactionStarted = true;

            $tokenPublico = bin2hex(random_bytes(16));
            $statement = $this->db->prepare(
                "INSERT INTO Conversa (tokenPublico, idCliente, idPrestador, idEspecialidade, canal, estado)
                 VALUES (?, ?, ?, ?, 'site', 'ativa')"
            );
            $statement->bind_param('siii', $tokenPublico, $clientId, $providerId, $specialtyId);
            $statement->execute();
            $conversationId = (int) $statement->insert_id;

            $participant = $this->db->prepare(
                "INSERT INTO ConversaParticipante (idConversa, idUsuario, papel, canal)
                 VALUES (?, ?, ?, 'site')"
            );

            $clientRole = 'cliente';
            $participant->bind_param('iis', $conversationId, $clientId, $clientRole);
            $participant->execute();

            $providerRole = 'prestador';
            $participant->bind_param('iis', $conversationId, $providerId, $providerRole);
            $participant->execute();

            $this->db->commit();
            $transactionStarted = false;

            return $conversationId;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('ConversationRepository::create -> ' . $e->getMessage());
            throw new ChatException('Não foi possível iniciar a conversa.', 500);
        }
    }

    public function findById(int $conversationId): array | false
    {
        $statement = $this->db->prepare('SELECT * FROM Conversa WHERE idConversa = ? LIMIT 1');
        $statement->bind_param('i', $conversationId);

        return $this->fetchOne($statement);
    }

    public function findForUser(int $conversationId, int $userId): array | false
    {
        return $this->findForUserBy('c.idConversa', $conversationId, $userId, 'i');
    }

    public function findForUserByToken(string $tokenPublico, int $userId): array | false
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $tokenPublico)) {
            return false;
        }

        return $this->findForUserBy('c.tokenPublico', $tokenPublico, $userId, 's');
    }

    private function findForUserBy(string $column, mixed $value, int $userId, string $type): array | false
    {
        $statement = $this->db->prepare(
            "SELECT c.*,
                    cp.papel,
                    cp.ultimaMensagemLida,
                    cp.lidaEm,
                    cp.ultimoVistoEm,
                    ou.idUsuario AS outroId,
                    COALESCE(NULLIF(ou.nome, ''), ou.razaoSocial) AS outroNome,
                    ou.media_reputacao AS outroReputacao
             FROM Conversa c
             JOIN ConversaParticipante cp
               ON cp.idConversa = c.idConversa AND cp.idUsuario = ?
             LEFT JOIN Usuario ou
               ON ou.idUsuario = CASE WHEN c.idCliente = ? THEN c.idPrestador ELSE c.idCliente END
             WHERE $column = ?
             LIMIT 1"
        );
        $statement->bind_param('ii' . $type, $userId, $userId, $value);

        return $this->fetchOne($statement);
    }

    public function listForUser(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT c.*,
                    cp.papel,
                    cp.ultimaMensagemLida,
                    cp.lidaEm,
                    cp.ultimoVistoEm,
                    ou.idUsuario AS outroId,
                    COALESCE(NULLIF(ou.nome, ''), ou.razaoSocial) AS outroNome,
                    ou.media_reputacao AS outroReputacao,
                    (SELECT COUNT(*)
                     FROM Mensagem m
                     WHERE m.idConversa = c.idConversa
                       AND m.idMensagem > COALESCE(cp.ultimaMensagemLida, 0)
                       AND m.tipoRemetente <> 'sistema'
                       AND (m.idRemetente IS NULL OR m.idRemetente <> ?)) AS naoLidas
             FROM Conversa c
             JOIN ConversaParticipante cp
               ON cp.idConversa = c.idConversa AND cp.idUsuario = ?
             LEFT JOIN Usuario ou
               ON ou.idUsuario = CASE WHEN c.idCliente = ? THEN c.idPrestador ELSE c.idCliente END
             ORDER BY COALESCE(c.ultimaMensagemEm, c.criadaEm) DESC, c.idConversa DESC
             LIMIT 100"
        );
        $statement->bind_param('iii', $userId, $userId, $userId);
        $statement->execute();

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findParticipant(int $conversationId, int $userId): array | false
    {
        $statement = $this->db->prepare(
            'SELECT * FROM ConversaParticipante WHERE idConversa = ? AND idUsuario = ? LIMIT 1'
        );
        $statement->bind_param('ii', $conversationId, $userId);

        return $this->fetchOne($statement);
    }

    public function findOtherParticipant(int $conversationId, int $userId): array | false
    {
        $statement = $this->db->prepare(
            "SELECT cp.*,
                    u.idUsuario,
                    COALESCE(NULLIF(u.nome, ''), u.razaoSocial) AS nome,
                    u.email
             FROM ConversaParticipante cp
             JOIN Usuario u ON u.idUsuario = cp.idUsuario
             WHERE cp.idConversa = ? AND cp.idUsuario <> ?
             LIMIT 1"
        );
        $statement->bind_param('ii', $conversationId, $userId);

        return $this->fetchOne($statement);
    }

    public function setState(int $conversationId, string $state): bool
    {
        if (!in_array($state, ['ativa', 'encerrada'], true)) {
            return false;
        }

        $statement = $this->db->prepare(
            'UPDATE Conversa SET estado = ? WHERE idConversa = ?'
        );
        $statement->bind_param('si', $state, $conversationId);
        $statement->execute();

        return $statement->affected_rows > 0;
    }

    public function touchLastMessage(int $conversationId, string $preview, string $createdAt): void
    {
        $statement = $this->db->prepare(
            'UPDATE Conversa
             SET ultimaMensagemEm = ?, ultimaMensagemPreview = ?, atualizadaEm = ?
             WHERE idConversa = ?'
        );
        $statement->bind_param('sssi', $createdAt, $preview, $createdAt, $conversationId);
        $statement->execute();
    }

    public function listProviders(int $excludeUserId): array
    {
        $statement = $this->db->prepare(
            "SELECT p.idUsuario,
                    COALESCE(NULLIF(u.nome, ''), u.razaoSocial) AS nome,
                    p.valor,
                    p.periodo_cobranca,
                    u.media_reputacao,
                    GROUP_CONCAT(DISTINCT s.nomeEspecialidade ORDER BY s.nomeEspecialidade SEPARATOR ', ') AS especialidades,
                    GROUP_CONCAT(DISTINCT pe.idEspecialidade ORDER BY pe.idEspecialidade SEPARATOR ',') AS especialidadeIds
             FROM Prestador p
             JOIN Usuario u ON u.idUsuario = p.idUsuario
             LEFT JOIN PrestadorEspecialidade pe ON pe.idUsuario = p.idUsuario
             LEFT JOIN Especialidade s ON s.idEspecialidade = pe.idEspecialidade
             WHERE p.idUsuario <> ?
               AND NOT EXISTS (
                   SELECT 1
                   FROM UsuarioBloqueio b
                   WHERE (b.idBloqueador = ? AND b.idBloqueado = p.idUsuario)
                      OR (b.idBloqueador = p.idUsuario AND b.idBloqueado = ?)
               )
             GROUP BY p.idUsuario, u.nome, u.razaoSocial, p.valor, p.periodo_cobranca, u.media_reputacao
             ORDER BY COALESCE(u.media_reputacao, 0) DESC, p.valor ASC, nome ASC
             LIMIT 200"
        );
        $statement->bind_param('iii', $excludeUserId, $excludeUserId, $excludeUserId);
        $statement->execute();

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findProvider(int $providerId): array | false
    {
        $statement = $this->db->prepare(
            "SELECT p.idUsuario,
                    COALESCE(NULLIF(u.nome, ''), u.razaoSocial) AS nome,
                    p.valor,
                    p.periodo_cobranca,
                    u.media_reputacao,
                    GROUP_CONCAT(DISTINCT s.nomeEspecialidade ORDER BY s.nomeEspecialidade SEPARATOR ', ') AS especialidades,
                    GROUP_CONCAT(DISTINCT pe.idEspecialidade ORDER BY pe.idEspecialidade SEPARATOR ',') AS especialidadeIds
             FROM Prestador p
             JOIN Usuario u ON u.idUsuario = p.idUsuario
             LEFT JOIN PrestadorEspecialidade pe ON pe.idUsuario = p.idUsuario
             LEFT JOIN Especialidade s ON s.idEspecialidade = pe.idEspecialidade
             WHERE p.idUsuario = ?
             GROUP BY p.idUsuario, u.nome, u.razaoSocial, p.valor, p.periodo_cobranca, u.media_reputacao
             LIMIT 1"
        );
        $statement->bind_param('i', $providerId);

        return $this->fetchOne($statement);
    }

    public function isBlocked(int $firstUserId, int $secondUserId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1
             FROM UsuarioBloqueio
             WHERE (idBloqueador = ? AND idBloqueado = ?)
                OR (idBloqueador = ? AND idBloqueado = ?)
             LIMIT 1'
        );
        $statement->bind_param('iiii', $firstUserId, $secondUserId, $secondUserId, $firstUserId);

        return (bool) $this->fetchOne($statement);
    }
}
