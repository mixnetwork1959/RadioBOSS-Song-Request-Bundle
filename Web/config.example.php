<?php
declare(strict_types=1);

/**
 * ==========================================================
 * RadioBOSS Song Request System
 * Version 1.5.0
 * Example configuration
 * ==========================================================
 *
 * Recommended: open install.php and use the Setup Wizard.
 *
 * Manual setup:
 * Copy this file to config.php and enter your own settings.
 * Never upload config.php to GitHub.
 */

define('ENABLE_SECONDARY_STATION', false);

$requestedStation = strtolower(trim((string) ($_GET['station'] ?? 'main')));

define(
    'REQUEST_STATION',
    ENABLE_SECONDARY_STATION && $requestedStation === 'rock'
        ? 'rock'
        : 'main'
);

define('IS_ROCK_REQUEST', REQUEST_STATION === 'rock');


/* Station */

define('MAIN_STATION_NAME', 'Your Main Station');
define('SECONDARY_STATION_NAME', 'Your Secondary Station');

define(
    'STATION_NAME',
    IS_ROCK_REQUEST
        ? SECONDARY_STATION_NAME
        : MAIN_STATION_NAME
);

define('STATION_TAGLINE', 'Your music, your station.');
define('STATION_LOGO_URL', '');


/* Optional embedded players */

define('MAIN_PLAYER_EMBED_URL', '');
define('SECONDARY_PLAYER_EMBED_URL', '');

define(
    'PLAYER_EMBED_URL',
    IS_ROCK_REQUEST
        ? SECONDARY_PLAYER_EMBED_URL
        : MAIN_PLAYER_EMBED_URL
);


/* Public song catalogs */

define(
    'MAIN_PUBLIC_SONGS_URL',
    'data/main/public/songs.json'
);

define(
    'SECONDARY_PUBLIC_SONGS_URL',
    'data/secondary/public/songs.json'
);

define(
    'PUBLIC_SONGS_URL',
    IS_ROCK_REQUEST
        ? SECONDARY_PUBLIC_SONGS_URL
        : MAIN_PUBLIC_SONGS_URL
);

define('MIN_SEARCH_LENGTH', 2);
define('SEARCH_RESULT_LIMIT', 25);


/* Private SongSync lookup */

define(
    'PRIVATE_LOOKUP_FILE',
    IS_ROCK_REQUEST
        ? __DIR__ . '/data/secondary/private/lookup.json'
        : __DIR__ . '/data/main/private/lookup.json'
);


/* RadioBOSS Remote Control API */

define(
    'MAIN_RADIOBOSS_API_URL',
    'http://127.0.0.1:9000/'
);

define(
    'SECONDARY_RADIOBOSS_API_URL',
    'http://127.0.0.1:9010/'
);

define(
    'RADIOBOSS_API_URL',
    IS_ROCK_REQUEST
        ? SECONDARY_RADIOBOSS_API_URL
        : MAIN_RADIOBOSS_API_URL
);

define('MAIN_RADIOBOSS_API_PASSWORD', 'CHANGE_ME');
define('SECONDARY_RADIOBOSS_API_PASSWORD', 'CHANGE_ME');

define(
    'RADIOBOSS_API_PASSWORD',
    IS_ROCK_REQUEST
        ? SECONDARY_RADIOBOSS_API_PASSWORD
        : MAIN_RADIOBOSS_API_PASSWORD
);

define('RADIOBOSS_API_TIMEOUT', 10);


/* Listener messages */

define('ALLOW_MESSAGES', true);
define('MAX_MESSAGE_LENGTH', 150);


/* Request timing */

define('REQUEST_TIME_FORMAT', 'H:i');
define('REQUEST_TIME_LABEL', 'Requested at');
define('STATION_TIMEZONE', 'Europe/Berlin');

define(
    'REQUEST_PLAY_MINUTES',
    [15, 45]
);

define('REQUESTS_PER_SLOT', 1);
define('SHOW_REQUEST_ESTIMATE', true);


/* Request protection */

define('IP_COOLDOWN_SECONDS', 60);
define('TRACK_COOLDOWN_SECONDS', 3600);
define('MAX_REQUESTS_PER_HOUR', 5);


/* Private runtime files */

define(
    'REQUEST_STATE_FILE',
    IS_ROCK_REQUEST
        ? __DIR__ . '/data/secondary/private/request-state.json'
        : __DIR__ . '/data/main/private/request-state.json'
);

define(
    'REQUEST_LOG_FILE',
    IS_ROCK_REQUEST
        ? __DIR__ . '/data/secondary/private/requests.log'
        : __DIR__ . '/data/main/private/requests.log'
);


/* Proxy headers */

define('TRUST_PROXY_HEADERS', false);
