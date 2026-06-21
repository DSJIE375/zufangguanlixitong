<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

$bill_id = intval($_POST['bill_id'] ?? 0);
$expire_type = $_POST['expire_type'] ?? 'permanent';
$expire_hours = intval($_POST['expire_hours'] ?? 24);

if (!$bill_id) {
    echo json_encode(['success' => false, 'error' => '无效的账单ID']);
    exit;
}

$token = bin2hex(random_bytes(16));

if ($expire_type == 'permanent') {
    $expire_at = 'NULL';
} else {
    $expire_at = "'" . date('Y-m-d H:i:s', strtotime("+{$expire_hours} hours")) . "'";
}

$sql = "INSERT INTO share_links (bill_id, token, expire_at) VALUES ($bill_id, '$token', $expire_at)";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'token' => $token]);
} else {
    echo json_encode(['success' => false, 'error' => '创建失败']);
}
