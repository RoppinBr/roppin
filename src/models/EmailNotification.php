<?php
require_once __DIR__ . '/Model.php';

class EmailNotification extends Model
{
    public static function table(): string { return 'NotificacaoEmail'; }
    public static function primaryKey(): string { return 'idNotificacao'; }
    public static function fields(): array
    {
        return [
            'idNotificacao',
            'idUsuario',
            'idConversa',
            'idMensagem',
            'tipo',
            'status',
            'tentativas',
            'ultimoErro',
            'criadaEm',
            'enviadaEm'
        ];
    }
}
