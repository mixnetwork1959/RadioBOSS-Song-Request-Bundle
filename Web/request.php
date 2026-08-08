<?php
declare(strict_types=1);

/**
 * ==========================================================
 * RadioBOSS Song Request System
 * Version 1.5.0
 * request.php
 * ==========================================================
 */

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        [
            'success' => false,
            'message' => 'Song Request is not configured. Run install.php first.',
        ],
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

require_once $configFile;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');


/*
|--------------------------------------------------------------------------
| JSON response
|--------------------------------------------------------------------------
*/

function sendJson(
    int $statusCode,
    bool $success,
    string $message,
    array $extra = []
): never {
    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Client IP
|--------------------------------------------------------------------------
*/

function clientIpAddress(): string
{
    if (
        defined('TRUST_PROXY_HEADERS') &&
        TRUST_PROXY_HEADERS === true
    ) {
        $cloudflareIp =
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';

        if (
            filter_var(
                $cloudflareIp,
                FILTER_VALIDATE_IP
            )
        ) {
            return $cloudflareIp;
        }

        $forwardedFor =
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwardedFor !== '') {
            $addresses = explode(',', $forwardedFor);
            $firstIp = trim($addresses[0]);

            if (
                filter_var(
                    $firstIp,
                    FILTER_VALIDATE_IP
                )
            ) {
                return $firstIp;
            }
        }
    }

    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if (
        filter_var(
            $remoteIp,
            FILTER_VALIDATE_IP
        )
    ) {
        return $remoteIp;
    }

    return 'unknown';
}


/*
|--------------------------------------------------------------------------
| Text helpers
|--------------------------------------------------------------------------
*/

function normalizeMessage(string $message): string
{
    $message = trim($message);

    $message = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        '',
        $message
    ) ?? '';

    $message = preg_replace(
        '/[ \t]+/u',
        ' ',
        $message
    ) ?? $message;

    $message = preg_replace(
        '/\R{3,}/u',
        "\n\n",
        $message
    ) ?? $message;

    return trim($message);
}


function textLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}


/*
|--------------------------------------------------------------------------
| Station time
|--------------------------------------------------------------------------
*/

function stationTimezone(): DateTimeZone
{
    try {
        return new DateTimeZone(
            defined('STATION_TIMEZONE')
                ? (string) STATION_TIMEZONE
                : 'UTC'
        );
    } catch (Throwable) {
        return new DateTimeZone('UTC');
    }
}


function requestTimeFormat(): string
{
    if (!defined('REQUEST_TIME_FORMAT')) {
        return 'H:i';
    }

    $format = trim(
        (string) REQUEST_TIME_FORMAT
    );

    return $format !== '' ? $format : 'H:i';
}


function formatRadioBossRequestMessage(
    string $visitorMessage,
    DateTimeImmutable $submittedAt
): string {
    $label = defined('REQUEST_TIME_LABEL')
        ? trim((string) REQUEST_TIME_LABEL)
        : 'Requested at';

    if ($label === '') {
        $label = 'Requested at';
    }

    $timeMessage =
        $label . ' ' .
        $submittedAt->format(
            requestTimeFormat()
        );

    if ($visitorMessage === '') {
        return $timeMessage;
    }

    return
        $timeMessage .
        ' | ' .
        $visitorMessage;
}


/*
|--------------------------------------------------------------------------
| Files
|--------------------------------------------------------------------------
*/

function ensurePrivateDirectory(string $filename): void
{
    $directory = dirname($filename);

    if (is_dir($directory)) {
        return;
    }

    if (
        !mkdir($directory, 0750, true) &&
        !is_dir($directory)
    ) {
        throw new RuntimeException(
            'Private directory could not be created.'
        );
    }
}


