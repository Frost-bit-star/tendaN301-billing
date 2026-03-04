<?php
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <h1 class="mt-4 mb-4 text-center">Users by Router (Wired Only)</h1>

            <div id="routersContainer">
                <div class="text-center text-muted">Loading routers...</div>
            </div>

        </div>
    </section>
</div>

<script>
const throttleApi = '/auth/throttle.php';

async function loadRouters() {
    const container = document.getElementById('routersContainer');
    container.innerHTML = '<div class="text-center text-info">Loading routers...</div>';

    try {
        const res = await fetch(throttleApi);
        const data = await res.json();

        if (!data.routers || !data.routers.length) {
            container.innerHTML = '<div class="text-center text-danger">No routers found</div>';
            return;
        }

        container.innerHTML = '';

        data.routers.forEach(router => {

            const wiredUsers = router.users.filter(u => u.interface === 'wires');

            let tableHTML = '';

            if (wiredUsers.length) {

                const rows = wiredUsers.map(user => `
                    <tr id="user-${user.mac}" 
                        style="background-color:${user.internet_access ? '' : '#f8d7da'}">
                        <td>${user.mac}</td>
                        <td>${user.ip}</td>
                        <td>${user.hostname}</td>
                        <td>${user.internet_access ? 'Yes' : 'No'}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm up-speed"
                                   value="${user.upLimit}">
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm down-speed"
                                   value="${user.downLimit}">
                        </td>
                        <td>${user.last_seen}</td>
                        <td>
                            <button class="btn btn-warning btn-sm throttle-btn"
                                data-mac="${user.mac}" data-router-id="${router.router_id}">
                                Set Throttle
                            </button>
                        </td>
                    </tr>
                `).join('');

                tableHTML = `
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
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
                `;
            } else {
                tableHTML = '<p>No wired users found for this router.</p>';
            }

            container.innerHTML += `
                <h2>${router.name} (${router.status})</h2>
                ${tableHTML}
            `;
        });

        attachThrottleHandlers();

    } catch (err) {
        container.innerHTML = '<div class="text-danger">Failed to load router data</div>';
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
