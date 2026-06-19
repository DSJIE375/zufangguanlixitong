<?php
require_once 'includes/database.php';
require_once 'includes/notify.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    
    if ($name && $phone && $content) {
        $sql = "INSERT INTO messages (name, phone, content) VALUES ('$name', '$phone', '$content')";
        if ($conn->query($sql)) {
            // 发送通知
            notifyNewMessage($name, $phone, $content);
            header("Location: index.php?msg=success#contact");
            exit;
        }
    }
}

header("Location: index.php#contact");
exit;
