<?php
declare(strict_types=1);

/**
 * ==========================================================
 * RadioBOSS Song Request System
 * Version 1.6.1
 * request-gateway.php
 * ==========================================================
 * Hourly artist protection in front of request.php.
 */

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Song Request is not configured. Run install.php first.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once $configFile;

function srGatewayJson(int $statusCode, bool $success, string $message, array $extra = []): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function srGatewayTimezone(): DateTimeZone
{
    try {
        return new DateTimeZone(defined('STATION_TIMEZONE') ? (string) STATION_TIMEZONE : 'UTC');
    } catch (Throwable) {
        return new DateTimeZone('UTC');
    }
}

function srGatewayNormalizeArtist(string $artist): string
{
    $artist = trim(preg_replace('/\s+/u', ' ', $artist) ?? $artist);
    return function_exists('mb_strtolower')
        ? mb_strtolower($artist, 'UTF-8')
        : strtolower($artist);
}

function srGatewayCandidateSongsFiles(): array
{
    $files = [];

    if (defined('PUBLIC_SONGS_URL')) {
        $url = trim((string) PUBLIC_SONGS_URL);

        if ($url !== '') {
            $parts = parse_url($url);

            if ($parts !== false) {
                $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                $path = (string) ($parts['path'] ?? $url);
                $path = str_replace('\\', '/', $path);

                if ($scheme === '' || $scheme === 'file') {
                    if (str_starts_with($path, '/')) {
                        $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                        if ($documentRoot !== '') {
                            $files[] = $documentRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
                        }
                    } else {
                        $files[] = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
                    }
                } elseif ($scheme === 'http' || $scheme === 'https') {
                    $urlHost = strtolower((string) ($parts['host'] ?? ''));
                    $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
                    $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;

                    if ($urlHost !== '' && $requestHost !== '' && $urlHost === $requestHost) {
                        $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
                        if ($documentRoot !== '' && $path !== '') {
                            $files[] = $documentRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
                        }
                    }
                }
            }
        }
    }

    /* Standard SongSync fallback:
     * data/<station>/private/lookup.json -> data/<station>/public/songs.json
     */
    if (defined('PRIVATE_LOOKUP_FILE')) {
        $lookupFile = (string) PRIVATE_LOOKUP_FILE;
        if ($lookupFile !== '') {
            $stationDir = dirname(dirname($lookupFile));
            $files[] = $stationDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'songs.json';
        }
    }

    return array_values(array_unique($files));
}

function srGatewayPublicSongsFile(): ?string
{
    foreach (srGatewayCandidateSongsFiles() as $filename) {
        if (is_file($filename) && is_readable($filename)) {
            return $filename;
        }
    }

    return null;
}

function srGatewayFindTrackArtist(int $trackId): ?string
{
    $catalogFile = srGatewayPublicSongsFile();

    if ($catalogFile === null) {
        return null;
    }

    $json = file_get_contents($catalogFile);
    if ($json === false) {
        return null;
    }

    $songs = json_decode($json, true);
    if (!is_array($songs)) {
        return null;
    }

    foreach ($songs as $song) {
        if (!is_array($song)) {
            continue;
        }

        $songTrackId = $song['track_id'] ?? null;
        if (!is_int($songTrackId) && !(is_string($songTrackId) && ctype_digit($songTrackId))) {
            continue;
        }

        if ((int) $songTrackId !== $trackId) {
            continue;
        }

        $artist = $song['artist'] ?? null;
        if (!is_string($artist)) {
            return null;
        }

        $artist = trim($artist);
        return $artist !== '' ? $artist : null;
    }

    return null;
}

function srGatewayEnsureStateDirectory(): void
{
    $directory = dirname(REQUEST_STATE_FILE);
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Request state directory could not be created.');
    }
}

function srGatewayReadState($handle): array
{
    rewind($handle);
    $json = stream_get_contents($handle);

    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $state = json_decode($json, true);
    return is_array($state) ? $state : [];
}

function srGatewayWriteState($handle, array $state): void
{
    $json = json_encode(
        $state,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE |
        JSON_PRETTY_PRINT
    );

    if ($json === false) {
        throw new RuntimeException('Request state could not be encoded.');
    }

    rewind($handle);
    ftruncate($handle, 0);

    if (fwrite($handle, $json . PHP_EOL) === false) {
        throw new RuntimeException('Request state could not be saved.');
    }

    fflush($handle);
}

