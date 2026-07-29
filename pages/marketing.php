<?php
ob_start();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>
<style>
.customer-phone {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    letter-spacing: 1px;
}
.select-col { width: 40px; }
.msg-counter {
    font-size: 0.8rem;
    color: #6c757d;
    text-align: right;
    margin-top: 4px;
}
.msg-counter.over { color: #dc3545; font-weight: 600; }
</style>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid">

<h2 class="mt-4 mb-2"><i class="fas fa-bullhorn text-primary"></i> Marketing</h2>
<p class="text-muted mb-4">Send promotional SMS messages to customers who have used your service.</p>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users"></i> Customers</h5>
                <span class="badge badge-light" id="customerCount">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="select-col"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                <th>Phone</th>
                                <th>Customer</th>
                                <th>Last Used</th>
                                <th>Plan</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Loading customers...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-comment"></i> Compose Message</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Recipients</label>
                    <div id="recipientCount" class="text-muted small">No customers selected</div>
                </div>
                <div class="form-group">
                    <label for="msgText">Message</label>
                    <textarea class="form-control" id="msgText" rows="5" placeholder="Type your promotional message here..." maxlength="160" oninput="updateMsgCounter()"></textarea>
                    <div class="msg-counter" id="msgCounter">0 / 160</div>
                </div>
                <button class="btn btn-primary btn-block btn-lg" id="sendBtn" onclick="sendMessage()" disabled>
                    <i class="fas fa-paper-plane"></i> Send
                </button>
                <div id="sendResult" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

</div>
</section>
</div>

<script>
let allCustomers = [];

async function loadCustomers() {
    try {
        const res = await fetch('/api/marketing.php?action=customers');
        const data = await res.json();
        allCustomers = data.customers || [];
        renderCustomers();
        document.getElementById('customerCount').textContent = data.total || 0;
    } catch (e) {
        document.getElementById('customerTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load customers</td></tr>';
    }
}

function renderCustomers() {
    const tbody = document.getElementById('customerTableBody');
    if (allCustomers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No customers found</td></tr>';
        return;
    }
    tbody.innerHTML = allCustomers.map((c, i) => `
        <tr>
            <td class="select-col"><input type="checkbox" class="customer-cb" value="${escapeHtml(c.phone)}" data-index="${i}" onchange="updateRecipients()"></td>
            <td><span class="customer-phone">${escapeHtml(c.phone)}</span></td>
            <td>${escapeHtml(c.customer_name || '—')}</td>
            <td>${c.used_at ? new Date(c.used_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) : '—'}</td>
            <td>${escapeHtml(c.plan_name || '—')}</td>
        </tr>
    `).join('');
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.customer-cb').forEach(cb => cb.checked = checked);
    updateRecipients();
}

function updateRecipients() {
    const checked = document.querySelectorAll('.customer-cb:checked');
    const count = checked.length;
    document.getElementById('recipientCount').textContent = count > 0 ? `${count} customer${count > 1 ? 's' : ''} selected` : 'No customers selected';
    document.getElementById('sendBtn').disabled = count === 0;
}

function updateMsgCounter() {
    const len = document.getElementById('msgText').value.length;
    const el = document.getElementById('msgCounter');
    el.textContent = len + ' / 160';
    el.className = 'msg-counter' + (len > 160 ? ' over' : '');
}

async function sendMessage() {
    const checked = document.querySelectorAll('.customer-cb:checked');
    const numbers = Array.from(checked).map(cb => cb.value);
    const message = document.getElementById('msgText').value.trim();

    if (numbers.length === 0) { alert('Select at least one customer'); return; }
    if (!message) { alert('Enter a message'); return; }

    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    document.getElementById('sendResult').style.display = 'none';

    try {
        const res = await fetch('/api/marketing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send', numbers, message })
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.error || 'Send failed');

        const resultDiv = document.getElementById('sendResult');
        resultDiv.style.display = 'block';

        let html = '';
        if (data.sent > 0) {
            html += `<div class="alert alert-success py-2 mb-1"><i class="fas fa-check-circle"></i> Sent to ${data.sent} recipient${data.sent > 1 ? 's' : ''}</div>`;
        }
        if (data.failed > 0) {
            html += `<div class="alert alert-danger py-2 mb-1"><i class="fas fa-exclamation-circle"></i> Failed: ${data.failed}</div>`;
            if (data.errors) {
                html += '<ul class="small text-danger mb-0">';
                data.errors.forEach(e => { html += `<li>${escapeHtml(e.number)}: ${escapeHtml(e.error)}</li>`; });
                html += '</ul>';
            }
        }
        if (data.sent === 0 && data.failed === 0) {
            html = '<div class="alert alert-warning py-2">No messages sent</div>';
        }
        resultDiv.innerHTML = html;
    } catch (e) {
        document.getElementById('sendResult').style.display = 'block';
        document.getElementById('sendResult').innerHTML = `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadCustomers();
</script>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
