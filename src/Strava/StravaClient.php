<?php

declare(strict_types=1);

namespace App\Strava;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class StravaClient
{
    private const TOKEN_FILE = 'var/strava-token.json';
    private const API_BASE = 'https://www.strava.com/api/v3';
    private const TOKEN_ENDPOINT = 'https://www.strava.com/oauth/token';
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes in seconds
    private const RATE_LIMIT_THRESHOLD = 90; // sleep before hitting 100

    /** @var array<string, mixed> */
    private array $token;
    /** @var array<int, float> */
    private array $requestTimestamps = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $initialRefreshToken,
        private readonly string $initialAccessToken = '',
        private readonly int $initialExpiresAt = 0,
    ) {
        $this->token = $this->loadToken();
    }

    /**
     * Fetch a page of the authenticated athlete's running activities.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivities(int $page = 1, int $perPage = 50, ?int $after = null): array
    {
        $this->sleepIfNeeded();
        $this->refreshTokenIfNeeded();

        $query = [
            'per_page' => $perPage,
            'page' => $page,
            'type' => 'Run',
        ];

        if ($after !== null) {
            $query['after'] = $after;
        }

        $response = $this->httpClient->request('GET', self::API_BASE . '/athlete/activities', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token['access_token']],
            'query' => $query,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Strava API error {$status}: " . $response->getContent(false)
            );
        }

        return $response->toArray();
    }

    /**
     * Fetch a single activity by its Strava ID (includes laps).
     *
     * @return array<string, mixed>
     */
    public function getActivity(int $stravaId): array
    {
        $this->sleepIfNeeded();
        $this->refreshTokenIfNeeded();

        $response = $this->httpClient->request(
            'GET',
            self::API_BASE . '/activities/' . $stravaId,
            [
                'headers' => ['Authorization' => 'Bearer ' . $this->token['access_token']],
            ]
        );

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Strava API error {$status}: " . $response->getContent(false)
            );
        }

        return $response->toArray();
    }

    /**
     * Fetch velocity and heartrate streams for an activity, keyed by stream type.
     *
     * @return array<string, mixed>
     */
    public function getActivityStreams(int $stravaId): array
    {
        $this->sleepIfNeeded();
        $this->refreshTokenIfNeeded();

        $response = $this->httpClient->request(
            'GET',
            self::API_BASE . '/activities/' . $stravaId . '/streams',
            [
                'headers' => ['Authorization' => 'Bearer ' . $this->token['access_token']],
                'query' => [
                    'keys' => 'velocity_smooth,heartrate',
                    'key_by_type' => 'true',
                ],
            ]
        );

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Strava API error {$status}: " . $response->getContent(false)
            );
        }

        $data = $response->toArray();

        if (empty($data)) {
            return [];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function loadToken(): array
    {
        $tokenFile = $this->projectDir . '/' . self::TOKEN_FILE;

        if (file_exists($tokenFile)) {
            $contents = file_get_contents($tokenFile);
            $token = is_string($contents) ? json_decode($contents, true) : null;

            if (is_array($token)
                && isset($token['access_token'], $token['expires_at'], $token['refresh_token'])
            ) {
                return $token;
            }
        }

        // Build initial token from constructor parameters and persist it.
        $token = [
            'access_token' => $this->initialAccessToken,
            'expires_at' => $this->initialExpiresAt,
            'refresh_token' => $this->initialRefreshToken,
        ];

        $this->writeToken($token);

        return $token;
    }

    /** @param array<string, mixed> $token */
    private function writeToken(array $token): void
    {
        $tokenFile = $this->projectDir . '/' . self::TOKEN_FILE;
        $dir = dirname($tokenFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($tokenFile, json_encode($token, JSON_PRETTY_PRINT));
    }

    private function refreshTokenIfNeeded(): void
    {
        // Token is still valid (with 60-second buffer).
        if (time() < $this->token['expires_at'] - 60) {
            return;
        }

        $response = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->token['refresh_token'],
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                "Strava token refresh error {$status}: " . $response->getContent(false)
            );
        }

        $data = $response->toArray();

        $this->token = [
            'access_token' => $data['access_token'],
            'expires_at' => $data['expires_at'],
            'refresh_token' => $data['refresh_token'],
        ];

        $this->writeToken($this->token);
    }

    private function sleepIfNeeded(): void
    {
        $now = microtime(true);
        $this->requestTimestamps[] = $now;

        // Count requests within the current rate-limit window.
        $windowStart = $now - self::RATE_LIMIT_WINDOW;
        $recentTimestamps = array_filter(
            $this->requestTimestamps,
            static fn (float $ts): bool => $ts >= $windowStart
        );

        if (count($recentTimestamps) >= self::RATE_LIMIT_THRESHOLD) {
            // Find the oldest timestamp still inside the window.
            $oldest = min($recentTimestamps);
            // Calculate how many seconds until that oldest request falls out of the window.
            $sleepSeconds = (int) ceil(($oldest + self::RATE_LIMIT_WINDOW) - $now);

            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
        }

        // Prune timestamps that are now outside the window.
        $cutoff = microtime(true) - self::RATE_LIMIT_WINDOW;
        $this->requestTimestamps = array_values(
            array_filter(
                $this->requestTimestamps,
                static fn (float $ts): bool => $ts >= $cutoff
            )
        );
    }
}
