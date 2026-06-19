<?php
require_once __DIR__ . '/../config.php';

// 数据库连接
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Session超时检查（30分钟）
if (isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 1800) {
        session_unset();
        session_destroy();
        header("Location: " . ADMIN_URL . "/login.php?timeout=1");
        exit;
    }
    $_SESSION['login_time'] = time(); // 刷新时间
}

// CSRF Token生成
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token验证
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 工具函数
function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(htmlspecialchars($data));
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatPrice($price) {
    return '¥' . number_format($price, 2);
}

function getUnreadMessages() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE is_read=0");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['cnt'];
    }
    return 0;
}

function getCount($table, $where = '') {
    global $conn;
    $sql = "SELECT COUNT(*) as cnt FROM $table";
    if ($where) $sql .= " WHERE $where";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['cnt'];
    }
    return 0;
}
