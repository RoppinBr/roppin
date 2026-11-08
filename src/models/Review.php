<?php
require_once __DIR__ . '/Model.php';

class Review extends Model
{
    public static function table(): string { return 'Avaliacao'; }
    public static function primaryKey(): string { return 'idAvaliacao'; }
    public static function fields(): array { return ['idAvaliacao', 'nota', 'data', 'hora', 'idAvaliador', 'idAvaliado']; }
}