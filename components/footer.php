<!-- components/footer.php -->
    </div><!-- /page-container -->
</main><!-- /main-content -->
</div><!-- /app-shell -->

<!-- Mobile bottom nav -->
<nav class="mobile-bottom-nav">
    <a href="dashboard" class="<?= $_cur === 'dashboard' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        Dashboard
    </a>
    <a href="mikrotik_devices" class="<?= $_cur === 'mikrotik_devices' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
        Devices
    </a>
    <a href="users" class="<?= $_cur === 'users' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        Users
    </a>
    <a href="vouchers" class="<?= $_cur === 'vouchers' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.07 15.93 0 13.35 0c-1.49 0-2.81.7-3.7 1.79L9 3l-.65-.21C7.48.7 6.16 0 4.65 0 2.07 0 0 2.07 0 4.65c0 .47.11.91.18 1.35H0v14h20V6zm-7-4.35c.55-.68 1.38-1.08 2.28-1.08 1.56 0 2.82 1.25 2.82 2.8 0 .48-.13.91-.31 1.31L11.38 4.5V2.73l1.62-.08zM1.85 4.65c0-1.55 1.26-2.8 2.82-2.8.9 0 1.73.4 2.28 1.08v1.77l-1.62.08-1.17.19c-.18-.4-.31-.83-.31-1.32zM18 18H2V8h16v10z"/></svg>
        Vouchers
    </a>
    <a href="billing" class="<?= $_cur === 'billing' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
        Billing
    </a>
</nav>

<script>
function toggleSub(parent) {
    var g = parent.closest('.nav-item-group');
    if (g) g.classList.toggle('open');
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.getElementById('main-content').classList.toggle('sidebar-collapsed');
}

// Notification badge (expired count)
function updateBadge() {
    fetch('/auth/worker.php?get_expired_count=true')
        .then(r => r.json())
        .then(d => {
            const badge = document.getElementById('expiredBadge');
            if (!badge) return;
            if (d.success && d.expired_count > 0) {
                badge.textContent = d.expired_count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {});
}

// Topbar search → live results
(function () {
    const input = document.getElementById('topSearch');
    const box = document.getElementById('topSearchResults');
    if (!input || !box) return;
    const pages = [
        { label: 'Dashboard', href: 'dashboard' },
        { label: 'View Devices', href: 'mikrotik_devices' },
        { label: 'Vouchers', href: 'vouchers' },
        { label: 'Manage Users', href: 'users' },
        { label: 'Billing', href: 'billing' },
        { label: 'Plans', href: 'plans' },
        { label: 'View Routers', href: 'view' },
        { label: 'Revenue', href: 'revenue' },
        { label: 'Reports', href: 'reports' }
    ];
    input.addEventListener('input', function () {
        const q = input.value.trim().toLowerCase();
        box.innerHTML = '';
        if (!q) return;
        pages.filter(p => p.label.toLowerCase().includes(q)).forEach(p => {
            const a = document.createElement('a');
            a.href = p.href;
            a.textContent = p.label;
            box.appendChild(a);
        });
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && input.value.trim()) {
            window.location.href = 'users?q=' + encodeURIComponent(input.value.trim());
        }
    });
})();

updateBadge();
setInterval(updateBadge, 60000);
</script>
</body>
</html>
