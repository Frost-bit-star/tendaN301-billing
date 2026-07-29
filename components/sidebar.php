<style>
/* ===== Professional Sidebar with Multi-Color Accents ===== */
.sidebar-glass {
    background: #343a40;
    color: #fff !important;
    height: 100vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    position: fixed;
    width: 250px;
    z-index: 1050;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.sidebar-glass .brand-link {
    background: #23272b;
    color: #fff !important;
    font-weight: 700;
    font-size: 1.25rem;
    text-align: center;
    padding: 1rem 0;
    border-bottom: 1px solid #3c4043;
    border-radius: 0 0 12px 12px;
}

.sidebar-top-buttons {
    display: flex;
    justify-content: space-around;
    margin: 1rem 0;
}
.sidebar-top-buttons .top-btn {
    background: rgba(0, 123, 255, 0.1);
    color: #fff !important;
    padding: 0.45rem 0.8rem;
    border-radius: 22px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}
.sidebar-top-buttons .top-btn.active,
.sidebar-top-buttons .top-btn:hover {
    background: rgba(0, 123, 255, 0.25);
}

.sidebar-glass .nav-sidebar .nav-link {
    color: #fff !important;
    border-radius: 8px;
    padding: 0.55rem 1rem;
    margin: 2px 8px;
    display: flex;
    align-items: center;
    transition: all 0.25s ease;
    font-size: 0.9rem;
}
.sidebar-glass .nav-sidebar .nav-link.active {
    background: rgba(0, 123, 255, 0.25);
}
.sidebar-glass .nav-sidebar .nav-link:hover {
    background: rgba(0, 123, 255, 0.15);
    transform: translateX(3px);
}

.sidebar-glass .nav-item:nth-child(1) .nav-icon { color: #dc3545; }
.sidebar-glass .nav-item:nth-child(2) .nav-icon { color: #28a745; }
.sidebar-glass .nav-item:nth-child(3) .nav-icon { color: #ffc107; }
.sidebar-glass .nav-item:nth-child(4) .nav-icon { color: #007bff; }
.sidebar-glass .nav-item:nth-child(5) .nav-icon { color: #17a2b8; }
.sidebar-glass .nav-item:nth-child(6) .nav-icon { color: #6f42c1; }
.sidebar-glass .nav-item:nth-child(7) .nav-icon { color: #fd7e14; }
.sidebar-glass .nav-item:nth-child(8) .nav-icon { color: #20c997; }
.sidebar-glass .nav-item:nth-child(9) .nav-icon { color: #e83e8c; }
.sidebar-glass .nav-item:nth-child(10) .nav-icon { color: #343a40; }

.sidebar-glass .right {
    color: #007bff;
    transition: transform 0.3s ease;
    margin-left: auto;
}
.sidebar-glass .nav-item.menu-open > .nav-link > .right {
    transform: rotate(90deg);
}

.sidebar-glass::-webkit-scrollbar {
    width: 8px;
}
.sidebar-glass::-webkit-scrollbar-thumb {
    background: rgba(0, 123, 255, 0.3);
    border-radius: 10px;
}
.sidebar-glass::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-logout {
    margin-top: auto;
    width: 80%;
    align-self: center;
}
.sidebar-logout a {
    display: block;
    text-align: center;
    padding: 10px 0;
    background: #dc3545;
    color: #fff !important;
    border-radius: 45px;
    font-weight: 600;
    transition: all 0.25s ease;
    text-decoration: none;
}
.sidebar-logout a:hover {
    background: #c82333;
}

.sidebar-section-label {
    color: rgba(255,255,255,0.4);
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0.6rem 1.2rem 0.2rem;
}

.nav-sidebar .nav-treeview {
    padding: 0;
    list-style: none;
}
.nav-sidebar .nav-treeview .nav-link {
    padding-left: 2.5rem;
    font-size: 0.85rem;
}

@media (max-width: 767px) {
    .sidebar-glass {
        width: 220px;
    }
}
</style>

<?php
$current_page = basename($_SERVER['REQUEST_URI'] ?? 'dashboard');
$tenda_pages = ['view', 'add_router', 'users', 'billuser', 'billing', 'plans', 'mikrotik'];
$mikrotik_pages = ['connect_mikrotik', 'mikrotik_devices', 'vouchers', 'revenue'];
?>

<aside class="main-sidebar sidebar-glass elevation-4">
    <a href="/" class="brand-link">
        <span class="brand-text font-weight-bold">Jasiri WiFi</span>
    </a>

    <div class="sidebar">
        <div class="sidebar-top-buttons">
            <a href="dashboard" class="top-btn <?= $current_page === 'dashboard' ? 'active' : '' ?>">Home</a>
            <a href="mikrotik_devices" class="top-btn <?= $current_page === 'mikrotik_devices' ? 'active' : '' ?>">Devices</a>
            <a href="users" class="top-btn <?= $current_page === 'users' ? 'active' : '' ?>">Users</a>
        </div>

        <nav class="mt-2 flex-grow-1">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">

                <li class="nav-item">
                    <a href="dashboard" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-tachometer-alt" style="color:#007bff"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- MIKROTIK SECTION -->
                <li class="sidebar-section-label">MikroTik</li>
                <li class="nav-item <?= in_array($current_page, $mikrotik_pages) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= in_array($current_page, $mikrotik_pages) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-router" style="color:#ff6b35"></i>
                        <p>
                            MikroTik
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="connect_mikrotik" class="nav-link <?= $current_page === 'connect_mikrotik' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-plug"></i>
                                <p>Connect Device</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="mikrotik_devices" class="nav-link <?= $current_page === 'mikrotik_devices' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-server"></i>
                                <p>View Devices</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="vouchers" class="nav-link <?= $current_page === 'vouchers' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-ticket-alt"></i>
                                <p>Vouchers</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="revenue" class="nav-link <?= $current_page === 'revenue' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-chart-line"></i>
                                <p>Revenue</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="marketing" class="nav-link <?= $current_page === 'marketing' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-bullhorn"></i>
                                <p>Marketing</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- TENDA SECTION (Add-on) -->
                <li class="sidebar-section-label">Tenda (Add-on)</li>
                <li class="nav-item <?= in_array($current_page, $tenda_pages) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= in_array($current_page, $tenda_pages) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-wifi" style="color:#20c997"></i>
                        <p>
                            Tenda Routers
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="view" class="nav-link <?= $current_page === 'view' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-network-wired"></i>
                                <p>View Routers</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="add_router" class="nav-link <?= $current_page === 'add_router' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-plus-circle"></i>
                                <p>Add Router</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="billuser" class="nav-link <?= $current_page === 'billuser' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-user-plus"></i>
                                <p>Add User</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="users" class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Manage Users</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="billing" class="nav-link <?= $current_page === 'billing' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>Billing</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="plans" class="nav-link <?= $current_page === 'plans' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-layer-group"></i>
                                <p>Plans</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="mikrotik" class="nav-link <?= $current_page === 'mikrotik' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-ethernet"></i>
                                <p>Wired Devices</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="reports" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-chart-bar" style="color:#ffc107"></i>
                        <p>Reports</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="support" class="nav-link <?= $current_page === 'support' ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-headset" style="color:#17a2b8"></i>
                        <p>Support</p>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-logout">
            <a href="logout">Logout</a>
        </div>
    </div>
</aside>
