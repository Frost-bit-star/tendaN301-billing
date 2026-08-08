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
        <span class="card-title"><i class="fas fa-chart-area"></i> Bandwidth Usage</span>
        <button class="btn btn-secondary btn-sm" onclick="loadBandwidth()"><i class="fas fa-sync"></i> Refresh</button>
    </div>
    <div class="card-body">
        <div id="bandwidthLoading" class="text-center py-3">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
        <div id="bandwidthContent" style="display:none;">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>User</th><th>IP</th><th>Bytes In</th><th>Bytes Out</th><th>Uptime</th></tr></thead>
                    <tbody id="bandwidthTable"></tbody>
                </table>
            </div>
        </div>
        <div id="bandwidthEmpty" class="text-center text-muted py-3" style="display:none;">
            <i class="fas fa-wifi"></i> No active users
        </div>
        <div id="bandwidthError" class="text-center text-danger py-3" style="display:none;">
            <i class="fas fa-exclamation-triangle"></i> <span id="bandwidthErrorMsg">Failed to load</span>
        </div>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header" style="cursor:pointer" onclick="toggleTerminal()">
        <span class="card-title"><i class="fas fa-terminal"></i> SSH Terminal</span>
        <div>
            <button class="btn btn-primary btn-sm" id="openTermBtn" onclick="event.stopPropagation();startTerminal()"><i class="fas fa-play"></i> Open Terminal</button>
            <button class="btn btn-danger btn-sm" id="closeTermBtn" onclick="event.stopPropagation();stopTerminal()" style="display:none"><i class="fas fa-stop"></i> Close</button>
        </div>
    </div>
    <div class="card-body p-0" id="terminalContainer" style="display:none;height:450px;background:#1a1a2e">
        <iframe id="terminalFrame" style="width:100%;height:100%;border:none;display:none"></iframe>
        <div id="terminalLoading" class="d-flex align-items-center justify-content-center h-100 text-white">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                <p>Connecting to router SSH...</p>
            </div>
        </div>
        <div id="terminalError" class="d-flex align-items-center justify-content-center h-100 text-danger" style="display:none">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                <p id="terminalErrorMsg">Connection failed</p>
            </div>
        </div>
    </div>
</div>

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

async function loadBandwidth() {
    if (!deviceData) return;
    document.getElementById('bandwidthLoading').style.display = 'block';
    document.getElementById('bandwidthContent').style.display = 'none';
    document.getElementById('bandwidthEmpty').style.display = 'none';
    document.getElementById('bandwidthError').style.display = 'none';

    try {
        const res = await fetch(`/api/mikrotik.php?action=bandwidth&router_id=${deviceData.id}`);
        const data = await res.json();
        document.getElementById('bandwidthLoading').style.display = 'none';

        if (!res.ok || data.error) {
            document.getElementById('bandwidthError').style.display = 'block';
            document.getElementById('bandwidthErrorMsg').textContent = data.error || 'API connection failed';
            return;
        }

        const users = data.active_users || [];
        if (users.length === 0) {
            document.getElementById('bandwidthEmpty').style.display = 'block';
            return;
        }

        document.getElementById('bandwidthContent').style.display = 'block';
        document.getElementById('bandwidthTable').innerHTML = users.map(u => `
            <tr>
                <td>${escapeHtml(u['user'] || u['name'] || '—')}</td>
                <td>${escapeHtml(u['address'] || '—')}</td>
                <td>${fmtBytes(u['bytes-in'] || 0)}</td>
                <td>${fmtBytes(u['bytes-out'] || 0)}</td>
                <td>${escapeHtml(u['uptime'] || '—')}</td>
            </tr>
        `).join('');
    } catch (e) {
        document.getElementById('bandwidthLoading').style.display = 'none';
        document.getElementById('bandwidthError').style.display = 'block';
        document.getElementById('bandwidthErrorMsg').textContent = e.message;
    }
}

loadBandwidth();
setInterval(loadBandwidth, 30000);

function toggleTerminal() {
    const c = document.getElementById('terminalContainer');
    c.style.display = c.style.display === 'none' ? 'block' : 'none';
}

async function startTerminal() {
    const btn = document.getElementById('openTermBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
    document.getElementById('terminalError').style.display = 'none';
    document.getElementById('terminalLoading').style.display = 'flex';
    document.getElementById('terminalFrame').style.display = 'none';
    document.getElementById('terminalContainer').style.display = 'block';

    try {
        const res = await fetch('/api/ssh_terminal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'start', router_id: deviceData.id })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed');

        const frame = document.getElementById('terminalFrame');
        frame.onload = function() {
            document.getElementById('terminalLoading').style.display = 'none';
            this.style.display = 'block';
        };
        frame.src = 'http://' + window.location.hostname + ':' + data.port + '/';

        btn.style.display = 'none';
        document.getElementById('closeTermBtn').style.display = 'inline-block';

        setTimeout(function() {
            if (document.getElementById('terminalLoading').style.display !== 'none') {
                document.getElementById('terminalLoading').style.display = 'none';
                document.getElementById('terminalError').style.display = 'flex';
                document.getElementById('terminalErrorMsg').textContent = 'Connection timed out. Router may be offline.';
            }
        }, 10000);
    } catch (err) {
        document.getElementById('terminalLoading').style.display = 'none';
        document.getElementById('terminalError').style.display = 'flex';
        document.getElementById('terminalErrorMsg').textContent = err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Open Terminal';
    }
}

async function stopTerminal() {
    try {
        await fetch('/api/ssh_terminal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'stop', router_id: deviceData.id })
        });
    } catch (e) {}
    document.getElementById('terminalFrame').style.display = 'none';
    document.getElementById('terminalFrame').src = '';
    document.getElementById('terminalLoading').style.display = 'flex';
    document.getElementById('closeTermBtn').style.display = 'none';
    document.getElementById('openTermBtn').style.display = 'inline-block';
}

window.addEventListener('beforeunload', function() { stopTerminal(); });
</script>

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
