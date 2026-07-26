<?php
ob_start();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
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

</div>
</section>
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
                <button class="btn btn-sm btn-outline-danger" style="position:absolute;top:10px;right:50px;z-index:2;" onclick="event.stopPropagation();deleteDevice(${r.id},'${escapeHtml(r.name)}')"><i class="fas fa-trash"></i></button>
                <span class="status-badge ${r.provisioning_status || 'offline'}">${r.provisioning_status || 'offline'}</span>
                <div class="device-icon ${r.provisioning_status || 'offline'}">
                    <i class="fas fa-router"></i>
                </div>
                <h5 class="mb-1">${escapeHtml(r.name)}</h5>
                <p class="text-muted small mb-2">${escapeHtml(r.location || 'No location set')}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted"><i class="fas fa-network-wired"></i> ${r.wireguard_ip || '—'}</small>
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

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
