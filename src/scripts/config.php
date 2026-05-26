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
?>