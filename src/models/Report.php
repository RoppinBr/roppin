<?php
require_once __DIR__ . '/Model.php';

class Report extends Model
{
    public static function table(): string { return 'Denuncia'; }
    public static function primaryKey(): string { return 'idDenuncia'; }
    public static function fields(): array
    {
        return [
            'idDenuncia',
            'idDenunciante',
            'idDenunciado',
            'idConversa',
            'idMensagem',
            'motivo',
            'descricao',
            'status',
            'criadaEm',
            'analisadaEm'
        ];
    }
}
