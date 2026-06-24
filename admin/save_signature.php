<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_signature') {
    $contract_id = intval($_POST['contract_id'] ?? 0);
    $signature_type = $_POST['signature_type'] ?? 'tenant';
    $signature_data = $_POST['signature_data'] ?? '';
    
    if (!$contract_id) {
        echo json_encode(['success' => false, 'error' => '无效的合同ID']);
        exit;
    }
    
    if (empty($signature_data)) {
        echo json_encode(['success' => false, 'error' => '签名数据为空']);
        exit;
    }
    
    $data = explode(',', $signature_data);
    if (count($data) == 2) {
        $imageData = base64_decode($data[1]);
        if ($imageData === false) {
            echo json_encode(['success' => false, 'error' => '无效的签名数据']);
            exit;
        }
        
        $prefix = $signature_type === 'owner' ? 'owner_sign' : 'sign';
        $filename = $prefix . '_' . $contract_id . '_' . time() . '.png';
        $uploadDir = __DIR__ . '/../uploads/signatures/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $uploadPath = $uploadDir . $filename;
        if (file_put_contents($uploadPath, $imageData)) {
            $relativePath = 'uploads/signatures/' . $filename;
            $field = $signature_type === 'owner' ? 'owner_signature' : 'signature';
            
            $stmt = $conn->prepare("UPDATE contracts SET $field = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $relativePath, $contract_id);
                $stmt->execute();
                $stmt->close();
            }
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
