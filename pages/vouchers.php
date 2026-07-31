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
        width: 100%; background: #fff; padding: 0.5rem;
    }
    .print-voucher {
        border: 2px dashed #333; padding: 0.8rem; margin: 0.3rem;
        width: 46%; display: inline-block; vertical-align: top;
        page-break-inside: avoid; text-align: center;
        border-radius: 8px;
    }
    .print-voucher h4 {
        margin: 0 0 0.3rem; font-size: 0.9rem; color: #007bff;
        text-transform: uppercase; letter-spacing: 1px;
    }
    .print-voucher .code {
        font-size: 1.6rem; font-weight: 700; letter-spacing: 4px;
        text-align: center; margin: 0.4rem 0; color: #1a1a2e;
        font-family: 'Courier New', monospace;
    }
    .print-voucher .detail {
        font-size: 0.75rem; color: #555; margin: 2px 0;
    }
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
    <li class="nav-item">
        <a class="nav-link" data-tab="track" onclick="showTab('track')">
            <i class="fas fa-search"></i> Track Voucher
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
                        <th>Phone</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="voucherTableBody">
                    <tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
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
                <div class="col-md-3">
                    <label>Filter by router</label>
                    <select class="form-control form-control-sm" id="printRouter" onchange="loadPrintVouchers()">
                        <option value="">All Routers</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Filter by status</label>
                    <select class="form-control form-control-sm" id="printFilter" onchange="loadPrintVouchers()">
                        <option value="active">Active Only</option>
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="mb-0"><strong>Select vouchers to print</strong> <span id="printCount" class="badge badge-primary" style="display:none;">0</span></label>
                        <label class="custom-control custom-checkbox mb-0" style="cursor:pointer;">
                            <input type="checkbox" class="custom-control-input" id="selectAllPrint" onchange="toggleSelectAll()">
                            <span class="custom-control-label" style="font-size:0.85rem;font-weight:600;">Select All</span>
                        </label>
                    </div>
                    <div id="printCheckboxes" style="max-height:250px;overflow-y:auto;border:1px solid #dee2e6;padding:0.5rem;border-radius:4px;">
                        Loading...
                    </div>
                </div>
            </div>
            <div id="printPreview"></div>
        </div>
    </div>
</div>

<!-- Track Voucher Tab -->
<div class="tab-content" id="tab-track" style="display:none;">
    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-search"></i> Track Voucher Usage</h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="trackCode">Enter Voucher Code</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="trackCode" placeholder="00000000" maxlength="11"
                                oninput="this.value = this.value.replace(/[^0-9]/g,'').replace(/(.{4})/g,'$1 ').trim()">
                            <div class="input-group-append">
                                <button class="btn btn-primary" onclick="trackVoucher()"><i class="fas fa-search"></i> Track</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="trackResult" style="display:none;"></div>
            <div id="trackLoading" class="text-center py-4" style="display:none;">
                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                <p class="text-muted mt-2">Checking router...</p>
            </div>
            <div id="trackEmpty" class="text-center py-4 text-muted">
                <i class="fas fa-search fa-3x mb-3" style="opacity:0.3;"></i>
                <p>Enter a voucher code above to track its usage and device status.</p>
            </div>
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
        const printRouterSel = document.getElementById('printRouter');
        printRouterSel.innerHTML = '<option value="">All Routers</option>' +
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
        document.getElementById('voucherTableBody').innerHTML = '<tr><td colspan="9" class="text-center text-danger">Failed to load vouchers</td></tr>';
    }
}

