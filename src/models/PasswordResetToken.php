<?php
class PasswordResetToken
{
    private int $usuarioId;
    private string $tokenHash;

    private function __construct(int $usuarioId, string $tokenHash)
    {
        $this->usuarioId = $usuarioId;
        $this->tokenHash = $tokenHash;
    }

    public static function issue(int $usuarioId, string $token): self
    {
        return new self($usuarioId, hash('sha256', $token));
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }
}