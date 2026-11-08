<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/PasswordResetTokenRepository.php';

$db = getConexao();
$userRepository = $db ? new UserRepository($db) : null;
$passwordResetTokenRepository = $db ? new PasswordResetTokenRepository($db) : null;

if (!$db) {
    error_log('bd_control.php -> Não foi possível acessar o banco de dados.');
}

function getLogin(string $email, string $senha): false | User
{
    global $userRepository;
    return $userRepository ? $userRepository->findByEmailAndPassword($email, $senha) : false;
}

function createUser(string $nome, string $cpf, string $email, string $senha): false | User
{
    global $userRepository;
    return $userRepository ? $userRepository->create($nome, $cpf, $email, $senha) : false;
}

function updateUser(int $id, string $nome, string $cpf, string $email, string $senha = ''): false | User
{
    global $userRepository;
    return $userRepository ? $userRepository->update($id, $nome, $cpf, $email, $senha) : false;
}

function deleteUser(int $id): bool
{
    global $userRepository;
    return $userRepository ? $userRepository->delete($id) : false;
}

function createPasswordReset(string $cpf): array | false
{
    global $passwordResetTokenRepository;
    return $passwordResetTokenRepository ? $passwordResetTokenRepository->createForDocument($cpf) : false;
}

function invalidatePasswordReset(string $token): void
{
    global $passwordResetTokenRepository;
    if ($passwordResetTokenRepository) {
        $passwordResetTokenRepository->invalidate($token);
    }
}

function resetPasswordWithToken(string $token, string $senha): bool
{
    global $passwordResetTokenRepository;
    return $passwordResetTokenRepository ? $passwordResetTokenRepository->resetPassword($token, $senha) : false;
}