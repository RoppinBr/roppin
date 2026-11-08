<?php
require_once __DIR__ . '/Model.php';

class RememberMeToken extends Model
{
    public static function table(): string { return 'RememberMeTokens'; }
    public static function primaryKey(): string { return 'selector'; }
    public static function fields(): array
    {
        return ['selector', 'usuario_id', 'validator_hash', 'criado_em', 'expira_em'];
    }
    public static function hasAutoIncrement(): bool { return false; }
}