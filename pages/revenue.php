<?php
ob_start();
$pageTitle = 'Revenue';
$activePage = 'revenue';
include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-chart-line"></i> Revenue</h1>
        <p class="page-subtitle">Income summary from used vouchers</p>
    </div>
</div>

<!-- Filters -->
<div class="flex-row" style="margin-bottom:20px;">
    <select class="form-control" id="revPeriod" onchange="loadRevenue()" style="max-width:220px;">
        <option value="all">All Time</option>
        <option value="today">Today</option>
        <option value="week">This Week</option>
        <option value="month">This Month</option>
    </select>
    <select class="form-control" id="revRouter" onchange="loadRevenue()" style="max-width:220px;">
        <option value="">All Routers</option>
    </select>
</div>

<!-- Stats Cards -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Total Revenue</span>
            <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        <div class="stat-value" id="revTotal"><?= htmlspecialchars($appCurrencySymbol) ?> 0</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Vouchers Used</span>
            <div class="stat-icon blue"><i class="fas fa-ticket-alt"></i></div>
        </div>
        <div class="stat-value" id="revCount">0</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Average per Voucher</span>
            <div class="stat-icon orange"><i class="fas fa-receipt"></i></div>
        </div>
        <div class="stat-value" id="revAvg"><?= htmlspecialchars($appCurrencySymbol) ?> 0</div>
    </div>
</div>

<div class="form-row">
    <!-- By Router -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-router"></i> Revenue by Router</span>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Router</th><th style="text-align:right;">Vouchers</th><th style="text-align:right;">Revenue</th></tr></thead>
                    <tbody id="revByRouter"><tr><td colspan="3" style="text-align:center;color:var(--on-surface-med);padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- By Day -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-calendar"></i> Revenue by Day</span>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th style="text-align:right;">Vouchers</th><th style="text-align:right;">Revenue</th></tr></thead>
                    <tbody id="revByDay"><tr><td colspan="3" style="text-align:center;color:var(--on-surface-med);padding:20px;">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
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

function fmt(n) { return window.APP_CURRENCY + ' ' + parseInt(n || 0).toLocaleString(); }

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
        document.getElementById('revAvg').textContent = data.count > 0 ? fmt(Math.round(data.total / data.count)) : window.APP_CURRENCY + ' 0';

        const routerBody = document.getElementById('revByRouter');
        if ((data.by_router || []).length === 0) {
            routerBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--on-surface-med);padding:20px;">No revenue data</td></tr>';
        } else {
            routerBody.innerHTML = data.by_router.map(r => `
                <tr>
                    <td>${escapeHtml(r.router_name || 'Unknown')}</td>
                    <td style="text-align:right;">${r.count}</td>
                    <td style="text-align:right;font-weight:600;">${fmt(r.revenue)}</td>
                </tr>
            `).join('');
        }

        const dayBody = document.getElementById('revByDay');
        if ((data.by_day || []).length === 0) {
            dayBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--on-surface-med);padding:20px;">No revenue data</td></tr>';
        } else {
            dayBody.innerHTML = data.by_day.map(d => `
                <tr>
                    <td>${d.day}</td>
                    <td style="text-align:right;">${d.count}</td>
                    <td style="text-align:right;font-weight:600;">${fmt(d.revenue)}</td>
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
