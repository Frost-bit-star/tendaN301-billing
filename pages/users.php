<?php
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">

            <h1 class="mt-4 mb-4">Users Management</h1>

            <div id="routersContainer">
                <!-- Routers and users tables will be injected here -->
            </div>

        </div>
    </section>
</div>

<style>
.router-card {
    margin-bottom: 30px;
}
.router-card .badge {
    padding: 5px 10px;
    font-size: 0.9em;
}
</style>

<script>
const apiBill = '/api/bill.php';

// Load all routers and their users
async function loadRouters() {
    const container = document.getElementById('routersContainer');
    container.innerHTML = '<p>Loading...</p>';

    try {
        const res = await fetch(apiBill);
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to load routers.'}</div>`;
            return;
        }

        container.innerHTML = '';

        data.routers.forEach(router => {
            const card = document.createElement('div');
            card.className = 'card router-card';

            const statusBadge = router.status === 'online'
                ? '<span class="badge badge-success">Online</span>'
                : '<span class="badge badge-danger">Offline</span>';

            card.innerHTML = `
                <div class="card-header">
                    <strong>${router.name}</strong> ${statusBadge}
                </div>
                <div class="card-body">
                    <h5>Active Users (${router.active.length})</h5>
                    ${buildUserTable(router.active, router.id)}
                    <h5 class="mt-3">Blocked Users (${router.blocked.length})</h5>
                    ${buildUserTable(router.blocked, router.id)}
                </div>
            `;

            container.appendChild(card);
        });

    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">Request failed: ${err}</div>`;
    }
}

// Build HTML table for users
function buildUserTable(users, routerId) {
    if (users.length === 0) return '<p>No users.</p>';

    let rows = users.map(u => {
        const status = u.internet
            ? '<span class="badge badge-success">Active</span>'
            : '<span class="badge badge-danger">Blocked</span>';

        const actionBtn = !u.internet
            ? `<button class="btn btn-sm btn-success" onclick="payUser(${routerId}, '${u.mac}')">Pay / Unblock</button>`
            : '';

        return `
            <tr>
                <td>${u.hostname || 'unknown'}</td>
                <td>${u.ip || ''}</td>
                <td>${u.mac.toUpperCase()}</td>
                <td>${status}</td>
                <td>${actionBtn}</td>
            </tr>
        `;
    }).join('');

    return `
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Hostname</th>
                    <th>IP</th>
                    <th>MAC</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

// Pay/unblock a user
async function payUser(routerId, mac) {
    const planId = prompt("Enter plan ID for this user:", "1");
    if (!planId) return;

    try {
        const url = `/auth/billing.php?id=${routerId}&paid_mac=${mac}&plan_id=${planId}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.status) {
            alert(data.status);
            loadRouters();
        } else if (data.error) {
            alert("Error: " + data.error);
        }
    } catch (err) {
        alert("Request failed: " + err);
    }
}

// Initial load
loadRouters();

// Optional: auto-refresh every 30s
// setInterval(loadRouters, 30000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
