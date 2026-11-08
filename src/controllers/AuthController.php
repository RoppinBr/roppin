<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AuthController
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function login(array $input): ControllerResponse
    {
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';

        if ($email === '' || $senha === '') {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Preencha todos os campos!'], 400);
        }

        $usuario = $this->users->findByEmailAndPassword($email, $senha);
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'E-mail ou senha incorretos.'], 401);
        }

        $_SESSION['usuario_logado'] = $usuario;
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Login efetuado com sucesso!']);
    }

    public function register(array $input): ControllerResponse
    {
        $nome = trim($input['nome'] ?? '');
        $documento = trim($input['documento'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';

        if ($nome === '' || $documento === '' || $email === '' || $senha === '') {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Todos os campos são obrigatórios!'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.'], 400);
        }

        $usuario = $this->users->create($nome, $documento, $email, $senha);
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Erro ao criar conta. Verifique se o CPF ou E-mail já existem.'], 409);
        }

        $_SESSION['usuario_logado'] = $usuario;
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Conta criada com sucesso!']);
    }

    public function logout(): ControllerResponse
    {
        $_SESSION = [];
        session_destroy();
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Saída efetuada com sucesso!']);
    }
}