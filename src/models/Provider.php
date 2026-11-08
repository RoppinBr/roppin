<?php
require_once __DIR__ . '/Model.php';

class Provider extends Model
{
    public static function table(): string { return 'Prestador'; }
    public static function primaryKey(): string { return 'idUsuario'; }
    public static function fields(): array { return ['idUsuario', 'idEspecialidade', 'valor_hora']; }
    public static function hasAutoIncrement(): bool { return false; }
}