<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/User.php';

class UserRepository extends BaseRepository
{
    private const USER_FIELDS = 'idUsuario, idPessoa, tipoPessoa, cpf, nome, cnpj, razaoSocial, email, senha, media_reputacao, ehConvidado';

    public function findById(int $id): User | false
    {
        try {
            $statement = $this->db->prepare('SELECT ' . self::USER_FIELDS . ' FROM Usuario WHERE idUsuario = ? LIMIT 1');
            $statement->bind_param('i', $id);
            $dados = $this->fetchOne($statement);
            return $dados ? new User($dados) : false;
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::findById -> ' . $e->getMessage());
            return false;
        }
    }

    public function findByEmailAndPassword(string $email, string $senha): User | false
    {
        try {
            $statement = $this->db->prepare('SELECT ' . self::USER_FIELDS . ' FROM Usuario WHERE email = ? AND ehConvidado = 0 LIMIT 1');
            $statement->bind_param('s', $email);
            $dados = $this->fetchOne($statement);

            return $dados && password_verify($senha, $dados['senha']) ? new User($dados) : false;
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::findByEmailAndPassword -> ' . $e->getMessage());
            return false;
        }
    }

    public function create(string $nome, string $documento, string $email, string $senha): User | false
    {
        $documento = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $documento));
        $tipoPessoa = strlen($documento) === 11 ? 'PF' : (strlen($documento) === 14 ? 'PJ' : '');

        if ($tipoPessoa === '') {
            return false;
        }

        $cpf = $tipoPessoa === 'PF' ? $documento : null;
        $nomePessoa = $tipoPessoa === 'PF' ? $nome : null;
        $cnpj = $tipoPessoa === 'PJ' ? $documento : null;
        $razaoSocial = $tipoPessoa === 'PJ' ? $nome : null;
        $hashSenha = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $statement = $this->db->prepare('INSERT INTO Usuario (idPessoa, tipoPessoa, cpf, nome, cnpj, razaoSocial, email, senha) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)');
            $statement->bind_param('sssssss', $tipoPessoa, $cpf, $nomePessoa, $cnpj, $razaoSocial, $email, $hashSenha);
            $statement->execute();

            return $this->findById((int) $statement->insert_id);
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::create -> ' . $e->getMessage());
            return false;
        }
    }

    public function createGuest(): User | false
    {
        $email = 'convidado_' . bin2hex(random_bytes(16)) . '@guest.roppin.local';
        $hashSenha = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        try {
            $statement = $this->db->prepare(
                "INSERT INTO Usuario (idPessoa, tipoPessoa, nome, email, senha, ehConvidado)
                 VALUES (NULL, 'PF', 'Visitante', ?, ?, 1)"
            );
            $statement->bind_param('ss', $email, $hashSenha);
            $statement->execute();

            return $this->findById((int) $statement->insert_id);
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::createGuest -> ' . $e->getMessage());
            return false;
        }
    }

    public function findByEmail(string $email): User | false
    {
        try {
            $statement = $this->db->prepare('SELECT ' . self::USER_FIELDS . ' FROM Usuario WHERE email = ? LIMIT 1');
            $statement->bind_param('s', $email);
            $dados = $this->fetchOne($statement);
            return $dados ? new User($dados) : false;
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::findByEmail -> ' . $e->getMessage());
            return false;
        }
    }

    public function promoteGuest(int $id, string $nome, string $documento, string $email, string $senha): User | false
    {
        $documento = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $documento));
        $tipoPessoa = strlen($documento) === 11 ? 'PF' : (strlen($documento) === 14 ? 'PJ' : '');

        if ($tipoPessoa === '') {
            return false;
        }

        $cpf = $tipoPessoa === 'PF' ? $documento : null;
        $nomePessoa = $tipoPessoa === 'PF' ? $nome : null;
        $cnpj = $tipoPessoa === 'PJ' ? $documento : null;
        $razaoSocial = $tipoPessoa === 'PJ' ? $nome : null;
        $hashSenha = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $statement = $this->db->prepare(
                'UPDATE Usuario
                 SET idPessoa = NULL, tipoPessoa = ?, cpf = ?, nome = ?, cnpj = ?, razaoSocial = ?, email = ?, senha = ?, ehConvidado = 0
                 WHERE idUsuario = ? AND ehConvidado = 1'
            );
            $statement->bind_param('sssssssi', $tipoPessoa, $cpf, $nomePessoa, $cnpj, $razaoSocial, $email, $hashSenha, $id);
            $statement->execute();

            $usuario = $this->findById($id);
            return $usuario && !$usuario->isGuest() ? $usuario : false;
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::promoteGuest -> ' . $e->getMessage());
            return false;
        }
    }

    public function updateGuestContact(int $id, string $nome, string $email): User | false
    {
        try {
            $statement = $this->db->prepare(
                'UPDATE Usuario SET nome = ?, email = ? WHERE idUsuario = ? AND ehConvidado = 1'
            );
            $statement->bind_param('ssi', $nome, $email, $id);
            $statement->execute();

            $usuario = $this->findById($id);
            return $usuario && $usuario->isGuest() ? $usuario : false;
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::updateGuestContact -> ' . $e->getMessage());
            return false;
        }
    }

    public function update(int $id, string $nome, string $documento, string $email, string $senha = ''): User | false
    {
        $documento = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $documento));
        $tipoPessoa = strlen($documento) === 11 ? 'PF' : (strlen($documento) === 14 ? 'PJ' : '');

        if ($tipoPessoa === '') {
            return false;
        }

        $cpf = $tipoPessoa === 'PF' ? $documento : null;
        $nomePessoa = $tipoPessoa === 'PF' ? $nome : null;
        $cnpj = $tipoPessoa === 'PJ' ? $documento : null;
        $razaoSocial = $tipoPessoa === 'PJ' ? $nome : null;

        try {
            if ($senha !== '') {
                $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
                $statement = $this->db->prepare('UPDATE Usuario SET tipoPessoa = ?, cpf = ?, nome = ?, cnpj = ?, razaoSocial = ?, email = ?, senha = ? WHERE idUsuario = ?');
                $statement->bind_param('sssssssi', $tipoPessoa, $cpf, $nomePessoa, $cnpj, $razaoSocial, $email, $hashSenha, $id);
            } else {
                $statement = $this->db->prepare('UPDATE Usuario SET tipoPessoa = ?, cpf = ?, nome = ?, cnpj = ?, razaoSocial = ?, email = ? WHERE idUsuario = ?');
                $statement->bind_param('ssssssi', $tipoPessoa, $cpf, $nomePessoa, $cnpj, $razaoSocial, $email, $id);
            }
            $statement->execute();

            return $this->findById($id);
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::update -> ' . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $statement = $this->db->prepare('DELETE FROM Usuario WHERE idUsuario = ?');
            $statement->bind_param('i', $id);
            $statement->execute();
            return $statement->affected_rows === 1;
        } catch (mysqli_sql_exception $e) {
            error_log('UserRepository::delete -> ' . $e->getMessage());
            return false;
        }
    }
}