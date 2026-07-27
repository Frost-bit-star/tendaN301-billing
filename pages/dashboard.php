<?php
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
$adminName = $_SESSION['username'] ?? 'Admin';
?>
<style>
.dash-stat-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #eef0f2;
    transition: transform 0.2s;
}
.dash-stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
.dash-stat-card .icon-box {
    width: 52px; height: 52px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; color: #fff; margin-bottom: 1rem;
}
.dash-stat-card .stat-value { font-size: 1.8rem; font-weight: 800; color: #1a1a2e; line-height: 1; }
.dash-stat-card .stat-label { font-size: 0.82rem; color: #6c757d; margin-top: 4px; font-weight: 500; }
.dash-stat-card .stat-sub { font-size: 0.78rem; color: #999; margin-top: 6px; }

.chart-card {
    background: #fff; border-radius: 14px; padding: 1.25rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #eef0f2;
}
.chart-card h6 { font-weight: 700; color: #1a1a2e; margin-bottom: 0; }

.device-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.75rem 0; border-bottom: 1px solid #f3f4f6;
}
.device-row:last-child { border-bottom: none; }
.device-dot { width: 10px; height: 10px; border-radius: 50%; margin-right: 10px; flex-shrink: 0; }
.device-dot.on { background: #28a745; box-shadow: 0 0 6px rgba(40,167,69,0.4); }
.device-dot.off { background: #dc3545; }

.quick-action-btn {
    display: flex; align-items: center; gap: 10px;
    background: #f8f9fc; border: 1px solid #e4e7ec; border-radius: 10px;
    padding: 0.85rem 1rem; text-decoration: none; color: #333;
    font-weight: 600; font-size: 0.9rem; transition: all 0.2s;
}
.quick-action-btn:hover { background: #e8f0fe; border-color: #b3cfff; color: #0056d2; transform: translateX(4px); }
.quick-action-btn i { font-size: 1.1rem; }

@media (max-width: 768px) {
    .dash-stat-card .stat-value { font-size: 1.4rem; }
}
</style>

<div class="content-wrapper" style="background: #f4f6f9;">
<section class="content">
<div class="container-fluid py-4">

    <!-- Welcome -->
    <div class="mb-4">
        <h4 class="mb-1" style="font-weight:800; color:#1a1a2e;">Welcome back, <?= htmlspecialchars($adminName) ?>! 👋</h4>
        <p class="text-muted mb-0" style="font-size:0.9rem;">Here's what's happening with your WISP business.</p>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dash-stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#4e73df,#224abe);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value" id="totalRevenue">—</div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-sub" id="monthRevenueSub">This month: —</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dash-stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#28a745,#1e7e34);">
                    <i class="fas fa-wifi"></i>
                </div>
                <div class="stat-value" id="onlineDevices">—</div>
                <div class="stat-label">Online Devices</div>
                <div class="stat-sub" id="onlineDevicesSub">0% online</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dash-stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#17a2b8,#117a8b);">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-value" id="totalVouchers">—</div>
                <div class="stat-label">Vouchers Sold</div>
                <div class="stat-sub" id="monthVouchersSub">This month: —</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="dash-stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#ffc107,#e0a800);">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="stat-value" id="todayRevenue">—</div>
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-sub" id="activeVouchersSub">Active vouchers: —</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Revenue Trend -->
        <div class="col-xl-8 mb-4">
            <div class="chart-card" style="height:320px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="fas fa-chart-line text-primary mr-1"></i> Revenue Trend</h6>
                    <small class="text-muted">Last 6 months</small>
                </div>
                <canvas id="revenueChart" style="width:100%;height:250px;"></canvas>
                <div id="revenueChartEmpty" class="text-center text-muted py-5" style="display:none;">
                    <i class="fas fa-chart-line fa-2x mb-2" style="color:#dee2e6;"></i><br>
                    No revenue data yet
                </div>
            </div>
        </div>

        <!-- Devices List -->
        <div class="col-xl-4 mb-4">
            <div class="chart-card" style="height:320px;">
                <h6 class="mb-3"><i class="fas fa-server text-success mr-1"></i> Devices</h6>
                <div id="devicesList" style="max-height:260px; overflow-y:auto;"></div>
                <div id="devicesEmpty" class="text-center text-muted py-4" style="display:none;">
                    <i class="fas fa-router fa-2x mb-2" style="color:#dee2e6;"></i><br>
                    No devices yet
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="chart-card">
                <h6 class="mb-3"><i class="fas fa-bolt text-warning mr-1"></i> Quick Actions</h6>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <a href="connect_mikrotik" class="quick-action-btn w-100">
                            <i class="fas fa-plus-circle text-primary"></i> Add New Device
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="vouchers" class="quick-action-btn w-100">
                            <i class="fas fa-ticket-alt text-success"></i> Generate Vouchers
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="revenue" class="quick-action-btn w-100">
                            <i class="fas fa-chart-bar text-warning"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
let revenueChart = null;

async function loadDashboard() {
    try {
        const res = await fetch('/api/mikrotik.php?action=dashboard_stats');
        const d = await res.json();
        if (d.error) throw new Error(d.error);

        document.getElementById('totalRevenue').textContent = 'TSh ' + formatNum(d.total_revenue);
        document.getElementById('monthRevenueSub').textContent = 'This month: TSh ' + formatNum(d.month_revenue);
        document.getElementById('todayRevenue').textContent = 'TSh ' + formatNum(d.today_revenue);
        document.getElementById('activeVouchersSub').textContent = 'Active vouchers: ' + d.active_vouchers;

        document.getElementById('totalVouchers').textContent = d.total_vouchers;
        document.getElementById('monthVouchersSub').textContent = 'This month: ' + d.month_vouchers;

        document.getElementById('onlineDevices').textContent = d.online_devices + ' / ' + d.total_devices;
        const pct = d.total_devices > 0 ? Math.round((d.online_devices / d.total_devices) * 100) : 0;
        document.getElementById('onlineDevicesSub').textContent = pct + '% online';

        renderDevices(d.devices);
        renderChart(d.revenue_by_month);
    } catch (e) {
        console.error('Dashboard load error:', e);
    }
}

function formatNum(n) {
    if (n === 0 || n === '0') return '0';
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function renderDevices(devices) {
    const el = document.getElementById('devicesList');
    const empty = document.getElementById('devicesEmpty');
    if (!devices || devices.length === 0) {
        el.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    el.innerHTML = devices.map(d => `
        <div class="device-row" style="cursor:pointer;" onclick="location.href='mikrotik_devices?id=${d.id}'">
            <div style="display:flex;align-items:center;">
                <div class="device-dot ${d.online ? 'on' : 'off'}"></div>
                <div>
                    <div style="font-weight:600;font-size:0.9rem;">${escHtml(d.name)}</div>
                    <div style="font-size:0.75rem;color:#999;">${escHtml(d.location || 'No location')}</div>
                </div>
            </div>
            <span class="badge ${d.online ? 'badge-success' : 'badge-danger'}" style="font-size:0.7rem;">${d.online ? 'Online' : 'Offline'}</span>
        </div>
    `).join('');
}

function renderChart(data) {
    const canvas = document.getElementById('revenueChart');
    const empty = document.getElementById('revenueChartEmpty');
    if (!data || data.length === 0) {
        canvas.style.display = 'none';
        empty.style.display = 'block';
        return;
    }
    canvas.style.display = 'block';
    empty.style.display = 'none';

    const sorted = data.sort((a, b) => a.month.localeCompare(b.month));
    const labels = sorted.map(d => {
        const [y, m] = d.month.split('-');
        return new Date(y, m - 1).toLocaleDateString('en', { month: 'short', year: '2-digit' });
    });
    const values = sorted.map(d => d.revenue);

    if (revenueChart) revenueChart.destroy();

    revenueChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Revenue (TSh)',
                data: values,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78,115,223,0.08)',
                fill: true,
                tension: 0.4,
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: '#4e73df',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'TSh ' + formatNum(v) },
                    grid: { color: '#f0f0f0' }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

loadDashboard();
setInterval(loadDashboard, 60000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
