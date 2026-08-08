<?php
$pageTitle = 'Router Management';
$activePage = 'add_router';
include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Router Management</h1>
        <p class="page-subtitle">Add, update and monitor your Tenda routers</p>
    </div>
</div>

<div class="two-col">
    <div class="stack">
        <!-- Add Router -->
        <div class="card">
            <div class="card-header"><div class="card-title">Add / Update Router</div></div>
            <div class="card-body">
                <form id="routerForm">
                    <input type="hidden" id="routerId">
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Router Name</label>
                            <input type="text" id="routerName" class="form-control" placeholder="Router Name" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">IP Address</label>
                            <input type="text" id="routerIP" class="form-control" placeholder="IP Address" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Port</label>
                            <input type="number" id="routerPort" class="form-control" value="80" placeholder="Port">
                        </div>
                        <div class="form-group" style="margin-bottom:0">
                            <label class="form-label">Password</label>
                            <input type="password" id="routerPassword" class="form-control" placeholder="Password" required>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-16">Save Router</button>
                    <div id="routerMessage" class="form-hint"></div>
                </form>
            </div>
        </div>
    </div>

    <div class="stack">
        <!-- Router List -->
        <div class="card">
            <div class="card-header"><div class="card-title">Current Routers</div></div>
            <div class="table-wrapper">
                <table id="routerTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>IP</th>
                            <th>Port</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
.online { background: var(--green); }
.offline { background: var(--red); }
</style>

<script>
// API URL for CRUD operations
const apiUrl = '/api/control.php';

// Load routers from the backend and dynamically check status
async function loadRouters() {
    const res = await fetch(apiUrl);
    const json = await res.json();
    const tbody = document.querySelector('#routerTable tbody');
    tbody.innerHTML = '';

    if (!json.success) return;

    json.routers.forEach(r => {
        // Use the status directly from the backend response
        const status = r.online
            ? `<span class="chip active"><span class="chip-dot"></span>Online</span>`
            : `<span class="chip expired"><span class="chip-dot"></span>Offline</span>`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.id}</td>
            <td style="font-weight:500">${r.name}</td>
            <td style="font-family:monospace">${r.ip}</td>
            <td>${r.port || 80}</td>
            <td>${status}</td>
            <td>
                <div class="td-actions">
                    <button class="btn btn-outline btn-sm"
                        onclick="editRouter(${r.id}, '${r.name}', '${r.ip}', ${r.port || 80})">
                        Edit
                    </button>
                    <button class="btn btn-danger btn-sm"
                        onclick="deleteRouter(${r.id})">
                        Delete
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// Edit router details in the form
function editRouter(id, name, ip, port) {
    routerId.value = id;
    routerName.value = name;
    routerIP.value = ip;
    routerPort.value = port;
    routerPassword.value = '';
}

// Delete router
async function deleteRouter(id) {
    if (!confirm('Delete this router?')) return;

    const res = await fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id, delete: true })
    });

    const json = await res.json();
    if (json.success) {
        alert(json.message); // Show confirmation message (optional)
        loadRouters(); // Reload the list of routers
    } else {
        alert('Error deleting router.'); // Show error message
    }
}

// Add/Update router form submission
routerForm.addEventListener('submit', async e => {
    e.preventDefault();

    const payload = {
        id: routerId.value || undefined,
        name: routerName.value,
        ip: routerIP.value,
        port: routerPort.value,
        password: routerPassword.value
    };

    const res = await fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });

    const json = await res.json();
    routerMessage.textContent = json.message || '';
    routerForm.reset();
    loadRouters();
});

// Initial load of routers
loadRouters();

// Optional: auto-refresh every 30s
// setInterval(loadRouters, 30000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
