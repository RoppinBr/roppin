<?php
require_once __DIR__ . '/BaseRepository.php';

class ChatGuestTransferRepository extends BaseRepository
{
    public function transferToUser(int $guestId, int $userId): bool
    {
        if ($guestId <= 0 || $userId <= 0 || $guestId === $userId) {
            return false;
        }

        $transacaoIniciada = false;

        try {
            $this->db->begin_transaction();
            $transacaoIniciada = true;

            $guestUser = $this->fetchOne(
                $this->prepareUser($guestId, true)
            );
            if (!$guestUser) {
                $this->db->rollback();
                return false;
            }

            $conversas = $this->db->prepare('SELECT idConversa FROM Conversa WHERE idCliente = ?');
            $conversas->bind_param('i', $guestId);
            $conversas->execute();
            $ids = array_map(
                static fn(array $row): int => (int) $row['idConversa'],
                $conversas->get_result()->fetch_all(MYSQLI_ASSOC)
            );

            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $tipos = str_repeat('i', count($ids));

                $removerDuplicatas = $this->db->prepare(
                    "DELETE cp FROM ConversaParticipante cp
                     WHERE cp.idUsuario = ? AND cp.idConversa IN ($placeholders)"
                );
                $removerDuplicatas->bind_param('i' . $tipos, $userId, ...$ids);
                $removerDuplicatas->execute();

                $removerAvaliacoesDuplicadas = $this->db->prepare(
                    "DELETE guest FROM Avaliacao guest
                     JOIN Avaliacao own
                       ON own.idConversa = guest.idConversa
                      AND own.idAvaliador = ?
                     WHERE guest.idAvaliador = ? AND guest.idConversa IN ($placeholders)"
                );
                $removerAvaliacoesDuplicadas->bind_param('ii' . $tipos, $userId, $guestId, ...$ids);
                $removerAvaliacoesDuplicadas->execute();

                $removerBloqueiosDuplicados = $this->db->prepare(
                    'DELETE bloqueio FROM UsuarioBloqueio bloqueio
                     JOIN UsuarioBloqueio own
                       ON own.idBloqueador = ?
                      AND own.idBloqueado = bloqueio.idBloqueado
                     WHERE bloqueio.idBloqueador = ?'
                );
                $removerBloqueiosDuplicados->bind_param('ii', $userId, $guestId);
                $removerBloqueiosDuplicados->execute();

                $removerBloqueiosDuplicados = $this->db->prepare(
                    'DELETE bloqueio FROM UsuarioBloqueio bloqueio
                     JOIN UsuarioBloqueio own
                       ON own.idBloqueado = ?
                      AND own.idBloqueador = bloqueio.idBloqueador
                     WHERE bloqueio.idBloqueado = ?'
                );
                $removerBloqueiosDuplicados->bind_param('ii', $userId, $guestId);
                $removerBloqueiosDuplicados->execute();

                $encerrarDuplicadas = $this->db->prepare(
                    "UPDATE Conversa c
                     JOIN Conversa own
                       ON own.idCliente = ?
                      AND own.idPrestador = c.idPrestador
                      AND own.estado = 'ativa'
                      AND own.idConversa <> c.idConversa
                     SET c.estado = 'encerrada'
                     WHERE c.estado = 'ativa' AND c.idConversa IN ($placeholders)"
                );
                $encerrarDuplicadas->bind_param('i' . $tipos, $userId, ...$ids);
                $encerrarDuplicadas->execute();

                $atualizarParticipantes = $this->db->prepare(
                    "UPDATE ConversaParticipante SET idUsuario = ? WHERE idUsuario = ? AND idConversa IN ($placeholders)"
                );
                $atualizarParticipantes->bind_param('ii' . $tipos, $userId, $guestId, ...$ids);
                $atualizarParticipantes->execute();
            }

            $this->executeUpdate('UPDATE Conversa SET idCliente = ? WHERE idCliente = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE Mensagem SET idRemetente = ? WHERE idRemetente = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE Mensagem SET idDestinatario = ? WHERE idDestinatario = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE Avaliacao SET idAvaliador = ? WHERE idAvaliador = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE Avaliacao SET idAvaliado = ? WHERE idAvaliado = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE UsuarioBloqueio SET idBloqueador = ? WHERE idBloqueador = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE UsuarioBloqueio SET idBloqueado = ? WHERE idBloqueado = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE Denuncia SET idDenunciante = ? WHERE idDenunciante = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE Denuncia SET idDenunciado = ? WHERE idDenunciado = ?', [$userId, $guestId]);
            $this->executeUpdate('UPDATE NotificacaoEmail SET idUsuario = ? WHERE idUsuario = ?', [$userId, $guestId]);
            $this->executeUpdate('DELETE FROM ChatConvidadoSessao WHERE idUsuario = ?', [$guestId]);
            $this->executeUpdate('DELETE FROM Usuario WHERE idUsuario = ?', [$guestId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($transacaoIniciada) {
                $this->db->rollback();
            }
            error_log('ChatGuestTransferRepository::transferToUser -> ' . $e->getMessage());
            return false;
        }
    }

    private function prepareUser(int $id, bool $somenteConvidado): mysqli_stmt
    {
        $sql = 'SELECT idUsuario FROM Usuario WHERE idUsuario = ?'
            . ($somenteConvidado ? ' AND ehConvidado = 1' : '')
            . ' LIMIT 1 FOR UPDATE';
        $statement = $this->db->prepare($sql);
        $statement->bind_param('i', $id);
        return $statement;
    }

    private function executeUpdate(string $sql, array $params): void
    {
        $statement = $this->db->prepare($sql);
        $statement->bind_param(str_repeat('i', count($params)), ...$params);
        $statement->execute();
    }
}