function loadLookup(): array
{
    if (
        !is_file(PRIVATE_LOOKUP_FILE) ||
        !is_readable(PRIVATE_LOOKUP_FILE)
    ) {
        throw new RuntimeException(
            'Private lookup file is unavailable.'
        );
    }

    $json = file_get_contents(
        PRIVATE_LOOKUP_FILE
    );

    if ($json === false) {
        throw new RuntimeException(
            'Private lookup file could not be read.'
        );
    }

    $lookup = json_decode($json, true);

    if (!is_array($lookup)) {
        throw new RuntimeException(
            'Private lookup file contains invalid JSON.'
        );
    }

    return $lookup;
}


/*
|--------------------------------------------------------------------------
| RadioBOSS API
|--------------------------------------------------------------------------
*/

function radioBossApi(array $parameters): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException(
            'PHP cURL extension is unavailable.'
        );
    }

    $apiUrl = trim(
        (string) RADIOBOSS_API_URL
    );

    if ($apiUrl === '') {
        throw new RuntimeException(
            'RadioBOSS API URL is not configured.'
        );
    }

    $query = array_merge(
        [
            'pass' => RADIOBOSS_API_PASSWORD,
        ],
        $parameters
    );

    $separator = str_contains($apiUrl, '?')
        ? '&'
        : '?';

    $requestUrl =
        $apiUrl .
        $separator .
        http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    $curl = curl_init();

    if ($curl === false) {
        throw new RuntimeException(
            'RadioBOSS connection could not be initialized.'
        );
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT =>
                RADIOBOSS_API_TIMEOUT,
            CURLOPT_TIMEOUT =>
                RADIOBOSS_API_TIMEOUT,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml, text/plain',
                'Connection: close',
            ],
        ]
    );

    $response = curl_exec($curl);
    $curlError = curl_error($curl);

    $httpCode = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException(
            'RadioBOSS connection failed: ' .
            $curlError
        );
    }

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        throw new RuntimeException(
            'RadioBOSS returned HTTP status ' .
            $httpCode . '.'
        );
    }

    return trim((string) $response);
}


/*
|--------------------------------------------------------------------------
| RadioBOSS queue
|--------------------------------------------------------------------------
*/

function countPendingRadioBossRequests(): int
{
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException(
            'PHP SimpleXML extension is unavailable.'
        );
    }

    $response = radioBossApi(
        [
            'action' => 'songrequestlist',
        ]
    );

    if ($response === '') {
        return 0;
    }

    $previousSetting =
        libxml_use_internal_errors(true);

    try {
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            throw new RuntimeException(
                'RadioBOSS returned invalid queue XML.'
            );
        }

        $requests = $xml->xpath('//Request');

        if ($requests === false) {
            return 0;
        }

        $pending = 0;

        foreach ($requests as $request) {
            $attributes = $request->attributes();

            $played = strtolower(
                trim((string) ($attributes['played'] ?? 'false'))
            );

            if (
                $played !== 'true' &&
                $played !== '1' &&
                $played !== 'yes'
            ) {
                $pending++;
            }
        }

        return $pending;

    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors(
            $previousSetting
        );
    }
}


/*
|--------------------------------------------------------------------------
| Request schedule
|--------------------------------------------------------------------------
*/

function requestPlayMinutes(): array
{
    if (
        !defined('REQUEST_PLAY_MINUTES') ||
        !is_array(REQUEST_PLAY_MINUTES)
    ) {
        return [];
    }

    $minutes = [];

    foreach (REQUEST_PLAY_MINUTES as $minute) {
        if (
            is_int($minute) ||
            (
                is_string($minute) &&
                ctype_digit($minute)
            )
        ) {
            $minute = (int) $minute;

            if ($minute >= 0 && $minute <= 59) {
                $minutes[] = $minute;
            }
        }
    }

    $minutes = array_values(
        array_unique($minutes)
    );

    sort($minutes, SORT_NUMERIC);

    return $minutes;
}


