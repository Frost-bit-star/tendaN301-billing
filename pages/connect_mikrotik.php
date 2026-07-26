<?php
ob_start();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>
<style>
.step-wizard { display: flex; justify-content: center; margin-bottom: 2rem; }
.step-wizard .step { display: flex; align-items: center; gap: 0.5rem; }
.step-wizard .step-number {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.9rem; border: 2px solid #dee2e6; color: #6c757d; background: #fff;
    transition: all 0.3s;
}
.step-wizard .step.active .step-number { border-color: #007bff; background: #007bff; color: #fff; }
.step-wizard .step.completed .step-number { border-color: #28a745; background: #28a745; color: #fff; }
.step-wizard .step-label { font-size: 0.8rem; color: #6c757d; }
.step-wizard .step.active .step-label { color: #007bff; font-weight: 600; }
.step-wizard .step.completed .step-label { color: #28a745; }
.step-wizard .step-connector { width: 60px; height: 2px; background: #dee2e6; margin: 0 0.5rem; align-self: center; }
.step-wizard .step-connector.active { background: #28a745; }

.step-content { display: none; }
.step-content.active { display: block; }

.code-block {
    background: #1a1a2e; color: #00ff41; padding: 1rem 1.2rem;
    border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.85rem;
    position: relative; overflow-x: auto; line-height: 1.6; border: 1px solid #333;
}
.code-block .copy-btn {
    position: absolute; top: 8px; right: 8px;
    background: rgba(255,255,255,0.1); color: #aaa; border: 1px solid #555;
    padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.75rem;
}
.code-block .copy-btn:hover { background: rgba(255,255,255,0.2); color: #fff; }

.status-indicator {
    display: inline-block; width: 12px; height: 12px; border-radius: 50%;
    margin-right: 8px; animation: pulse 2s infinite;
}
.status-indicator.offline { background: #dc3545; }
.status-indicator.provisioning { background: #ffc107; }
.status-indicator.online { background: #28a745; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.config-card {
    border: 1px solid #dee2e6; border-radius: 8px; padding: 1.2rem;
    margin-bottom: 1rem; background: #f8f9fa;
}
.config-card h6 { margin-bottom: 0.5rem; color: #495057; }

.wizard-nav .btn { min-width: 140px; }

.service-toggle {
    display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem;
    border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 0.5rem; background: #fff;
}
.service-toggle label { margin: 0; font-weight: 500; flex: 1; }
.service-toggle input[type="number"] { width: 100px; }
</style>

<div class="content-wrapper">
<section class="content">
<div class="container-fluid" style="max-width: 900px; margin: 0 auto;">

<h2 class="mt-4 mb-2"><i class="fas fa-plug text-primary"></i> Connect MikroTik Device</h2>
<p class="text-muted mb-4">Register your device in 3 steps: device details, basic provisioning, and service configuration.</p>

<!-- Step Indicator -->
<div class="step-wizard" id="stepWizard">
    <div class="step active" data-step="1">
        <div class="step-number">1</div>
        <span class="step-label">Device Details</span>
    </div>
    <div class="step-connector"></div>
    <div class="step" data-step="2">
        <div class="step-number">2</div>
        <span class="step-label">Basic Provisioning</span>
    </div>
    <div class="step-connector"></div>
    <div class="step" data-step="3">
        <div class="step-number">3</div>
        <span class="step-label">Service Configuration</span>
    </div>
</div>

<!-- STEP 1: Device Details -->
<div class="step-content active" id="step1">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Step 1: Device Details</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Name and location</p>
            <form id="deviceForm">
                <div class="form-group">
                    <label for="deviceName">Device Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="deviceName" placeholder="e.g. sirari-mt-49152" required>
                    <small class="text-muted">A unique name for this MikroTik router</small>
                </div>
                <div class="form-group">
                    <label for="deviceLocation">Location</label>
                    <input type="text" class="form-control" id="deviceLocation" placeholder="e.g. Sirari, Geita">
                    <small class="text-muted">Physical location of the device</small>
                </div>
                <div class="form-group">
                    <label for="deviceLanIP">Router LAN IP <small class="text-muted">(for local testing without WireGuard)</small></label>
                    <input type="text" class="form-control" id="deviceLanIP" placeholder="e.g. 192.168.88.130">
                    <small class="text-muted">If set, dashboard checks connectivity via this IP instead of WireGuard</small>
                </div>
                <div class="wizard-nav text-right">
                    <button type="submit" class="btn btn-primary" id="registerBtn">
                        Register Device <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- STEP 2: Basic Provisioning -->
<div class="step-content" id="step2">
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-terminal"></i> Step 2: Basic Provisioning</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Connect to your MikroTik via Winbox or SSH, then run both terminal commands below in order.</p>

            <div id="localModeNotice" class="alert alert-info" style="display:none;">
                <i class="fas fa-info-circle"></i> <strong>Local Mode:</strong> Your server is running on a local network. WireGuard VPN will be skipped. The MikroTik connects directly to this server.
            </div>

            <div class="mb-4">
                <h6><span class="badge badge-primary">1</span> Enable advanced device mode and fetch (RouterOS 7+)</h6>
                <p class="text-muted small">New routers start in basic mode and block <code>/tool fetch</code> until device-mode allows it. Run this once.</p>
                <div class="code-block">
                    <button class="copy-btn" onclick="copyCode('cmd1')"><i class="fas fa-copy"></i> Copy</button>
                    <code id="cmd1">/system device-mode update mode=advanced fetch=yes</code>
                </div>
            </div>

            <div class="mb-4">
                <h6><span class="badge badge-primary">2</span> Download and import Jasiri script</h6>
                <p class="text-muted small">Fetches the provisioning script from Jasiri and imports it. Wait 1–2 minutes for completion.</p>
                <div class="code-block">
                    <button class="copy-btn" onclick="copyCode('cmd2')"><i class="fas fa-copy"></i> Copy</button>
                    <code id="cmd2"></code>
                </div>
            </div>

            <!-- Connection Status -->
            <div class="card mt-4" id="connectionStatusCard">
                <div class="card-body text-center">
                    <h6>Connection Status</h6>
                    <div id="statusDisplay">
                        <span class="status-indicator provisioning"></span>
                        <strong>Waiting for MikroTik</strong>
                        <p class="text-muted small mt-2 mb-0">After both Step 2 commands finish on the MikroTik, we'll detect when it's online over WireGuard.</p>
                    </div>
                    <div id="statusDetails" class="mt-3" style="display:none;">
                        <div class="row text-left">
                            <div class="col-md-6">
                                <small><strong>WireGuard IP:</strong> <span id="wgIPDisplay">—</span></small>
                            </div>
                            <div class="col-md-6">
                                <small><strong>Status:</strong> <span id="statusText">—</span></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wizard-nav d-flex justify-content-between mt-4">
                <button class="btn btn-secondary" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn btn-success" id="toStep3Btn" disabled onclick="goToStep(3)">
                    Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- STEP 3: Service Configuration -->
<div class="step-content" id="step3">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-cogs"></i> Step 3: Service Configuration</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Port assignment and services. Configure which services to enable on the device.</p>

            <div class="config-card">
                <h6><i class="fas fa-wifi text-primary"></i> Hotspot / PPPoE</h6>
                <div class="form-group mb-2">
                    <label>Service Mode</label>
                    <select class="form-control form-control-sm" id="serviceMode">
                        <option value="hotspot">Hotspot (Captive Portal)</option>
                        <option value="pppoe">PPPoE</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label>Bridge Interface</label>
                            <input type="text" class="form-control form-control-sm" value="jasiri-bridge" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label>IP Pool</label>
                            <input type="text" class="form-control form-control-sm" value="10.10.0.2-10.10.0.254" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="config-card">
                <h6><i class="fas fa-shield-alt text-warning"></i> Firewall &amp; Services</h6>
                <div class="service-toggle">
                    <label><i class="fas fa-lock"></i> API SSL (port 8729)</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="svcApiSsl" checked>
                        <label class="custom-control-label" for="svcApiSsl"></label>
                    </div>
                </div>
                <div class="service-toggle">
                    <label><i class="fas fa-terminal"></i> SSH (port 22)</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="svcSsh" checked>
                        <label class="custom-control-label" for="svcSsh"></label>
                    </div>
                </div>
                <div class="service-toggle">
                    <label><i class="fas fa-globe"></i> Webfig (port 80)</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="svcWeb" checked>
                        <label class="custom-control-label" for="svcWeb"></label>
                    </div>
                </div>
                <div class="service-toggle">
                    <label><i class="fas fa-satellite-dish"></i> SNMP (port 161)</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="svcSnmp" checked>
                        <label class="custom-control-label" for="svcSnmp"></label>
                    </div>
                </div>
                <div class="service-toggle">
                    <label><i class="fas fa-exchange-alt"></i> RADIUS CoA (port 3799)</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="svcRadius" checked>
                        <label class="custom-control-label" for="svcRadius"></label>
                    </div>
                </div>
            </div>

            <div class="config-card">
                <h6><i class="fas fa-file-download text-info"></i> Provisioning Script</h6>
                <p class="text-muted small mb-2">Download the generated RouterOS script for this device:</p>
                <button class="btn btn-outline-info btn-sm" id="downloadConfigBtn" onclick="downloadConfig()">
                    <i class="fas fa-download"></i> Download routersetup.txt
                </button>
            </div>

            <div class="wizard-nav d-flex justify-content-between mt-4">
                <button class="btn btn-secondary" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                <button class="btn btn-success btn-lg" id="finishBtn" onclick="finishSetup()">
                    <i class="fas fa-check-circle"></i> Finish Setup
                </button>
            </div>
        </div>
    </div>
</div>

</div>
</section>
</div>

<script>
let currentStep = 1;
let registeredRouter = null;
let statusPollInterval = null;

function goToStep(step) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');

    document.querySelectorAll('.step-wizard .step').forEach(el => {
        const s = parseInt(el.dataset.step);
        el.classList.remove('active', 'completed');
        if (s < step) el.classList.add('completed');
        if (s === step) el.classList.add('active');
    });
    document.querySelectorAll('.step-wizard .step-connector').forEach((el, i) => {
        el.classList.toggle('active', i < step - 1);
    });
    currentStep = step;
    window.scrollTo(0, 0);
}

function copyCode(id) {
    const text = document.getElementById(id).textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById(id).closest('.code-block').querySelector('.copy-btn');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i> Copy', 2000);
    });
}

document.getElementById('deviceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const name = document.getElementById('deviceName').value.trim();
    const location = document.getElementById('deviceLocation').value.trim();
    const lanIP = document.getElementById('deviceLanIP').value.trim();

    if (!name) return alert('Device name is required');

    const btn = document.getElementById('registerBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';

    try {
        const res = await fetch('/api/mikrotik.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register', name, location, lan_ip: lanIP })
        });
        const data = await res.json();

        if (!res.ok) throw new Error(data.error || 'Registration failed');

        registeredRouter = data;

        const serverHost = window.location.host || 'jasiri.stackverify.site';
        const scheme = window.location.protocol === 'https:' ? 'https' : 'http';
        const fetchMode = scheme === 'https' ? 'https' : 'http';
        const fetchCmd = `/tool fetch mode=${fetchMode} url="${scheme}://${serverHost}/provision/${data.provision_token}" dst-path=jasiri_${data.timestamp}.rsc; :delay 2s; /import jasiri_${data.timestamp}.rsc;`;
        document.getElementById('cmd2').textContent = fetchCmd;

        if (scheme === 'http' && window.location.hostname !== 'localhost') {
            document.getElementById('localModeNotice').style.display = 'block';
        }

        goToStep(2);
        startStatusPolling(data.router_id);

    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Register Device <i class="fas fa-arrow-right"></i>';
    }
});

function startStatusPolling(routerId) {
    if (statusPollInterval) clearInterval(statusPollInterval);

    statusPollInterval = setInterval(async () => {
        try {
            const res = await fetch(`/api/mikrotik.php?action=check_status&router_id=${routerId}`);
            const data = await res.json();

            const indicator = document.querySelector('#statusDisplay .status-indicator');
            const statusStrong = document.querySelector('#statusDisplay strong');
            const details = document.getElementById('statusDetails');

            indicator.className = 'status-indicator ' + data.status;

            if (data.status === 'online') {
                statusStrong.textContent = 'MikroTik Connected!';
                document.querySelector('#statusDisplay p').textContent = data.wireguard_ip && data.wireguard_ip !== '0.0.0.0' ? 'Secure connection established via WireGuard VPN tunnel.' : 'Device is reachable on the local network.';
                details.style.display = 'block';
                document.getElementById('wgIPDisplay').textContent = data.wireguard_ip || 'N/A (local mode)';
                document.getElementById('statusText').textContent = 'Online';
                document.getElementById('statusText').className = 'text-success';
                document.getElementById('toStep3Btn').disabled = false;
                clearInterval(statusPollInterval);
            } else if (data.status === 'provisioning') {
                statusStrong.textContent = 'Provisioning in progress...';
                document.querySelector('#statusDisplay p').textContent = 'Script has been served to the device. Waiting for it to come online.';
                details.style.display = 'none';
            } else {
                statusStrong.textContent = 'Waiting for MikroTik';
                document.querySelector('#statusDisplay p').textContent = 'After both Step 2 commands finish on the MikroTik, we\'ll detect when it\'s online over WireGuard.';
                details.style.display = 'none';
            }
        } catch (err) {
            console.error('Status check failed:', err);
        }
    }, 5000);
}

async function downloadConfig() {
    if (!registeredRouter) return alert('No device registered yet');
    window.open(`/api/mikrotik.php?action=provision_script&token=${registeredRouter.provision_token}`, '_blank');
}

async function finishSetup() {
    if (!registeredRouter) return;

    const btn = document.getElementById('finishBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
        const res = await fetch('/api/mikrotik.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_config',
                router_id: registeredRouter.router_id,
                services: {
                    api_ssl: document.getElementById('svcApiSsl').checked,
                    ssh: document.getElementById('svcSsh').checked,
                    web: document.getElementById('svcWeb').checked,
                    snmp: document.getElementById('svcSnmp').checked,
                    radius: document.getElementById('svcRadius').checked,
                    service_mode: document.getElementById('serviceMode').value
                }
            })
        });
        const data = await res.json();

        if (data.success) {
            alert('Setup complete! Your MikroTik device is now connected to Jasiri WiFi.');
            window.location.href = '/mikrotik_devices';
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Finish Setup';
    }
}
</script>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
