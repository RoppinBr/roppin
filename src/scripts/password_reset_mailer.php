<?php
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function sendPasswordResetEmail(string $email, string $nome, string $resetUrl): bool
{
    $host = trim((string) getenv('SMTP_HOST'));
    $usuario = trim((string) getenv('SMTP_USER'));
    $senha = (string) getenv('SMTP_PASS');
    $remetente = trim((string) (getenv('SMTP_FROM') ?: $usuario));
    $criptografia = strtolower(trim((string) (getenv('SMTP_ENCRYPTION') ?: 'tls')));

    if ($host === '' || $remetente === '' || !filter_var($remetente, FILTER_VALIDATE_EMAIL)) {
        error_log('password_reset_mailer -> SMTP_HOST e SMTP_FROM/SMTP_USER válidos são obrigatórios.');
        return false;
    }

    if (!in_array($criptografia, ['tls', 'ssl', 'none'], true)) {
        error_log('password_reset_mailer -> SMTP_ENCRYPTION deve ser tls, ssl ou none.');
        return false;
    }

    $mailer = new PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = (int) (getenv('SMTP_PORT') ?: ($criptografia === 'ssl' ? 465 : 587));
        $mailer->SMTPAuth = $usuario !== '';
        $mailer->Username = $usuario;
        $mailer->Password = $senha;
        $mailer->Timeout = 15;

        if ($criptografia === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($criptografia === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($remetente, (string) (getenv('SMTP_FROM_NAME') ?: 'Roppin'));
        $mailer->addAddress($email, $nome);
        $mailer->isHTML(true);
        $mailer->Subject = 'Redefinição de senha da Roppin';

        $urlEscapada = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $nomeEscapado = htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mailer->Body = "<p>Olá, {$nomeEscapado}.</p><p>Recebemos uma solicitação para redefinir a senha da sua conta Roppin.</p><p><a href=\"{$urlEscapada}\">Criar uma nova senha</a></p><p>Este link expira em 30 minutos e só pode ser usado uma vez. Se você não solicitou a redefinição, ignore esta mensagem.</p>";
        $mailer->AltBody = "Olá, {$nome}. Para redefinir sua senha da Roppin, acesse: {$resetUrl}\n\nO link expira em 30 minutos e só pode ser usado uma vez. Se você não solicitou a redefinição, ignore esta mensagem.";

        return $mailer->send();
    } catch (\Throwable $e) {
        error_log('password_reset_mailer -> Falha ao enviar email: ' . $mailer->ErrorInfo);
        return false;
    }
}