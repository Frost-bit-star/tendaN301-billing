<?php
$pageTitle = 'Users by Router';
$activePage = 'mikrotik';
include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-network-wired"></i> Users by Router <span style="font-size:13px;color:var(--on-surface-med);font-weight:400;">(Wired Only)</span></h1>
        <p class="page-subtitle">Throttle wired users on each MikroTik router.</p>
    </div>
</div>

<div id="routersContainer">
    <div class="text-center" style="color:var(--on-surface-med);">Loading routers...</div>
</div>

<script>
const throttleApi = '/auth/throttle.php';

async function loadRouters() {
    const container = document.getElementById('routersContainer');
    container.innerHTML = '<div class="text-center" style="color:var(--on-surface-med);">Loading routers...</div>';

    try {
        const res = await fetch(throttleApi);
        const data = await res.json();

        if (!data.routers || !data.routers.length) {
            container.innerHTML = '<div class="text-center" style="color:var(--red);">No routers found</div>';
            return;
        }

        container.innerHTML = '';

        data.routers.forEach(router => {

            const wiredUsers = router.users.filter(u => u.interface === 'wires');

            let tableHTML = '';

            if (wiredUsers.length) {

                const rows = wiredUsers.map(user => `
                    <tr id="user-${user.mac}" 
                        style="background-color:${user.internet_access ? '' : '#FCE8E6'}">
                        <td><code>${user.mac}</code></td>
                        <td>${user.ip}</td>
                        <td>${user.hostname}</td>
                        <td>${user.internet_access ? 'Yes' : 'No'}</td>
                        <td>
                            <input type="number" class="form-control up-speed" style="max-width:110px;padding:6px 10px;font-size:12px;"
                                   value="${user.upLimit}">
                        </td>
                        <td>
                            <input type="number" class="form-control down-speed" style="max-width:110px;padding:6px 10px;font-size:12px;"
                                   value="${user.downLimit}">
                        </td>
                        <td>${user.last_seen}</td>
                        <td>
                            <button class="btn btn-primary btn-sm throttle-btn"
                                data-mac="${user.mac}" data-router-id="${router.router_id}">
                                Set Throttle
                            </button>
                        </td>
                    </tr>
                `).join('');

                tableHTML = `
                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-header">
                            <span class="card-title"><i class="fas fa-server"></i> ${router.name} <span class="chip ${router.status === 'online' ? 'active' : router.status === 'provisioning' ? 'pending' : 'inactive'}"><span class="chip-dot"></span>${router.status}</span></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>MAC</th>
                                        <th>IP</th>
                                        <th>Hostname</th>
                                        <th>Access</th>
                                        <th>Up (kbps)</th>
                                        <th>Down (kbps)</th>
                                        <th>Last Seen</th>
                                        <th>Throttle</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                tableHTML = '<p style="color:var(--on-surface-med);margin:8px 0 20px;">No wired users found for this router.</p>';
            }

            container.innerHTML += `
                <h2 style="font-size:16px;font-family:\'Google Sans\',sans-serif;font-weight:500;color:var(--on-surface);margin:0 0 12px;">${router.name} (${router.status})</h2>
                ${tableHTML}
            `;
        });

        attachThrottleHandlers();

    } catch (err) {
        container.innerHTML = '<div style="color:var(--red);">Failed to load router data</div>';
        console.error(err);
    }
}

function attachThrottleHandlers() {
    document.querySelectorAll('.throttle-btn').forEach(btn => {
        btn.addEventListener('click', async () => {

            const row = btn.closest('tr');
            const mac = btn.dataset.mac;
            const routerId = btn.dataset.routerId; // pass router_id now
            const up = row.querySelector('.up-speed').value;
            const down = row.querySelector('.down-speed').value;

            btn.disabled = true;
            btn.innerText = "Setting...";

            try {
                const response = await fetch(
                    `${throttleApi}?action=set_throttle&router_id=${routerId}&mac=${mac}&up=${up}&down=${down}`
                );
                const result = await response.json();

                if (result.success) {
                    alert(`✅ Throttle set for ${mac}`);
                } else {
                    alert(`❌ Failed to set throttle: ${result.error || 'Unknown error'}`);
                }

            } catch (err) {
                console.error(err);
                alert('⚠️ Error contacting throttle API');
            }

            btn.disabled = false;
            btn.innerText = "Set Throttle";
        });
    });
}

// Initial load
loadRouters();
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
