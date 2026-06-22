<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_signature') {
    $contract_id = intval($_POST['contract_id'] ?? 0);
    $signature_data = $_POST['signature_data'] ?? '';
    
    if (!$contract_id) {
        echo json_encode(['success' => false, 'error' => '无效的合同ID']);
        exit;
    }
    
    if (empty($signature_data)) {
        echo json_encode(['success' => false, 'error' => '签名数据为空']);
        exit;
    }
    
    // 将base64图片保存为文件
    $data = explode(',', $signature_data);
    if (count($data) == 2) {
        $imageData = base64_decode($data[1]);
        $filename = 'signature_' . $contract_id . '_' . time() . '.png';
        $uploadDir = __DIR__ . '/../uploads/signatures/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $uploadPath = $uploadDir . $filename;
        if (file_put_contents($uploadPath, $imageData)) {
            $relativePath = 'uploads/signatures/' . $filename;
            $conn->query("UPDATE contracts SET signature = '$relativePath' WHERE id = $contract_id");
            echo json_encode(['success' => true, 'path' => $relativePath]);
        } else {
            echo json_encode(['success' => false, 'error' => '保存图片失败']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => '无效的签名数据']);
    }
} else {
    echo json_encode(['success' => false, 'error' => '无效请求']);
}
