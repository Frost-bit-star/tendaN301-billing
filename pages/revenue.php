<?php
ob_start();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>
<style>
.stat-card { border-radius: 12px; padding: 1.5rem; color: #fff; position: relative; overflow: hidden; }
.stat-card .stat-icon { font-size: 2.5rem; opacity: 0.3; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); }
.stat-card .stat-value { font-size: 2rem; font-weight: 700; }
.stat-card .stat-label { font-size: 0.85rem; opacity: 0.85; }
</style>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">

<h2 class="mt-4 mb-2"><i class="fas fa-chart-line text-success"></i> Revenue</h2>
<p class="text-muted mb-4">Income summary from used vouchers</p>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-md-3">
        <select class="form-control" id="revPeriod" onchange="loadRevenue()">
            <option value="all">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-control" id="revRouter" onchange="loadRevenue()">
            <option value="">All Routers</option>
        </select>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-value" id="revTotal">TSh 0</div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #007bff, #6610f2);">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-value" id="revCount">0</div>
            <div class="stat-label">Vouchers Used</div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #fd7e14, #e83e8c);">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-value" id="revAvg">TSh 0</div>
            <div class="stat-label">Average per Voucher</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- By Router -->
    <div class="col-md-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-router"></i> Revenue by Router</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Router</th><th class="text-right">Vouchers</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody id="revByRouter"><tr><td colspan="3" class="text-center text-muted">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- By Day -->
    <div class="col-md-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-calendar"></i> Revenue by Day</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th class="text-right">Vouchers</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody id="revByDay"><tr><td colspan="3" class="text-center text-muted">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
</section>
</div>

<script>
async function loadRouters() {
    try {
        const res = await fetch('/api/mikrotik.php?action=list');
        const data = await res.json();
        const sel = document.getElementById('revRouter');
        sel.innerHTML = '<option value="">All Routers</option>' +
            (data.routers || []).map(r => `<option value="${r.id}">${r.name}</option>`).join('');
    } catch (e) {}
}

function fmt(n) { return 'TSh ' + parseInt(n || 0).toLocaleString(); }

async function loadRevenue() {
    const period = document.getElementById('revPeriod').value;
    const routerId = document.getElementById('revRouter').value;
    const params = new URLSearchParams({ period });
    if (routerId) params.set('router_id', routerId);

    try {
        const res = await fetch('/api/vouchers.php?action=revenue&' + params);
        const data = await res.json();

        document.getElementById('revTotal').textContent = fmt(data.total);
        document.getElementById('revCount').textContent = data.count || 0;
        document.getElementById('revAvg').textContent = data.count > 0 ? fmt(Math.round(data.total / data.count)) : 'TSh 0';

        const routerBody = document.getElementById('revByRouter');
        if ((data.by_router || []).length === 0) {
            routerBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No revenue data</td></tr>';
        } else {
            routerBody.innerHTML = data.by_router.map(r => `
                <tr>
                    <td>${escapeHtml(r.router_name || 'Unknown')}</td>
                    <td class="text-right">${r.count}</td>
                    <td class="text-right font-weight-bold">${fmt(r.revenue)}</td>
                </tr>
            `).join('');
        }

        const dayBody = document.getElementById('revByDay');
        if ((data.by_day || []).length === 0) {
            dayBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No revenue data</td></tr>';
        } else {
            dayBody.innerHTML = data.by_day.map(d => `
                <tr>
                    <td>${d.day}</td>
                    <td class="text-right">${d.count}</td>
                    <td class="text-right font-weight-bold">${fmt(d.revenue)}</td>
                </tr>
            `).join('');
        }
    } catch (e) {
        console.error('Failed to load revenue:', e);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadRouters();
loadRevenue();
</script>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
