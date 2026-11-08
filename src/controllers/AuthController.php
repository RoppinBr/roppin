<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/RememberMeTokenRepository.php';

class AuthController
{
    private UserRepository $users;
    private RememberMeTokenRepository $rememberTokens;

    public function __construct(UserRepository $users, RememberMeTokenRepository $rememberTokens)
    {
        $this->users = $users;
        $this->rememberTokens = $rememberTokens;
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

        $this->rememberTokens->revoke($_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '');
        clearRememberCookie();
        session_regenerate_id(true);
        $_SESSION['usuario_logado'] = $usuario;

        if (filter_var($input['lembrar_me'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $token = $this->rememberTokens->issue((int) $usuario->getId());
            if ($token) {
                setRememberCookie($token['cookie'], $token['expires']);
            }
        }

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

        $this->rememberTokens->revoke($_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '');
        clearRememberCookie();
        session_regenerate_id(true);
        $_SESSION['usuario_logado'] = $usuario;
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Conta criada com sucesso!']);
    }

    public function logout(): ControllerResponse
    {
        $this->rememberTokens->revoke($_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '');
        clearRememberCookie();
        $_SESSION = [];
        session_destroy();
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Saída efetuada com sucesso!']);
    }
}