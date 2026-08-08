<?php
$pageTitle = 'WiFi Plans';
$activePage = 'plans';
include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> WiFi Plans</h1>
        <p class="page-subtitle">Define the duration packages sold to customers.</p>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">Create New Plan</span>
    </div>
    <div class="card-body">
        <form id="planForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="planName">Plan Name</label>
                    <input type="text" id="planName" class="form-control" placeholder="Plan Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="planDays">Days</label>
                    <input type="number" id="planDays" class="form-control" placeholder="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label" for="planHours">Hours</label>
                    <input type="number" id="planHours" class="form-control" placeholder="0" min="0" max="23">
                </div>
                <div class="form-group">
                    <label class="form-label" for="planMinutes">Minutes</label>
                    <input type="number" id="planMinutes" class="form-control" placeholder="0" min="0" max="59">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary" style="width:100%;">Add Plan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Plans List -->
<div id="plansList" class="plans-grid">
    <div style="color:var(--on-surface-med);">Loading plans...</div>
</div>

<style>
/* Plans grid */
.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

/* Plan cards */
.plan-card {
    background: var(--surface);
    border: 1px solid var(--surface-4);
    border-radius: var(--radius-lg);
    padding: 30px 20px;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: var(--shadow-1);
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
}

.plan-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-3);
    border-color: var(--blue-300);
}

.plan-card h5 {
    font-size: 1.4rem;
    margin-bottom: 15px;
    color: var(--on-surface);
    font-family: 'Google Sans', sans-serif;
    font-weight: 500;
}

.plan-card p {
    font-size: 1.1rem;
    color: var(--on-surface-med);
    margin: 0;
    font-weight: 500;
}

/* Delete button */
.plan-card .delete-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--red);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background var(--transition);
}
.plan-card .delete-btn:hover { background: #C62828; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .plan-card { min-height: 180px; }
}
@media (max-width: 500px) {
    .plan-card { min-height: 160px; }
}
</style>

<script>
const plansApi = '/api/plans.php';

// Load plans
async function loadPlans() {
    const res = await fetch(plansApi);
    const data = await res.json();
    const container = document.getElementById('plansList');
    container.innerHTML = '';

    if (!data.success || !data.plans.length) {
        container.innerHTML = `<div style="color:var(--on-surface-med);">No plans available</div>`;
        return;
    }

    data.plans.forEach(plan => {
        const div = document.createElement('div');
        div.className = 'plan-card';
        div.innerHTML = `
            <button class="delete-btn" onclick="deletePlan(${plan.id})">&times;</button>
            <h5>${plan.name}</h5>
            <p>${plan.days}d ${plan.hours}h ${plan.minutes}m</p>
        `;
        container.appendChild(div);
    });
}

// Add plan
document.getElementById('planForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('planName').value.trim();
    const days = parseInt(document.getElementById('planDays').value) || 0;
    const hours = parseInt(document.getElementById('planHours').value) || 0;
    const minutes = parseInt(document.getElementById('planMinutes').value) || 0;

    const res = await fetch(plansApi, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, days, hours, minutes })
    });
    const data = await res.json();

    if (data.success) {
        document.getElementById('planForm').reset();
        loadPlans();
    } else {
        alert(data.error || 'Failed to create plan');
    }
});

// Delete plan
async function deletePlan(id) {
    if (!confirm('Are you sure you want to delete this plan?')) return;

    const res = await fetch(plansApi, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (data.success) loadPlans();
    else alert(data.error || 'Failed to delete plan');
}

// Initial load
loadPlans();
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
