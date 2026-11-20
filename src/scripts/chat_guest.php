<?php
require_once __DIR__ . '/auth_session.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../repositories/ChatGuestSessionRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

function chatCurrentUser(UserRepository $users, ChatGuestSessionRepository $sessions): User | false
{
    $user = $_SESSION['usuario_logado'] ?? null;
    if ($user instanceof User && !$user->isGuest()) {
        return $user;
    }

    $cookie = (string) ($_COOKIE[ROPPIN_GUEST_COOKIE] ?? '');
    if ($cookie === '') {
        return false;
    }

    $userId = $sessions->findUserIdByToken($cookie);
    if ($userId === false) {
        clearChatGuestCookie();
        return false;
    }

    $guest = $users->findById($userId);
    if (!$guest || !$guest->isGuest()) {
        clearChatGuestCookie();
        return false;
    }

    return $guest;
}

function chatEnsureGuest(UserRepository $users, ChatGuestSessionRepository $sessions): User | false
{
    $user = chatCurrentUser($users, $sessions);
    if ($user) {
        return $user;
    }

    $guest = $users->createGuest();
    if (!$guest) {
        return false;
    }

    $token = $sessions->createForUser((int) $guest->getId());
    if (!$token) {
        return false;
    }

    setChatGuestCookie($token, time() + $sessions->ttl());
    return $guest;
}

function chatClearGuest(): void
{
    clearChatGuestCookie();
}
