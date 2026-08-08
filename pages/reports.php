<?php
ob_start();

$pageTitle = 'Database Inspector';
$activePage = 'reports';

include __DIR__ . '/../components/header.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/../db/routers.db';
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-database"></i> SQLite Database Inspector</h1>
        <p class="page-subtitle">Inspect tables, schema and sample data from the routers database.</p>
    </div>
</div>

<?php
if (!file_exists($dbPath)) {
    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Database file not found at: $dbPath</div>";
    include __DIR__ . '/../components/footer.php';
    ob_end_flush();
    exit;
}

echo "<div class='card' style='margin-bottom:20px;'>
        <div class='card-body'>
            <strong>Database Path:</strong> $dbPath<br>
            <strong>File Size:</strong> " . round(filesize($dbPath) / 1024, 2) . " KB
        </div>
      </div>";

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $version = $db->query("SELECT sqlite_version()")->fetchColumn();

    echo "<div class='card' style='margin-bottom:20px;'>
            <div class='card-body'>
                <strong style='color:var(--green)'>Connection Status:</strong> Connected Successfully<br>
                <strong>SQLite Version:</strong> $version
            </div>
          </div>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Connection Failed: " . $e->getMessage() . "</div>";
    include __DIR__ . '/../components/footer.php';
    ob_end_flush();
    exit;
}

// Get tables
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
             ->fetchAll(PDO::FETCH_ASSOC);

if (!$tables) {
    echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> No tables found in this database.</div>";
}

foreach ($tables as $table) {

    $tableName = $table['name'];
    $rowCount = $db->query("SELECT COUNT(*) FROM $tableName")->fetchColumn();

    echo "<div class='card' style='margin-bottom:20px;'>
            <div class='card-header'>
                <span class='card-title'>Table: $tableName</span>
            </div>
            <div class='card-body'>
                <p><strong>Row Count:</strong> $rowCount</p>";

    // Columns
    $columns = $db->query("PRAGMA table_info($tableName)")
                  ->fetchAll(PDO::FETCH_ASSOC);

    echo "<h6 style='margin:0 0 8px;font-weight:600;'>Columns</h6>";
    echo "<div class='table-wrapper'>";
    echo "<table>";
    echo "<thead>
            <tr>
                <th>Column</th>
                <th>Type</th>
                <th>Not Null</th>
                <th>Default</th>
                <th>Primary Key</th>
            </tr>
          </thead><tbody>";

    foreach ($columns as $col) {
        echo "<tr>
                <td>{$col['name']}</td>
                <td>{$col['type']}</td>
                <td>" . ($col['notnull'] ? 'YES' : 'NO') . "</td>
                <td>{$col['dflt_value']}</td>
                <td>" . ($col['pk'] ? 'YES' : 'NO') . "</td>
              </tr>";
    }

    echo "</tbody></table></div>";

    // Sample Data
    if ($rowCount > 0) {
        $rows = $db->query("SELECT * FROM $tableName LIMIT 5")
                   ->fetchAll(PDO::FETCH_ASSOC);

        echo "<h6 style='margin:20px 0 8px;font-weight:600;'>Sample Data (First 5 Rows)</h6>";
        echo "<div class='table-wrapper'>";
        echo "<table><thead><tr>";

        foreach (array_keys($rows[0]) as $header) {
            echo "<th>$header</th>";
        }

        echo "</tr></thead><tbody>";

        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }

        echo "</tbody></table></div>";
    }

    echo "</div></div>";
}
?>

<?php
include __DIR__ . '/../components/footer.php';
ob_end_flush();
?>
