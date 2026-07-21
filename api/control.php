<?php
header('Content-Type: application/json');
session_start();

$dbPath = __DIR__ . '/../db/routers.db';

function isRouterOnline($ip, $port = 80, $timeout = 2) {
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tenant scope: account users see only their routers, admins see all
    $tenantId = $_SESSION['account_id'] ?? null;
    $isAdmin  = ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'admin');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        if (!empty($input['delete']) && !empty($input['id'])) {
            if ($isAdmin) {
                $stmt = $db->prepare("DELETE FROM routers WHERE id = :id");
                $stmt->execute([':id' => $input['id']]);
            } else {
                $stmt = $db->prepare("DELETE FROM routers WHERE id = :id AND tenant_id = :tid");
                $stmt->execute([':id' => $input['id'], ':tid' => $tenantId]);
            }
            echo json_encode(['success' => true, 'message' => 'Router deleted successfully.']);
            exit;
        }

        $required = ['name', 'ip', 'port', 'password'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing field: $field"]);
                exit;
            }
        }

        $name = trim($input['name']);
        $ip   = trim($input['ip']);
        $port = intval($input['port']);
        $pass = trim($input['password']);

        if ($isAdmin) {
            $stmt = $db->prepare("SELECT id FROM routers WHERE name = :name");
            $stmt->execute([':name' => $name]);
        } else {
            $stmt = $db->prepare("SELECT id FROM routers WHERE name = :name AND tenant_id = :tid");
            $stmt->execute([':name' => $name, ':tid' => $tenantId]);
        }
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $db->prepare("UPDATE routers SET ip = :ip, port = :port, password = :password WHERE id = :id");
            $stmt->execute([':ip' => $ip, ':port' => $port, ':password' => $pass, ':id' => $existing['id']]);
            $message = "Router '$name' updated successfully.";
        } else {
            $stmt = $db->prepare("INSERT INTO routers (name, ip, port, password, tenant_id) VALUES (:name, :ip, :port, :password, :tid)");
            $stmt->execute([':name' => $name, ':ip' => $ip, ':port' => $port, ':password' => $pass, ':tid' => $tenantId]);
            $message = "Router '$name' added successfully.";
        }

        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }

    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($isAdmin) {
            $stmt = $db->query("SELECT id, name, ip, port FROM routers ORDER BY name ASC");
        } else {
            $stmt = $db->prepare("SELECT id, name, ip, port FROM routers WHERE tenant_id = ? ORDER BY name ASC");
            $stmt->execute([$tenantId]);
        }
        $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($routers as &$router) {
            $router['online'] = isRouterOnline($router['ip'], $router['port'] ?: 80);
        }

        echo json_encode(['success' => true, 'routers' => $routers]);
        exit;
    }

    else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
