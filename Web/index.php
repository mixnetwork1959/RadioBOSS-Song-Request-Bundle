<?php
declare(strict_types=1);

/**
 * ==========================================================
 * RadioBOSS Song Request System
 * Version v1.5.0
 * index.php
 * ==========================================================
 */

$configFile = __DIR__ . '/config.php';

if (!is_file($configFile)) {
    header('Location: install.php');
    exit;
}

require_once $configFile;


/**
 * Escape output for safe HTML rendering.
 */
function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="<?= e(STATION_NAME) ?> song request system"
    >

    <title>
        <?= e(STATION_NAME) ?> Song Requests
    </title>

    <link
        rel="stylesheet"
        href="assets/style.css?v=1.5.0"
    >
</head>

<body
    data-songs-file="<?= e(PUBLIC_SONGS_URL) ?>"
    data-min-search-length="<?= (int) MIN_SEARCH_LENGTH ?>"
    data-result-limit="<?= (int) SEARCH_RESULT_LIMIT ?>"
>
<div class="request-container">
    <main class="search-panel">

        <section
            id="stationGuard"
            class="station-guard"
            role="alert"
            hidden
        >
            <div class="station-guard-icon" aria-hidden="true">↪</div>
            <div class="station-guard-copy">
                <strong id="stationGuardTitle"></strong>
                <p id="stationGuardMessage"></p>
            </div>
            <a id="stationGuardLink" class="station-guard-link" href="#"></a>
        </section>

        <label
            class="search-label"
            for="searchBox"
        >
            Search the music library
        </label>

        <input
            type="search"
            id="searchBox"
            placeholder="Search artist or title..."
            autocomplete="off"
            spellcheck="false"
            disabled
        >

        <div
            id="catalogStatus"
            class="catalog-status"
            aria-live="polite"
        >
            Loading song catalog...
        </div>

        <div
            id="results"
            aria-live="polite"
        ></div>

    </main>

</div>


<script>
window.SONG_REQUEST_CONFIG = {
    stationName: <?= json_encode(
        STATION_NAME,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>,

    mainStationName: <?= json_encode(
        defined('MAIN_STATION_NAME') ? MAIN_STATION_NAME : STATION_NAME,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>,

    secondaryStationName: <?= json_encode(
        defined('SECONDARY_STATION_NAME') ? SECONDARY_STATION_NAME : 'Secondary Station',
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>,

    requestEndpoint: 'request.php?station=<?= IS_ROCK_REQUEST ? 'rock' : 'main' ?>',

    stationKey: <?= json_encode(
        IS_ROCK_REQUEST ? 'secondary' : 'main',
        JSON_UNESCAPED_SLASHES
    ) ?>,
    otherRequestUrl: '<?= IS_ROCK_REQUEST ? '?station=main' : '?station=rock' ?>',
    activeStationMaxAgeMs: 300000,

    allowMessages: <?= ALLOW_MESSAGES
        ? 'true'
        : 'false'
    ?>,

    maxMessageLength: <?= (int) MAX_MESSAGE_LENGTH ?>
};
</script>

<script src="assets/app.js?v=1.5.0" defer></script>

</body>
</html>
