<?php
require_once __DIR__ . '/Model.php';

class UserBlock extends Model
{
    public static function table(): string { return 'UsuarioBloqueio'; }
    public static function primaryKey(): string { return 'idBloqueador'; }
    public static function fields(): array
    {
        return ['idBloqueador', 'idBloqueado', 'motivo', 'criadoEm'];
    }
    public static function hasAutoIncrement(): bool { return false; }
}
