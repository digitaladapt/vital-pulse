// ── Config & State ────────────────────────────────────────
const API_KEY_STORAGE = 'vitalpulse_api_key';
let apiKey = localStorage.getItem(API_KEY_STORAGE);

const EMOJIS = ['🤩', '😀', '🙂', '😐', '🙁', '😩', '🥵', '😵‍💫', '🤢', '🥶'];
let selectedEmoji = '😐';
let filterEmojis = new Set(); // emoji(s) currently selected for filtering
let bpChart, hrChart, wtChart;

function getCommonOptions() {
    return {
        responsive: true,
        maintainAspectRatio: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: { callbacks: { afterBody(items) {
                return items[0].raw.emoji;
    } } } },
        scales: { x: {
            type: 'time',
            adapters: { date: { zone: 'utc' } },
            time: { tooltipFormat: 'MMM d, h:mm a',
                displayFormats: { day: 'MMM d', week: 'MMM d', month: 'MMM yyyy' } },
            ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 8, font: { size: 10 } }
    } } };
}

// ── Initialization ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initMoodSelector();
    initFilterEmoji();
    setDefaultDates();
    document.querySelector('.preset-btn[data-preset="30"]').classList.add('active');
    setDefaultReadingDateTime();
    initAutoAdvance();
    requestApiKeyIfMissing().then(() => renderCharts());
});

// ── Auto-Advance ──────────────────────────────────────────
// Field order: sys → dia → hr-input → wt-input → mood → submit
const AUTO_ADVANCE_FIELDS = ['sys', 'dia', 'hr-input', 'wt-input'];

// ── Soft Validation Warnings ───────────────────────────────
// "Warn, don't block" — gentle frontend nudges for abnormal values.
// These are purely informational; the server still accepts any value.
const VALIDATION_THRESHOLDS = {
    sys: [
        { low: 80,   high: 120, msg: 'Systolic looks normal 👍' },
        { low: 120,  high: 140, msg: 'Slightly elevated — keep an eye on it.' },
        { low: 140,  high: 160, msg: "BP is in the high range. Is that correct?" },
        { low: 160,  high: Infinity, msg: 'Quite high BP — double-check?' }
    ],
    dia: [
        { low: 60,   high: 80,  msg: 'Diastolic looks normal 👍' },
        { low: 80,   high: 90,  msg: 'Slightly elevated — keep an eye on it.' },
        { low: 90,   high: 100, msg: "BP is in the high range. Is that correct?" },
        { low: 100,  high: Infinity, msg: 'Quite high BP — double-check?' }
    ],
    hr: [
        { low: 50,   high: 100, msg: 'Resting heart rate looks normal 👍' },
        { low: 40,   high: 50,  msg: 'Low resting HR — are you an athlete?' },
        { low: 100,  high: 120, msg: 'Elevated resting heart rate.' },
        { low: 120,  high: Infinity, msg: 'High resting heart rate — felt okay?' }
    ],
    wt: [
        { low: 70,   high: 350, msg: null }, // no general warning
        { low: 350,  high: Infinity, msg: 'That is quite a heavy weight — sure?' }
    ]
};


function checkValidation(fieldId) {
    const input = document.getElementById(fieldId);
    if (!input || !input.value) return null;
    
    const val = parseFloat(input.value);
    if (isNaN(val)) return null;
    
    // Only warn for values in a plausible but abnormal range
    if (val <= 0) return null; // negative numbers get no special warning
    
    const thresholds = VALIDATION_THRESHOLDS[fieldId];
    if (!thresholds) return null;
    
    for (const t of thresholds) {
        if (val >= t.low && val < t.high) {
            return { field: fieldId, message: t.msg };
        }
    }
    return null;
}

function showWarning(message) {
    const banner = document.getElementById('reading-warning');
    if (!banner) return;
    
    // Find or create the warning text container
    let textEl = banner.querySelector('.warning-text');
    if (textEl) {
        textEl.textContent = message;
    } else {
        const span = document.createElement('span');
        span.className = 'warning-text';
        span.textContent = message;
        banner.prepend(span);
    }
    
    banner.classList.remove('hidden');
}

