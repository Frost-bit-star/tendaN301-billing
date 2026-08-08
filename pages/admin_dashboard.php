<?php
$pageTitle = 'Admin Dashboard';
$activePage = 'admin_dashboard';
include __DIR__ . '/../components/header.php';
$adminName = $_SESSION['username'] ?? 'Super Admin';
?>
<style>
.blink { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--green); animation:blink 2s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
</style>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Welcome back, <?= htmlspecialchars($adminName) ?></h1>
        <p class="page-subtitle">
            <span class="blink"></span>&nbsp;
            Manage all tenant admins across the platform &middot; <span id="lastUpdated">Last updated: —</span>
        </p>
    </div>
    <div class="page-header-right">
        <a href="admins" class="btn btn-primary"><i class="fas fa-user-shield"></i> Manage Admins</a>
    </div>
</div>

<!-- Stats Row -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Tenant Admins</span>
            <span class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
        </div>
        <div class="stat-value" id="statAdmins">—</div>
        <div class="stat-trend"><span class="stat-trend-label" id="statAdminsSub">—</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Routers (All Tenants)</span>
            <span class="stat-icon green"><svg viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg></span>
        </div>
        <div class="stat-value" id="statRouters">—</div>
        <div class="stat-trend"><span class="stat-trend-label" id="statRoutersSub">0% online</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Total Revenue</span>
            <span class="stat-icon yellow"><svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg></span>
        </div>
        <div class="stat-value" id="statRevenue">—</div>
        <div class="stat-trend"><span class="stat-trend-label" id="statRevenueSub">This month: —</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Vouchers Sold</span>
            <span class="stat-icon teal"><svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.07 15.93 0 13.35 0c-1.49 0-2.81.7-3.7 1.79L9 3l-.65-.21C7.48.7 6.16 0 4.65 0 2.07 0 0 2.07 0 4.65c0 .47.11.91.18 1.35H0v14h20V6zm-7-4.35c.55-.68 1.38-1.08 2.28-1.08 1.56 0 2.82 1.25 2.82 2.8 0 .48-.13.91-.31 1.31L11.38 4.5V2.73l1.62-.08zM1.85 4.65c0-1.55 1.26-2.8 2.82-2.8.9 0 1.73.4 2.28 1.08v1.77l-1.62.08-1.17.19c-.18-.4-.31-.83-.31-1.32zM18 18H2V8h16v10z"/></svg></span>
        </div>
        <div class="stat-value" id="statVouchers">—</div>
        <div class="stat-trend"><span class="stat-trend-label" id="statVouchersSub">Active: —</span></div>
    </div>
</div>

<!-- Tenant Admins -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Tenant Admins</div>
        <a href="admins" class="card-subtitle" style="text-decoration:none;">Manage all &raquo;</a>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Routers</th>
                        <th>Vouchers</th>
                        <th>Revenue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="tenantTable">
                    <tr><td colspan="5" style="text-align:center;color:var(--on-surface-med);padding:20px;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function formatNum(n) {
    if (n === 0 || n === '0') return '0';
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

async function loadPlatform() {
    try {
        const res = await fetch('/api/mikrotik.php?action=platform_stats');
        const d = await res.json();
        if (d.error) throw new Error(d.error);

        document.getElementById('statAdmins').textContent = d.total_admins;
        document.getElementById('statAdminsSub').textContent = d.active_admins + ' active · ' + d.disabled_admins + ' disabled';

        document.getElementById('statRouters').textContent = d.online_routers + ' / ' + d.total_routers;
        const pct = d.total_routers > 0 ? Math.round((d.online_routers / d.total_routers) * 100) : 0;
        document.getElementById('statRoutersSub').textContent = pct + '% online';

        document.getElementById('statRevenue').textContent = window.APP_CURRENCY + ' ' + formatNum(d.total_revenue);
        document.getElementById('statRevenueSub').textContent = 'This month: ' + window.APP_CURRENCY + ' ' + formatNum(d.month_revenue);

        document.getElementById('statVouchers').textContent = d.total_vouchers;
        document.getElementById('statVouchersSub').textContent = 'Active: ' + d.active_vouchers;

        const tbody = document.getElementById('tenantTable');
        if (!d.tenants || d.tenants.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--on-surface-med);padding:20px;">No tenant admins yet</td></tr>';
        } else {
            tbody.innerHTML = d.tenants.map(t => `
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px;">${escHtml(t.name)}</div>
                        <div style="font-size:12px;color:var(--on-surface-med);">${escHtml(t.email)}</div>
                    </td>
                    <td>${t.routers_count}</td>
                    <td>${t.vouchers_used}<span style="color:var(--on-surface-med);"> / ${t.voucher_limit === -1 ? '∞' : t.voucher_limit}</span></td>
                    <td style="font-weight:600;">${window.APP_CURRENCY} ${formatNum(t.revenue)}</td>
                    <td>${t.status ? '<span class="status-pill active">Active</span>' : '<span class="status-pill expired">Disabled</span>'}</td>
                </tr>
            `).join('');
        }

        const el = document.getElementById('lastUpdated');
        if (el) el.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
    } catch (e) {
        console.error('Platform load error:', e);
    }
}

loadPlatform();
setInterval(loadPlatform, 30000);
document.addEventListener('visibilitychange', function () {
    if (!document.hidden) loadPlatform();
});
window.addEventListener('focus', loadPlatform);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
