<?php
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center">WiFi Routers Dashboard</h1>

            <!-- Routers List -->
            <div id="routersList" class="routers-grid">
                <div class="col-12 text-center text-muted">Loading routers...</div>
            </div>

            <!-- Devices Table (hidden initially) -->
            <div id="devicesSection" style="display:none;">
                <!-- Buttons Container -->
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <button class="btn btn-secondary" onclick="backToRouters()">← Back to Routers</button>
                    <button class="btn btn-info" onclick="refreshDevicesTable()">⟳ Refresh</button>
                </div>

                <h3 id="routerNameHeading" class="mb-3" data-router-id=""></h3>

                <!-- Filter Mode Switch -->
                <div class="mb-3">
                    <div class="switch">
                        <input class="switch-check" id="filterSwitch" type="checkbox">
                        <label class="switch-label" for="filterSwitch">
                            Filter Mode
                            <span></span>
                        </label>
                    </div>
                </div>

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
/* --- Routers Cards --- */
.routers-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }
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
.router-card h4 { margin-bottom: 8px; color: #224abe; }
.router-card p { margin: 3px 0; font-size: 0.9rem; }
.router-card .device-info { margin-top: 10px; font-size: 0.85rem; color: #555; }
.router-card:hover { transform: translateY(-6px); box-shadow: 0 12px 28px rgba(0,0,0,0.2); }
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
.plan-badge:hover { background: #224abe; }

/* Whitelisted row style */
.whitelisted { background-color: #d4edda !important; }

/* Responsive */
@media (max-width: 1024px) { .routers-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .routers-grid { grid-template-columns: 1fr; } }

/* --- Filter Switch --- */
.switch {
  background-color: rgba(0, 0, 0, 0.2);
  border-radius: 30px;
  border: 4px solid rgba(58, 58, 58, 0.1);
  box-shadow: 0 0 6px rgba(0, 0, 0, 0.5) inset;
  height: 48px;
  margin: 2px;
  position: relative;
  width: 120px;
  display: inline-block;
  user-select: none;
}
.switch-check { position: absolute; visibility: hidden; user-select: none; }
.switch-label { cursor: pointer; display: block; height: 42px; text-indent: -9999px; width: 115px; user-select: none; }
.switch-label span {
  background: linear-gradient(#4f4f4f, #2b2b2b);
  border-radius: 30px;
  border: 1px solid #1a1a1a;
  box-shadow: 0 0 4px rgba(0,0,0,0.5), 0 1px 1px rgba(255,255,255,0.1) inset, 0 -2px 0 rgba(0,0,0,0.2) inset;
  display: block;
  height: 38px;
  left: 1px;
  position: absolute;
  top: 1px;
  width: 53px;
  transition: all 0.2s linear;
}
.switch-check:checked + .switch-label span { left: 59px; }
</style>

<script>
const routersApi = '/auth/v2.php';
const loginApi   = '/auth/login.php';
const plansApi   = '/api/plans.php';
const billingApi = '/api/billing.php'; // new API for storing users

async function loadRouters() {
    const container = document.getElementById('routersList');
    container.innerHTML = '<div class="col-12 text-center text-muted">Loading routers...</div>';

    try {
        const res = await fetch(routersApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_routers' })
        });
        const json = await res.json();
        container.innerHTML = '';

        if (!json.success || !json.results || !json.results.length) {
            container.innerHTML = `<div class="col-12 text-center text-danger">No routers found</div>`;
            return;
        }

        for (const router of json.results) {
            const devicesRes = await fetch(`${loginApi}?id=${router.router_id}`);
            const devicesJson = await devicesRes.json();
            const totalDevices = devicesJson.devices ? devicesJson.devices.length : 0;

            const filterMode = router.filter_mode ? router.filter_mode.toUpperCase() : 'N/A';

            const cardDiv = document.createElement('div');
            cardDiv.className = 'router-card';
            cardDiv.innerHTML = `
                <h4>Router ${router.router_id}</h4>
                <p>Filter Mode: ${filterMode}</p>
                <div class="device-info">Devices: ${totalDevices}</div>
            `;
            cardDiv.onclick = () => showDevices(router.router_id, `Router ${router.router_id}`, filterMode);
            container.appendChild(cardDiv);
        }

    } catch (err) {
        container.innerHTML = `<div class="col-12 text-center text-danger">Failed to fetch routers</div>`;
        console.error(err);
    }
}

async function showDevices(routerId, routerName, currentMode) {
    document.getElementById('routersList').style.display = 'none';
    const section = document.getElementById('devicesSection');
    section.style.display = 'block';
    const heading = document.getElementById('routerNameHeading');
    heading.textContent = routerName;
    heading.dataset.routerId = routerId;

    const switchEl = document.getElementById('filterSwitch');
    switchEl.checked = (currentMode === 'deny');

    switchEl.onchange = async () => {
        if (switchEl.checked) {
            try {
                const res = await fetch(routersApi, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle_mode', router_id: routerId, mode: 'deny' })
                });
                const json = await res.json();
                if (json.success && json.results[0].new_mode === 'deny') {
                    alert(`Router ${routerId} mode updated to DENY`);
                }
            } catch (err) {
                console.error(err);
                alert('Failed to update filter mode');
                switchEl.checked = false;
            }
        }
    };

    await loadDevicesTable(routerId);
}

async function loadDevicesTable(routerId) {
    const tbody = document.querySelector('#devicesTable tbody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-info">Loading devices...</td></tr>`;

    try {
        const res = await fetch(routersApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_users', router_id: routerId })
        });
        const json = await res.json();
        if (!json.success || !json.results || !json.results.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No devices connected</td></tr>`;
            return;
        }

        const routerData = json.results[0];
        const whitelist = routerData.whitelist || {};
        const onlineClients = routerData.online_clients || {};

        const plansRes = await fetch(`${plansApi}?router_id=${routerId}`);
        const plansJson = await plansRes.json();
        const plans = plansJson.success ? plansJson.plans : [];

        tbody.innerHTML = '';
        Object.keys(onlineClients).forEach(mac => {
            const dev = onlineClients[mac];
            const tr = document.createElement('tr');
            if (whitelist[mac.toUpperCase()]) tr.classList.add('whitelisted');

            let plansHTML = '';
            plans.forEach(plan => {
                let parts = [];
                if (plan.days) parts.push(`${plan.days}d`);
                if (plan.hours) parts.push(`${plan.hours}h`);
                if (plan.minutes) parts.push(`${plan.minutes}m`);
                let duration = parts.join(' ') || '0m';
                plansHTML += `<span class="plan-badge" onclick="storeUser('${mac}', '${dev.hostname}', ${plan.id})">
                    ${plan.name} (${duration})
                </span>`;
            });
            if (!plansHTML) plansHTML = '<span class="text-muted">No plans</span>';
            tr.innerHTML = `<td>${dev.hostname}</td><td>${dev.ip}</td><td>${mac}</td><td>${dev.type}</td><td>${plansHTML}</td>`;
            tbody.appendChild(tr);
        });

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to fetch devices</td></tr>`;
        console.error(err);
    }
}

// --- UPDATED: store user reliably and whitelist ---
async function storeUser(mac, hostname, planId) {
    const routerId = document.getElementById('routerNameHeading').dataset.routerId;
    const phone = prompt(`Enter phone number for ${hostname} (${mac}):`);
    if (!phone) return alert('Phone number is required');

    try {
        // Store user via billing API
        const billingRes = await fetch(billingApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                router_id: parseInt(routerId),
                paid_mac: mac.toUpperCase(),
                plan_id: planId,
                name: hostname,
                phone_number: phone
            })
        });
        const billingJson = await billingRes.json();
        if (!billingJson.success) {
            console.error(billingJson);
            return alert(billingJson.message || 'Failed to store user');
        }

        // Add device to whitelist
        const whitelistRes = await fetch(routersApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_device', router_id: parseInt(routerId), device: { mac: mac.toUpperCase(), hostname } })
        });
        const whitelistJson = await whitelistRes.json();
        if (!whitelistJson.success) {
            console.warn('User stored but failed to whitelist device', whitelistJson);
            alert('User stored but failed to whitelist device');
        } else {
            alert('User stored and device whitelisted successfully');
        }

        // Refresh table
        loadDevicesTable(routerId);

    } catch (err) {
        console.error(err);
        alert('Error storing user');
    }
}

// Back to routers
function backToRouters() {
    document.getElementById('devicesSection').style.display = 'none';
    document.getElementById('routersList').style.display = 'grid';
}

// Refresh devices table
function refreshDevicesTable() {
    const routerId = document.getElementById('routerNameHeading').dataset.routerId;
    if (routerId) loadDevicesTable(routerId);
}

loadRouters();
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