function renderVoucherTable() {
    const tbody = document.getElementById('voucherTableBody');
    if (allVouchers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No vouchers found</td></tr>';
        return;
    }
    tbody.innerHTML = allVouchers.map(v => `
        <tr>
            <td><span class="voucher-code">${escapeHtml(v.code)}</span></td>
            <td>${escapeHtml(v.router_name || '—')}</td>
            <td>${escapeHtml(v.plan_name || '—')}</td>
            <td>${escapeHtml(v.customer_name || '—')}</td>
            <td>${v.phone ? escapeHtml(v.phone) : '—'}</td>
            <td>TSh ${parseInt(v.price || 0).toLocaleString()}</td>
            <td><span class="status-pill ${v.status}">${v.status}</span></td>
            <td>${v.expires_at ? new Date(v.expires_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) : '—'}</td>
            <td>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteVoucher(${v.id}, '${v.status}')"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

let printVouchers = [];

async function loadPrintVouchers() {
    const filter = document.getElementById('printFilter').value;
    const routerId = document.getElementById('printRouter').value;
    const params = new URLSearchParams();
    if (filter) params.set('status', filter);
    if (routerId) params.set('router_id', routerId);
    params.set('limit', '200');

    try {
        const res = await fetch('/api/vouchers.php?' + params);
        const data = await res.json();
        printVouchers = data.vouchers || [];

        document.getElementById('printCheckboxes').innerHTML = printVouchers.map(v => `
            <div class="custom-control custom-checkbox mb-1">
                <input type="checkbox" class="custom-control-input print-cb" id="pv_${v.id}" data-id="${v.id}" onchange="updatePrintPreview()">
                <label class="custom-control-label" for="pv_${v.id}">
                    <span class="voucher-code" style="font-size:0.85rem;">${v.code}</span>
                    <small class="text-muted"> - ${escapeHtml(v.plan_name || '')} - TSh ${parseInt(v.price || 0).toLocaleString()}</small>
                </label>
            </div>
        `).join('') || '<p class="text-muted">No vouchers found</p>';

        document.getElementById('printPreview').innerHTML = '<p class="text-muted">Select vouchers above to preview</p>';
        document.getElementById('printArea').innerHTML = '';
    } catch (e) {
        document.getElementById('printCheckboxes').innerHTML = '<p class="text-danger">Failed to load</p>';
    }
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAllPrint').checked;
    document.querySelectorAll('.print-cb').forEach(cb => cb.checked = checked);
    updatePrintPreview();
}

function updatePrintPreview() {
    const checked = document.querySelectorAll('.print-cb:checked');
    const ids = Array.from(checked).map(cb => cb.dataset.id);
    const selected = printVouchers.filter(v => ids.includes(String(v.id)));

    // Update count badge
    const countBadge = document.getElementById('printCount');
    if (selected.length > 0) {
        countBadge.textContent = selected.length;
        countBadge.style.display = 'inline';
    } else {
        countBadge.style.display = 'none';
    }

    // Sync select-all checkbox
    const allCbs = document.querySelectorAll('.print-cb');
    document.getElementById('selectAllPrint').checked = allCbs.length > 0 && checked.length === allCbs.length;

    const printArea = document.getElementById('printArea');
    const preview = document.getElementById('printPreview');

    if (selected.length === 0) {
        preview.innerHTML = '<p class="text-muted">Select vouchers above to preview</p>';
        printArea.innerHTML = '';
        return;
    }

    // Screen preview
    preview.innerHTML = '<div class="row">' + selected.map(v => `
        <div class="col-md-3 col-sm-6 mb-3">
            <div style="border:2px dashed #333;padding:1rem;text-align:center;border-radius:8px;background:#fff;">
                <strong style="color:#007bff;">${escapeHtml((v.router_ssid || 'Jasiri WiFi').toUpperCase())}</strong><br>
                <div class="voucher-code my-2" style="font-size:1.4rem;letter-spacing:3px;">${v.code}</div>
                <small style="font-weight:600;">${escapeHtml(v.plan_name || '')}</small><br>
                <small>TSh ${parseInt(v.price || 0).toLocaleString()}</small><br>
                <small class="text-muted">Exp: ${v.expires_at ? new Date(v.expires_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) : '—'}</small>
            </div>
        </div>
    `).join('') + '</div>';

    // Print layout (hidden, shown only on print)
    printArea.innerHTML = selected.map(v => `
        <div class="print-voucher">
            <h4>${escapeHtml(v.router_ssid || 'Jasiri WiFi')}</h4>
            <div class="code">${v.code}</div>
            <div class="detail"><strong>Package:</strong> ${escapeHtml(v.plan_name || '—')}</div>
            <div class="detail"><strong>Price:</strong> TSh ${parseInt(v.price || 0).toLocaleString()}</div>
            <div class="detail"><strong>Expires:</strong> ${v.expires_at ? new Date(v.expires_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) : '—'}</div>
        </div>
    `).join('');
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
    if (tab === 'track') { document.getElementById('trackCode').focus(); }
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

async function deleteVoucher(id, status) {
    const msg = status === 'used'
        ? 'Delete this used voucher? This will also remove the hotspot user from the router.'
        : 'Delete this voucher?';
    if (!confirm(msg)) return;
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

function formatUptime(s) {
    if (!s) return '—';
    return s;
}

function formatBytes(b) {
    b = parseInt(b || 0);
    if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB';
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024) return (b / 1024).toFixed(1) + ' KB';
    return b + ' B';
}

async function trackVoucher() {
    const code = document.getElementById('trackCode').value.replace(/\s+/g, '');
    if (!code) { alert('Please enter a voucher code'); return; }

    document.getElementById('trackEmpty').style.display = 'none';
    document.getElementById('trackResult').style.display = 'none';
    document.getElementById('trackLoading').style.display = 'block';

    try {
        const res = await fetch('/api/vouchers.php?action=track&code=' + encodeURIComponent(code));
        const data = await res.json();

        document.getElementById('trackLoading').style.display = 'none';

        if (!res.ok) {
            document.getElementById('trackResult').style.display = 'block';
            document.getElementById('trackResult').innerHTML = '<div class="alert alert-danger">' + escapeHtml(data.error || 'Voucher not found') + '</div>';
            return;
        }

        const v = data.voucher;
        const online = data.is_online;
        const device = data.device;
        const onlineBadge = online ? '<span class="badge badge-success"><i class="fas fa-circle"></i> Online</span>' : '<span class="badge badge-secondary"><i class="fas fa-circle"></i> Offline</span>';

        document.getElementById('trackResult').style.display = 'block';
        document.getElementById('trackResult').innerHTML = `
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Voucher: <span class="voucher-code">${escapeHtml(v.code)}</span></h5>
                    <span class="status-pill ${v.status}">${v.status.toUpperCase()}</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><td class="text-muted">Plan</td><td><strong>${escapeHtml(v.plan_name || '—')}</strong></td></tr>
                                <tr><td class="text-muted">Router</td><td>${escapeHtml(v.router_name || '—')}</td></tr>
                                <tr><td class="text-muted">Customer Phone</td><td><strong>${escapeHtml(v.phone || '—')}</strong></td></tr>
                                <tr><td class="text-muted">Customer Name</td><td>${escapeHtml(v.customer_name || '—')}</td></tr>
                                <tr><td class="text-muted">Price</td><td>TSh ${parseInt(v.price || 0).toLocaleString()}</td></tr>
                                <tr><td class="text-muted">Used At</td><td>${v.used_at ? new Date(v.used_at).toLocaleString('en-GB') : '—'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Device Connection Status</h6>
                            <div class="p-3 mb-3 rounded ${online ? 'bg-white' : 'bg-light'}" style="border:2px solid ${online ? '#28a745' : '#dee2e6'};background:${online ? '#f0fff4' : ''} !important;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Status:</span> ${onlineBadge}
                                </div>
                                ${device ? `
                                <table class="table table-sm mb-0">
                                    <tr><td class="text-muted">MAC Address</td><td><code>${escapeHtml(device.mac || '—')}</code></td></tr>
                                    <tr><td class="text-muted">IP Address</td><td>${escapeHtml(device.address || '—')}</td></tr>
                                    <tr><td class="text-muted">Uptime</td><td>${formatUptime(device.uptime)}</td></tr>
                                    <tr><td class="text-muted">Traffic In</td><td>${formatBytes(device.bytes_in)}</td></tr>
                                    <tr><td class="text-muted">Traffic Out</td><td>${formatBytes(device.bytes_out)}</td></tr>
                                </table>
                                ` : '<p class="text-muted mb-0 small">No active session found on router</p>'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } catch (e) {
        document.getElementById('trackLoading').style.display = 'none';
        document.getElementById('trackResult').style.display = 'block';
        document.getElementById('trackResult').innerHTML = '<div class="alert alert-danger">Failed to track voucher: ' + e.message + '</div>';
    }
}

loadPlans();
loadRouters();
loadStats();
</script>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
