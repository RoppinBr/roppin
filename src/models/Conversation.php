<?php
require_once __DIR__ . '/Model.php';

class Conversation extends Model
{
    public static function table(): string { return 'Conversa'; }
    public static function primaryKey(): string { return 'idConversa'; }
    public static function fields(): array
    {
        return [
            'idConversa',
            'idCliente',
            'idPrestador',
            'idChamado',
            'idEspecialidade',
            'canal',
            'estado',
            'assunto',
            'contexto',
            'ultimaMensagemEm',
            'ultimaMensagemPreview',
            'criadaEm',
            'atualizadaEm'
        ];
    }
}
