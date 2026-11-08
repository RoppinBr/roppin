<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/Provider.php';
require_once __DIR__ . '/SpecialtyRepository.php';

class ProviderRepository extends BaseRepository
{
    public function findByUserId(int $userId): Provider | false
    {
        $statement = $this->db->prepare('SELECT idUsuario, valor, periodo_cobranca FROM Prestador WHERE idUsuario = ? LIMIT 1');
        $statement->bind_param('i', $userId);
        $row = $this->fetchOne($statement);

        return $row ? new Provider($row) : false;
    }

    public function exists(int $userId): bool
    {
        return $this->findByUserId($userId) !== false;
    }

    public function create(int $userId, float $hourlyRate, string $billingPeriod, array $specialtyIds, SpecialtyRepository $specialties): Provider | false
    {
        $transactionStarted = false;

        try {
            $this->db->begin_transaction();
            $transactionStarted = true;

            $statement = $this->db->prepare('INSERT INTO Prestador (idUsuario, valor, periodo_cobranca) VALUES (?, ?, ?)');
            $statement->bind_param('ids', $userId, $hourlyRate, $billingPeriod);
            $statement->execute();

            $insert = $this->db->prepare('INSERT INTO PrestadorEspecialidade (idUsuario, idEspecialidade) VALUES (?, ?)');
            foreach (array_unique(array_map('intval', $specialtyIds)) as $specialtyId) {
                if ($specialtyId <= 0 || !$specialties->findById($specialtyId)) {
                    throw new InvalidArgumentException('Uma especialidade selecionada não existe.');
                }
                $insert->bind_param('ii', $userId, $specialtyId);
                $insert->execute();
            }

            $this->db->commit();
            $transactionStarted = false;
            return $this->findByUserId($userId);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('ProviderRepository::create -> ' . $e->getMessage());
            return false;
        }
    }

    public function updatePricing(int $userId, float $hourlyRate, string $billingPeriod): Provider | false
    {
        $statement = $this->db->prepare('UPDATE Prestador SET valor = ?, periodo_cobranca = ? WHERE idUsuario = ?');
        $statement->bind_param('dsi', $hourlyRate, $billingPeriod, $userId);
        $statement->execute();

        return $this->findByUserId($userId);
    }

    public function findSpecialtiesByUserId(int $userId): array
    {
        $statement = $this->db->prepare('SELECT s.idEspecialidade, s.nomeEspecialidade FROM PrestadorEspecialidade ps JOIN Especialidade s ON s.idEspecialidade = ps.idEspecialidade WHERE ps.idUsuario = ? ORDER BY s.nomeEspecialidade');
        $statement->bind_param('i', $userId);
        $statement->execute();

        return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function saveSpecialties(int $userId, array $specialtyIds, array $newNames, SpecialtyRepository $specialties): array | false
    {
        if (!$this->exists($userId)) {
            return false;
        }

        $transactionStarted = false;

        try {
            $this->db->begin_transaction();
            $transactionStarted = true;
            $selectedIds = [];

            foreach (array_unique(array_map('intval', $specialtyIds)) as $specialtyId) {
                if ($specialtyId <= 0 || !$specialties->findById($specialtyId)) {
                    throw new InvalidArgumentException('Uma das especialidades selecionadas não existe.');
                }
                $selectedIds[] = $specialtyId;
            }

            foreach ($newNames as $name) {
                if (!is_string($name)) {
                    throw new InvalidArgumentException('Nome de especialidade inválido.');
                }

                $name = trim($name);
                if (!$specialties->isValidName($name)) {
                    throw new InvalidArgumentException('Cada especialidade nova deve ter até 100 caracteres.');
                }

                $existing = $specialties->findByName($name);
                $specialty = $existing ?: $specialties->create($name);
                if (!$specialty) {
                    throw new RuntimeException('Não foi possível criar uma especialidade.');
                }
                $selectedIds[] = (int) $specialty->get('idEspecialidade');
            }

            $selectedIds = array_values(array_unique($selectedIds));
            $delete = $this->db->prepare('DELETE FROM PrestadorEspecialidade WHERE idUsuario = ?');
            $delete->bind_param('i', $userId);
            $delete->execute();

            $insert = $this->db->prepare('INSERT INTO PrestadorEspecialidade (idUsuario, idEspecialidade) VALUES (?, ?)');
            foreach ($selectedIds as $specialtyId) {
                $insert->bind_param('ii', $userId, $specialtyId);
                $insert->execute();
            }

            $this->db->commit();
            $transactionStarted = false;
            return $this->findSpecialtiesByUserId($userId);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('ProviderRepository::saveSpecialties -> ' . $e->getMessage());
            return false;
        }
    }
}