function srGatewayReserveArtist(string $artist, DateTimeImmutable $submittedAt): array
{
    srGatewayEnsureStateDirectory();
    $handle = fopen(REQUEST_STATE_FILE, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Request state could not be opened.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Request state could not be locked.');
    }

    try {
        $state = srGatewayReadState($handle);
        if (!isset($state['artists']) || !is_array($state['artists'])) {
            $state['artists'] = [];
        }

        $hourKey = $submittedAt->format('Y-m-d-H');
        $artistKey = hash('sha256', srGatewayNormalizeArtist($artist));

        foreach ($state['artists'] as $storedKey => $storedEntry) {
            if (!is_array($storedEntry) || ($storedEntry['hour_key'] ?? '') !== $hourKey) {
                unset($state['artists'][$storedKey]);
            }
        }

        $existing = $state['artists'][$artistKey] ?? null;
        if (is_array($existing) && ($existing['hour_key'] ?? '') === $hourKey) {
            return ['allowed' => false, 'token' => null];
        }

        $token = bin2hex(random_bytes(16));
        $state['artists'][$artistKey] = [
            'artist' => $artist,
            'hour_key' => $hourKey,
            'reserved_at' => $submittedAt->format(DATE_ATOM),
            'token' => $token,
        ];

        srGatewayWriteState($handle, $state);
        return ['allowed' => true, 'token' => $token, 'artist_key' => $artistKey];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function srGatewayReleaseArtistReservation(string $artistKey, string $token): void
{
    try {
        srGatewayEnsureStateDirectory();
        $handle = fopen(REQUEST_STATE_FILE, 'c+');

        if ($handle === false) {
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }

        try {
            $state = srGatewayReadState($handle);
            $entry = $state['artists'][$artistKey] ?? null;

            if (is_array($entry) && hash_equals((string) ($entry['token'] ?? ''), $token)) {
                unset($state['artists'][$artistKey]);
                srGatewayWriteState($handle, $state);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    } catch (Throwable $exception) {
        error_log('Song Request artist reservation cleanup error: ' . $exception->getMessage());
    }
}

$artistProtectionEnabled = !defined('ARTIST_HOURLY_PROTECTION') || ARTIST_HOURLY_PROTECTION === true;

if ($artistProtectionEnabled && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawBody = file_get_contents('php://input');
    $input = is_string($rawBody) ? json_decode($rawBody, true) : null;
    $trackIdValue = is_array($input) ? ($input['track_id'] ?? null) : null;

    if (is_int($trackIdValue) || (is_string($trackIdValue) && ctype_digit($trackIdValue))) {
        $trackId = (int) $trackIdValue;

        if ($trackId > 0) {
            $artist = srGatewayFindTrackArtist($trackId);

            if ($artist === null) {
                error_log(
                    'Song Request artist catalog lookup failed. Candidates: ' .
                    implode(' | ', srGatewayCandidateSongsFiles())
                );

                srGatewayJson(
                    503,
                    false,
                    'The song catalog is temporarily unavailable. Please try again shortly.'
                );
            }

            $submittedAt = new DateTimeImmutable('now', srGatewayTimezone());

            try {
                $reservation = srGatewayReserveArtist($artist, $submittedAt);
            } catch (Throwable $exception) {
                error_log('Song Request artist protection error: ' . $exception->getMessage());
                srGatewayJson(503, false, 'The request protection service is temporarily unavailable.');
            }

            if (($reservation['allowed'] ?? false) !== true) {
                $nextHour = $submittedAt->modify('+1 hour')->setTime(
                    (int) $submittedAt->modify('+1 hour')->format('H'),
                    0,
                    0
                );

                srGatewayJson(
                    409,
                    false,
                    'A song by ' . $artist .
                    ' has already been requested this hour. Please try again after ' .
                    $nextHour->format('H:i') . '.',
                    [
                        'artist' => $artist,
                        'next_artist_request_time' => $nextHour->format('H:i'),
                        'next_artist_request_at' => $nextHour->format(DATE_ATOM),
                        'reason' => 'artist_hourly_protection',
                    ]
                );
            }

            $artistKey = (string) ($reservation['artist_key'] ?? '');
            $reservationToken = (string) ($reservation['token'] ?? '');

            ob_start();
            register_shutdown_function(
                static function () use ($artistKey, $reservationToken): void {
                    $responseBody = ob_get_contents();
                    $response = is_string($responseBody) ? json_decode($responseBody, true) : null;
                    $successful = is_array($response) && ($response['success'] ?? false) === true;

                    if (!$successful && $artistKey !== '' && $reservationToken !== '') {
                        srGatewayReleaseArtistReservation($artistKey, $reservationToken);
                    }
                }
            );
        }
    }
}

require __DIR__ . '/request.php';
