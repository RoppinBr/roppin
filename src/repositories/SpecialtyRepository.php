<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/Specialty.php';

class SpecialtyRepository extends BaseRepository
{
    public function findAll(): array
    {
        $result = $this->db->query('SELECT idEspecialidade, nomeEspecialidade FROM Especialidade ORDER BY nomeEspecialidade');
        $specialties = [];

        while ($row = $result->fetch_assoc()) {
            $specialties[] = new Specialty($row);
        }

        return $specialties;
    }

    public function findById(int $id): Specialty | false
    {
        $statement = $this->db->prepare('SELECT idEspecialidade, nomeEspecialidade FROM Especialidade WHERE idEspecialidade = ? LIMIT 1');
        $statement->bind_param('i', $id);
        $row = $this->fetchOne($statement);

        return $row ? new Specialty($row) : false;
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        $statement = $this->db->prepare('SELECT idEspecialidade FROM Especialidade WHERE LOWER(nomeEspecialidade) = LOWER(?) AND idEspecialidade <> ? LIMIT 1');
        $statement->bind_param('si', $name, $exceptId);
        return (bool) $this->fetchOne($statement);
    }

    public function create(string $name): Specialty | false
    {
        $statement = $this->db->prepare('INSERT INTO Especialidade (nomeEspecialidade) VALUES (?)');
        $statement->bind_param('s', $name);
        $statement->execute();

        return $this->findById((int) $statement->insert_id);
    }

    public function update(int $id, string $name): Specialty | false
    {
        $statement = $this->db->prepare('UPDATE Especialidade SET nomeEspecialidade = ? WHERE idEspecialidade = ?');
        $statement->bind_param('si', $name, $id);
        $statement->execute();

        return $this->findById($id);
    }

    public function hasProviders(int $id): bool
    {
        $statement = $this->db->prepare('SELECT idUsuario FROM Prestador WHERE idEspecialidade = ? LIMIT 1');
        $statement->bind_param('i', $id);
        return (bool) $this->fetchOne($statement);
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM Especialidade WHERE idEspecialidade = ?');
        $statement->bind_param('i', $id);
        $statement->execute();

        return $statement->affected_rows === 1;
    }
}