function hideWarning() {
    const banner = document.getElementById('reading-warning');
    if (!banner) return;
    
    const textEl = banner.querySelector('.warning-text');
    if (textEl) textEl.textContent = '';
    banner.classList.add('hidden');
}

function dismissWarning() {
    hideWarning();
}

// ── Soft Validation on Input (per-field, real-time) ─────────
const VALIDATION_FIELDS = ['sys', 'dia', 'hr-input'];

VALIDATION_FIELDS.forEach(fieldId => {
    const input = document.getElementById(fieldId);
    if (!input) return;

    // Show warning when user types into this field
    input.addEventListener('input', () => {
        const result = checkValidation(fieldId);
        if (result?.message) {
            showWarning(`⚠️ ${result.message}`);
        }
    });

    // Hide the per-field warning when user clears the input
    input.addEventListener('blur', () => {
        if (!input.value) hideWarning();
    });
});

// ── Auto-Advance ──────────────────────────────────────────

function initAutoAdvance() {
    AUTO_ADVANCE_FIELDS.forEach((fieldId, index) => {
        const input = document.getElementById(fieldId);
        if (!input) return;

        input.addEventListener('input', () => handleAutoAdvance(input, index));
        input.addEventListener('keydown', (e) => handleAdvanceKey(e, index));
    });
}

function handleAutoAdvance(input, index) {
    // Weight (last in the chain) has no auto-advance — range too wide
    if (index >= AUTO_ADVANCE_FIELDS.length - 1) return;

    const value = input.value;
    if (value.length === 0) return;

    const firstDigit = parseInt(value[0]);

    // If first digit is 0-2, it's likely a 3-digit number → advance after 3rd digit
    // If first digit is 3-9, it's likely a 2-digit number → advance after 2nd digit
    const expectedLength = (firstDigit >= 0 && firstDigit <= 2) ? 3 : 2;

    if (value.length >= expectedLength) {
        focusNextField(index);
    }
}

function handleAdvanceKey(e, index) {
    // Enter always advances to next field (or submits on last field)
    if (e.key === 'Enter') {
        e.preventDefault();
        focusNextField(index);
        return;
    }

    // Backspace on empty field moves focus back
    if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) {
        e.preventDefault();
        focusField(index - 1);
    }
}

function focusNextField(index) {
    // After the last numeric field, move to mood selector / submit
    if (index >= AUTO_ADVANCE_FIELDS.length - 1) {
        // Focus the first emoji option or the submit button
        const firstEmoji = document.querySelector('.emoji-option');
        if (firstEmoji) {
            firstEmoji.focus();
        } else {
            document.querySelector('.btn-primary')?.focus();
        }
        return;
    }
    focusField(index + 1);
}

function focusField(index) {
    const nextInput = document.getElementById(AUTO_ADVANCE_FIELDS[index]);
    if (nextInput) {
        nextInput.focus();
        // Highlight animation
        nextInput.classList.remove('auto-advance-highlight');
        // Force reflow to restart animation
        void nextInput.offsetWidth;
        nextInput.classList.add('auto-advance-highlight');
    }
}

function initMoodSelector() {
    const container = document.getElementById('mood-selector');
    EMOJIS.forEach(e => {
        const span = document.createElement('span');
        span.className = 'emoji-option';
        span.textContent = e;
        if (e === selectedEmoji) span.classList.add('selected');
        span.onclick = () => {
            container.querySelectorAll('.emoji-option').forEach(s => s.classList.remove('selected'));
            span.classList.add('selected');
            selectedEmoji = e;
        };
        container.appendChild(span);
    });
}

function initFilterEmoji() {
    const container = document.getElementById('filter-emoji');
    EMOJIS.forEach(e => {
        const span = document.createElement('span');
        span.className = 'emoji-filter-option selected';
        span.textContent = e;
        span.onclick = () => {
            if (filterEmojis.has(e)) {
                filterEmojis.delete(e);
                span.classList.remove('selected');
            } else {
                filterEmojis.add(e);
                span.classList.add('selected');
            }
        };
        filterEmojis.add(e);
        container.appendChild(span);
    });
}

