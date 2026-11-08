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

    public function findByName(string $name): Specialty | false
    {
        $statement = $this->db->prepare('SELECT idEspecialidade, nomeEspecialidade FROM Especialidade WHERE LOWER(nomeEspecialidade) = LOWER(?) LIMIT 1');
        $statement->bind_param('s', $name);
        $row = $this->fetchOne($statement);

        return $row ? new Specialty($row) : false;
    }

    public function isValidName(string $name): bool
    {
        $length = preg_match_all('/./us', $name);
        return $name !== '' && $length !== false && $length <= 100;
    }

    public function create(string $name): Specialty | false
    {
        $statement = $this->db->prepare('INSERT INTO Especialidade (nomeEspecialidade) VALUES (?)');
        $statement->bind_param('s', $name);
        $statement->execute();

        return $this->findById((int) $statement->insert_id);
    }

    public function createOrFindMany(array $names): array | false
    {
        $transactionStarted = false;

        try {
            $this->db->begin_transaction();
            $transactionStarted = true;
            $specialties = [];

            foreach ($names as $name) {
                if (!is_string($name)) {
                    throw new InvalidArgumentException('Nome de especialidade inválido.');
                }

                $name = trim($name);
                if (!$this->isValidName($name)) {
                    throw new InvalidArgumentException('Nome de especialidade inválido.');
                }

                $specialty = $this->findByName($name) ?: $this->create($name);
                if (!$specialty) {
                    throw new RuntimeException('Não foi possível criar a especialidade.');
                }
                $specialties[(int) $specialty->get('idEspecialidade')] = $specialty->toArray();
            }

            $this->db->commit();
            $transactionStarted = false;
            return array_values($specialties);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('SpecialtyRepository::createOrFindMany -> ' . $e->getMessage());
            return false;
        }
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
        $statement = $this->db->prepare('SELECT idUsuario FROM PrestadorEspecialidade WHERE idEspecialidade = ? LIMIT 1');
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