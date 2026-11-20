<?php
require_once __DIR__ . '/BaseRepository.php';

class ReviewRepository extends BaseRepository
{
    public function findForPair(int $conversationId, int $reviewerId, int $reviewedId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                 MAX(CASE WHEN idAvaliador = ? THEN nota END) AS minhaNota,
                 MAX(CASE WHEN idAvaliado = ? THEN nota END) AS notaRecebida
             FROM Avaliacao
             WHERE idConversa = ?
               AND ((idAvaliador = ? AND idAvaliado = ?)
                    OR (idAvaliador = ? AND idAvaliado = ?))"
        );
        $statement->bind_param(
            'iiiiiii',
            $reviewerId,
            $reviewerId,
            $conversationId,
            $reviewerId,
            $reviewedId,
            $reviewedId,
            $reviewerId
        );

        return $this->fetchOne($statement) ?: ['minhaNota' => null, 'notaRecebida' => null];
    }

    public function existsForReviewer(int $conversationId, int $reviewerId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM Avaliacao WHERE idConversa = ? AND idAvaliador = ? LIMIT 1'
        );
        $statement->bind_param('ii', $conversationId, $reviewerId);
        $statement->execute();
        $result = $statement->get_result();

        return $result->num_rows > 0;
    }

    public function create(
        int $conversationId,
        int $reviewerId,
        int $reviewedId,
        float $note,
        string $date,
        string $time
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO Avaliacao (idConversa, nota, data, hora, idAvaliador, idAvaliado)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $statement->bind_param('idssii', $conversationId, $note, $date, $time, $reviewerId, $reviewedId);
        $statement->execute();

        return (int) $statement->insert_id;
    }

    public function recalculateReputation(int $userId): float
    {
        $statement = $this->db->prepare(
            'UPDATE Usuario
             SET media_reputacao = (
                 SELECT ROUND(AVG(nota), 2) FROM Avaliacao WHERE idAvaliado = ?
             )
             WHERE idUsuario = ?'
        );
        $statement->bind_param('ii', $userId, $userId);
        $statement->execute();

        $statement = $this->db->prepare('SELECT media_reputacao FROM Usuario WHERE idUsuario = ?');
        $statement->bind_param('i', $userId);
        $row = $this->fetchOne($statement);

        return $row && $row['media_reputacao'] !== null ? (float) $row['media_reputacao'] : 0.0;
    }
}
