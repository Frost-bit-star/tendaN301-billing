<?php
ob_start();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>
<style>
.stat-card {
    border-radius: 12px; padding: 1.5rem; color: #fff; position: relative; overflow: hidden;
}
.stat-card .stat-icon { font-size: 2.5rem; opacity: 0.3; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); }
.stat-card .stat-value { font-size: 2rem; font-weight: 700; }
.stat-card .stat-label { font-size: 0.85rem; opacity: 0.85; }
.stat-card.total { background: linear-gradient(135deg, #667eea, #764ba2); }
.stat-card.active { background: linear-gradient(135deg, #28a745, #20c997); }
.stat-card.used { background: linear-gradient(135deg, #dc3545, #e83e8c); }

.voucher-tabs .nav-link {
    border-radius: 20px; margin-right: 0.5rem; padding: 0.5rem 1.2rem;
    font-weight: 600; color: #6c757d; border: 1px solid #dee2e6;
}
.voucher-tabs .nav-link.active {
    background: #007bff; color: #fff; border-color: #007bff;
}

.voucher-code {
    font-family: 'Courier New', monospace; font-size: 1.1rem; font-weight: 700;
    letter-spacing: 2px; color: #495057;
}

.status-pill {
    padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.status-pill.active { background: #d4edda; color: #155724; }
.status-pill.used { background: #f8d7da; color: #721c24; }
.status-pill.expired { background: #e2e3e5; color: #383d41; }

.print-area { display: none; }

@media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area {
        display: block !important; position: absolute; left: 0; top: 0;
        width: 100%; background: #fff; padding: 1rem;
    }
    .print-voucher {
        border: 2px dashed #333; padding: 1rem; margin: 0.5rem;
        width: 48%; display: inline-block; vertical-align: top; page-break-inside: avoid;
    }
    .print-voucher h4 { margin: 0 0 0.5rem; font-size: 1rem; }
    .print-voucher .code { font-size: 1.5rem; font-weight: 700; letter-spacing: 3px; text-align: center; margin: 0.5rem 0; }
    .print-voucher .detail { font-size: 0.75rem; color: #555; }
}

.generate-form .form-group label { font-weight: 600; font-size: 0.85rem; }
</style>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">

<h2 class="mt-4 mb-2"><i class="fas fa-ticket-alt text-primary"></i> Internet Vouchers</h2>
<p class="text-muted mb-4">Generate, track and manage vouchers for customer access</p>

<!-- Stats Cards -->
<div class="row mb-4" id="statsRow">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-value" id="statTotal">0</div>
            <div class="stat-label">Total Vouchers</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card active">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" id="statActive">0</div>
            <div class="stat-label">Active</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card used">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value" id="statUsed">0</div>
            <div class="stat-label">Used</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #6c757d, #adb5bd);">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value" id="statExpired">0</div>
            <div class="stat-label">Expired</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav voucher-tabs mb-4" id="voucherTabs">
    <li class="nav-item">
        <a class="nav-link active" data-tab="generate" onclick="showTab('generate')">
            <i class="fas fa-plus-circle"></i> Generate Vouchers
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tab="online" onclick="showTab('online')">
            <i class="fas fa-wifi"></i> Online Vouchers
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-tab="print" onclick="showTab('print')">
            <i class="fas fa-print"></i> Print Vouchers
        </a>
    </li>
</ul>

<!-- Generate Tab -->
<div class="tab-content" id="tab-generate">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-magic"></i> Generate New Vouchers</h5>
        </div>
        <div class="card-body">
            <form id="generateForm" class="generate-form">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Router *</label>
                            <select class="form-control" id="genRouter" required>
                                <option value="">Select router...</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Package / Plan *</label>
                            <select class="form-control" id="genPlan" required>
                                <option value="">Select plan...</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" class="form-control" id="genQuantity" value="1" min="1" max="100">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Price (TSh)</label>
                            <input type="number" class="form-control" id="genPrice" value="500" min="0">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" class="form-control" id="genPhone" placeholder="2557...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" class="form-control" id="genCustomer" placeholder="Optional">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" id="generateBtn">
                    <i class="fas fa-cogs"></i> Generate Vouchers
                </button>
            </form>
            <div id="generatedCodes" class="mt-3" style="display:none;">
                <div class="alert alert-success">
                    <strong><i class="fas fa-check-circle"></i> Generated!</strong>
                    <div id="generatedList" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Online Vouchers Tab -->
<div class="tab-content" id="tab-online" style="display:none;">
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list"></i> Vouchers</h5>
            <div>
                <select class="form-control form-control-sm" id="filterRouter" style="width:auto;display:inline-block;" onchange="loadVouchers()">
                    <option value="">All Routers</option>
                </select>
                <select class="form-control form-control-sm" id="filterStatus" style="width:auto;display:inline-block;" onchange="loadVouchers()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="used">Used</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Voucher Code</th>
                        <th>Router</th>
                        <th>Package</th>
                        <th>Customer</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="voucherTableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print Tab -->
<div class="tab-content" id="tab-print" style="display:none;">
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-print"></i> Print Vouchers</h5>
            <button class="btn btn-success btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Now</button>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Filter by status</label>
                    <select class="form-control form-control-sm" id="printFilter" onchange="loadPrintVouchers()">
                        <option value="active">Active Only</option>
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Select vouchers to print</label>
                    <div id="printCheckboxes" style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;padding:0.5rem;border-radius:4px;">
                        Loading...
                    </div>
                </div>
            </div>
            <div id="printPreview"></div>
        </div>
    </div>
</div>

<!-- Print Layout (hidden, shown only when printing) -->
<div class="print-area" id="printArea"></div>

</div>
</section>
</div>

<script>
let allPlans = [];
let allVouchers = [];
let allRouters = [];

async function loadPlans() {
    try {
        const res = await fetch('/api/plans.php');
        const data = await res.json();
        allPlans = data.plans || [];
        const sel = document.getElementById('genPlan');
        sel.innerHTML = '<option value="">Select plan...</option>' +
            allPlans.map(p => `<option value="${p.id}">${p.name} (${p.days}d ${p.hours}h ${p.minutes}m)</option>`).join('');
    } catch (e) { console.error('Failed to load plans:', e); }
}

async function loadRouters() {
    try {
        const res = await fetch('/api/mikrotik.php?action=list');
        const data = await res.json();
        allRouters = data.routers || [];
        const genSel = document.getElementById('genRouter');
        genSel.innerHTML = '<option value="">Select router...</option>' +
            allRouters.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
        const filterSel = document.getElementById('filterRouter');
        filterSel.innerHTML = '<option value="">All Routers</option>' +
            allRouters.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
    } catch (e) { console.error('Failed to load routers:', e); }
}

async function loadStats() {
    try {
        const res = await fetch('/api/vouchers.php?action=stats');
        const data = await res.json();
        const s = data.stats;
        document.getElementById('statTotal').textContent = s.total || 0;
        document.getElementById('statActive').textContent = s.active || 0;
        document.getElementById('statUsed').textContent = s.used || 0;
        document.getElementById('statExpired').textContent = s.expired || 0;
    } catch (e) { console.error('Failed to load stats:', e); }
}

async function loadVouchers() {
    const status = document.getElementById('filterStatus').value;
    const routerId = document.getElementById('filterRouter').value;
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (routerId) params.set('router_id', routerId);
    params.set('limit', '100');

    try {
        const res = await fetch('/api/vouchers.php?' + params);
        const data = await res.json();
        allVouchers = data.vouchers || [];
        renderVoucherTable();
    } catch (e) {
        document.getElementById('voucherTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load vouchers</td></tr>';
    }
}

function renderVoucherTable() {
    const tbody = document.getElementById('voucherTableBody');
    if (allVouchers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No vouchers found</td></tr>';
        return;
    }
    tbody.innerHTML = allVouchers.map(v => `
        <tr>
            <td><span class="voucher-code">${escapeHtml(v.code)}</span></td>
            <td>${escapeHtml(v.router_name || '—')}</td>
            <td>${escapeHtml(v.plan_name || '—')}</td>
            <td>${escapeHtml(v.customer_name || v.phone || '—')}</td>
            <td>TSh ${parseInt(v.price || 0).toLocaleString()}</td>
            <td><span class="status-pill ${v.status}">${v.status}</span></td>
            <td>${v.expires_at ? new Date(v.expires_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) : '—'}</td>
            <td>
                ${v.status === 'active' ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteVoucher(${v.id})"><i class="fas fa-trash"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
}

async function loadPrintVouchers() {
    const filter = document.getElementById('printFilter').value;
    const params = new URLSearchParams();
    if (filter) params.set('status', filter);
    params.set('limit', '200');

    try {
        const res = await fetch('/api/vouchers.php?' + params);
        const data = await res.json();
        const vouchers = data.vouchers || [];

        document.getElementById('printCheckboxes').innerHTML = vouchers.map(v => `
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input print-cb" id="pv_${v.id}" data-id="${v.id}" onchange="updatePrintPreview()">
                <label class="custom-control-label" for="pv_${v.id}">
                    <span class="voucher-code" style="font-size:0.85rem;">${v.code}</span>
                    <small class="text-muted"> - ${escapeHtml(v.plan_name || '')}</small>
                </label>
            </div>
        `).join('') || '<p class="text-muted">No vouchers found</p>';
    } catch (e) {
        document.getElementById('printCheckboxes').innerHTML = '<p class="text-danger">Failed to load</p>';
    }
}

function updatePrintPreview() {
    const checked = document.querySelectorAll('.print-cb:checked');
    const ids = Array.from(checked).map(cb => cb.dataset.id);
    const selected = allVouchers.length > 0 ? allVouchers.filter(v => ids.includes(String(v.id))) : [];

    const printArea = document.getElementById('printArea');
    const preview = document.getElementById('printPreview');

    if (selected.length === 0) {
        preview.innerHTML = '<p class="text-muted">Select vouchers above to preview</p>';
        printArea.innerHTML = '';
        return;
    }

    const html = selected.map(v => `
        <div class="print-voucher">
            <h4>Jasiri WiFi</h4>
            <div class="code">${v.code}</div>
            <div class="detail"><strong>Package:</strong> ${escapeHtml(v.plan_name || '—')}</div>
            <div class="detail"><strong>Price:</strong> TSh ${parseInt(v.price || 0).toLocaleString()}</div>
            <div class="detail"><strong>Expires:</strong> ${v.expires_at ? new Date(v.expires_at).toLocaleDateString() : '—'}</div>
        </div>
    `).join('');

    preview.innerHTML = '<div class="row">' + selected.map(v => `
        <div class="col-md-3 mb-3">
            <div style="border:2px dashed #333;padding:1rem;text-align:center;border-radius:8px;">
                <strong>Jasiri WiFi</strong><br>
                <div class="voucher-code my-2" style="font-size:1.3rem;">${v.code}</div>
                <small>${escapeHtml(v.plan_name || '')} | TSh ${parseInt(v.price || 0).toLocaleString()}</small><br>
                <small class="text-muted">Exp: ${v.expires_at ? new Date(v.expires_at).toLocaleDateString() : '—'}</small>
            </div>
        </div>
    `).join('') + '</div>';

    printArea.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.voucher-tabs .nav-link').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelector(`.voucher-tabs .nav-link[data-tab="${tab}"]`).classList.add('active');

    if (tab === 'online') loadVouchers();
    if (tab === 'print') loadPrintVouchers();
}

document.getElementById('generateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('generateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

    try {
        const res = await fetch('/api/vouchers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate',
                plan_id: document.getElementById('genPlan').value,
                router_id: document.getElementById('genRouter').value,
                quantity: document.getElementById('genQuantity').value,
                price: document.getElementById('genPrice').value,
                phone: document.getElementById('genPhone').value,
                customer_name: document.getElementById('genCustomer').value
            })
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.error || 'Generation failed');

        const listDiv = document.getElementById('generatedCodes');
        const listEl = document.getElementById('generatedList');
        listDiv.style.display = 'block';
        listEl.innerHTML = data.vouchers.map(v =>
            `<span class="voucher-code" style="font-size:1.2rem;margin-right:1rem;">${v.code}</span>`
        ).join('');

        loadStats();
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cogs"></i> Generate Vouchers';
    }
});

async function deleteVoucher(id) {
    if (!confirm('Delete this voucher?')) return;
    try {
        await fetch('/api/vouchers.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        loadVouchers();
        loadStats();
    } catch (e) {
        alert('Delete failed');
    }
}

loadPlans();
loadRouters();
loadStats();
</script>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
