<?php
require_once __DIR__ . '/Model.php';

class ConversationParticipant extends Model
{
    public static function table(): string { return 'ConversaParticipante'; }
    public static function primaryKey(): string { return 'idConversa'; }
    public static function fields(): array
    {
        return [
            'idConversa',
            'idUsuario',
            'papel',
            'canal',
            'ultimaMensagemLida',
            'lidaEm',
            'ultimoVistoEm',
            'digitandoEm'
        ];
    }
    public static function hasAutoIncrement(): bool { return false; }
}
