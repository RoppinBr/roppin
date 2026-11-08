<?php
require_once __DIR__ . '/Model.php';

class Requester extends Model
{
    public static function table(): string { return 'Tomador'; }
    public static function primaryKey(): string { return 'idUsuario'; }
    public static function fields(): array { return ['idUsuario', 'endereco', 'telefone']; }
    public static function hasAutoIncrement(): bool { return false; }
}