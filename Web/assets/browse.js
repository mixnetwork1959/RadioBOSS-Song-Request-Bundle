/**
 * ==========================================================
 * RadioBOSS Song Request System
 * Version 1.7.0
 * Paginated song library browser
 * ==========================================================
 */

'use strict';

(() => {
    const body = document.body;
    const searchBox = document.getElementById('searchBox');
    const results = document.getElementById('results');
    const catalogStatus = document.getElementById('catalogStatus');
    const stationGuard = document.getElementById('stationGuard');

    if (!body || !searchBox || !results || !catalogStatus) {
        return;
    }

    const appConfig = window.SONG_REQUEST_CONFIG || {};
    const songsFile = body.dataset.songsFile || '';
    const requestEndpoint = appConfig.requestEndpoint || 'request.php';
    const minSearchLength = Math.max(
        1,
        Number.parseInt(body.dataset.minSearchLength || '2', 10) || 2
    );
    const allowMessages = Boolean(appConfig.allowMessages);
    const maxMessageLength = Math.max(
        1,
        Number.parseInt(appConfig.maxMessageLength || '150', 10) || 150
    );

    let songs = [];
    let currentPage = 1;
    let pageSize = 20;
    let sortMode = 'artist';
    let renderTimer = null;
    const successfulRequests = new Map();

    const controls = document.createElement('div');
    controls.id = 'libraryControls';
    controls.className = 'library-controls';
    controls.hidden = true;
    controls.innerHTML = `
        <div class="library-toolbar">
            <div id="paginationTop" class="pagination pagination-top" aria-label="Song pages"></div>
            <div class="library-options">
                <label>
                    <span>Sort</span>
                    <select id="sortSongs" aria-label="Sort songs">
                        <option value="artist">Artist A-Z</option>
                        <option value="title">Title A-Z</option>
                    </select>
                </label>
                <label>
                    <span>Show</span>
                    <select id="songsPerPage" aria-label="Songs per page">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>
    `;

    catalogStatus.insertAdjacentElement('afterend', controls);

    const paginationBottom = document.createElement('div');
    paginationBottom.id = 'paginationBottom';
    paginationBottom.className = 'pagination pagination-bottom';
    paginationBottom.hidden = true;
    results.insertAdjacentElement('afterend', paginationBottom);

    const sortSelect = document.getElementById('sortSongs');
    const pageSizeSelect = document.getElementById('songsPerPage');
    const paginationTop = document.getElementById('paginationTop');

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFKC')
            .toLocaleLowerCase()
            .replace(/[-‐‑‒–—―]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function prepareSongs(rawSongs) {
        return rawSongs
            .filter(song => (
                Number.isInteger(Number(song.track_id)) &&
                String(song.artist || '').trim() !== '' &&
                String(song.title || '').trim() !== ''
            ))
            .map(song => {
                const artist = String(song.artist).trim();
                const title = String(song.title).trim();

                return {
                    track_id: Number(song.track_id),
                    artist,
                    title,
                    artistKey: normalizeText(artist),
                    titleKey: normalizeText(title),
                    searchText: normalizeText(`${artist} ${title}`)
                };
            });
    }

    function stationIsBlocked() {
        return Boolean(stationGuard && stationGuard.hidden === false);
    }

    function filteredSongs() {
        const query = searchBox.value.trim();

        let filtered = songs;

        if (query.length >= minSearchLength) {
            const words = normalizeText(query)
                .split(' ')
                .filter(Boolean);

            filtered = songs.filter(song =>
                words.every(word => song.searchText.includes(word))
            );
        }

        const sorted = [...filtered];

        sorted.sort((a, b) => {
            if (sortMode === 'title') {
                return (
                    a.titleKey.localeCompare(b.titleKey) ||
                    a.artistKey.localeCompare(b.artistKey)
                );
            }

            return (
                a.artistKey.localeCompare(b.artistKey) ||
                a.titleKey.localeCompare(b.titleKey)
            );
        });

        return sorted;
    }

    function pageButton(label, page, options = {}) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-btn';
        button.textContent = label;
        button.dataset.page = String(page);

        if (options.active) {
            button.classList.add('active');
            button.setAttribute('aria-current', 'page');
        }

        if (options.disabled) {
            button.disabled = true;
        }

        return button;
    }

    function appendEllipsis(container) {
        const span = document.createElement('span');
        span.className = 'page-ellipsis';
        span.textContent = '…';
        container.appendChild(span);
    }

    function renderPager(container, totalPages) {
        container.replaceChildren();

        if (totalPages <= 1) {
            return;
        }

        container.appendChild(
            pageButton('‹ Previous', Math.max(1, currentPage - 1), {
                disabled: currentPage === 1
            })
        );

        const wanted = new Set([
            1,
            totalPages,
            currentPage - 2,
            currentPage - 1,
            currentPage,
            currentPage + 1,
            currentPage + 2
        ]);

        const pages = [...wanted]
            .filter(page => page >= 1 && page <= totalPages)
            .sort((a, b) => a - b);

        let previous = 0;

        for (const page of pages) {
            if (previous > 0 && page - previous > 1) {
                appendEllipsis(container);
            }

            container.appendChild(
                pageButton(String(page), page, {
                    active: page === currentPage
                })
            );

            previous = page;
        }

        container.appendChild(
            pageButton('Next ›', Math.min(totalPages, currentPage + 1), {
                disabled: currentPage === totalPages
            })
        );
    }

    function showRequestStatus(row, message, type = '') {
        const status = row.querySelector('.request-status');

        if (!status) {
            return;
        }

        status.classList.remove(
            'request-success',
            'request-error',
            'request-pending'
        );

        if (type) {
            status.classList.add(type);
        }

        status.textContent = message;
        status.hidden = false;
    }

    function createSongRow(song) {
        const row = document.createElement('article');
        row.className = 'song-row browse-song-row';
        row.dataset.trackId = String(song.track_id);

        const successMessage = successfulRequests.get(song.track_id) || '';
        const alreadyRequested = successMessage !== '';

        const messageField = allowMessages
            ? `
                <input
                    type="text"
                    class="inline-message browse-message"
                    maxlength="${maxMessageLength}"
                    placeholder="Optional message..."
                    aria-label="Optional message for ${escapeHtml(song.artist)} - ${escapeHtml(song.title)}"
                    ${alreadyRequested ? 'disabled' : ''}
                >
              `
            : '';

        row.innerHTML = `
            <div class="song-info">
                <div class="browse-song-main">
                    <span class="music-note" aria-hidden="true">♪</span>
                    <div class="browse-song-text">
                        <strong>${escapeHtml(song.title)}</strong>
                        <span>${escapeHtml(song.artist)}</span>
                    </div>
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
                    class="request-btn browse-request-btn"
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
                successMessage,
                'request-success'
            );
        }

        return row;
    }

    function updateCatalogStatus(totalMatches, start, end) {
        const query = searchBox.value.trim();
        const queryActive = query.length >= minSearchLength;

        if (queryActive) {
            if (totalMatches === 0) {
                catalogStatus.textContent =
                    `No songs found for "${query}".`;
            } else {
                catalogStatus.textContent =
                    `${totalMatches.toLocaleString()} songs found. ` +
                    `Showing ${start.toLocaleString()}-${end.toLocaleString()}.`;
            }
            return;
        }

        if (query.length > 0 && query.length < minSearchLength) {
            catalogStatus.textContent =
                `${songs.length.toLocaleString()} songs available. ` +
                `Enter at least ${minSearchLength} characters to filter.`;
            return;
        }

        catalogStatus.textContent =
            `${songs.length.toLocaleString()} songs available. ` +
            `Showing ${start.toLocaleString()}-${end.toLocaleString()}.`;
    }

    function renderLibrary() {
        if (stationIsBlocked() || songs.length === 0) {
            return;
        }

        const filtered = filteredSongs();
        const totalItems = filtered.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const startIndex = (currentPage - 1) * pageSize;
        const pageSongs = filtered.slice(startIndex, startIndex + pageSize);

        results.replaceChildren();

        if (pageSongs.length === 0) {
            const message = document.createElement('div');
            message.className = 'no-results';
            message.textContent = 'No songs found.';
            results.appendChild(message);
        } else {
            const fragment = document.createDocumentFragment();

            for (const song of pageSongs) {
                fragment.appendChild(createSongRow(song));
            }

            results.appendChild(fragment);
        }

        const start = totalItems > 0 ? startIndex + 1 : 0;
        const end = Math.min(startIndex + pageSongs.length, totalItems);

        updateCatalogStatus(totalItems, start, end);
        renderPager(paginationTop, totalPages);
        renderPager(paginationBottom, totalPages);

        controls.hidden = false;
        paginationBottom.hidden = totalPages <= 1;
    }

    function scheduleRender(resetPage = false) {
        window.clearTimeout(renderTimer);

        if (resetPage) {
            currentPage = 1;
        }

        renderTimer = window.setTimeout(renderLibrary, 320);
    }

    function formatRetryTime(seconds) {
        const retrySeconds = Number.parseInt(seconds, 10);

        if (!Number.isFinite(retrySeconds) || retrySeconds <= 0) {
            return '';
        }

        if (retrySeconds < 60) {
            return `${retrySeconds} seconds`;
        }

        const minutes = Math.ceil(retrySeconds / 60);

        if (minutes === 1) {
            return '1 minute';
        }

        if (minutes < 60) {
            return `${minutes} minutes`;
        }

        const hours = Math.ceil(minutes / 60);
        return hours === 1 ? '1 hour' : `${hours} hours`;
    }

    async function readJsonResponse(response) {
        const responseText = await response.text();

        if (responseText.trim() === '') {
            throw new Error('The server returned an empty response.');
        }

        try {
            return JSON.parse(responseText);
        } catch (error) {
            console.error('Invalid server response:', responseText);
            throw new Error('The server returned an invalid response.');
        }
    }

    async function sendBrowseRequest(button) {
        if (stationIsBlocked()) {
            return;
        }

        const row = button.closest('.browse-song-row');

        if (!row) {
            return;
        }

        const trackId = Number.parseInt(button.dataset.trackId || '', 10);

        if (!Number.isInteger(trackId) || trackId <= 0) {
            showRequestStatus(row, 'Invalid song selection.', 'request-error');
            return;
        }

        const messageField = row.querySelector('.browse-message');
        const message = messageField ? messageField.value.trim() : '';

        if (message.length > maxMessageLength) {
            showRequestStatus(
                row,
                `The message may contain no more than ${maxMessageLength} characters.`,
                'request-error'
            );
            return;
        }

        button.disabled = true;
        button.textContent = 'Sending...';

        if (messageField) {
            messageField.disabled = true;
        }

        showRequestStatus(row, 'Sending your request...', 'request-pending');

        try {
            const response = await fetch(requestEndpoint, {
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
            });

            const payload = await readJsonResponse(response);

            if (!response.ok || payload.success !== true) {
                let errorMessage =
                    payload.message || 'The request could not be sent.';

                if (payload.retry_after) {
                    const retryTime = formatRetryTime(payload.retry_after);
                    if (retryTime) {
                        errorMessage += ` Try again in ${retryTime}.`;
                    }
                }

                throw new Error(errorMessage);
            }

            const successMessage =
                payload.message || 'Your song request was sent successfully.';

            successfulRequests.set(trackId, successMessage);
            button.textContent = 'Requested';
            button.disabled = true;

            if (messageField) {
                messageField.disabled = true;
            }

            showRequestStatus(row, successMessage, 'request-success');
        } catch (error) {
            console.error(error);
            button.disabled = false;
            button.textContent = 'Request';

            if (messageField) {
                messageField.disabled = false;
            }

            showRequestStatus(
                row,
                error.message || 'The request could not be sent.',
                'request-error'
            );
        }
    }

    function handlePagerClick(event) {
        const button = event.target.closest('.page-btn');

        if (!button || button.disabled) {
            return;
        }

        const page = Number.parseInt(button.dataset.page || '1', 10);

        if (!Number.isInteger(page) || page < 1) {
            return;
        }

        currentPage = page;
        renderLibrary();
        controls.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function loadBrowseCatalog() {
        if (!songsFile) {
            return;
        }

        try {
            const response = await fetch(songsFile, { cache: 'no-store' });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();

            if (!Array.isArray(payload)) {
                throw new Error('The song catalog is not a JSON array.');
            }

            songs = prepareSongs(payload);

            if (!stationIsBlocked()) {
                searchBox.disabled = false;
                renderLibrary();
            }
        } catch (error) {
            console.error('Browse catalog error:', error);
        }
    }

    searchBox.addEventListener('input', () => scheduleRender(true));

    sortSelect.addEventListener('change', () => {
        sortMode = sortSelect.value === 'title' ? 'title' : 'artist';
        currentPage = 1;
        renderLibrary();
    });

    pageSizeSelect.addEventListener('change', () => {
        const newSize = Number.parseInt(pageSizeSelect.value || '20', 10);
        pageSize = [20, 50, 100].includes(newSize) ? newSize : 20;
        currentPage = 1;
        renderLibrary();
    });

    paginationTop.addEventListener('click', handlePagerClick);
    paginationBottom.addEventListener('click', handlePagerClick);

    results.addEventListener('click', event => {
        const button = event.target.closest('.browse-request-btn');

        if (!button || button.disabled) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        sendBrowseRequest(button);
    }, true);

    window.addEventListener('focus', () => {
        window.setTimeout(() => {
            if (!stationIsBlocked() && songs.length > 0) {
                renderLibrary();
            }
        }, 50);
    });

    window.addEventListener('storage', () => {
        window.setTimeout(() => {
            if (stationIsBlocked()) {
                controls.hidden = true;
                paginationBottom.hidden = true;
            } else if (songs.length > 0) {
                renderLibrary();
            }
        }, 50);
    });

    loadBrowseCatalog();
})();