function setDefaultDates() {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    document.getElementById('filter-to').valueAsDate = tomorrow;
    document.getElementById('filter-from').valueAsDate = thirtyDaysAgo;
}

function applyDatePreset(preset) {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);

    const fromEl = document.getElementById('filter-from');
    const toEl = document.getElementById('filter-to');

    if (preset === 'all') {
        fromEl.value = '';
        toEl.valueAsDate = tomorrow;
    } else {
        const days = parseInt(preset, 10);
        const fromDate = new Date(today);
        fromDate.setDate(today.getDate() - days);
        fromEl.valueAsDate = fromDate;
        toEl.valueAsDate = tomorrow;
    }

    // Highlight the active preset button
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.preset === String(preset));
    });

    renderCharts();
}

function setDefaultReadingDateTime() {
    const now = new Date();
    // Adjust to local time for datetime-local input (YYYY-MM-DDTHH:MM format)
    const offset = now.getTimezoneOffset();
    const local = new Date(now.getTime() - offset * 60000);
    document.getElementById('reading-datetime').value = local.toISOString().slice(0, 16);
}

async function requestApiKeyIfMissing() {
    if (!apiKey) {
        apiKey = prompt('Enter your VitalPulse API key (from .env):');
        if (!apiKey) {
            alert('API key is required.');
            return false;
        }
        localStorage.setItem(API_KEY_STORAGE, apiKey);
    }
    return true;
}

// ── Data Fetching ─────────────────────────────────────────
async function fetchLogs(retryCount = 0) {
    const from = document.getElementById('filter-from').value || null;
    const to = document.getElementById('filter-to').value || null;

    let url = '/api/v1/logs?';
    if (from) url += `from=${encodeURIComponent(from)}&`;
    if (to) url += `to=${encodeURIComponent(to)}&`;

    // If some emojis selected but not all, send them as emoji[]=x&emoji[]=y
    const filterArray = Array.from(filterEmojis);
    if (filterArray.length > 0 && filterArray.length < EMOJIS.length) {
        for (const e of filterArray) {
            url += `emoji[]=${encodeURIComponent(e)}&`;
        }
    }

    try {
        const resp = await fetch(url, {
            headers: { 'X-API-Key': apiKey }
        });
        if (!resp.ok) {
            if (resp.status === 401 && retryCount < 1) {
                localStorage.removeItem(API_KEY_STORAGE);
                apiKey = prompt('API key invalid. Enter correct API key:');
                if (!apiKey) return null;
                localStorage.setItem(API_KEY_STORAGE, apiKey);
                return fetchLogs(retryCount + 1); // retry once
            }
            throw new Error(`Server error ${resp.status}`);
        }
        const json = await resp.json();
        // Handle paginated or aggregated response: extract data array
        const data = Array.isArray(json) ? json : (json.data || []);
        // Store aggregation metadata for UI feedback
        if (json.meta?.aggregated) {
            window.__vitalPulseAggregated = { interval: json.meta.interval, total: json.meta.total };
        } else {
            window.__vitalPulseAggregated = null;
        }
        return data;
    } catch (err) {
        console.error('Fetch failed:', err);
        alert('Could not load data from server: ' + err.message);
        return null;
    }
}

// ── CSV Export ────────────────────────────────────────────
async function exportCsv() {
    const from = document.getElementById('filter-from').value || null;
    const to = document.getElementById('filter-to').value || null;

    let url = '/api/v1/logs/export?';
    if (from) url += `from=${encodeURIComponent(from)}&`;
    if (to) url += `to=${encodeURIComponent(to)}&`;

    const filterArray = Array.from(filterEmojis);
    if (filterArray.length > 0 && filterArray.length < EMOJIS.length) {
        for (const e of filterArray) {
            url += `emoji[]=${encodeURIComponent(e)}&`;
        }
    }

    try {
        const resp = await fetch(url, {
            headers: { 'X-API-Key': apiKey }
        });
        if (!resp.ok) {
            if (resp.status === 401) {
                localStorage.removeItem(API_KEY_STORAGE);
                apiKey = prompt('API key invalid. Enter correct API key:');
                if (apiKey) {
                    localStorage.setItem(API_KEY_STORAGE, apiKey);
                    return exportCsv();
                }
            }
            throw new Error(`Server error ${resp.status}`);
        }
        const blob = await resp.blob();
        const downloadUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = 'vitalpulse_export.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(downloadUrl);
    } catch (err) {
        console.error('CSV export failed:', err);
        alert('Could not export CSV: ' + err.message);
    }
}

