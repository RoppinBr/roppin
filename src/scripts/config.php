<?php
    function loadEnv($caminho) {
        if (!file_exists($caminho)) return;

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linhas as $linha) {
            if (strpos(trim($linha), '#') === 0) continue;
            list($nome, $valor) = explode('=', $linha, 2);
            putenv(trim($nome) . "=" . trim($valor));
        }
    }

    loadEnv(__DIR__ . '/../../.env');

    $roppinTimezone = trim((string) getenv('APP_TIMEZONE'));
    if ($roppinTimezone === '' || !in_array($roppinTimezone, timezone_identifiers_list(), true)) {
        $roppinTimezone = 'America/Sao_Paulo';
    }
    date_default_timezone_set($roppinTimezone);
    unset($roppinTimezone);

    function chatEnabled(): bool {
        $valor = strtolower(trim((string) getenv('CHAT_ENABLED')));
        return $valor === '' || !in_array($valor, ['0', 'false', 'off', 'no'], true);
    }
?>