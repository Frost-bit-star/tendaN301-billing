<?php
$pageTitle = 'Router Dashboard';
$activePage = 'view';
include __DIR__ . '/../components/header.php';
?>
<style>
/* Router cards */
.view-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}
.router-card {
    background: var(--surface);
    border: 1px solid var(--surface-4);
    border-radius: var(--radius-lg);
    padding: 20px;
    cursor: pointer;
    transition: all var(--transition);
    position: relative;
    box-shadow: var(--shadow-1);
}
.router-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-3); border-color: var(--blue-300); }
.router-card .card-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; }

/* Fullscreen iframe modal */
#iframeModal {
    position: fixed; inset: 0; background: rgba(10,10,14,0.96);
    z-index: 99999; display: none; flex-direction: column; opacity: 0; transition: opacity .3s;
}
#iframeModal.visible { display: flex; opacity: 1; }
#iframeModal .modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 24px; background: rgba(30,30,40,0.95); border-bottom: 1px solid var(--surface-4);
}
#iframeModal .modal-header span { color: #fff; font-family: 'Google Sans', sans-serif; font-size: 18px; font-weight: 500; }
#iframeModal .modal-header button {
    color: #fff; background: none; border: none; font-size: 32px; line-height: 1; cursor: pointer; padding: 0 8px;
}
#iframeModal .modal-header button:hover { color: var(--blue-300); }
#iframeModal iframe { flex: 1; width: 100%; border: 0; }
body.modal-open #sidebar, body.modal-open .topbar, body.modal-open .mobile-bottom-nav { display: none !important; }
body.modal-open #main-content { margin-left: 0 !important; }
</style>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-desktop"></i> Router Dashboard</h1>
        <p class="page-subtitle">Click a router to open its web interface in fullscreen.</p>
    </div>
</div>

<!-- Router Cards -->
<div id="routerCardsContainer" class="view-grid">
    <!-- Cards injected dynamically -->
</div>

<!-- Fullscreen Iframe Modal -->
<div id="iframeModal">
    <div class="modal-header">
        <span id="modalRouterName"></span>
        <button id="closeModal" aria-label="Close">&times;</button>
    </div>
    <iframe id="routerModalIframe" src=""></iframe>
</div>

<script>
// API endpoint
const apiUrl = '/api/control.php';

// Elements
const iframeModal = document.getElementById('iframeModal');
const routerModalIframe = document.getElementById('routerModalIframe');
const closeModal = document.getElementById('closeModal');
const modalRouterName = document.getElementById('modalRouterName');

// Store original layout classes for restoration
const body = document.body;
const header = document.querySelector('header.main-header');
const footer = document.querySelector('footer.main-footer');

// Collapse sidebar, header, and footer only when opening the iframe
function enterFullscreenMode() {
    document.body.classList.add('modal-open');
}

// Restore layout when closing iframe
function exitFullscreenMode() {
    document.body.classList.remove('modal-open');
}

// Load routers as cards
async function loadRouters() {
    try {
        const res = await fetch(apiUrl);
        const json = await res.json();
        const container = document.getElementById('routerCardsContainer');
        container.innerHTML = '';

        if (!json.success || !json.routers) return;

        json.routers.forEach(r => {
            const card = document.createElement('div');
            card.className = 'router-card';
            card.innerHTML = `
                <div class="card-title">
                    <span style="font-size:15px;font-weight:500;font-family:'Google Sans',sans-serif;">${r.name}</span>
                    <span class="chip ${r.online ? 'active' : 'inactive'}"><span class="chip-dot"></span>${r.online ? 'Online' : 'Offline'}</span>
                </div>
                <div style="margin-top:12px;font-size:13px;color:var(--on-surface-med);">IP: ${r.ip}:${r.port || 80}</div>
            `;

            card.onclick = () => {
                enterFullscreenMode(); // Collapse everything

                modalRouterName.textContent = r.name;
                routerModalIframe.src = `http://${r.ip}:${r.port || 80}`;
                iframeModal.classList.add('visible');
            };

            container.appendChild(card);
        });

    } catch (err) {
        console.error('Failed to load routers:', err);
    }
}

// Close modal
function closeOverlay() {
    iframeModal.classList.remove('visible');
    setTimeout(() => {
        routerModalIframe.src = '';
        modalRouterName.textContent = '';
        exitFullscreenMode(); // Restore layout
    }, 300);
}

closeModal.onclick = closeOverlay;

// Close modal with Esc key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && iframeModal.classList.contains('visible')) {
        closeOverlay();
    }
});

// Load routers on page load
loadRouters();
</script>