// ── Chart Rendering ───────────────────────────────────────
async function renderCharts() {
    const logs = await fetchLogs();
    if (!logs || !Array.isArray(logs)) return;

    // Sort ascending by timestamp for charts (oldest → newest)
    logs.sort((a, b) => a.timestamp.localeCompare(b.timestamp));

    updateLatestReading(logs);
    updateAggregationNotice();
    updateStatsFromApi();

    try {
        renderBpChart(logs);
        renderHrChart(logs);
        renderWtChart(logs);
    } catch (err) {
        console.error('Chart rendering failed:', err);
        const grid = document.querySelector('.grid');
        if (grid) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:2rem;color:#991b1b;">Could not render charts. Please check the console for details.</div>';
        }
    }
}

function buildStatsUrl(from, to) {
    let url = '/api/v1/logs/stats?';
    if (from) url += `from=${encodeURIComponent(from)}&`;
    if (to) url += `to=${encodeURIComponent(to)}&`;
    return url;
}

async function fetchStats(from, to) {
    const url = buildStatsUrl(from, to);
    try {
        const resp = await fetch(url, {
            headers: { 'X-API-Key': apiKey }
        });
        if (!resp.ok) throw new Error(`Server error ${resp.status}`);
        return await resp.json();
    } catch (err) {
        console.error('Stats fetch failed:', err);
        return null;
    }
}

/**
 * Compute the previous period of equal length for trend comparison.
 * If from=2025-06-01, to=2025-06-30 (30 days), previous = 2025-05-01 to 2025-05-31.
 */
function computePreviousPeriod(fromStr, toStr) {
    if (!fromStr || !toStr) return null;
    const from = new Date(fromStr);
    const to = new Date(toStr);
    if (isNaN(from) || isNaN(to)) return null;

    const spanMs = to.getTime() - from.getTime();
    const prevTo = new Date(from.getTime() - 86400000); // day before current "from"
    const prevFrom = new Date(prevTo.getTime() - spanMs);

    return {
        from: prevFrom.toISOString().slice(0, 10),
        to: prevTo.toISOString().slice(0, 10)
    };
}

function formatStat(value, isFloat) {
    if (value === null || value === undefined) return '-';
    return isFloat ? Number(value).toFixed(1) : String(value);
}

function formatRange(min, max, isFloat) {
    if (min === null && max === null) return '';
    return `Range: ${formatStat(min, isFloat)} – ${formatStat(max, isFloat)}`;
}

function formatTrend(current, previous, isWeight) {
    if (current === null || previous === null) return null;
    const delta = Number(current) - Number(previous);
    if (Math.abs(delta) < 0.05) return { text: '→ No change', cls: 'flat' };

    const arrow = delta > 0 ? '↑' : '↓';
    const absDelta = Math.abs(delta).toFixed(isWeight ? 1 : 0);
    const cls = delta > 0 ? 'up' : 'down';

    if (isWeight) {
        return { text: `${arrow} ${absDelta} lbs vs prev period`, cls };
    }
    return { text: `${arrow} ${absDelta} vs prev period`, cls };
}

const METRIC_MAP = {
    sys: { api: 'systolic', isFloat: false, isWeight: false },
    dia: { api: 'diastolic', isFloat: false, isWeight: false },
    hr:  { api: 'heart_rate', isFloat: false, isWeight: false },
    wt:  { api: 'weight', isFloat: true, isWeight: true },
};

