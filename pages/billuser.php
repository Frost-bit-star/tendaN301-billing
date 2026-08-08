<?php
$pageTitle = 'Bill User';
$activePage = 'billuser';
include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">WiFi Routers Dashboard</h1>
        <p class="page-subtitle">Select a router to view connected devices and assign plans</p>
    </div>
</div>

<!-- Routers List -->
<div id="routersList" class="routers-grid">
    <div class="col-12 text-center text-muted">Loading routers...</div>
</div>

<!-- Devices Table (hidden initially) -->
<div id="devicesSection" style="display:none;">
    <!-- Buttons Container -->
    <div class="flex-row" style="margin-bottom:16px;justify-content:space-between">
        <button class="btn btn-outline" onclick="backToRouters()">← Back to Routers</button>
        <button class="btn btn-primary" onclick="refreshDevicesTable()">⟳ Refresh</button>
    </div>

    <h3 id="routerNameHeading" class="card-title" style="margin-bottom:16px" data-router-id=""></h3>

    <!-- Filter Mode Switch -->
    <div class="mb-16">
        <div class="switch">
            <input class="switch-check" id="filterSwitch" type="checkbox">
            <label class="switch-label" for="filterSwitch">
                Filter Mode
                <span></span>
            </label>
        </div>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table id="devicesTable">
                <thead>
                    <tr>
                        <th>Hostname</th>
                        <th>IP</th>
                        <th>MAC</th>
                        <th>Connection</th>
                        <th>Plans</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--on-surface-med)">Select a router to view devices</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
/* --- Routers Cards --- */
.routers-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.router-card {
    background: var(--surface);
    color: var(--on-surface);
    font-weight: 600;
    border: 1px solid var(--surface-4);
    border-radius: var(--radius-lg);
    padding: 24px 20px;
    text-align: center;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    height: 200px;
    box-shadow: var(--shadow-1);
}
.router-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-3); border-color: var(--blue-300); }
.router-card h4 { margin-bottom: 8px; color: var(--blue-500); font-family: 'Google Sans', sans-serif; }
.router-card p { margin: 3px 0; font-size: 0.9rem; }
.router-card .device-info { margin-top: 10px; font-size: 0.85rem; color: var(--on-surface-med); }
.plan-badge {
    display: inline-block;
    background: var(--blue-50);
    color: var(--blue-600);
    border: 1px solid var(--blue-200);
    padding: 5px 12px;
    border-radius: var(--radius-full);
    margin: 3px 2px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all .2s;
}
.plan-badge:hover { background: var(--blue-100); }

/* Whitelisted row style */
.whitelisted { background-color: #E6F4EA !important; }

/* Responsive */
@media (max-width: 1024px) { .routers-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .routers-grid { grid-template-columns: 1fr; } }

/* --- Filter Switch --- */
.switch {
  background-color: var(--surface-3);
  border-radius: 30px;
  border: 4px solid var(--surface-4);
  height: 48px;
  position: relative;
  width: 120px;
  display: inline-block;
  user-select: none;
}
.switch-check { position: absolute; visibility: hidden; user-select: none; }
.switch-label { cursor: pointer; display: block; height: 42px; text-indent: -9999px; width: 115px; user-select: none; }
.switch-label span {
  background: var(--blue-500);
  border-radius: 30px;
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
                <h4>${router.name}</h4>
                <p>Filter Mode: ${filterMode}</p>
                <div class="device-info">Devices: ${totalDevices}</div>
            `;
            cardDiv.onclick = () => showDevices(router.router_id, router.name, filterMode);
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
