<?php
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center">WiFi Routers Dashboard</h1>

            <!-- Cron Control Panel -->
            <div class="card shadow mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-sync-alt mr-1"></i> Billing Worker</strong>
                    <div>
                        <label class="switch-sm mr-2" title="Auto-sync every 60s">
                            <input type="checkbox" id="autoSyncToggle" onchange="toggleAutoSync()">
                            <span class="slider-sm"></span>
                        </label>
                        <small class="text-muted">Auto-sync</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">Status</small><br>
                            <span id="cronStatus" class="font-weight-bold">Checking...</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Last Run</small><br>
                            <span id="cronLastRun" class="font-weight-bold">Never</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Expired Users</small><br>
                            <span id="cronExpired" class="font-weight-bold">-</span>
                        </div>
                        <div class="col-md-3 text-right">
                            <button class="btn btn-success btn-sm" id="runCronBtn" onclick="runCron()">
                                <i class="fas fa-play mr-1"></i> Run Now
                            </button>
                            <button class="btn btn-danger btn-sm" id="stopCronBtn" onclick="stopCron()" style="display:none">
                                <i class="fas fa-stop mr-1"></i> Stop
                            </button>
                        </div>
                    </div>
                    <div id="cronResults" class="mt-3" style="display:none">
                        <small class="text-muted">Last Run Details:</small>
                        <div id="cronResultsBody" class="bg-light p-2 rounded" style="max-height:200px;overflow:auto;font-size:0.85rem"></div>
                    </div>
                </div>
            </div>

            <!-- Routers List -->
            <div id="routersList" class="routers-grid">
                <div class="col-12 text-center text-muted">Loading routers...</div>
            </div>

            <!-- Devices Table (hidden initially) -->
            <div id="devicesSection" style="display:none;">
                <div class="mb-3">
                    <button class="btn btn-secondary" onclick="backToRouters()">← Back to Routers</button>
                </div>
                <h3 id="routerNameHeading" class="mb-3" data-router-id=""></h3>
                <div class="card shadow">
                    <div class="card-body p-0">
                        <table class="table table-bordered table-striped mb-0" id="devicesTable">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Hostname</th>
                                    <th>IP</th>
                                    <th>MAC</th>
                                    <th>Connection</th>
                                    <th>Plans</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="text-center text-muted">Select a router to view devices</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<style>
/* Cron switch */
.switch-sm { position: relative; display: inline-block; width: 36px; height: 20px; }
.switch-sm input { opacity: 0; width: 0; height: 0; }
.slider-sm {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc; transition: .3s; border-radius: 20px;
}
.slider-sm:before {
    position: absolute; content: ""; height: 14px; width: 14px;
    left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;
}
.switch-sm input:checked + .slider-sm { background-color: #28a745; }
.switch-sm input:checked + .slider-sm:before { transform: translateX(16px); }

/* Routers grid container */
.routers-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}

/* Professional router cards */
.router-card {
    background: linear-gradient(135deg, #ffffff, #4e73df);
    color: #1a1a1a;
    font-weight: 600;
    border-radius: 16px;
    padding: 25px 20px;
    text-align: center;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    height: 200px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.router-card h4 {
    margin-bottom: 8px;
    color: #224abe;
}
.router-card p {
    margin: 3px 0;
    font-size: 0.9rem;
}
.router-card .device-info {
    margin-top: 10px;
    font-size: 0.85rem;
    color: #555;
}
.router-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.2);
}