async function updateStatsFromApi() {
    const from = document.getElementById('filter-from').value || null;
    const to = document.getElementById('filter-to').value || null;

    // Reset all fields
    Object.keys(METRIC_MAP).forEach(k => {
        document.getElementById('avg-' + k).textContent = '-';
        document.getElementById('range-' + k).textContent = '';
        document.getElementById('trend-' + k).textContent = '';
        document.getElementById('trend-' + k).className = 'stat-trend';
    });
    document.getElementById('total-count').textContent = '-';

    // Fetch current period stats
    const stats = await fetchStats(from, to);
    if (!stats) return;

    document.getElementById('total-count').textContent = stats.count ?? '-';

    Object.entries(METRIC_MAP).forEach(([k, cfg]) => {
        const d = stats[cfg.api] || {};
        document.getElementById('avg-' + k).textContent = formatStat(d.avg, cfg.isFloat);
        document.getElementById('range-' + k).textContent = formatRange(d.min, d.max, cfg.isFloat);
    });

    // Fetch previous period for trend comparison
    const prevPeriod = computePreviousPeriod(from, to);
    if (!prevPeriod) return;

    const prevStats = await fetchStats(prevPeriod.from, prevPeriod.to);
    if (!prevStats) return;

    Object.entries(METRIC_MAP).forEach(([k, cfg]) => {
        const cur = stats[cfg.api]?.avg ?? null;
        const prev = prevStats[cfg.api]?.avg ?? null;
        const trend = formatTrend(cur, prev, cfg.isWeight);
        if (trend) {
            const el = document.getElementById('trend-' + k);
            el.textContent = trend.text;
            el.className = 'stat-trend ' + trend.cls;
        }
    });
}

function updateAggregationNotice() {
    const notice = document.getElementById('aggregation-notice');
    if (!notice) return;
    const agg = window.__vitalPulseAggregated;
    if (agg) {
        notice.style.display = 'block';
        document.getElementById('agg-interval').textContent = agg.interval;
        document.getElementById('agg-total').textContent = agg.total.toLocaleString();
    } else {
        notice.style.display = 'none';
    }
}

function updateLatestReading(logs) {
    const container = document.getElementById('latest-reading');
    if (!container) return;

    if (logs.length === 0) {
        container.innerHTML = '<span class="lr-empty">No readings yet. Submit your first log above!</span>';
        return;
    }

    // logs are sorted ascending (oldest → newest), so last is latest
    const latest = logs[logs.length - 1];
    const previous = logs.length >= 2 ? logs[logs.length - 2] : null;

    const parts = [];
    parts.push(`<span class="lr-emoji">${latest.emoji || '😐'}</span>`);

    const metrics = [];
    if (latest.systolic != null && latest.diastolic != null) {
        metrics.push(`<div class="lr-metric"><span class="lr-value">${latest.systolic}/${latest.diastolic}</span>${formatReadingTrend(previous, latest, 'systolic', false)}<br>Blood Pressure</div>`);
    }
    if (latest.heart_rate != null) {
        metrics.push(`<div class="lr-metric"><span class="lr-value">${latest.heart_rate}</span>${formatReadingTrend(previous, latest, 'heart_rate', false)}<br>Heart Rate (bpm)</div>`);
    }
    if (latest.weight != null) {
        metrics.push(`<div class="lr-metric"><span class="lr-value">${latest.weight}</span>${formatReadingTrend(previous, latest, 'weight', true)}<br>Weight (lbs)</div>`);
    }

    if (metrics.length === 0) {
        metrics.push('<div class="lr-metric"><span class="lr-value">—</span><br>No measurements</div>');
    }

    parts.push(`<div class="lr-metrics">${metrics.join('')}</div>`);

    // Format timestamp for display
    let timeStr = 'Unknown time';
    if (latest.timestamp) {
        try {
            const d = new Date(latest.timestamp);
            timeStr = d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        } catch {
            timeStr = latest.timestamp;
        }
    }
    parts.push(`<span class="lr-time">${timeStr}</span>`);

    container.innerHTML = parts.join('');
}

/**
 * Compare a metric between the previous and latest reading.
 * Returns an HTML span with ↑/↓ arrow and delta, or empty string if not comparable.
 */
