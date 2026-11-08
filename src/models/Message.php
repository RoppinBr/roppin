<?php
require_once __DIR__ . '/Model.php';

class Message extends Model
{
    public static function table(): string { return 'Mensagem'; }
    public static function primaryKey(): string { return 'idMensagem'; }
    public static function fields(): array { return ['idMensagem', 'textoMensagem', 'data', 'hora', 'idRemetente', 'idDestinatario']; }
}