function calculateExpectedPlayTime(
    int $pendingBeforeRequest
): ?array {
    if (
        !defined('SHOW_REQUEST_ESTIMATE') ||
        SHOW_REQUEST_ESTIMATE !== true
    ) {
        return null;
    }

    $minutes = requestPlayMinutes();

    if ($minutes === []) {
        return null;
    }

    $requestsPerSlot = max(
        1,
        (int) REQUESTS_PER_SLOT
    );

    /*
     * The new request is placed behind all currently
     * pending requests.
     */
    $queuePosition =
        $pendingBeforeRequest + 1;

    /*
     * Zero-based number of request slots to skip.
     */
    $slotsToSkip = intdiv(
        $queuePosition - 1,
        $requestsPerSlot
    );

    $timezone = stationTimezone();

    $now = new DateTimeImmutable(
        'now',
        $timezone
    );

    $hourStart = $now->setTime(
        (int) $now->format('H'),
        0,
        0
    );

    $remainingSlots = $slotsToSkip;
    $expectedTime = null;

    /*
     * Search far enough ahead even for a long queue.
     */
    $maximumHours =
        (int) ceil(
            ($slotsToSkip + 1) /
            count($minutes)
        ) + 48;

    for (
        $hourOffset = 0;
        $hourOffset <= $maximumHours;
        $hourOffset++
    ) {
        $hour = $hourStart->modify(
            '+' . $hourOffset . ' hours'
        );

        foreach ($minutes as $minute) {
            $candidate = $hour->setTime(
                (int) $hour->format('H'),
                $minute,
                0
            );

            /*
             * A slot at the current or an earlier time
             * has already passed.
             */
            if ($candidate <= $now) {
                continue;
            }

            if ($remainingSlots === 0) {
                $expectedTime = $candidate;
                break 2;
            }

            $remainingSlots--;
        }
    }

    if ($expectedTime === null) {
        return null;
    }

    $secondsUntil =
        $expectedTime->getTimestamp() -
        $now->getTimestamp();

    $waitingMinutes = max(
        1,
        (int) ceil($secondsUntil / 60)
    );

    $sameDay =
        $expectedTime->format('Y-m-d') ===
        $now->format('Y-m-d');

    $formattedTime = $sameDay
        ? $expectedTime->format('H:i')
        : $expectedTime->format('D H:i');

    return [
        'queue_position' => $queuePosition,
        'expected_play_time' => $formattedTime,
        'expected_play_time_iso' =>
            $expectedTime->format(DATE_ATOM),
        'estimated_wait_minutes' =>
            $waitingMinutes,
    ];
}


function formatScheduleMessage(): string
{
    $minutes = requestPlayMinutes();

    if ($minutes === []) {
        return '';
    }

    $formatted = array_map(
        static fn (int $minute): string =>
            ':' . str_pad(
                (string) $minute,
                2,
                '0',
                STR_PAD_LEFT
            ),
        $minutes
    );

    if (count($formatted) === 1) {
        $timeList = $formatted[0];
    } else {
        $last = array_pop($formatted);

        $timeList =
            implode(', ', $formatted) .
            ' and ' .
            $last;
    }

    return
        ' Song requests are played at ' .
        $timeList .
        ' each hour.';
}


