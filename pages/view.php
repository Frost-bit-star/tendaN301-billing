<?php
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content">
        <div id="pageContent" class="container mx-auto py-8">

            <h1 class="text-3xl font-bold mb-8 text-center">Router Dashboard</h1>

            <!-- Router Cards -->
            <div id="routerCardsContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!-- Cards injected dynamically -->
            </div>

            <!-- Fullscreen Iframe Modal -->
            <div id="iframeModal" class="fixed inset-0 bg-black bg-opacity-90 hidden z-[99999] flex flex-col transition-opacity duration-300">
                <!-- Modal Header -->
                <div class="relative flex justify-between items-center p-4 bg-gray-800 bg-opacity-90">
                    <span id="modalRouterName" class="text-white text-2xl font-bold"></span>
                    <button id="closeModal" 
                            class="absolute top-4 right-4 text-white text-5xl font-bold hover:text-gray-300 z-[100000]">
                        &times;
                    </button>
                </div>
                <!-- Iframe -->
                <iframe id="routerModalIframe" src="" class="flex-1 w-full h-full" frameborder="0"></iframe>
            </div>

        </div>
    </section>
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
    body.classList.add('sidebar-collapse');
    header?.classList.add('hidden');
    footer?.classList.add('hidden');
}

// Restore layout when closing iframe
function exitFullscreenMode() {
    body.classList.remove('sidebar-collapse');
    header?.classList.remove('hidden');
    footer?.classList.remove('hidden');
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
            card.className = 'bg-blue-600 text-white rounded-xl shadow-lg p-6 cursor-pointer hover:bg-blue-700 transition';
            card.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-xl font-bold">${r.name}</span>
                    <span class="w-4 h-4 rounded-full ${r.online ? 'bg-green-500' : 'bg-red-500'}"></span>
                </div>
                <div class="mt-4 text-sm">IP: ${r.ip}:${r.port || 80}</div>
            `;

            card.onclick = () => {
                enterFullscreenMode(); // Collapse everything

                modalRouterName.textContent = r.name;
                routerModalIframe.src = `http://${r.ip}:${r.port || 80}`;
                iframeModal.classList.remove('hidden');
                setTimeout(() => iframeModal.classList.add('opacity-100'), 10);
            };

            container.appendChild(card);
        });

    } catch (err) {
        console.error('Failed to load routers:', err);
    }
}

// Close modal
function closeOverlay() {
    iframeModal.classList.remove('opacity-100');
    setTimeout(() => {
        iframeModal.classList.add('hidden');
        routerModalIframe.src = '';
        modalRouterName.textContent = '';
        exitFullscreenMode(); // Restore layout
    }, 300);
}

closeModal.onclick = closeOverlay;

// Close modal with Esc key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !iframeModal.classList.contains('hidden')) {
        closeOverlay();
    }
});

// Load routers on page load
loadRouters();
</script>

