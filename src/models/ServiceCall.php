<?php
require_once __DIR__ . '/Model.php';

class ServiceCall extends Model
{
    public static function table(): string { return 'Chamado'; }
    public static function primaryKey(): string { return 'idChamado'; }
    public static function fields(): array { return ['idChamado', 'horario', 'idEspecialidade', 'idTomador', 'idPrestador']; }
}