function buildSuccessResponse(
    ?array $estimate,
    DateTimeImmutable $submittedAt
): array {
    $submittedTime = $submittedAt->format(
        requestTimeFormat()
    );

    $submittedData = [
        'submitted_time' => $submittedTime,
        'submitted_at' =>
            $submittedAt->format(DATE_ATOM),
    ];

    if ($estimate === null) {
        return array_merge(
            [
                'message' =>
                    'Your song request was sent successfully at ' .
                    $submittedTime . '.' .
                    formatScheduleMessage(),
            ],
            $submittedData
        );
    }

    $queuePosition =
        (int) $estimate['queue_position'];

    $playTime =
        (string) $estimate['expected_play_time'];

    $waitingMinutes =
        (int) $estimate['estimated_wait_minutes'];

    if ($queuePosition === 1) {
        $message =
            'Your song request was sent successfully at ' .
            $submittedTime . '.';
    } else {
        $message =
            'Your song request was added to queue position ' .
            $queuePosition .
            ' at ' .
            $submittedTime . '.';
    }

    $message .=
        ' Expected play time: around ' .
        $playTime . '.';

    if ($waitingMinutes === 1) {
        $message .=
            ' Estimated waiting time: about 1 minute.';
    } else {
        $message .=
            ' Estimated waiting time: about ' .
            $waitingMinutes .
            ' minutes.';
    }

    $message .= formatScheduleMessage();

    return array_merge(
        [
            'message' => $message,
        ],
        $submittedData,
        $estimate
    );
}


/*
|--------------------------------------------------------------------------
| Request log
|--------------------------------------------------------------------------
*/

