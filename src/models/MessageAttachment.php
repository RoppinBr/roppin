<?php
require_once __DIR__ . '/Model.php';

class MessageAttachment extends Model
{
    public static function table(): string { return 'MensagemAnexo'; }
    public static function primaryKey(): string { return 'idAnexo'; }
    public static function fields(): array
    {
        return [
            'idAnexo',
            'idMensagem',
            'nomeOriginal',
            'caminhoRelativo',
            'mime',
            'tamanho',
            'criptografia',
            'criadoEm'
        ];
    }
}
