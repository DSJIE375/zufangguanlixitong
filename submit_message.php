<?php
require_once 'includes/database.php';
require_once 'includes/notify.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    // 验证输入
    if (empty($name) || empty($phone) || empty($content)) {
        header("Location: index.php?msg=empty#contact");
        exit;
    }
    
    // 验证电话格式
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        header("Location: index.php?msg=invalid_phone#contact");
        exit;
    }
    
    // 限制输入长度
    if (mb_strlen($name) > 50 || mb_strlen($content) > 1000) {
        header("Location: index.php?msg=too_long#contact");
        exit;
    }
    
    // 使用准备语句
    $stmt = $conn->prepare("INSERT INTO messages (name, phone, content) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $name, $phone, $content);
        if ($stmt->execute()) {
            // 发送通知
            notifyNewMessage($name, $phone, $content);
            header("Location: index.php?msg=success#contact");
            exit;
        }
        $stmt->close();
    }
}

header("Location: index.php#contact");
exit;
