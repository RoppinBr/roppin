<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/config.php';

const ROPPIN_REMEMBER_COOKIE = 'roppin_remember';

function rememberCookieOptions(int $expires): array
{
    $appScheme = strtolower((string) parse_url((string) getenv('APP_URL'), PHP_URL_SCHEME));
    $requestIsHttps = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $requestIsHttps || $appScheme === 'https',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function setRememberCookie(string $value, int $expires): void
{
    setcookie(ROPPIN_REMEMBER_COOKIE, $value, rememberCookieOptions($expires));
    $_COOKIE[ROPPIN_REMEMBER_COOKIE] = $value;
}

function clearRememberCookie(): void
{
    setcookie(ROPPIN_REMEMBER_COOKIE, '', rememberCookieOptions(time() - 3600));
    unset($_COOKIE[ROPPIN_REMEMBER_COOKIE]);
}

function startRoppinSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (($_SESSION['usuario_logado'] ?? null) instanceof User) {
        return;
    }

    $cookie = $_COOKIE[ROPPIN_REMEMBER_COOKIE] ?? '';
    if (!is_string($cookie) || $cookie === '') {
        return;
    }

    require_once __DIR__ . '/conexao.php';
    require_once __DIR__ . '/../repositories/UserRepository.php';
    require_once __DIR__ . '/../repositories/RememberMeTokenRepository.php';

    $db = getConexao();
    if (!$db) {
        return;
    }

    $rememberTokens = new RememberMeTokenRepository($db);
    $token = $rememberTokens->consume($cookie);
    if (!$token) {
        clearRememberCookie();
        $db->close();
        return;
    }

    $usuario = (new UserRepository($db))->findById($token['userId']);
    if (!$usuario) {
        $rememberTokens->revoke($token['cookie']);
        clearRememberCookie();
        $db->close();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_logado'] = $usuario;
    setRememberCookie($token['cookie'], $token['expires']);
    $db->close();
}