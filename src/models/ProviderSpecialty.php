<?php
require_once __DIR__ . '/Model.php';

class ProviderSpecialty extends Model
{
    public static function table(): string { return 'PrestadorEspecialidade'; }
    public static function primaryKey(): string { return 'idUsuario'; }
    public static function fields(): array { return ['idUsuario', 'idEspecialidade']; }
    public static function hasAutoIncrement(): bool { return false; }
}