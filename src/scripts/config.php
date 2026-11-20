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

    function roppinRequestScheme(): string
    {
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off') {
            return 'https';
        }

        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto === 'https') {
            return 'https';
        }

        return 'http';
    }

    function roppinBaseUrl(): string
    {
        $scheme = roppinRequestScheme();
        $serverName = strtolower(trim((string) ($_SERVER['SERVER_NAME'] ?? '')));
        $httpHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);

        $host = $serverName;
        if ($serverName !== '' && $httpHost !== '') {
            $hostDoHeader = parse_url('http://' . $httpHost, PHP_URL_HOST);
            if (is_string($hostDoHeader) && strtolower($hostDoHeader) === $serverName) {
                $host = $httpHost;
            }
        }
        if ($host === '') {
            $host = $httpHost;
        }
        if ($host === '') {
            return '';
        }

        if (!preg_match('/^(?:[a-z0-9_-]+(?:\.[a-z0-9_-]+)*|\[[0-9a-f:]+\])(?::\d{1,5})?$/i', $host)) {
            return '';
        }

        if ($host === $serverName && $serverPort > 0 && !preg_match('/:\d{1,5}$/', $host)) {
            if (!in_array($serverPort, [80, 443], true)) {
                $host .= ':' . $serverPort;
            }
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = $scriptName !== '' ? dirname($scriptName) : '';
        if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }
        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && strpos($basePath, '/') !== 0) {
            $basePath = '/' . $basePath;
        }

        return $scheme . '://' . $host . $basePath;
    }

    function chatEnabled(): bool {
        $valor = strtolower(trim((string) getenv('CHAT_ENABLED')));
        return $valor === '' || !in_array($valor, ['0', 'false', 'off', 'no'], true);
    }
?>