function formatReadingTrend(previous, latest, field, isFloat) {
    if (!previous || previous[field] == null || latest[field] == null) return '';
    const delta = Number(latest[field]) - Number(previous[field]);
    if (Math.abs(delta) < (isFloat ? 0.05 : 0.5)) return ' <span class="lr-trend flat">→</span>';
    const arrow = delta > 0 ? '↑' : '↓';
    const absDelta = Math.abs(delta).toFixed(isFloat ? 1 : 0);
    const cls = delta > 0 ? 'up' : 'down';
    return ` <span class="lr-trend ${cls}">${arrow} ${absDelta}</span>`;
}

function makeDataset(logs, field) {
    return logs
        .filter(l => l[field] != null)
        .map(l => ({
            emoji: l.emoji,
            x: l.timestamp,
            y: l[field]
        }));
}

function renderBpChart(logs) {
    const ctx = document.getElementById('bp').getContext('2d');

    if (bpChart) bpChart.destroy();
    bpChart = new Chart(ctx, {
        type: 'line',
        data: { datasets: [
            { label: 'Systolic',  data: makeDataset(logs, 'systolic' ), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', tension: 0.4 },
            { label: 'Diastolic', data: makeDataset(logs, 'diastolic'), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.4 }
        ] },
        options: getCommonOptions()
    });
}

function renderHrChart(logs) {
    const ctx = document.getElementById('hr').getContext('2d');

    if (hrChart) hrChart.destroy();
    hrChart = new Chart(ctx, {
        type: 'line',
        data: { datasets: [
            { label: 'Heart Rate', data: makeDataset(logs, 'heart_rate'), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', tension: 0.4 }
        ] },
        options: getCommonOptions()
    });
}

function renderWtChart(logs) {
    const ctx = document.getElementById('wt').getContext('2d');

    if (wtChart) wtChart.destroy();
    wtChart = new Chart(ctx, {
        type: 'line',
        data: { datasets: [
            { label: 'Weight', data: makeDataset(logs, 'weight'), borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.1)', tension: 0.4 }
        ] },
        options: getCommonOptions()
    });
}

// ── Submit Log ────────────────────────────────────────────
async function submitLog() {
    const sys = document.getElementById('sys').value;
    const dia = document.getElementById('dia').value;
    const hrVal = document.getElementById('hr-input').value;
    const wtVal = document.getElementById('wt-input').value;

    // Validate: if one BP value provided, require both
    if ((sys && !dia) || (!sys && dia)) {
        showStatus('Please provide both systolic and diastolic values.', 'error');
        return;
    }

    const body = {};
    if (sys !== '') body.systolic = parseInt(sys);
    if (dia !== '') body.diastolic = parseInt(dia);
    if (hrVal !== '') body.heart_rate = parseInt(hrVal);
    if (wtVal !== '') body.weight = parseFloat(wtVal);
    const readingDateTime = document.getElementById('reading-datetime').value;
    body.emoji = selectedEmoji;

    // Include timestamp only if user provided one
    if (readingDateTime) {
        body.timestamp = readingDateTime;
    }

    // Ensure at least one measurement
    if (Object.keys(body).length <= 1) { // only emoji would remain
        showStatus('Please enter at least one measurement.', 'error');
        return;
    }

    try {
        const resp = await fetch('/api/v1/logs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-API-Key': apiKey },
            body: JSON.stringify(body)
        });

        if (!resp.ok) {
            let msg = `Server error ${resp.status}`;
            try { const e = await resp.json(); msg = e.error || msg; } catch {}
            showStatus(msg, 'error');
            return;
        }

        // Clear inputs and refresh charts
        document.getElementById('sys').value = '';
        document.getElementById('dia').value = '';
        document.getElementById('hr-input').value = '';
        document.getElementById('wt-input').value = '';
        setDefaultReadingDateTime();
        showStatus('✓ Log saved successfully!', 'success');
        renderCharts();

    } catch (err) {
        showStatus('Network error: ' + err.message, 'error');
    }
}

function showStatus(msg, type) {
    const el = document.getElementById('status');
    el.textContent = msg;
    el.className = type; // success or error
    if (type === 'success') setTimeout(() => { el.className = null; }, 3000);
}
