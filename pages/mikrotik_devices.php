<?php
ob_start();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
$deviceId = $_GET['id'] ?? null;
?>
<style>
.mikrotik-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}
.mikrotik-card {
    background: linear-gradient(135deg, #ffffff, #fff5f5);
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.mikrotik-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.mikrotik-card .device-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: #fff; margin-bottom: 1rem;
}
.mikrotik-card .device-icon.online { background: linear-gradient(135deg, #28a745, #20c997); }
.mikrotik-card .device-icon.offline { background: linear-gradient(135deg, #dc3545, #e83e8c); }
.mikrotik-card .device-icon.pending { background: linear-gradient(135deg, #ffc107, #fd7e14); }

.mikrotik-card .status-badge {
    position: absolute; top: 12px; right: 12px;
    padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
}
.mikrotik-card .status-badge.online { background: #d4edda; color: #155724; }
.mikrotik-card .status-badge.offline { background: #f8d7da; color: #721c24; }
.mikrotik-card .status-badge.pending { background: #fff3cd; color: #856404; }

.empty-state {
    text-align: center; padding: 4rem 2rem; color: #6c757d;
}
.empty-state i { font-size: 4rem; margin-bottom: 1rem; color: #dee2e6; }
</style>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">

<?php if ($deviceId): ?>
<!-- DEVICE DETAIL VIEW -->
<div class="d-flex justify-content-between align-items-center mt-4 mb-4">
    <div>
        <a href="/mikrotik_devices" class="text-muted"><i class="fas fa-arrow-left"></i> Back to devices</a>
        <h2 class="mb-0 mt-1"><i class="fas fa-router text-orange"></i> <span id="deviceName">Loading...</span></h2>
    </div>
    <div id="deviceStatusBadge"></div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-wifi"></i> Wireless Configuration</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Configure the WiFi network name and security for this device.</p>
                <form id="wirelessForm">
                    <input type="hidden" id="routerId" value="<?= htmlspecialchars($deviceId) ?>">
                    <div class="form-group">
                        <label for="ssid">Network Name (SSID)</label>
                        <input type="text" class="form-control" id="ssid" placeholder="e.g. JasiriWiFi" maxlength="32" required>
                    </div>
                    <div class="form-group">
                        <label for="security">Security</label>
                        <select class="form-control" id="security">
                            <option value="open">Open (No Password)</option>
                            <option value="wpa2">WPA2 (Password Protected)</option>
                        </select>
                    </div>
                    <div id="passwordField" class="form-group" style="display:none;">
                        <label for="wifiPassword">WiFi Password</label>
                        <input type="text" class="form-control" id="wifiPassword" placeholder="Min 8 characters" maxlength="64">
                    </div>
                    <div id="wirelessMsg"></div>
                    <button type="submit" class="btn btn-primary" id="applyWirelessBtn">
                        <i class="fas fa-check"></i> Apply Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Device Info</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><td class="text-muted">Device ID</td><td id="infoDeviceId">—</td></tr>
                    <tr><td class="text-muted">Location</td><td id="infoLocation">—</td></tr>
                    <tr><td class="text-muted">IP Address</td><td id="infoIP">—</td></tr>
                    <tr><td class="text-muted">WireGuard IP</td><td id="infoWG">—</td></tr>
                    <tr><td class="text-muted">API Password</td><td id="infoApiPass">—</td></tr>
                    <tr><td class="text-muted">Status</td><td id="infoStatus">—</td></tr>
                    <tr><td class="text-muted">Last Provisioned</td><td id="infoLastProv">—</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-area"></i> Bandwidth Usage</h5>
                <button class="btn btn-sm btn-light" onclick="loadBandwidth()"><i class="fas fa-sync"></i> Refresh</button>
            </div>
            <div class="card-body">
                <div id="bandwidthLoading" class="text-center py-3">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
                <div id="bandwidthContent" style="display:none;">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>User</th><th>IP</th><th>Bytes In</th><th>Bytes Out</th><th>Uptime</th></tr></thead>
                        <tbody id="bandwidthTable"></tbody>
                    </table>
                </div>
                <div id="bandwidthEmpty" class="text-center text-muted py-3" style="display:none;">
                    <i class="fas fa-wifi"></i> No active users
                </div>
                <div id="bandwidthError" class="text-center text-danger py-3" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i> <span id="bandwidthErrorMsg">Failed to load</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" style="cursor:pointer" onclick="toggleTerminal()">
                <h5 class="mb-0"><i class="fas fa-terminal"></i> SSH Terminal</h5>
                <div>
                    <button class="btn btn-sm btn-success" id="openTermBtn" onclick="event.stopPropagation();startTerminal()"><i class="fas fa-play"></i> Open Terminal</button>
                    <button class="btn btn-sm btn-danger" id="closeTermBtn" onclick="event.stopPropagation();stopTerminal()" style="display:none"><i class="fas fa-stop"></i> Close</button>
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
    </div>
</div>

<script>
let deviceData = null;

document.getElementById('security').addEventListener('change', function() {
    document.getElementById('passwordField').style.display = this.value === 'wpa2' ? 'block' : 'none';
});

document.getElementById('wirelessForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!deviceData) return;

    const btn = document.getElementById('applyWirelessBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
    document.getElementById('wirelessMsg').innerHTML = '';

    try {
        const res = await fetch('/api/mikrotik.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'configure_wireless',
                router_id: deviceData.id,
                ssid: document.getElementById('ssid').value.trim(),
                security: document.getElementById('security').value
            })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Failed');
        document.getElementById('wirelessMsg').innerHTML = '<div class="alert alert-success py-2"><i class="fas fa-check"></i> ' + data.message + '</div>';
    } catch (err) {
        document.getElementById('wirelessMsg').innerHTML = '<div class="alert alert-danger py-2">' + err.message + '</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Apply Configuration';
    }
});

async function loadDevice() {
    try {
        const res = await fetch('/api/mikrotik.php?action=list');
        const data = await res.json();
        deviceData = (data.routers || []).find(r => r.id == <?= json_encode($deviceId) ?>);
        if (!deviceData) {
            document.querySelector('.container-fluid').innerHTML = '<div class="alert alert-danger mt-4">Device not found. <a href="/mikrotik_devices">Go back</a></div>';
            return;
        }

        document.getElementById('deviceName').textContent = deviceData.name;
        document.getElementById('routerId').value = deviceData.id;
        document.getElementById('ssid').value = 'JasiriWiFi';

        const status = deviceData.provisioning_status || 'offline';
        document.getElementById('deviceStatusBadge').innerHTML = '<span class="badge badge-' + (status === 'online' ? 'success' : status === 'provisioning' ? 'warning' : 'danger') + '" style="font-size:0.9rem;padding:8px 16px;">' + status.toUpperCase() + '</span>';

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
<div class="d-flex justify-content-between align-items-center mt-4 mb-4">
    <h2 class="mb-0"><i class="fas fa-server text-orange"></i> MikroTik Devices</h2>
    <a href="connect_mikrotik" class="btn btn-primary"><i class="fas fa-plus"></i> Add Device</a>
</div>

<div id="devicesGrid" class="mikrotik-grid"></div>

<div id="emptyState" class="empty-state" style="display:none;">
    <i class="fas fa-router"></i>
    <h4>No MikroTik Devices</h4>
    <p>Connect your first MikroTik router to get started.</p>
    <a href="connect_mikrotik" class="btn btn-primary"><i class="fas fa-plug"></i> Connect Device</a>
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

</div>
</section>
</div>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
