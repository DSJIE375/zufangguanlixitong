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
    $_SESSION['login_time'] = time();
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

// CSRF Token隐藏字段
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// CSRF验证（在POST请求中调用）
function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($token)) {
            setFlash('error', '安全验证失败，请重试');
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
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

// 安全的HTML输出
function h($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// 安全的输出（已过时，保留兼容）
function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
}

// 准备语句辅助函数
function prepareQuery($sql, $types = '', $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    return $stmt;
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

// 安全的getCount函数
function getCount($table, $where = '', $params = [], $types = '') {
    global $conn;
    // 白名单校验表名
    $allowedTables = ['users', 'rooms', 'room_types', 'tenants', 'contracts', 'bills', 'messages', 'share_links', 'tenant_history', 'settings', 'room_photos', 'operation_logs', 'bill_history'];
    if (!in_array($table, $allowedTables)) {
        return 0;
    }
    
    if ($where) {
        $sql = "SELECT COUNT(*) as cnt FROM `$table` WHERE $where";
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
                if ($result && $result->num_rows > 0) {
                    return $result->fetch_assoc()['cnt'];
                }
                return 0;
            }
        }
    } else {
        $sql = "SELECT COUNT(*) as cnt FROM `$table`";
    }
    
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['cnt'];
    }
    return 0;
}

// 操作日志函数（使用准备语句）
function logAction($action, $detail = '') {
    global $conn;
    if (!isset($_SESSION['user_id'])) return;
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'system';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $detail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    
    $stmt = $conn->prepare("INSERT INTO operation_logs (user_id, username, action, detail, ip_address) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $username, $action, $detail, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// 生成随机令牌
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// 验证输入长度
function validateLength($data, $min, $max) {
    $len = strlen($data);
    return $len >= $min && $len <= $max;
}