function appendRequestLog(
    string $ipHash,
    int $trackId,
    string $filename,
    string $message,
    ?array $estimate,
    DateTimeImmutable $submittedAt
): void {
    ensurePrivateDirectory(
        REQUEST_LOG_FILE
    );

    $entry = [
        'time' => $submittedAt->format(DATE_ATOM),
        'ip_hash' => $ipHash,
        'track_id' => $trackId,
        'filename' => basename(
            str_replace('\\', '/', $filename)
        ),
        'message' => $message,
        'estimate' => $estimate,
    ];

    $line = json_encode(
        $entry,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($line !== false) {
        file_put_contents(
            REQUEST_LOG_FILE,
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate HTTP request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    sendJson(
        405,
        false,
        'Only POST requests are allowed.'
    );
}

$contentType = strtolower(
    trim(
        explode(
            ';',
            $_SERVER['CONTENT_TYPE'] ?? ''
        )[0]
    )
);

if ($contentType !== 'application/json') {
    sendJson(
        415,
        false,
        'The request must contain JSON.'
    );
}

$rawBody = file_get_contents(
    'php://input'
);

if (
    $rawBody === false ||
    trim($rawBody) === ''
) {
    sendJson(
        400,
        false,
        'The request body is empty.'
    );
}

try {
    $input = json_decode(
        $rawBody,
        true,
        32,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException) {
    sendJson(
        400,
        false,
        'The request contains invalid JSON.'
    );
}

if (!is_array($input)) {
    sendJson(
        400,
        false,
        'The request data is invalid.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate track ID
|--------------------------------------------------------------------------
*/

$trackIdValue =
    $input['track_id'] ?? null;

if (
    !is_int($trackIdValue) &&
    !(
        is_string($trackIdValue) &&
        ctype_digit($trackIdValue)
    )
) {
    sendJson(
        400,
        false,
        'Invalid song selection.'
    );
}

$trackId = (int) $trackIdValue;

if ($trackId <= 0) {
    sendJson(
        400,
        false,
        'Invalid song selection.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate message
|--------------------------------------------------------------------------
*/

$message = '';

if (ALLOW_MESSAGES) {
    $messageValue =
        $input['message'] ?? '';

    if (!is_string($messageValue)) {
        sendJson(
            400,
            false,
            'The message is invalid.'
        );
    }

    $message = normalizeMessage(
        $messageValue
    );

    if (
        textLength($message) >
        MAX_MESSAGE_LENGTH
    ) {
        sendJson(
            400,
            false,
            'The message is too long.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Resolve private filename
|--------------------------------------------------------------------------
*/

try {
    $lookup = loadLookup();
} catch (Throwable $exception) {
    error_log(
        'Song Request lookup error: ' .
        $exception->getMessage()
    );

    sendJson(
        503,
        false,
        'The song catalog is temporarily unavailable.'
    );
}

$lookupKey = (string) $trackId;
$track = $lookup[$lookupKey] ?? null;

if (
    !is_array($track) ||
    !isset($track['filename']) ||
    !is_string($track['filename']) ||
    trim($track['filename']) === ''
) {
    sendJson(
        404,
        false,
        'The selected song is no longer available.'
    );
}

$filename = trim(
    $track['filename']
);


/*
|--------------------------------------------------------------------------
| Open request state
|--------------------------------------------------------------------------
*/

try {
    ensurePrivateDirectory(
        REQUEST_STATE_FILE
    );

    $stateHandle = fopen(
        REQUEST_STATE_FILE,
        'c+'
    );

    if ($stateHandle === false) {
        throw new RuntimeException(
            'Request state could not be opened.'
        );
    }

    if (!flock($stateHandle, LOCK_EX)) {
        fclose($stateHandle);

        throw new RuntimeException(
            'Request state could not be locked.'
        );
    }

    rewind($stateHandle);

    $stateJson =
        stream_get_contents($stateHandle);

    $state = [];

    if (
        is_string($stateJson) &&
        trim($stateJson) !== ''
    ) {
        $decodedState =
            json_decode($stateJson, true);

        if (is_array($decodedState)) {
            $state = $decodedState;
        }
    }

    if (
        !isset($state['ips']) ||
        !is_array($state['ips'])
    ) {
        $state['ips'] = [];
    }

    if (
        !isset($state['tracks']) ||
        !is_array($state['tracks'])
    ) {
        $state['tracks'] = [];
    }

    $now = time();
    $submittedAt = (
        new DateTimeImmutable('@' . $now)
    )->setTimezone(
        stationTimezone()
    );

    $oneHourAgo = $now - 3600;

    $trackCutoff =
        $now - TRACK_COOLDOWN_SECONDS;

    $ipHash = hash(
        'sha256',
        clientIpAddress()
    );

    $ipRequests =
        $state['ips'][$ipHash] ?? [];

    if (!is_array($ipRequests)) {
        $ipRequests = [];
    }

    $ipRequests = array_values(
        array_filter(
            $ipRequests,
            static fn ($timestamp): bool =>
                is_int($timestamp) &&
                $timestamp > $oneHourAgo
        )
    );

    $lastIpRequest = $ipRequests === []
        ? 0
        : max($ipRequests);

    $ipWait =
        IP_COOLDOWN_SECONDS -
        ($now - $lastIpRequest);

    if (
        $lastIpRequest > 0 &&
        $ipWait > 0
    ) {
        flock($stateHandle, LOCK_UN);
        fclose($stateHandle);

        sendJson(
            429,
            false,
            'Please wait before sending another request.',
            [
                'retry_after' => $ipWait,
            ]
        );
    }

    if (
        count($ipRequests) >=
        MAX_REQUESTS_PER_HOUR
    ) {
        $oldestRequest =
            min($ipRequests);

        $retryAfter = max(
            1,
            3600 - ($now - $oldestRequest)
        );

        flock($stateHandle, LOCK_UN);
        fclose($stateHandle);

        sendJson(
            429,
            false,
            'You have reached the hourly request limit.',
            [
                'retry_after' => $retryAfter,
            ]
        );
    }

    $lastTrackRequest =
        $state['tracks'][$lookupKey] ?? 0;

    if (
        is_int($lastTrackRequest) &&
        $lastTrackRequest > $trackCutoff
    ) {
        $retryAfter = max(
            1,
            TRACK_COOLDOWN_SECONDS -
            ($now - $lastTrackRequest)
        );

        flock($stateHandle, LOCK_UN);
        fclose($stateHandle);

        sendJson(
            429,
            false,
            'This song was requested recently. Please choose another song.',
            [
                'retry_after' => $retryAfter,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check RadioBOSS queue
    |--------------------------------------------------------------------------
    */

    $pendingBeforeRequest = null;

    if (
        defined('SHOW_REQUEST_ESTIMATE') &&
        SHOW_REQUEST_ESTIMATE === true &&
        requestPlayMinutes() !== []
    ) {
        try {
            $pendingBeforeRequest =
                countPendingRadioBossRequests();
        } catch (Throwable $exception) {
            error_log(
                'RadioBOSS queue check error: ' .
                $exception->getMessage()
            );

            /*
             * Queue checking is optional. The request may
             * still be sent if the queue cannot be read.
             */
            $pendingBeforeRequest = null;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Send request to RadioBOSS
    |--------------------------------------------------------------------------
    */

    try {
        $radioBossMessage =
            formatRadioBossRequestMessage(
                $message,
                $submittedAt
            );

        $radioBossResponse = radioBossApi(
            [
                'action' => 'songrequest',
                'filename' => $filename,
                'message' => $radioBossMessage,
            ]
        );

        if ($radioBossResponse !== 'OK') {
            throw new RuntimeException(
                'RadioBOSS rejected the request.'
            );
        }

    } catch (Throwable $exception) {
        error_log(
            'Song Request RadioBOSS error: ' .
            $exception->getMessage()
        );

        flock($stateHandle, LOCK_UN);
        fclose($stateHandle);

        sendJson(
            503,
            false,
            'The request could not be sent to the station.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate expected play time
    |--------------------------------------------------------------------------
    */

    $estimate = $pendingBeforeRequest === null
        ? null
        : calculateExpectedPlayTime(
            $pendingBeforeRequest
        );


    /*
    |--------------------------------------------------------------------------
    | Save successful request
    |--------------------------------------------------------------------------
    */

    $ipRequests[] = $now;

    $state['ips'][$ipHash] =
        $ipRequests;

    $state['tracks'][$lookupKey] =
        $now;

    foreach (
        $state['ips']
        as $storedIp => $timestamps
    ) {
        if (!is_array($timestamps)) {
            unset($state['ips'][$storedIp]);
            continue;
        }

        $timestamps = array_values(
            array_filter(
                $timestamps,
                static fn ($timestamp): bool =>
                    is_int($timestamp) &&
                    $timestamp > $oneHourAgo
            )
        );

        if ($timestamps === []) {
            unset($state['ips'][$storedIp]);
        } else {
            $state['ips'][$storedIp] =
                $timestamps;
        }
    }

    foreach (
        $state['tracks']
        as $storedTrackId => $timestamp
    ) {
        if (
            !is_int($timestamp) ||
            $timestamp <= $trackCutoff
        ) {
            unset(
                $state['tracks'][$storedTrackId]
            );
        }
    }

    $encodedState = json_encode(
        $state,
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE |
        JSON_PRETTY_PRINT
    );

    if ($encodedState === false) {
        throw new RuntimeException(
            'Request state could not be encoded.'
        );
    }

    rewind($stateHandle);
    ftruncate($stateHandle, 0);

    if (
        fwrite(
            $stateHandle,
            $encodedState . PHP_EOL
        ) === false
    ) {
        throw new RuntimeException(
            'Request state could not be saved.'
        );
    }

    fflush($stateHandle);
    flock($stateHandle, LOCK_UN);
    fclose($stateHandle);

    appendRequestLog(
        $ipHash,
        $trackId,
        $filename,
        $message,
        $estimate,
        $submittedAt
    );

} catch (Throwable $exception) {
    if (
        isset($stateHandle) &&
        is_resource($stateHandle)
    ) {
        flock($stateHandle, LOCK_UN);
        fclose($stateHandle);
    }

    error_log(
        'Song Request state error: ' .
        $exception->getMessage()
    );

    sendJson(
        503,
        false,
        'The request service is temporarily unavailable.'
    );
}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

$successResponse =
    buildSuccessResponse(
        $estimate,
        $submittedAt
    );

sendJson(
    200,
    true,
    $successResponse['message'],
    $successResponse
);
