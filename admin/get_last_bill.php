<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

$contract_id = intval($_GET['contract_id'] ?? 0);
if (!$contract_id) {
    echo json_encode(['success' => false, 'error' => '无效的合同ID']);
    exit;
}

// 先获取房间ID
$contract = $conn->query("SELECT room_id FROM contracts WHERE id = $contract_id")->fetch_assoc();
if (!$contract) {
    echo json_encode(['success' => false, 'error' => '合同不存在']);
    exit;
}

$room_id = $contract['room_id'];

// 获取该房间最近一次账单的水电读数（通过房间关联的所有合同）
$result = $conn->query("SELECT b.water_end, b.elec_end 
    FROM bills b
    JOIN contracts c ON b.contract_id = c.id
    WHERE c.room_id = $room_id 
    ORDER BY b.bill_month DESC 
    LIMIT 1");

if ($result && $result->num_rows > 0) {
    $bill = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'water_end' => floatval($bill['water_end']),
        'elec_end' => floatval($bill['elec_end'])
    ]);
} else {
    echo json_encode([
        'success' => true,
        'water_end' => 0,
        'elec_end' => 0
    ]);
}
