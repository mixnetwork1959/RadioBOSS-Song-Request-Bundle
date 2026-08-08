/**
 * ==========================================================
 * RadioBOSS Song Request System
 * Version 1.5.0
 * JSON Search and Song Requests
 * ==========================================================
 */

'use strict';

const body = document.body;
const searchBox = document.getElementById('searchBox');
const results = document.getElementById('results');
const catalogStatus = document.getElementById('catalogStatus');
const stationGuard = document.getElementById('stationGuard');
const stationGuardTitle = document.getElementById('stationGuardTitle');
const stationGuardMessage = document.getElementById('stationGuardMessage');
const stationGuardLink = document.getElementById('stationGuardLink');

const songsFile = body.dataset.songsFile;

const minSearchLength = Number.parseInt(
    body.dataset.minSearchLength || '2',
    10
);

const resultLimit = Number.parseInt(
    body.dataset.resultLimit || '25',
    10
);

const appConfig = window.SONG_REQUEST_CONFIG || {};

const allowMessages = Boolean(
    appConfig.allowMessages
);

const maxMessageLength = Number.parseInt(
    appConfig.maxMessageLength || '150',
    10
);

const requestEndpoint =
    appConfig.requestEndpoint || 'request.php';

const activeStationStorageKey =
    'radioboss_request_active_station_v1';

const expectedStation = canonicalStation(
    appConfig.stationKey || 'main'
);

const activeStationMaxAgeMs = Number.parseInt(
    appConfig.activeStationMaxAgeMs || '300000',
    10
);

let songs = [];
let searchTimer = null;
let catalogLoadStarted = false;
let stationBlocked = false;

const successfulRequests = new Map();


function canonicalStation(value) {
    const key = String(value || '')
        .toLocaleLowerCase()
        .replaceAll('_', '-');

    return key === 'secondary' || key === 'rock'
        ? 'secondary'
        : 'main';
}


function stationDisplayName(value) {
    return canonicalStation(value) === 'secondary'
        ? (appConfig.secondaryStationName || 'Secondary station')
        : (appConfig.mainStationName || appConfig.stationName || 'Main station');
}


function readActiveStation() {
    try {
        const payload = JSON.parse(
            localStorage.getItem(activeStationStorageKey) || 'null'
        );
        const age = Date.now() - Number(payload?.updatedAt || 0);

        if (
            payload?.playing !== true ||
            !Number.isFinite(age) ||
            age < 0 ||
            age > activeStationMaxAgeMs
        ) {
            return null;
        }

        return canonicalStation(payload.station);
    } catch (error) {
        return null;
    }
}


function blockWrongStation(activeStation) {
    stationBlocked = true;
    const activeName = stationDisplayName(activeStation);
    const expectedName = stationDisplayName(expectedStation);

    stationGuardTitle.textContent =
        `You are currently listening to ${activeName}.`;
    stationGuardMessage.textContent =
        `Please use the ${activeName} request page. Requests for ${expectedName} are blocked while the other station is playing.`;
    stationGuardLink.textContent =
        `Open ${activeName} requests`;
    stationGuardLink.href = appConfig.otherRequestUrl || '#';
    stationGuard.hidden = false;

    searchBox.disabled = true;
    searchBox.value = '';
    results.replaceChildren();
    catalogStatus.classList.add('station-blocked-status');
    catalogStatus.textContent =
        `Song requests are currently available for ${activeName}.`;
}


function unblockStation() {
    const wasBlocked = stationBlocked;
    stationBlocked = false;
    stationGuard.hidden = true;
    catalogStatus.classList.remove('station-blocked-status');

    if (songs.length > 0) {
        catalogStatus.textContent =
            `${songs.length.toLocaleString()} songs available.`;
        searchBox.disabled = false;
    } else if (wasBlocked && !catalogLoadStarted) {
        loadCatalog();
    }
}


function applyStationGuard() {
    const activeStation = readActiveStation();

    if (activeStation && activeStation !== expectedStation) {
        blockWrongStation(activeStation);
        return true;
    }

    unblockStation();
    return false;
}


/**
 * Normalize text for case-insensitive and
 * whitespace-tolerant search.
 */
function normalizeText(value) {
    return String(value || '')
        .normalize('NFKC')
        .toLocaleLowerCase()
        .replace(/[-‐‑‒–—―]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}


/**
 * Escape user-controlled text before inserting it into HTML.
 */
function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}


/**
 * Build searchable text once after loading the catalog.
 */