/* Status Dot */
.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}
.online { background:#1cc88a; }
.offline { background:#e74a3b; }

.plan-badge {
    display: inline-block;
    background: #4e73df;
    color: #fff;
    padding: 6px 12px;
    border-radius: 16px;
    margin: 3px 2px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
}
.plan-badge:hover {
    background: #224abe;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .routers-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .routers-grid { grid-template-columns: 1fr; }
}
</style>

<script>
const routersApi = '/api/control.php';
const loginApi   = '/auth/login.php';
const plansApi   = '/api/plans.php';

// Load routers with device counts
async function loadRouters() {
    const res = await fetch(routersApi);
    const json = await res.json();
    const container = document.getElementById('routersList');
    container.innerHTML = '';

    if (!json.success || !json.routers.length) {
        container.innerHTML = `<div class="col-12 text-center text-danger">No routers found</div>`;
        return;
    }

    for (const router of json.routers) {
        // Fetch devices for counts
        const devicesRes = await fetch(`${loginApi}?id=${router.id}`);
        const devicesJson = await devicesRes.json();

        const totalDevices = devicesJson.devices ? devicesJson.devices.length : 0;
        const onlineDevices = devicesJson.devices 
            ? devicesJson.devices.filter(d => d.online).length 
            : 0;

        const status = router.online
            ? `<span class="status-dot online"></span>Online`
            : `<span class="status-dot offline"></span>Offline`;

        const cardDiv = document.createElement('div');
        cardDiv.className = 'router-card';
        cardDiv.innerHTML = `
            <h4>${router.name}</h4>
            <p>${status}</p>
            <div class="device-info">
                Devices: ${totalDevices}<br>
                Online: ${onlineDevices}
            </div>
        `;
        cardDiv.onclick = () => showDevices(router.id, router.name);
        container.appendChild(cardDiv);
    }
}

// Show devices and plans
async function showDevices(routerId, routerName) {
    document.getElementById('routersList').style.display = 'none';
    const section = document.getElementById('devicesSection');
    section.style.display = 'block';
    const heading = document.getElementById('routerNameHeading');
    heading.textContent = routerName;
    heading.dataset.routerId = routerId; // store router ID for applyPlan

    const tbody = document.querySelector('#devicesTable tbody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-info">Loading devices...</td></tr>`;

    try {
        const res = await fetch(`${loginApi}?id=${routerId}`);
        const json = await res.json();

        if (!json.devices || !json.devices.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No devices connected</td></tr>`;
            return;
        }

        const plansRes = await fetch(`${plansApi}?router_id=${routerId}`);
        const plansJson = await plansRes.json();

        tbody.innerHTML = '';
        json.devices.forEach(dev => {
            let plansHTML = '';
            if (plansJson.success && plansJson.plans.length) {
                plansJson.plans.forEach(plan => {
                    let parts = [];
                    if (plan.days) parts.push(`${plan.days}d`);
                    if (plan.hours) parts.push(`${plan.hours}h`);
                    if (plan.minutes) parts.push(`${plan.minutes}m`);
                    let duration = parts.join(' ') || '0m';
                    plansHTML += `<span class="plan-badge" onclick="redirectToAddUser('${dev.mac}', ${plan.id})">
                        ${plan.name} (${duration})
                    </span>`;
                });
            } else {
                plansHTML = '<span class="text-muted">No plans</span>';
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${dev.hostname}</td>
                <td>${dev.ip}</td>
                <td>${dev.mac}</td>
                <td>${dev.type}</td>
                <td>${plansHTML}</td>
            `;
            tbody.appendChild(tr);
        });
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to fetch devices</td></tr>`;
        console.error(err);
    }
}

// Redirect to add_user.php with plan data in URL
function redirectToAddUser(mac, planId) {
    const routerId = document.getElementById('routerNameHeading').dataset.routerId;
    const url = `/add_user?router_id=${routerId}&paid_mac=${mac}&plan_id=${planId}`;
    window.location.href = url;
}

// Back to routers
function backToRouters() {
    document.getElementById('devicesSection').style.display = 'none';
    document.getElementById('routersList').style.display = 'grid';
}

// Initial load
loadRouters();

// ========================
// CRON CONTROL
// ========================
const cronApi = '/api/cron.php';
let autoSyncInterval = null;

async function checkCronStatus() {
    try {
        const res = await fetch(`${cronApi}?action=status`);
        const json = await res.json();
        if (!json.success) return;

        const s = json.status;
        const statusEl = document.getElementById('cronStatus');
        const lastRunEl = document.getElementById('cronLastRun');
        const expiredEl = document.getElementById('cronExpired');
        const runBtn = document.getElementById('runCronBtn');
        const stopBtn = document.getElementById('stopCronBtn');
        const resultsEl = document.getElementById('cronResults');
        const resultsBody = document.getElementById('cronResultsBody');

        if (s.running) {
            statusEl.innerHTML = '<span class="text-warning">Running...</span>';
            runBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
        } else {
            statusEl.innerHTML = '<span class="text-success">Idle</span>';
            runBtn.style.display = 'inline-block';
            stopBtn.style.display = 'none';
        }

        if (s.last_run) {
            lastRunEl.textContent = s.last_run;
        }

        if (s.last_result) {
            const r = s.last_result;
            if (r.expired) {
                expiredEl.textContent = r.expired.count;
            }
            resultsEl.style.display = 'block';
            let html = '';
            if (r.expired) {
                html += `<div class="mb-1"><strong>Expired:</strong> ${r.expired.count} users</div>`;
            }
            if (r.routers && r.routers.length) {
                html += '<div><strong>Routers:</strong></div>';
                r.routers.forEach(rt => {
                    const icon = rt.status === 'synced' ? 'text-success' : (rt.status === 'offline' ? 'text-danger' : 'text-warning');
                    html += `<div class="ml-2">${rt.name || 'Router ' + rt.router_id}: <span class="${icon}">${rt.status}</span>`;
                    if (rt.devices !== undefined) html += ` (${rt.devices} devices, ${rt.throttled} throttled)`;
                    if (rt.error) html += ` - ${rt.error}`;
                    html += '</div>';
                });
            }
            resultsBody.innerHTML = html || '<span class="text-muted">No details</span>';
        }
    } catch (e) {
        console.error('Cron status error:', e);
    }
}

async function runCron() {
    const btn = document.getElementById('runCronBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Running...';

    try {
        const res = await fetch(`${cronApi}?action=run`);
        const json = await res.json();
        if (json.success) {
            checkCronStatus();
        } else {
            alert(json.error || 'Worker failed');
        }
    } catch (e) {
        alert('Error running worker');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play mr-1"></i> Run Now';
    }
}

async function stopCron() {
    try {
        await fetch(`${cronApi}?action=stop`);
        checkCronStatus();
    } catch (e) {
        console.error('Stop error:', e);
    }
}

function toggleAutoSync() {
    const toggle = document.getElementById('autoSyncToggle');
    if (toggle.checked) {
        runCron();
        autoSyncInterval = setInterval(() => {
            checkCronStatus();
            runCron();
        }, 60000);
    } else {
        if (autoSyncInterval) {
            clearInterval(autoSyncInterval);
            autoSyncInterval = null;
        }
    }
}

// Check status on load
checkCronStatus();
// Auto-refresh status every 10s
setInterval(checkCronStatus, 10000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
