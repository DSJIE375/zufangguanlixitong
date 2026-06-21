<?php
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => '请先登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_contract') {
    $contract_id = intval($_POST['contract_id'] ?? 0);
    
    if (!$contract_id) {
        echo json_encode(['success' => false, 'error' => '无效的合同ID']);
        exit;
    }
    
    if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['contract_file']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/../uploads/contracts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newname = 'contract_' . $contract_id . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $newname;
            
            if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $uploadPath)) {
                $relativePath = 'uploads/contracts/' . $newname;
                $conn->query("UPDATE contracts SET contract_file = '$relativePath' WHERE id = $contract_id");
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => '文件上传失败']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => '不支持的文件格式']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => '请选择文件']);
    }
} else {
    echo json_encode(['success' => false, 'error' => '无效请求']);
}
