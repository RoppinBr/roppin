<?php 
    require_once './src/scripts/conexao.php';
    require_once './src/models/User.php';

    $db = getConexao();

    if (!$db) {
        error_log("bd_control.php -> Erro crítico: Não foi possível acessar o banco de dados.");
    }

    function getLogin(string $email, string $senha): false | User
    {
        global $db;

        if (!$db) {
            error_log("getLogin -> Tentativa de login sem conexão com o banco.");
            return false;
        }

        $stmt = $db->prepare("SELECT * FROM Usuario WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        try {
            $stmt->execute();

            $resultado = $stmt->get_result();
            $dados = $resultado->fetch_assoc();

            if ($dados && password_verify($senha, $dados['senha'])) {
                $user = new User($dados);
                return $user;
            }

            return false;
        } catch (mysqli_sql_exception $e) {
            error_log("getLogin -> Erro de banco: " . $e->getMessage());
        }

        return false;
    }

    function createUser(string $nome, string $cpf, string $email, string $senha): false | User
    {
        global $db;

        if (!$db) {
            error_log("createUser -> Tentativa de cadastro sem conexão com o banco.");
            return false;
        }

        $hashSenha = password_hash($senha, PASSWORD_DEFAULT); # Em BCRIPT

        $stmt = $db->prepare("INSERT INTO Usuario (nome, cpf, email, senha) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nome, $cpf, $email, $hashSenha);
        
        try {
            if ($stmt->execute()) {

                $dados = [
                    'id'    => $stmt->insert_id,
                    'nome'  => $nome,
                    'cpf'   => $cpf,
                    'email' => $email,
                    'senha' => $hashSenha
                ];

                return new User($dados);
            }

        } catch (mysqli_sql_exception $e) {
            error_log("createUser -> Erro de banco: " . $e->getMessage());
        }

        return false;
    }

    // createUser("Otávio Custódio Zampar", "11111111111", "otavio.zampar@aluno.cps.sp.gov.br", "teste123");
?>