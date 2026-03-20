<?php
// ── search.php — Live search API for admin dashboard ─────────────────────
// Returns JSON results for visitor logs and visitors table
session_start();
require 'db.php';

// Must be logged in as admin
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$type   = $_GET['type']   ?? 'logs';   // 'logs' or 'visitors'
$search = trim($_GET['q'] ?? '');
$period = $_GET['period'] ?? 'today';
$from   = $_GET['from']   ?? '';
$to     = $_GET['to']     ?? '';

// ── Search Logs ───────────────────────────────────────────────────────────
if ($type === 'logs') {
    $where  = "1=1";
    $params = [];
    $types  = "";

    if ($search !== '') {
        $s = "%{$search}%";
        $where .= " AND (name LIKE ? OR email LIKE ? OR program LIKE ? OR reason LIKE ? OR year_level LIKE ?)";
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
        $types .= "sssss";
    }

    if ($period === 'today')
        $where .= " AND DATE(timestamp) = CURDATE()";
    elseif ($period === 'week')
        $where .= " AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    elseif ($period === 'month')
        $where .= " AND MONTH(timestamp)=MONTH(NOW()) AND YEAR(timestamp)=YEAR(NOW())";
    elseif ($period === 'range' && $from && $to) {
        $where .= " AND DATE(timestamp) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types   .= "ss";
    }

    $sql  = "SELECT * FROM visitor_logs WHERE $where ORDER BY timestamp DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($r = $result->fetch_assoc()) {
        $rows[] = [
            'name'       => $r['name'],
            'email'      => $r['email'],
            'rfid'       => $r['rfid'],
            'year_level' => $r['year_level'],
            'program'    => $r['program'],
            'type'       => $r['type'],
            'reason'     => $r['reason'],
            'timestamp'  => date('M j, Y g:i A', strtotime($r['timestamp']))
        ];
    }

    echo json_encode(['count' => count($rows), 'rows' => $rows]);
}

// ── Search Visitors ───────────────────────────────────────────────────────
elseif ($type === 'visitors') {
    $where  = "1=1";
    $params = [];
    $types  = "";

    if ($search !== '') {
        $s = "%{$search}%";
        $where .= " AND (name LIKE ? OR email LIKE ? OR rfid LIKE ? OR program LIKE ? OR year_level LIKE ?)";
        $params = [$s, $s, $s, $s, $s];
        $types  = "sssss";
    }

    $sql  = "SELECT * FROM visitors WHERE $where ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($v = $result->fetch_assoc()) {
        $rows[] = [
            'id'         => $v['id'],
            'rfid'       => $v['rfid'],
            'name'       => $v['name'],
            'email'      => $v['email'],
            'year_level' => $v['year_level'],
            'program'    => $v['program'],
            'type'       => $v['type'],
            'blocked'    => (bool)$v['blocked']
        ];
    }

    echo json_encode(['count' => count($rows), 'rows' => $rows]);
}
?>