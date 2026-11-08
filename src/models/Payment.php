<?php
require_once __DIR__ . '/Model.php';

class Payment extends Model
{
    public static function table(): string { return 'Pagamento'; }
    public static function primaryKey(): string { return 'idPagamento'; }
    public static function fields(): array { return ['idPagamento', 'idChamado', 'meioPagamento', 'valor', 'data', 'hora', 'banco']; }
}