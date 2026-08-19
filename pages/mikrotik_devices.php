<?php
ob_start();
$pageTitle = 'MikroTik Devices';
$activePage = 'mikrotik_devices';
include __DIR__ . '/../components/header.php';
$deviceId = $_GET['id'] ?? null;
?>
<style>
.mikrotik-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}
.mikrotik-card {
    background: var(--surface);
    border: 1px solid var(--surface-4);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    cursor: pointer;
    transition: all var(--transition);
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-1);
}
.mikrotik-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-3);
    border-color: var(--blue-300);
}
.mikrotik-card .device-icon {
    width: 48px; height: 48px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: #fff; margin-bottom: 1rem;
}
.mikrotik-card .device-icon.online { background: linear-gradient(135deg, var(--green), #20c997); }
.mikrotik-card .device-icon.offline { background: linear-gradient(135deg, var(--red), #e83e8c); }
.mikrotik-card .device-icon.pending { background: linear-gradient(135deg, var(--yellow), var(--orange)); }

.mikrotik-card .status-badge {
    position: absolute; top: 12px; right: 12px;
    padding: 3px 10px; border-radius: var(--radius-full); font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
}
.mikrotik-card .status-badge.online { background: #E6F4EA; color: #137333; }
.mikrotik-card .status-badge.offline { background: #FCE8E6; color: #C5221F; }
.mikrotik-card .status-badge.pending { background: #FEF7E0; color: #B45309; }

.empty-state { text-align:center; padding:4rem 2rem; color:var(--on-surface-med); }
</style>

<?php if ($deviceId): ?>
<!-- DEVICE DETAIL VIEW -->
<div class="page-header">
    <div class="page-header-left">
        <a href="/mikrotik_devices" class="back-link"><i class="fas fa-arrow-left"></i> Back to devices</a>
        <h1 class="page-title"><i class="fas fa-router"></i> <span id="deviceName">Loading...</span></h1>
    </div>
    <div class="page-header-actions" id="deviceStatusBadge"></div>
</div>

<div class="form-row">
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-wifi"></i> Wireless Configuration</span>
        </div>
        <div class="card-body">
            <p class="card-subtitle">Configure the WiFi network name (SSID) for this device.</p>
            <form id="wirelessForm">
                <input type="hidden" id="routerId" value="<?= htmlspecialchars($deviceId) ?>">
                <div class="form-group">
                    <label class="form-label" for="ssid">Network Name (SSID)</label>
                    <input type="text" class="form-control" id="ssid" placeholder="e.g. JasiriWiFi" maxlength="32" required>
                </div>
                <div id="wirelessMsg"></div>
                <button type="submit" class="btn btn-primary" id="applyWirelessBtn">
                    <i class="fas fa-check"></i> Apply Configuration
                </button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-info-circle"></i> Device Info</span>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table>
                    <tbody>
                        <tr><td class="td-label">Device ID</td><td id="infoDeviceId">—</td></tr>
                        <tr><td class="td-label">Location</td><td id="infoLocation">—</td></tr>
                        <tr><td class="td-label">IP Address</td><td id="infoIP">—</td></tr>
                        <tr><td class="td-label">WireGuard IP</td><td id="infoWG">—</td></tr>
                        <tr><td class="td-label">API Password</td><td id="infoApiPass">—</td></tr>
                        <tr><td class="td-label">Status</td><td id="infoStatus">—</td></tr>
                        <tr><td class="td-label">Last Provisioned</td><td id="infoLastProv">—</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-microchip"></i> Router Resources</span>
        <button class="btn btn-secondary btn-sm" onclick="loadResources()"><i class="fas fa-sync"></i> Refresh</button>
    </div>
    <div class="card-body">
        <div id="resourcesLoading" class="text-center py-3" style="display:none;">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
        <div id="resourcesContent" style="display:none;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
                <div class="resource-stat">
                    <div class="resource-label">CPU Usage</div>
                    <div class="resource-value" id="resCpu">—</div>
                    <div class="resource-bar"><div class="resource-bar-fill" id="resCpuBar"></div></div>
                </div>
                <div class="resource-stat">
                    <div class="resource-label">Memory</div>
                    <div class="resource-value" id="resMem">—</div>
                    <div class="resource-bar"><div class="resource-bar-fill" id="resMemBar"></div></div>
                </div>
                <div class="resource-stat">
                    <div class="resource-label">Disk</div>
                    <div class="resource-value" id="resDisk">—</div>
                    <div class="resource-bar"><div class="resource-bar-fill" id="resDiskBar"></div></div>
                </div>
                <div class="resource-stat">
                    <div class="resource-label">Uptime</div>
                    <div class="resource-value" id="resUptime">—</div>
                </div>
                <div class="resource-stat">
                    <div class="resource-label">RouterOS</div>
                    <div class="resource-value" id="resVersion">—</div>
                </div>
                <div class="resource-stat">
                    <div class="resource-label">Board</div>
                    <div class="resource-value" id="resBoard">—</div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <h6 style="margin-bottom:12px;color:var(--on-surface-med);"><i class="fas fa-network-wired"></i> Interfaces</h6>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Name</th><th>Type</th><th>RX Rate</th><th>TX Rate</th><th>RX Bytes</th><th>TX Bytes</th><th>Status</th></tr></thead>
                        <tbody id="interfacesTable"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="resourcesError" class="text-center text-danger py-3" style="display:none;">
            <i class="fas fa-exclamation-triangle"></i> <span id="resourcesErrorMsg">Failed to load</span>
        </div>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chart-line"></i> Live Resource Stream</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span id="streamDot" style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block;"></span>
            <small id="streamLabel" style="color:var(--on-surface-med);">Streaming</small>
        </div>
    </div>
    <div class="card-body">
        <div id="streamLoading" class="text-center py-3" style="display:none;">
            <i class="fas fa-spinner fa-spin"></i> Connecting...
        </div>
        <div id="streamContent" style="display:none;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
                <div class="stream-gauge">
                    <canvas id="gaugeCpu" width="120" height="120"></canvas>
                    <div class="gauge-label">CPU</div>
                    <div class="gauge-value" id="streamCpu">0%</div>
                </div>
                <div class="stream-gauge">
                    <canvas id="gaugeMem" width="120" height="120"></canvas>
                    <div class="gauge-label">Memory</div>
                    <div class="gauge-value" id="streamMem">0%</div>
                </div>
                <div class="stream-gauge">
                    <canvas id="gaugeDisk" width="120" height="120"></canvas>
                    <div class="gauge-label">Disk</div>
                    <div class="gauge-value" id="streamDisk">0%</div>
                </div>
                <div class="stream-gauge">
                    <canvas id="gaugeUsers" width="120" height="120"></canvas>
                    <div class="gauge-label">Active Users</div>
                    <div class="gauge-value" id="streamUsers">0</div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <canvas id="cpuHistory" height="100"></canvas>
            </div>
        </div>
        <div id="streamError" class="text-center text-danger py-3" style="display:none;">
            <i class="fas fa-exclamation-triangle"></i> <span id="streamErrorMsg">Connection lost</span>
        </div>
    </div>
</div>

<style>
.stream-gauge { display:flex; flex-direction:column; align-items:center; padding:12px; background:var(--surface-2); border-radius:var(--radius-md); }
.gauge-label { font-size:0.75rem; color:var(--on-surface-med); text-transform:uppercase; letter-spacing:0.5px; margin-top:4px; }
.gauge-value { font-size:1.1rem; font-weight:700; color:var(--on-surface); }
</style>

<style>
.resource-stat { background:var(--surface-2); border-radius:var(--radius-md); padding:16px; }
.resource-label { font-size:0.75rem; color:var(--on-surface-med); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
.resource-value { font-size:1.25rem; font-weight:600; color:var(--on-surface); }
.resource-bar { height:6px; background:var(--surface-4); border-radius:3px; margin-top:8px; overflow:hidden; }
.resource-bar-fill { height:100%; border-radius:3px; transition:width 0.5s ease; }
</style>

<script>
let deviceData = null;

document.getElementById('wirelessForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!deviceData) return;

    const btn = document.getElementById('applyWirelessBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
    document.getElementById('wirelessMsg').innerHTML = '';

    try {
        const res = await fetch('/api/wireless.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'configure',
                router_id: deviceData.id,
                ssid: document.getElementById('ssid').value.trim()
            })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed');
        document.getElementById('ssid').value = data.ssid || document.getElementById('ssid').value.trim();
        document.getElementById('wirelessMsg').innerHTML = '<div class="alert alert-success py-2"><i class="fas fa-check"></i> ' + data.message + '</div>';
    } catch (err) {
        document.getElementById('wirelessMsg').innerHTML = '<div class="alert alert-danger py-2"><i class="fas fa-exclamation-triangle"></i> ' + err.message + '</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Apply Configuration';
    }
});

async function loadWirelessSsid() {
    if (!deviceData) return;
    try {
        const res = await fetch(`/api/wireless.php?action=get&router_id=${deviceData.id}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed');
        document.getElementById('ssid').value = data.ssid || deviceData.ssid || 'JasiriWiFi';
    } catch (e) {
        document.getElementById('ssid').value = deviceData.ssid || 'JasiriWiFi';
    }
}

async function loadDevice() {
    try {
        const res = await fetch('/api/mikrotik.php?action=list');
        const data = await res.json();
        deviceData = (data.routers || []).find(r => r.id == <?= json_encode($deviceId) ?>);
        if (!deviceData) {
            document.querySelector('.page-container').innerHTML = '<div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle"></i> Device not found. <a href="/mikrotik_devices">Go back</a></div>';
            return;
        }

        document.getElementById('deviceName').textContent = deviceData.name;
        document.getElementById('routerId').value = deviceData.id;
        document.getElementById('ssid').value = deviceData.ssid || 'JasiriWiFi';
        loadWirelessSsid();

        const status = deviceData.provisioning_status || 'offline';
        const statusClass = status === 'online' ? 'active' : status === 'provisioning' ? 'pending' : 'inactive';
        const statusLabel = status === 'provisioning' ? 'provisioning' : status;
        document.getElementById('deviceStatusBadge').innerHTML = '<span class="chip ' + statusClass + '"><span class="chip-dot"></span>' + statusLabel.toUpperCase() + '</span>';

        document.getElementById('infoDeviceId').textContent = (deviceData.device_id || '').substring(0, 16) + '...';
        document.getElementById('infoLocation').textContent = deviceData.location || '—';
        document.getElementById('infoIP').textContent = deviceData.ip || '—';
        document.getElementById('infoWG').textContent = deviceData.wireguard_ip || '—';
        document.getElementById('infoApiPass').textContent = deviceData.password ? '••••••••' : '—';
        document.getElementById('infoStatus').textContent = status;
        document.getElementById('infoLastProv').textContent = deviceData.last_provisioned_at || '—';
    } catch (err) {
        document.getElementById('deviceName').textContent = 'Error loading device';
    }
}

loadDevice();

function fmtBytes(b) {
    b = parseInt(b || 0);
    if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB';
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024) return (b / 1024).toFixed(1) + ' KB';
    return b + ' B';
}

function fmtRate(r) {
    if (!r || r === '0') return '—';
    return r;
}

function setBarColor(bar, pct) {
    if (pct < 50) bar.style.background = 'var(--green)';
    else if (pct < 80) bar.style.background = 'var(--yellow)';
    else bar.style.background = 'var(--red)';
}

function drawGauge(canvasId, pct, color) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    const cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 8;

    ctx.clearRect(0, 0, w, h);

    ctx.beginPath();
    ctx.arc(cx, cy, r, 0.75 * Math.PI, 2.25 * Math.PI);
    ctx.strokeStyle = 'rgba(255,255,255,0.1)';
    ctx.lineWidth = 10;
    ctx.lineCap = 'round';
    ctx.stroke();

    const range = 1.5 * Math.PI;
    const end = 0.75 * Math.PI + (pct / 100) * range;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0.75 * Math.PI, end);
    ctx.strokeStyle = color;
    ctx.lineWidth = 10;
    ctx.lineCap = 'round';
    ctx.stroke();
}

const cpuHistoryData = [];
const MAX_HISTORY = 60;

function drawCpuHistory() {
    const canvas = document.getElementById('cpuHistory');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.parentElement.clientWidth;
    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    if (cpuHistoryData.length < 2) return;

    const step = w / (MAX_HISTORY - 1);

    ctx.beginPath();
    ctx.strokeStyle = 'rgba(255,255,255,0.1)';
    ctx.lineWidth = 1;
    for (let y = 0; y <= 100; y += 25) {
        const py = h - (y / 100) * h;
        ctx.moveTo(0, py);
        ctx.lineTo(w, py);
    }
    ctx.stroke();

    ctx.beginPath();
    cpuHistoryData.forEach((val, i) => {
        const x = i * step;
        const y = h - (val / 100) * h;
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    });
    ctx.strokeStyle = cpuHistoryData[cpuHistoryData.length - 1] > 80 ? '#ef4444' : cpuHistoryData[cpuHistoryData.length - 1] > 50 ? '#eab308' : '#22c55e';
    ctx.lineWidth = 2;
    ctx.stroke();

    const gradient = ctx.createLinearGradient(0, 0, 0, h);
    gradient.addColorStop(0, ctx.strokeStyle + '33');
    gradient.addColorStop(1, ctx.strokeStyle + '00');
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.fillStyle = gradient;
    ctx.fill();

    ctx.fillStyle = 'rgba(255,255,255,0.5)';
    ctx.font = '11px sans-serif';
    ctx.fillText('CPU % (last ' + MAX_HISTORY + ' samples)', 4, 14);
}

function colorForPct(pct) {
    if (pct < 50) return '#22c55e';
    if (pct < 80) return '#eab308';
    return '#ef4444';
}

async function loadResources() {
    if (!deviceData) return;
    document.getElementById('resourcesLoading').style.display = 'block';
    document.getElementById('resourcesContent').style.display = 'none';
    document.getElementById('resourcesError').style.display = 'none';
    document.getElementById('streamLoading').style.display = 'block';
    document.getElementById('streamContent').style.display = 'none';
    document.getElementById('streamError').style.display = 'none';

    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        const res = await fetch(`/api/mikrotik.php?action=resources&router_id=${deviceData.id}`, { signal: controller.signal });
        clearTimeout(timeout);
        const data = await res.json();
        document.getElementById('resourcesLoading').style.display = 'none';
        document.getElementById('streamLoading').style.display = 'none';

        if (!res.ok || data.error) {
            document.getElementById('resourcesError').style.display = 'block';
            document.getElementById('resourcesErrorMsg').textContent = data.error || 'API connection failed';
            document.getElementById('streamError').style.display = 'block';
            document.getElementById('streamErrorMsg').textContent = data.error || 'API connection failed';
            return;
        }

        updateResourceCards(data);
        updateStreamGauges(data);

        document.getElementById('resourcesContent').style.display = 'block';
        document.getElementById('streamContent').style.display = 'block';
    } catch (e) {
        document.getElementById('resourcesLoading').style.display = 'none';
        document.getElementById('streamLoading').style.display = 'none';
        const msg = e.name === 'AbortError' ? 'Router not reachable (timeout)' : e.message;
        document.getElementById('resourcesError').style.display = 'block';
        document.getElementById('resourcesErrorMsg').textContent = msg;
        document.getElementById('streamError').style.display = 'block';
        document.getElementById('streamErrorMsg').textContent = msg;
    }
}

function updateResourceCards(data) {
    const r = data.resources || {};
    const totalMem = parseInt(r['total-memory'] || 0);
    const freeMem = parseInt(r['free-memory'] || 0);
    const usedMem = totalMem - freeMem;
    const memPct = totalMem > 0 ? Math.round((usedMem / totalMem) * 100) : 0;

    const totalDisk = parseInt(r['total disk-space'] || 0);
    const freeDisk = parseInt(r['free disk-space'] || 0);
    const usedDisk = totalDisk - freeDisk;
    const diskPct = totalDisk > 0 ? Math.round((usedDisk / totalDisk) * 100) : 0;

    const cpuLoad = parseInt(r['cpu-load'] || 0);

    document.getElementById('resCpu').textContent = cpuLoad + '%';
    const cpuBar = document.getElementById('resCpuBar');
    cpuBar.style.width = cpuLoad + '%';
    setBarColor(cpuBar, cpuLoad);

    document.getElementById('resMem').textContent = fmtBytes(usedMem) + ' / ' + fmtBytes(totalMem);
    const memBar = document.getElementById('resMemBar');
    memBar.style.width = memPct + '%';
    setBarColor(memBar, memPct);

    document.getElementById('resDisk').textContent = fmtBytes(usedDisk) + ' / ' + fmtBytes(totalDisk);
    const diskBar = document.getElementById('resDiskBar');
    diskBar.style.width = diskPct + '%';
    setBarColor(diskBar, diskPct);

    document.getElementById('resUptime').textContent = r.uptime || '—';
    document.getElementById('resVersion').textContent = (r['version'] || '—') + (r['firmware'] ? ' (' + r['firmware'] + ')' : '');
    document.getElementById('resBoard').textContent = r['board-name'] || r['platform'] || '—';

    const ifaces = data.interfaces || [];
    const exclude = ['jsbridge', 'l2tp0', 'pptp-client', 'ovpn-client', 'sstp-client', 'lte1'];
    const filtered = ifaces.filter(iface => {
        const name = (iface.name || '').toLowerCase();
        return !exclude.some(ex => name.includes(ex)) && !name.startsWith('loopback');
    });

    document.getElementById('interfacesTable').innerHTML = filtered.map(iface => {
        const status = iface['running'] === 'true' || iface['disabled'] === 'false';
        const statusHtml = status
            ? '<span class="chip active"><span class="chip-dot"></span>UP</span>'
            : '<span class="chip inactive"><span class="chip-dot"></span>DOWN</span>';
        return `<tr>
            <td><strong>${escapeHtml(iface.name || '—')}</strong></td>
            <td>${escapeHtml(iface.type || '—')}</td>
            <td>${fmtRate(iface['rx-rate'] || iface['rate'])}</td>
            <td>${fmtRate(iface['tx-rate'] || iface['rate'])}</td>
            <td>${fmtBytes(iface['rx-byte'] || 0)}</td>
            <td>${fmtBytes(iface['tx-byte'] || 0)}</td>
            <td>${statusHtml}</td>
        </tr>`;
    }).join('');
}

function updateStreamGauges(data) {
    const r = data.resources || {};
    const totalMem = parseInt(r['total-memory'] || 0);
    const freeMem = parseInt(r['free-memory'] || 0);
    const usedMem = totalMem - freeMem;
    const memPct = totalMem > 0 ? Math.round((usedMem / totalMem) * 100) : 0;

    const totalDisk = parseInt(r['total disk-space'] || 0);
    const freeDisk = parseInt(r['free disk-space'] || 0);
    const usedDisk = totalDisk - freeDisk;
    const diskPct = totalDisk > 0 ? Math.round((usedDisk / totalDisk) * 100) : 0;

    const cpuLoad = parseInt(r['cpu-load'] || 0);
    const userCount = (data.active_users || []).length;

    cpuHistoryData.push(cpuLoad);
    if (cpuHistoryData.length > MAX_HISTORY) cpuHistoryData.shift();

    drawGauge('gaugeCpu', cpuLoad, colorForPct(cpuLoad));
    drawGauge('gaugeMem', memPct, colorForPct(memPct));
    drawGauge('gaugeDisk', diskPct, colorForPct(diskPct));
    drawGauge('gaugeUsers', Math.min(userCount * 5, 100), userCount > 0 ? '#3b82f6' : '#6b7280');

    document.getElementById('streamCpu').textContent = cpuLoad + '%';
    document.getElementById('streamMem').textContent = memPct + '%';
    document.getElementById('streamDisk').textContent = diskPct + '%';
    document.getElementById('streamUsers').textContent = userCount;

    document.getElementById('streamDot').style.background = 'var(--green)';
    document.getElementById('streamLabel').textContent = 'Live — ' + new Date().toLocaleTimeString();

    drawCpuHistory();
}

let streamInterval = null;

function startStream() {
    if (streamInterval) clearInterval(streamInterval);
    streamInterval = setInterval(loadResources, 5000);
}

loadResources().then(() => startStream());

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

<?php else: ?>
<!-- DEVICE LIST VIEW -->
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-server"></i> MikroTik Devices</h1>
        <p class="page-subtitle">Manage your connected MikroTik routers.</p>
    </div>
    <div class="page-header-actions">
        <a href="connect_mikrotik" class="btn btn-primary"><i class="fas fa-plus"></i> Add Device</a>
    </div>
</div>

<div id="devicesGrid" class="mikrotik-grid"></div>

<div id="emptyState" class="empty-state" style="display:none;">
    <div class="empty-state-icon"><i class="fas fa-router"></i></div>
    <h3>No MikroTik Devices</h3>
    <p>Connect your first MikroTik router to get started.</p>
    <a href="connect_mikrotik" class="btn btn-primary" style="margin-top:16px;"><i class="fas fa-plug"></i> Connect Device</a>
</div>

<div id="loading" class="text-center py-5">
    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
    <p class="text-muted mt-2">Loading devices...</p>
</div>

<script>
async function loadDevices() {
    try {
        const res = await fetch('/api/mikrotik.php?action=list');
        const data = await res.json();

        document.getElementById('loading').style.display = 'none';

        if (!data.routers || data.routers.length === 0) {
            document.getElementById('emptyState').style.display = 'block';
            return;
        }

        const grid = document.getElementById('devicesGrid');
        grid.innerHTML = data.routers.map(r => `
            <div class="mikrotik-card" onclick="viewDevice(${r.id})">
                <button class="btn btn-sm btn-outline-danger" style="position:absolute;top:10px;left:10px;z-index:2;" onclick="event.stopPropagation();deleteDevice(${r.id},'${escapeHtml(r.name)}')"><i class="fas fa-trash"></i></button>
                <span class="status-badge ${r.provisioning_status || 'offline'}">${r.provisioning_status || 'offline'}</span>
                <div class="device-icon ${r.provisioning_status || 'offline'}">
                    <i class="fas fa-router"></i>
                </div>
                <h5 class="mb-1">${escapeHtml(r.name)}</h5>
                <p class="text-muted small mb-2">${escapeHtml(r.location || 'No location set')}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted"><i class="fas fa-network-wired"></i> ${r.wireguard_ip || r.ip || '—'}</small>
                    <small class="text-muted"><i class="fas fa-fingerprint"></i> ${(r.device_id || '').substring(0, 8)}...</small>
                </div>
                ${r.last_provisioned_at ? `<div class="mt-2"><small class="text-success"><i class="fas fa-check-circle"></i> Provisioned ${r.last_provisioned_at}</small></div>` : ''}
            </div>
        `).join('');

    } catch (err) {
        document.getElementById('loading').innerHTML = '<div class="alert alert-danger">Failed to load devices</div>';
    }
}

function viewDevice(id) {
    window.location.href = `/mikrotik_devices?id=${id}`;
}

async function deleteDevice(id, name) {
    if (!confirm(`Delete device "${name}"? This cannot be undone.`)) return;
    try {
        const res = await fetch('/api/mikrotik.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', router_id: id })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Delete failed');
        loadDevices();
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadDevices();
setInterval(loadDevices, 30000);
</script>
<?php endif; ?>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
