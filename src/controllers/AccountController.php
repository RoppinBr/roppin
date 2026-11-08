<?php
require_once __DIR__ . '/ControllerResponse.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AccountController
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function update(array $input): ControllerResponse
    {
        $usuario = $this->authenticatedUser();
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        if (!$this->validCsrfToken($input['csrf_token'] ?? '')) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
        }

        $nome = trim($input['nome'] ?? '');
        $documento = trim($input['cpf'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';

        if ($nome === '' || $documento === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Informe nome, documento e um e-mail válido.'], 400);
        }

        $atualizado = $this->users->update((int) $usuario->getId(), $nome, $documento, $email, $senha);
        if (!$atualizado) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível atualizar. Verifique se o e-mail ou documento já estão em uso.'], 409);
        }

        $_SESSION['usuario_logado'] = $atualizado;
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Dados atualizados com sucesso!']);
    }

    public function delete(array $input): ControllerResponse
    {
        $usuario = $this->authenticatedUser();
        if (!$usuario) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Faça login para continuar.'], 401);
        }

        if (!$this->validCsrfToken($input['csrf_token'] ?? '')) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
        }

        if (!$this->users->delete((int) $usuario->getId())) {
            return new ControllerResponse(['sucesso' => false, 'mensagem' => 'Não foi possível excluir sua conta.'], 409);
        }

        $_SESSION = [];
        session_destroy();
        return new ControllerResponse(['sucesso' => true, 'mensagem' => 'Conta excluída com sucesso.']);
    }

    private function authenticatedUser(): User | false
    {
        $usuario = $_SESSION['usuario_logado'] ?? null;
        return $usuario instanceof User ? $usuario : false;
    }

    private function validCsrfToken(string $tokenRecebido): bool
    {
        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        return $tokenSessao !== '' && hash_equals($tokenSessao, $tokenRecebido);
    }
}