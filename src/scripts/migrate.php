<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/conexao.php';

$db = getConexao();
if (!$db) {
    fwrite(STDERR, "Não foi possível conectar ao banco configurado em .env.\n");
    exit(1);
}

$lockAdquirido = false;
$exitCode = 0;

try {
    $db->query("CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(190) NOT NULL,
        checksum CHAR(64) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $lockResult = $db->query("SELECT GET_LOCK('roppin_schema_migrations', 10)");
    $lockAdquirido = (int) $lockResult->fetch_row()[0] === 1;

    if (!$lockAdquirido) {
        throw new RuntimeException('Não foi possível obter o lock de migrations.');
    }

    $raizProjeto = dirname(__DIR__, 2);
    $arquivos = [[
        'path' => $raizProjeto . '/database/initial_migration.sql',
        'name' => 'initial_migration.sql'
    ]];

    foreach (glob($raizProjeto . '/database/migrations/*.sql') ?: [] as $arquivo) {
        $arquivos[] = [
            'path' => $arquivo,
            'name' => str_replace('\\', '/', substr($arquivo, strlen($raizProjeto) + 1))
        ];
    }

    foreach ($arquivos as $migration) {
        $arquivo = $migration['path'];
        $nome = $migration['name'];

        if (!is_file($arquivo)) {
            throw new RuntimeException('Arquivo de migration não encontrado: ' . $arquivo);
        }

        $sql = file_get_contents($arquivo);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migration vazia ou ilegível: ' . $nome);
        }

        $checksum = hash('sha256', $sql);
        $consulta = $db->prepare('SELECT checksum FROM schema_migrations WHERE migration = ? LIMIT 1');
        $consulta->bind_param('s', $nome);
        $consulta->execute();
        $aplicada = $consulta->get_result()->fetch_assoc();

        if ($aplicada) {
            if (!hash_equals($aplicada['checksum'], $checksum)) {
                throw new RuntimeException('Migration aplicada foi alterada: ' . $nome);
            }
            echo "Já aplicada: {$nome}\n";
            continue;
        }

        echo "Aplicando: {$nome}\n";
        $db->multi_query($sql);
        do {
            if ($resultado = $db->store_result()) {
                $resultado->free();
            }
        } while ($db->more_results() && $db->next_result());

        $registro = $db->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)');
        $registro->bind_param('ss', $nome, $checksum);
        $registro->execute();
        echo "Concluída: {$nome}\n";
    }

    echo "Migrations atualizadas.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro na migration: " . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($lockAdquirido) {
        $db->query("SELECT RELEASE_LOCK('roppin_schema_migrations')");
    }
    $db->close();
}

exit($exitCode);