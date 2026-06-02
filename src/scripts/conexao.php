<?php
require_once 'config.php';

function getConexao() {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $name = getenv('DB_NAME');

    // Força o mysqli a lançar exceções para podermos capturá-las
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli($host, $user, $pass, $name);
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Log do erro internamente (arquivo de log)
        error_log("Erro de conexão: " . $e->getMessage());
        
        // Retorna falso para o seu código saber que falhou
        return false;
    }
}

?>