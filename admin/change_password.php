<?php
require_once '../includes/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCSRF();
    
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($new_password !== $confirm_password) {
        setFlash('error', '两次输入的密码不一致');
        redirect('settings.php');
    }
    
    if (strlen($new_password) < 6) {
        setFlash('error', '新密码长度不能少于6位');
        redirect('settings.php');
    }
    
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }
    
    if (!password_verify($old_password, $user['password'])) {
        setFlash('error', '当前密码错误');
        redirect('settings.php');
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            logAction('修改密码', '修改管理员密码');
            setFlash('success', '密码修改成功');
        } else {
            setFlash('error', '密码修改失败');
        }
        $stmt->close();
    }
    
    redirect('settings.php');
}
