<?php
require_once __DIR__ . '/ChatException.php';

class ChatCryptoService
{
    public const ALGORITHM = 'aes-256-gcm';
    public const LEGACY = 'nenhuma';
    public const PREVIEW_PREFIX = 'enc:v1:';
    public const UNAVAILABLE = '[conteúdo indisponível]';

    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    private ?string $key = null;
    private bool $keyLoaded = false;

    public function isConfigured(): bool
    {
        try {
            $this->key();
            return true;
        } catch (ChatException $e) {
            return false;
        }
    }

    public function encrypt(string $plaintext): string
    {
        return base64_encode($this->encryptBinary($plaintext));
    }

    public function decrypt(string $payload, string $algorithm = self::ALGORITHM): string
    {
        if ($this->isLegacy($algorithm)) {
            return $payload;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false) {
            throw new ChatException('O conteúdo da mensagem está corrompido.', 500);
        }

        return $this->decryptBinary($raw, $algorithm);
    }

    public function encryptPreview(string $text): string
    {
        return self::PREVIEW_PREFIX . $this->encrypt($text);
    }

    public function tryDecryptPreview(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (str_starts_with($value, self::PREVIEW_PREFIX)) {
            return $this->tryDecrypt(substr($value, strlen(self::PREVIEW_PREFIX)));
        }

        return $value;
    }

    public function tryDecrypt(?string $payload, string $algorithm = self::ALGORITHM): string
    {
        if ($payload === null) {
            return '';
        }

        try {
            return $this->decrypt($payload, $algorithm);
        } catch (Throwable $e) {
            error_log('ChatCryptoService::tryDecrypt -> ' . $e->getMessage());
            return self::UNAVAILABLE;
        }
    }

    public function encryptBinary(string $data): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $data,
            self::ALGORITHM,
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new ChatException('Não foi possível criptografar a mensagem.', 500);
        }

        return $nonce . $tag . $ciphertext;
    }

    public function decryptBinary(string $data, string $algorithm = self::ALGORITHM): string
    {
        if ($this->isLegacy($algorithm)) {
            return $data;
        }

        if ($algorithm !== self::ALGORITHM) {
            throw new ChatException('Algoritmo de criptografia não suportado.', 500);
        }

        if (strlen($data) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new ChatException('O conteúdo da mensagem está corrompido.', 500);
        }

        $nonce = substr($data, 0, self::NONCE_BYTES);
        $tag = substr($data, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($data, self::NONCE_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGORITHM,
            $this->key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new ChatException('Não foi possível descriptografar a mensagem.', 500);
        }

        return $plaintext;
    }

    private function isLegacy(string $algorithm): bool
    {
        return $algorithm === '' || $algorithm === self::LEGACY;
    }

    private function key(): string
    {
        if ($this->keyLoaded) {
            if ($this->key === null) {
                throw new ChatException('A criptografia do chat não está configurada. Defina CHAT_ENCRYPTION_KEY no .env.', 503);
            }
            return $this->key;
        }

        $this->keyLoaded = true;
        $encoded = trim((string) getenv('CHAT_ENCRYPTION_KEY'));
        if ($encoded === '') {
            throw new ChatException('A criptografia do chat não está configurada. Defina CHAT_ENCRYPTION_KEY no .env.', 503);
        }

        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== 32) {
            error_log('ChatCryptoService -> CHAT_ENCRYPTION_KEY inválida: esperados 32 bytes em base64.');
            throw new ChatException('A chave de criptografia do chat é inválida.', 503);
        }

        $this->key = $key;
        return $this->key;
    }
}
