<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle  = $pageTitle  ?? 'Admin Dashboard';
$activePage = $activePage ?? basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$adminName  = $_SESSION['username'] ?? 'Admin';
$role       = $_SESSION['role'] ?? 'admin';

$_cur = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

function _navActive($page) { global $_cur; return $_cur === $page ? 'active' : ''; }

$appCurrencyMap = [
    'TZS' => 'TSh',
    'KES' => 'KES',
    'USD' => '$',
    'UGX' => 'UGX',
    'RWF' => 'RWF',
    'NGN' => 'NGN',
    'ZMW' => 'K',
    'GHS' => 'GH₵',
    'XOF' => 'FCFA',
    'MWK' => 'MK',
];
$appCurrencyCode = $_SESSION['currency'] ?? 'TZS';
$appCurrencySymbol = $appCurrencyMap[$appCurrencyCode] ?? $appCurrencyCode;
?>
<!-- components/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · Jasiri WiFi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Google+Sans+Display:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/mobile-nav.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    window.APP_CURRENCY = '<?= htmlspecialchars($appCurrencySymbol, ENT_QUOTES) ?>';
    window.APP_CURRENCY_CODE = '<?= htmlspecialchars($appCurrencyCode, ENT_QUOTES) ?>';
    function navActive(p) { return location.pathname.replace(/\/+$/, '') === ('/' + p) ? 'active' : ''; }
    function navOpen(pages) { return pages.indexOf(location.pathname.replace(/\/+$/, '').split('/').pop()) !== -1; }
    function _esc(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    async function loadSidebarStatus() {
        const list = document.getElementById('sidebarStatusList');
        if (!list) return;
        try {
            const res = await fetch('/api/mikrotik.php?action=list');
            const data = await res.json();
            const routers = data.routers || [];
            if (routers.length === 0) {
                list.innerHTML = '<div class="sidebar-status-item"><span class="status-dot offline"></span><span>No MikroTik devices</span></div>';
                return;
            }
            list.innerHTML = routers.map(function(r) {
                let dot = 'offline', label = 'Offline';
                if (r.online) { dot = 'online'; label = 'Online'; }
                else if (r.provisioning_status === 'provisioning' || r.provisioning_status === 'pending') { dot = 'pending'; label = 'Provisioning'; }
                return '<div class="sidebar-status-item" title="' + _esc(r.wireguard_ip || '') + '">'
                    + '<span class="status-dot ' + dot + '"></span>'
                    + '<span class="sidebar-status-name">' + _esc(r.name) + '</span>'
                    + '<span class="sidebar-status-state">' + label + '</span>'
                    + '</div>';
            }).join('');
        } catch (e) {
            list.innerHTML = '<div class="sidebar-status-item"><span class="status-dot offline"></span><span>Status unavailable</span></div>';
        }
    }
    loadSidebarStatus();
    setInterval(loadSidebarStatus, 30000);
    </script>
</head>
<body>
<div class="app-shell">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
        </div>
        <div>
            <div class="logo-text">Jasiri WiFi</div>
            <div class="logo-sub">ISP Billing System</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>

        <a href="dashboard" class="nav-item <?= _navActive('dashboard') ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg></span>
            <span class="nav-label">Dashboard</span>
        </a>

        <div class="nav-section-label">MikroTik</div>

        <div class="nav-item-group open <?= in_array($_cur, ['connect_mikrotik','mikrotik_devices','vouchers','revenue','marketing']) ? 'open' : '' ?>">
            <div class="nav-item nav-item-parent <?= in_array($_cur, ['connect_mikrotik','mikrotik_devices','vouchers','revenue','marketing']) ? 'active' : '' ?>" onclick="toggleSub(this)">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35 0-2.58-2.09-4.65-4.67-4.65-1.49 0-2.81.7-3.7 1.79L9 3l-.63-.21C7.48 1.7 6.16 1 4.67 1 2.09 1 0 3.07 0 5.65c0 .47.11.91.18 1.35H0v14h20V6zm-7.62-3.27c.55-.68 1.38-1.08 2.28-1.08 1.56 0 2.82 1.25 2.82 2.8 0 .48-.13.91-.31 1.31L13 5.76l-.62-.03V2.73zM1.85 5.65c0-1.55 1.26-2.8 2.82-2.8.9 0 1.73.4 2.28 1.08v3l-.62.03L4.16 7.76c-.18-.4-.31-.83-.31-1.31zM18 18H2V8h16v10z"/></svg></span>
                <span class="nav-label">MikroTik</span>
                <svg class="chevron" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
            </div>
            <div class="nav-submenu">
                <a href="connect_mikrotik" class="nav-item <?= _navActive('connect_mikrotik') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 3v8h2V7.41L15.59 18H11v2h10V10h-2v4.59L8.41 4H13V2H3z"/></svg></span>
                    <span class="nav-label">Connect Device</span>
                </a>
                <a href="mikrotik_devices" class="nav-item <?= _navActive('mikrotik_devices') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.07 15.93 0 13.35 0c-1.49 0-2.81.7-3.7 1.79L9 3l-.65-.21C7.48.7 6.16 0 4.65 0 2.07 0 0 2.07 0 4.65c0 .47.11.91.18 1.35H0v14h20V6zm-7-4.35c.55-.68 1.38-1.08 2.28-1.08 1.56 0 2.82 1.25 2.82 2.8 0 .48-.13.91-.31 1.31L11.38 4.5V2.73l1.62-.08zM1.85 4.65c0-1.55 1.26-2.8 2.82-2.8.9 0 1.73.4 2.28 1.08v1.77l-1.62.08-1.17.19c-.18-.4-.31-.83-.31-1.32zM18 18H2V8h16v10z"/></svg></span>
                    <span class="nav-label">View Devices</span>
                </a>
                <a href="vouchers" class="nav-item <?= _navActive('vouchers') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.07 15.93 0 13.35 0c-1.49 0-2.81.7-3.7 1.79L9 3l-.65-.21C7.48.7 6.16 0 4.65 0 2.07 0 0 2.07 0 4.65c0 .47.11.91.18 1.35H0v14h20V6zm-7-4.35c.55-.68 1.38-1.08 2.28-1.08 1.56 0 2.82 1.25 2.82 2.8 0 .48-.13.91-.31 1.31L11.38 4.5V2.73l1.62-.08zM1.85 4.65c0-1.55 1.26-2.8 2.82-2.8.9 0 1.73.4 2.28 1.08v1.77l-1.62.08-1.17.19c-.18-.4-.31-.83-.31-1.32zM18 18H2V8h16v10z"/></svg></span>
                    <span class="nav-label">Vouchers</span>
                </a>
                <a href="revenue" class="nav-item <?= _navActive('revenue') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 9.2h3V19H5V9.2zM10.6 5h2.8v14h-2.8V5zm5.6 8H19v6h-2.8v-6z"/></svg></span>
                    <span class="nav-label">Revenue</span>
                </a>
                <a href="marketing" class="nav-item <?= _navActive('marketing') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg></span>
                    <span class="nav-label">Marketing</span>
                </a>
            </div>
        </div>

        <div class="nav-section-label">Tenda Routers</div>

        <div class="nav-item-group <?= in_array($_cur, ['view','add_router','billuser','users','billing','plans','mikrotik']) ? 'open' : '' ?>">
            <div class="nav-item nav-item-parent <?= in_array($_cur, ['view','add_router','billuser','users','billing','plans','mikrotik']) ? 'active' : '' ?>" onclick="toggleSub(this)">
                <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 7H7C4.24 7 2 9.24 2 12s2.24 5 5 5h10c2.76 0 5-2.24 5-5s-2.24-5-5-5zm0 8H7c-1.66 0-3-1.34-3-3s1.34-3 3-3h10c1.66 0 3 1.34 3 3s-1.34 3-3 3zM7 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg></span>
                <span class="nav-label">Tenda Routers</span>
                <svg class="chevron" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
            </div>
            <div class="nav-submenu">
                <a href="view" class="nav-item <?= _navActive('view') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg></span>
                    <span class="nav-label">View Routers</span>
                </a>
                <a href="add_router" class="nav-item <?= _navActive('add_router') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg></span>
                    <span class="nav-label">Add Router</span>
                </a>
                <a href="billuser" class="nav-item <?= _navActive('billuser') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></span>
                    <span class="nav-label">Add User</span>
                </a>
                <a href="users" class="nav-item <?= _navActive('users') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></span>
                    <span class="nav-label">Manage Users</span>
                </a>
                <a href="billing" class="nav-item <?= _navActive('billing') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></span>
                    <span class="nav-label">Billing</span>
                </a>
                <a href="plans" class="nav-item <?= _navActive('plans') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M11.99 18.54l-7.37-5.73L3 14.07l9 7 9-7-1.63-1.27-7.38 5.74zM12 16l7.36-5.73L21 9l-9-7-9 7 1.63 1.27L12 16z"/></svg></span>
                    <span class="nav-label">Plans</span>
                </a>
                <a href="mikrotik" class="nav-item <?= _navActive('mikrotik') ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M20 7h-8V5h8v2zm0-4h-8v2h8V3zm4 12c0 2.21-1.79 4-4 4h-6v6l-4-4H4c-2.21 0-4-1.79-4-4V4c0-2.21 1.79-4 4-4h16c2.21 0 4 1.79 4 4v11z"/></svg></span>
                    <span class="nav-label">Wired Devices</span>
                </a>
            </div>
        </div>

        <div class="nav-section-label">Insights</div>

        <a href="reports" class="nav-item <?= _navActive('reports') ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 9.2h3V19H5V9.2zM10.6 5h2.8v14h-2.8V5zm5.6 8H19v6h-2.8v-6z"/></svg></span>
            <span class="nav-label">Reports</span>
        </a>

        <div class="nav-section-label">System</div>

        <a href="settings" class="nav-item <?= _navActive('settings') ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg></span>
            <span class="nav-label">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-status">
            <div class="sidebar-status-title">
                <i class="fas fa-signal"></i>
                <span class="nav-label">Router Status</span>
                <button type="button" class="sidebar-status-refresh" onclick="loadSidebarStatus()" title="Refresh status">
                    <i class="fas fa-sync"></i>
                </button>
            </div>
            <div class="sidebar-status-list" id="sidebarStatusList">
                <div class="sidebar-status-item"><span class="status-dot loading"></span><span>Loading…</span></div>
            </div>
        </div>
        <a href="logout" class="sidebar-collapse-btn" style="text-decoration:none">
            <svg viewBox="0 0 24 24"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            <span class="nav-label">Logout</span>
        </a>
    </div>
</aside>

<main class="main-content" id="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
        <div class="topbar-search" style="position:relative">
            <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <input type="text" id="topSearch" placeholder="Search customers, users…" autocomplete="off">
            <div class="topbar-search-results" id="topSearchResults"></div>
        </div>
        <div class="topbar-actions">
            <a href="billing" class="icon-btn" title="Notifications">
                <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                <span class="badge" id="expiredBadge" style="display:none"></span>
            </a>
            <div class="avatar" title="<?= htmlspecialchars($adminName) ?>"><?= htmlspecialchars(strtoupper(substr($adminName, 0, 1))) ?></div>
        </div>
    </div>

    <div class="page-container">