function prepareSongs(rawSongs) {
    return rawSongs
        .filter(song => {
            return (
                Number.isInteger(Number(song.track_id)) &&
                String(song.artist || '').trim() !== '' &&
                String(song.title || '').trim() !== ''
            );
        })
        .map(song => {
            const artist = String(song.artist).trim();
            const title = String(song.title).trim();

            return {
                track_id: Number(song.track_id),
                artist,
                title,
                searchText: normalizeText(
                    `${artist} ${title}`
                )
            };
        });
}


/**
 * Load the public JSON catalog generated by SongSync Engine.
 */
async function loadCatalog() {
    if (catalogLoadStarted || stationBlocked) {
        return;
    }
    catalogLoadStarted = true;

    try {
        const response = await fetch(
            songsFile,
            {
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();

        if (!Array.isArray(payload)) {
            throw new Error(
                'The song catalog is not a JSON array.'
            );
        }

        songs = prepareSongs(payload);

        if (!stationBlocked) {
            catalogStatus.textContent =
                `${songs.length.toLocaleString()} songs available.`;
            searchBox.disabled = false;
            searchBox.focus();
        }

    } catch (error) {
        console.error(error);

        catalogStatus.classList.add('error-message');

        catalogStatus.textContent =
            'The song catalog could not be loaded.';

        searchBox.disabled = true;
    }
}


/**
 * Search every entered word in the combined artist/title text.
 */
function findSongs(query) {
    const words = normalizeText(query)
        .split(' ')
        .filter(Boolean);

    if (words.length === 0) {
        return [];
    }

    const matches = [];

    for (const song of songs) {
        const isMatch = words.every(
            word => song.searchText.includes(word)
        );

        if (!isMatch) {
            continue;
        }

        matches.push(song);

        if (matches.length >= resultLimit) {
            break;
        }
    }

    return matches;
}


/**
 * Show a status message for one result row.
 */
function showRequestStatus(
    row,
    message,
    type = ''
) {
    const status = row.querySelector(
        '.request-status'
    );

    if (!status) {
        return;
    }

    status.classList.remove(
        'request-success',
        'request-error',
        'request-pending'
    );

    if (type !== '') {
        status.classList.add(type);
    }

    status.textContent = message;
    status.hidden = false;
}


/**
 * Render one song result.
 */
function createSongRow(song) {
    const row = document.createElement('article');

    row.className = 'song-row';
    row.dataset.trackId = String(song.track_id);

    const alreadyRequested =
        successfulRequests.has(song.track_id);

    const messageField = allowMessages
        ? `
            <textarea
                class="inline-message"
                maxlength="${maxMessageLength}"
                placeholder="Optional message..."
                aria-label="Optional message for ${escapeHtml(song.artist)} - ${escapeHtml(song.title)}"
                ${alreadyRequested ? 'disabled' : ''}
            ></textarea>
          `
        : '';

    row.innerHTML = `
        <div class="song-info">
            <div class="song-title">
                <span
                    class="music-note"
                    aria-hidden="true"
                >♪</span>

                <span>
                    ${escapeHtml(song.artist)}
                    -
                    ${escapeHtml(song.title)}
                </span>
            </div>

            ${messageField}

            <div
                class="request-status"
                aria-live="polite"
                hidden
            ></div>
        </div>

        <div class="request-actions">
            <button
                type="button"
                class="request-btn"
                data-track-id="${song.track_id}"
                ${alreadyRequested ? 'disabled' : ''}
            >
                ${alreadyRequested ? 'Requested' : 'Request'}
            </button>
        </div>
    `;

    if (alreadyRequested) {
        showRequestStatus(
            row,
            successfulRequests.get(song.track_id) ||
            'Your request was sent successfully.',
            'request-success'
        );
    }

    return row;
}


/**
 * Render search results.
 */
function renderResults(matches, query) {
    results.replaceChildren();

    if (matches.length === 0) {
        const message = document.createElement('div');

        message.className = 'no-results';

        message.textContent =
            `No songs found for "${query}".`;

        results.appendChild(message);
        return;
    }

    const fragment = document.createDocumentFragment();

    for (const song of matches) {
        fragment.appendChild(
            createSongRow(song)
        );
    }

    results.appendChild(fragment);

    if (matches.length >= resultLimit) {
        const notice = document.createElement('div');

        notice.className = 'search-notice';

        notice.textContent =
            `Showing the first ${resultLimit} results. ` +
            'Please refine your search.';

        results.appendChild(notice);
    }
}


/**
 * Convert retry time into a readable message.
 */
function formatRetryTime(seconds) {
    const retrySeconds = Number.parseInt(
        seconds,
        10
    );

    if (
        !Number.isFinite(retrySeconds) ||
        retrySeconds <= 0
    ) {
        return '';
    }

    if (retrySeconds < 60) {
        return `${retrySeconds} seconds`;
    }

    const minutes = Math.ceil(
        retrySeconds / 60
    );

    if (minutes === 1) {
        return '1 minute';
    }

    if (minutes < 60) {
        return `${minutes} minutes`;
    }

    const hours = Math.ceil(
        minutes / 60
    );

    if (hours === 1) {
        return '1 hour';
    }

    return `${hours} hours`;
}


/**
 * Read a JSON response safely.
 */
async function readJsonResponse(response) {
    const responseText = await response.text();

    if (responseText.trim() === '') {
        throw new Error(
            'The server returned an empty response.'
        );
    }

    try {
        return JSON.parse(responseText);
    } catch (error) {
        console.error(
            'Invalid server response:',
            responseText
        );

        throw new Error(
            'The server returned an invalid response.'
        );
    }
}


/**
 * Send one song request to the PHP endpoint.
 */
async function sendSongRequest(button) {
    if (applyStationGuard()) {
        return;
    }

    const row = button.closest('.song-row');

    if (!row) {
        return;
    }

    const trackId = Number.parseInt(
        button.dataset.trackId || '',
        10
    );

    if (!Number.isInteger(trackId) || trackId <= 0) {
        showRequestStatus(
            row,
            'Invalid song selection.',
            'request-error'
        );

        return;
    }

    const messageField = row.querySelector(
        '.inline-message'
    );

    const message = messageField
        ? messageField.value.trim()
        : '';

    if (message.length > maxMessageLength) {
        showRequestStatus(
            row,
            `The message may contain no more than ` +
            `${maxMessageLength} characters.`,
            'request-error'
        );

        return;
    }

    button.disabled = true;
    button.textContent = 'Sending...';

    if (messageField) {
        messageField.disabled = true;
    }

    showRequestStatus(
        row,
        'Sending your request...',
        'request-pending'
    );

    try {
        const response = await fetch(
            requestEndpoint,
            {
                method: 'POST',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    track_id: trackId,
                    message
                })
            }
        );

        const payload = await readJsonResponse(
            response
        );

        if (!response.ok || payload.success !== true) {
            let errorMessage =
                payload.message ||
                'The request could not be sent.';

            if (payload.retry_after) {
                const retryTime = formatRetryTime(
                    payload.retry_after
                );

                if (retryTime !== '') {
                    errorMessage +=
                        ` Try again in ${retryTime}.`;
                }
            }

            throw new Error(errorMessage);
        }

        const successMessage =
            payload.message ||
            'Your song request was sent successfully.';

        successfulRequests.set(
            trackId,
            successMessage
        );

        button.textContent = 'Requested';
        button.disabled = true;

        if (messageField) {
            messageField.disabled = true;
        }

        showRequestStatus(
            row,
            successMessage,
            'request-success'
        );

    } catch (error) {
        console.error(error);

        button.disabled = false;
        button.textContent = 'Request';

        if (messageField) {
            messageField.disabled = false;
        }

        showRequestStatus(
            row,
            error.message ||
            'The request could not be sent.',
            'request-error'
        );
    }
}


