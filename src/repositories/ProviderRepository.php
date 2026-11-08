<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/Provider.php';

class ProviderRepository extends BaseRepository
{
    public function findByUserId(int $userId): Provider | false
    {
        $statement = $this->db->prepare('SELECT idUsuario, idEspecialidade, valor_hora FROM Prestador WHERE idUsuario = ? LIMIT 1');
        $statement->bind_param('i', $userId);
        $row = $this->fetchOne($statement);

        return $row ? new Provider($row) : false;
    }

    public function exists(int $userId): bool
    {
        return $this->findByUserId($userId) !== false;
    }

    public function create(int $userId, int $specialtyId, float $hourlyRate): Provider | false
    {
        $statement = $this->db->prepare('INSERT INTO Prestador (idUsuario, idEspecialidade, valor_hora) SELECT ?, idEspecialidade, ? FROM Especialidade WHERE idEspecialidade = ?');
        $statement->bind_param('idi', $userId, $hourlyRate, $specialtyId);
        $statement->execute();

        return $statement->affected_rows === 1 ? $this->findByUserId($userId) : false;
    }
}