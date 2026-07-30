// ── Config & State ────────────────────────────────────────
const API_KEY_STORAGE = 'vitalpulse_api_key';
let apiKey = localStorage.getItem(API_KEY_STORAGE);

const EMOJIS = ['🤩', '😀', '🙂', '😐', '☹️', '😩', '🥵', '😵‍💫', '🤢', '🥶'];
let selectedEmoji = '😐';
let filterEmojis = new Set(); // emoji(s) currently selected for filtering
let bpChart, hrChart, wtChart;

const commonOptions = {
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
    } }
};

// ── Initialization ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initMoodSelector();
    initFilterEmoji();
    setDefaultDates();
    setDefaultReadingDateTime();
    requestApiKeyIfMissing().then(() => renderCharts());
});

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
    const sel = document.getElementById('filter-emoji');
    EMOJIS.forEach(e => {
        const span = document.createElement('span');
        span.className = 'emoji-filter-option';
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
async function fetchLogs() {
    const from = document.getElementById('filter-from').value || null;
    const to = document.getElementById('filter-to').value || null;
    const emojiFilter = document.getElementById('filter-emoji').value || null;

    let url = '/api/v1/logs?';
    if (from) url += `from=${encodeURIComponent(from)}&`;
    if (to) url += `to=${encodeURIComponent(to)}&`;
    if (emojiFilter) url += `emoji=${encodeURIComponent(emojiFilter)}&`;

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
            if (resp.status === 401) {
                localStorage.removeItem(API_KEY_STORAGE);
                apiKey = prompt('API key invalid. Enter correct API key:');
                if (!apiKey) return null;
                localStorage.setItem(API_KEY_STORAGE, apiKey);
                return fetchLogs(); // retry
            }
            throw new Error(`Server error ${resp.status}`);
        }
        return await resp.json();
    } catch (err) {
        console.error('Fetch failed:', err);
        alert('Could not load data from server: ' + err.message);
        return null;
    }
}

// ── Chart Rendering ───────────────────────────────────────
async function renderCharts() {
    const logs = await fetchLogs();
    if (!logs || !Array.isArray(logs)) return;

    // Sort ascending by timestamp for charts (oldest → newest)
    logs.sort((a, b) => a.timestamp.localeCompare(b.timestamp));

    updateStats(logs);
    renderBpChart(logs);
    renderHrChart(logs);
    renderWtChart(logs);
}

function updateStats(logs) {
    const n = logs.length;
    document.getElementById('total-count').textContent = n;

    if (n === 0) {
        document.getElementById('avg-sys').textContent = '-';
        document.getElementById('avg-dia').textContent = '-';
        document.getElementById('avg-hr').textContent = '-';
        document.getElementById('avg-wt').textContent = '-';
        return;
    }

    function avg(arr) {
        const vals = arr.filter(v => v !== null && v !== undefined);
        if (vals.length === 0) return '-';
        return (vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(1);
    }

    document.getElementById('avg-sys').textContent = avg(logs.map(l => l.systolic));
    document.getElementById('avg-dia').textContent = avg(logs.map(l => l.diastolic));
    document.getElementById('avg-hr').textContent = avg(logs.map(l => l.heart_rate));
    document.getElementById('avg-wt').textContent = avg(logs.map(l => l.weight));
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
        options: commonOptions
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
        options: commonOptions
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
        options: commonOptions
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
