<?php

namespace App\Services\Pos;

use RuntimeException;

class QzTrayService
{
    /**
     * Whether a usable certificate + private key pair is configured.
     */
    public function isConfigured(): bool
    {
        return $this->certificate() !== null && $this->privateKey() !== null;
    }

    /**
     * The public digital certificate handed to the browser, or null when
     * signing is not configured.
     *
     * Resolves in order: file path → base64 env var.
     */
    public function certificate(): ?string
    {
        $path = config('qz.certificate_path');

        if ($path && is_readable($path)) {
            $contents = file_get_contents($path);

            return $contents !== false ? trim($contents) : null;
        }

        $b64 = config('qz.certificate_base64');

        if ($b64) {
            $decoded = base64_decode($b64, strict: true);

            return $decoded !== false ? trim($decoded) : null;
        }

        return null;
    }

    /**
     * Sign the request payload QZ Tray sends, returning a base64 signature.
     *
     * @throws RuntimeException when signing is not configured or the key is invalid.
     */
    public function sign(string $data): string
    {
        $key = $this->privateKey();

        if ($key === null) {
            throw new RuntimeException('QZ Tray signing is not configured.');
        }

        $privateKey = openssl_pkey_get_private($key, config('qz.private_key_passphrase') ?? '');

        if ($privateKey === false) {
            throw new RuntimeException('QZ Tray private key could not be loaded.');
        }

        $signature = '';

        if (! openssl_sign($data, $signature, $privateKey, $this->opensslAlgorithm())) {
            throw new RuntimeException('QZ Tray request could not be signed.');
        }

        return base64_encode($signature);
    }

    /**
     * The PEM-encoded private key contents, or null when not configured.
     *
     * Resolves in order: file path → base64 env var.
     */
    protected function privateKey(): ?string
    {
        $path = config('qz.private_key_path');

        if ($path && is_readable($path)) {
            $contents = file_get_contents($path);

            return $contents !== false ? $contents : null;
        }

        $b64 = config('qz.private_key_base64');

        if ($b64) {
            $decoded = base64_decode($b64, strict: true);

            return $decoded !== false ? $decoded : null;
        }

        return null;
    }

    /**
     * Map the configured algorithm name to an OpenSSL constant.
     */
    protected function opensslAlgorithm(): int
    {
        return match (strtoupper((string) config('qz.algorithm', 'SHA512'))) {
            'SHA1' => OPENSSL_ALGO_SHA1,
            'SHA256' => OPENSSL_ALGO_SHA256,
            default => OPENSSL_ALGO_SHA512,
        };
    }
}