/**
 * Handle delayed search input.
 */
function runSearch() {
    const query = searchBox.value.trim();

    if (query.length < minSearchLength) {
        results.replaceChildren();

        if (query.length > 0) {
            const notice = document.createElement('div');

            notice.className = 'search-notice';

            notice.textContent =
                `Enter at least ${minSearchLength} characters.`;

            results.appendChild(notice);
        }

        return;
    }

    const matches = findSongs(query);

    renderResults(matches, query);
}


/*
|--------------------------------------------------------------------------
| Event listeners
|--------------------------------------------------------------------------
*/

searchBox.disabled = true;

searchBox.addEventListener('input', () => {
    window.clearTimeout(searchTimer);

    searchTimer = window.setTimeout(
        runSearch,
        250
    );
});

results.addEventListener('click', event => {
    const button = event.target.closest(
        '.request-btn'
    );

    if (!button || button.disabled) {
        return;
    }

    sendSongRequest(button);
});


/*
|--------------------------------------------------------------------------
| Start application
|--------------------------------------------------------------------------
*/

window.addEventListener('storage', event => {
    if (event.key === activeStationStorageKey) {
        applyStationGuard();
    }
});

window.addEventListener('focus', applyStationGuard);

if (!applyStationGuard()) {
    loadCatalog();
}
