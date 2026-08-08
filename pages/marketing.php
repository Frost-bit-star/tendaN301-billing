<?php
ob_start();
$pageTitle = 'Marketing';
$activePage = 'marketing';
include __DIR__ . '/../components/header.php';
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
    color: var(--on-surface-med);
    text-align: right;
    margin-top: 4px;
}
.msg-counter.over { color: var(--red); font-weight: 600; }
</style>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-bullhorn"></i> Marketing</h1>
        <p class="page-subtitle">Send promotional SMS messages to customers who have used your service.</p>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-users"></i> Customers</span>
            <span class="chip info" id="customerCount">0</span>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th class="select-col"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th>Phone</th>
                            <th>Customer</th>
                            <th>Last Used</th>
                            <th>Plan</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <tr><td colspan="5" style="text-align:center;color:var(--on-surface-med);padding:24px;">Loading customers...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-comment"></i> Compose Message</span>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Recipients</label>
                <div id="recipientCount" style="color:var(--on-surface-med);font-size:12px;">No customers selected</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="msgText">Message</label>
                <textarea class="form-control" id="msgText" rows="5" placeholder="Type your promotional message here..." maxlength="160" oninput="updateMsgCounter()"></textarea>
                <div class="msg-counter" id="msgCounter">0 / 160</div>
            </div>
            <button class="btn btn-primary" style="width:100%;" id="sendBtn" onclick="sendMessage()" disabled>
                <i class="fas fa-paper-plane"></i> Send
            </button>
            <div id="sendResult" style="display:none;margin-top:12px;"></div>
        </div>
    </div>
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
        document.getElementById('customerTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--red);padding:24px;">Failed to load customers</td></tr>';
    }
}

function renderCustomers() {
    const tbody = document.getElementById('customerTableBody');
    if (allCustomers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--on-surface-med);padding:24px;">No customers found</td></tr>';
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
                html += '<ul style="font-size:12px;color:var(--red);margin:0;">';
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
