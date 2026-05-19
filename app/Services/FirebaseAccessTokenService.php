<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseAccessTokenService
{
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $credentials = null;

    public function isConfigured(): bool
    {
        $credentialsPath = $this->getConfiguredCredentialsPath();

        return $credentialsPath !== null
            && is_file($credentialsPath)
            && is_readable($credentialsPath);
    }

    public function getProjectId(): ?string
    {
        $projectId = config('services.firebase.project_id');
        if (is_string($projectId) && trim($projectId) !== '') {
            return trim($projectId);
        }

        $credentials = $this->getCredentials();
        $credentialProjectId = $credentials['project_id'] ?? null;

        return is_string($credentialProjectId) && trim($credentialProjectId) !== ''
            ? trim($credentialProjectId)
            : null;
    }

    public function getAccessToken(): string
    {
        $credentials = $this->getCredentials();
        $cacheKey = 'firebase:messaging:access-token:' . sha1((string) ($credentials['client_email'] ?? 'default'));

        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $tokenPayload = $this->requestAccessToken($credentials);
        Cache::put(
            $cacheKey,
            $tokenPayload['access_token'],
            now()->addSeconds(max($tokenPayload['expires_in'] - 60, 60)),
        );

        return $tokenPayload['access_token'];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    protected function requestAccessToken(array $credentials): array
    {
        $issuedAt = now()->timestamp;
        $jwt = $this->buildSignedJwt($credentials, $issuedAt);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post((string) $credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Firebase access token request failed with status %d: %s',
                $response->status(),
                $response->body(),
            ));
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 0);

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Firebase access token response did not contain a valid access token.');
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => max($expiresIn, 3600),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function buildSignedJwt(array $credentials, int $issuedAt): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $privateKeyId = $credentials['private_key_id'] ?? null;
        if (is_string($privateKeyId) && $privateKeyId !== '') {
            $header['kid'] = $privateKeyId;
        }

        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::MESSAGING_SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $segments = [
            $this->base64UrlEncode($this->encodeJson($header)),
            $this->base64UrlEncode($this->encodeJson($claims)),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        $signed = openssl_sign(
            $signingInput,
            $signature,
            (string) $credentials['private_key'],
            OPENSSL_ALGO_SHA256,
        );

        if (! $signed) {
            throw new RuntimeException('Unable to sign Firebase access token JWT.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Unable to encode Firebase JWT payload.');
        }

        return $json;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    private function getCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $credentialsPath = $this->getConfiguredCredentialsPath();
        if (! $credentialsPath || ! is_file($credentialsPath) || ! is_readable($credentialsPath)) {
            throw new RuntimeException('Firebase credentials file is missing or unreadable.');
        }

        $contents = file_get_contents($credentialsPath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read Firebase credentials file.');
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Firebase credentials file is not valid JSON.');
        }

        foreach (['client_email', 'private_key'] as $requiredField) {
            $value = $decoded[$requiredField] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException(sprintf(
                    'Firebase credentials file is missing the "%s" field.',
                    $requiredField,
                ));
            }
        }

        if (empty($decoded['token_uri']) || ! is_string($decoded['token_uri'])) {
            $decoded['token_uri'] = 'https://oauth2.googleapis.com/token';
        }

        $this->credentials = $decoded;

        return $this->credentials;
    }

    private function getConfiguredCredentialsPath(): ?string
    {
        $configuredPath = config('services.firebase.credentials');

        if (! is_string($configuredPath) || trim($configuredPath) === '') {
            return null;
        }

        $trimmedPath = trim($configuredPath);

        if ($this->isAbsolutePath($trimmedPath)) {
            return $trimmedPath;
        }

        return base_path($trimmedPath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
