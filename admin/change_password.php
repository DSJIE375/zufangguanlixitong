<?php
require_once '../includes/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    $result = $conn->query("SELECT password FROM users WHERE id = $user_id");
    $user = $result->fetch_assoc();
    
    if (!password_verify($old_password, $user['password'])) {
        setFlash('error', '当前密码错误');
        redirect('settings.php');
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
    
    if ($conn->query($sql)) {
        setFlash('success', '密码修改成功');
    } else {
        setFlash('error', '密码修改失败');
    }
    
    redirect('settings.php');
}
