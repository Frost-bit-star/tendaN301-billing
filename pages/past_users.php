<?php
ob_start();
$pageTitle = 'Past & Dead Users';
$activePage = 'past_users';

include __DIR__ . '/../components/header.php';

$db = new PDO('sqlite:' . __DIR__ . '/../db/routers.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tenantId = $_SESSION['account_id'] ?? null;
$isAdmin  = ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'admin');

$query = trim($_GET['q'] ?? '');
$digitsOnly = preg_replace('/[^0-9]/', '', $query);

$searchTerm = $query !== '' ? '%' . $query . '%' : null;
$searchDigits = $digitsOnly !== '' ? '%' . $digitsOnly . '%' : null;

// Routers scoped to tenant (used to restrict results)
$routerScope = '';
if (!$isAdmin) {
    $routerScope = ' AND r.tenant_id = :tenant';
}

$params = [];
$billingWhere = [];
$voucherWhere = [];

if ($searchDigits !== null) {
    $billingWhere[] = 'REPLACE(REPLACE(REPLACE(REPLACE(b.phone_number," ",""),"-",""),"(",""),")","") LIKE :digits';
    $voucherWhere[] = 'REPLACE(REPLACE(REPLACE(REPLACE(v.phone," ",""),"-",""),"(",""),")","") LIKE :digits';
    $params[':digits'] = $searchDigits;
} elseif ($searchTerm !== null) {
    $billingWhere[] = '(b.phone_number LIKE :q OR b.name LIKE :q)';
    $voucherWhere[] = '(v.phone LIKE :q OR v.customer_name LIKE :q)';
    $params[':q'] = $searchTerm;
}

$billingWhereSql = $billingWhere ? ' AND ' . implode(' AND ', $billingWhere) : '';
$voucherWhereSql = $voucherWhere ? ' AND ' . implode(' AND ', $voucherWhere) : '';

if (!$isAdmin) $params[':tenant'] = $tenantId;

$stmt = $db->prepare("
    SELECT
        b.id                        AS billing_id,
        b.name                      AS name,
        b.phone_number              AS phone,
        b.mac                       AS mac,
        b.plan_id                   AS plan_id,
        b.internet_access           AS internet_access,
        b.created_at                AS created_at,
        b.end_at                    AS end_at,
        p.name                      AS plan_name,
        r.name                      AS router_name,
        'billing'                   AS source
    FROM billing b
    LEFT JOIN plans   p ON b.plan_id = p.id
    LEFT JOIN routers r ON b.router_id = r.id
    WHERE 1=1
      AND (b.internet_access = 0 OR b.end_at IS NULL OR b.end_at < datetime('now'))
      AND b.phone_number IS NOT NULL AND b.phone_number != ''
      $routerScope
      $billingWhereSql

    UNION ALL

    SELECT
        v.id                        AS billing_id,
        v.customer_name             AS name,
        v.phone                     AS phone,
        v.used_mac                  AS mac,
        v.plan_id                   AS plan_id,
        NULL                        AS internet_access,
        v.used_at                   AS created_at,
        v.expires_at                AS end_at,
        p.name                      AS plan_name,
        r.name                      AS router_name,
        'voucher'                   AS source
    FROM vouchers v
    LEFT JOIN plans   p ON v.plan_id = p.id
    LEFT JOIN routers r ON v.router_id = r.id
    WHERE 1=1
      AND v.status IN ('used', 'expired')
      AND v.phone IS NOT NULL AND v.phone != ''
      $routerScope
      $voucherWhereSql

    ORDER BY end_at DESC
    LIMIT 500
");

$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tenant: if scoped, join column alias references r.* which is fine above.
// Classify each row as expired / disabled / dead based on its data.
$now = time();
foreach ($users as &$u) {
    $endTs = $u['end_at'] ? strtotime($u['end_at']) : null;
    if ($u['source'] === 'voucher') {
        $u['chip']   = 'expired';
        $u['status'] = 'Dead';
    } elseif ((int)$u['internet_access'] === 0 && $endTs !== false && $endTs !== null && $endTs <= $now) {
        $u['chip']   = 'expired';
        $u['status'] = 'Expired';
    } elseif ((int)$u['internet_access'] === 0) {
        $u['chip']   = 'inactive';
        $u['status'] = 'Disabled';
    } elseif ($endTs !== null && $endTs <= $now) {
        $u['chip']   = 'expired';
        $u['status'] = 'Expired';
    } else {
        $u['chip']   = 'info';
        $u['status'] = 'Past';
    }
}
unset($u);
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-history"></i> Past &amp; Dead Users</h1>
        <p class="page-subtitle">Users whose access has ended — searchable by mobile number.</p>
    </div>
</div>

<div class="card" style="margin-bottom:24px">
    <div class="card-body">
        <form method="GET" action="past_users" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <div style="position:relative;flex:1;min-width:220px">
                <svg viewBox="0 0 24 24" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:16px;height:16px;fill:var(--on-surface-med)"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" name="q" class="form-control" style="padding-left:32px"
                       placeholder="Search by mobile number (e.g. 0758 224 994)…"
                       value="<?php echo htmlspecialchars($query); ?>" autocomplete="off">
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if ($query !== ''): ?>
                <a href="past_users" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom:24px">
    <i class="fas fa-user-slash"></i>
    <span><strong><?php echo count($users); ?> past/dead user(s)</strong> found
    <?php echo $query !== '' ? 'for "' . htmlspecialchars($query) . '"' : 'in the database'; ?></span>
</div>

<?php if ($users): ?>
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone Number</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Router</th>
                    <th>MAC Address</th>
                    <th>Started</th>
                    <th>Ended</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-weight:500"><?php echo htmlspecialchars($u['name'] ?: '—'); ?></td>
                    <td style="font-family:'Courier New',monospace;font-weight:600;letter-spacing:1px"><?php echo htmlspecialchars($u['phone']); ?></td>
                    <td><span class="chip <?php echo $u['chip']; ?>"><span class="chip-dot"></span><?php echo $u['status']; ?></span></td>
                    <td><?php echo htmlspecialchars($u['plan_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($u['router_name'] ?: '—'); ?></td>
                    <td><code><?php echo htmlspecialchars($u['mac'] ?: '—'); ?></code></td>
                    <td style="font-size:12px;color:var(--on-surface-med)"><?php echo htmlspecialchars($u['created_at'] ?: '—'); ?></td>
                    <td style="font-size:12px;color:var(--on-surface-med)"><?php echo htmlspecialchars($u['end_at'] ?: '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="empty-state" style="padding:60px">
    <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
    <h3>No past or dead users found</h3>
    <p><?php echo $query !== '' ? 'No records match that search. Try a different number.' : 'There are no expired or dead users in the database yet.'; ?></p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../components/footer.php'; ?>
<?php ob_end_flush(); ?>
