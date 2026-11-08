<?php
require_once __DIR__ . '/Model.php';

class Specialty extends Model
{
    public static function table(): string { return 'Especialidade'; }
    public static function primaryKey(): string { return 'idEspecialidade'; }
    public static function fields(): array { return ['idEspecialidade', 'nomeEspecialidade']; }
}