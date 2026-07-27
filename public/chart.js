// ── Config & State ────────────────────────────────────────
const API_KEY_STORAGE = 'vitalpulse_api_key';
let apiKey = localStorage.getItem(API_KEY_STORAGE);

const EMOJIS = ['🤩', '😀', '🙂', '😐', '☹️', '😩', '🥵', '😵‍💫', '🤢', '🥶'];
let selectedEmoji = '😐';
let bpChart, hrChart, wtChart;

// ── Initialization ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initMoodSelector();
    initFilterEmoji();
    setDefaultDates();
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
    const sel = document.getElementById('filter-emoji');
    EMOJIS.forEach(e => {
        const opt = document.createElement('option');
        opt.value = encodeURIComponent(e);
        opt.textContent = e + ' ' + e;
        sel.appendChild(opt);
    });
}

function setDefaultDates() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    document.getElementById('filter-to').valueAsDate = today;
    document.getElementById('filter-from').valueAsDate = thirtyDaysAgo;
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
    logs.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));

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

function makeLabels(logs) {
    return logs.map(l => new Date(l.timestamp).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }));
}

function renderBpChart(logs) {
    const ctx = document.getElementById('bp').getContext('2d');
    const labels = makeLabels(logs);
    const systolicData = logs.map(l => l.systolic);
    const diastolicData = logs.map(l => l.diastolic);

    if (bpChart) bpChart.destroy();
    bpChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Systolic', data: systolicData, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', tension: 0.4 },
                { label: 'Diastolic', data: diastolicData, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', tension: 0.4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index' },
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { x: { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 8, font: { size: 10 } } } }
        }
    });
}

function renderHrChart(logs) {
    const ctx = document.getElementById('hr').getContext('2d');
    const labels = makeLabels(logs);
    const data = logs.map(l => l.heart_rate);

    if (hrChart) hrChart.destroy();
    hrChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{ label: 'Heart Rate', data, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', tension: 0.4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index' },
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { x: { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 8, font: { size: 10 } } } }
        }
    });
}

function renderWtChart(logs) {
    const ctx = document.getElementById('wt').getContext('2d');
    const labels = makeLabels(logs);
    const data = logs.map(l => l.weight);

    if (wtChart) wtChart.destroy();
    wtChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{ label: 'Weight', data, borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,0.1)', tension: 0.4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index' },
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: { x: { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 8, font: { size: 10 } } } }
        }
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
    body.emoji = selectedEmoji;

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
    if (type === 'success') setTimeout(() => { el.style.display = 'none'; }, 3000);
}
