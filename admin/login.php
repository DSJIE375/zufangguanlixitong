<?php
require_once '../includes/database.php';

function getSiteName() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return 'DSJIE.租房管理系统';
}
$siteName = getSiteName();

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// 检查登录尝试次数（使用IP限制）
function checkLoginAttempts() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'login_attempts_' . md5($ip);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = 0;
        $_SESSION[$key . '_time'] = time();
    }
    
    // 5分钟内最多尝试5次
    if (time() - $_SESSION[$key . '_time'] > 300) {
        $_SESSION[$key] = 0;
    }
    
    return $_SESSION[$key];
}

// 记录登录尝试
function recordLoginAttempt() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'login_attempts_' . md5($ip);
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    $_SESSION[$key . '_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF验证
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = '安全验证失败，请重试';
    } else {
        $attempts = checkLoginAttempts();
        if ($attempts >= 5) {
            $error = '登录尝试次数过多，请5分钟后再试';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = '请输入用户名和密码';
            } else {
                // 使用准备语句查询
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        if (password_verify($password, $user['password'])) {
                            // 登录成功
                            unset($_SESSION['login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? '')]);
                            unset($_SESSION['login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '_time']);
                            
                            // 重新生成session ID防止会话固定攻击
                            session_regenerate_id(true);
                            
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['realname'] = $user['realname'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
                            
                            logAction('管理员登录', "用户 {$user['username']} 登录成功");
                            redirect('index.php');
                        } else {
                            recordLoginAttempt();
                            $error = '密码错误';
                            logAction('登录失败', "用户 {$username} 密码错误");
                        }
                    } else {
                        recordLoginAttempt();
                        $error = '用户名或密码错误';
                        logAction('登录失败', "用户 {$username} 不存在");
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>后台登录 - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f5f7;
            --card: #ffffff;
            --text: #1d1d1f;
            --text-muted: #86868b;
            --accent: #000000;
            --border: #e5e5e7;
            --radius: 16px;
            --radius-sm: 10px;
        }
        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Helvetica Neue", Arial, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: var(--text);
            color: var(--card);
            padding: 40px 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        .login-header h3 {
            font-weight: 700;
            margin-bottom: 5px;
        }
        .login-body {
            padding: 30px;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        .btn-login {
            background: var(--accent);
            color: var(--card);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            width: 100%;
        }
        .btn-login:hover {
            background: #333;
        }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="login-header">
            <img src="../images/logo.svg" alt="<?php echo h($siteName); ?>" height="48" style="margin-bottom: 15px;">
            <h3><?php echo h($siteName); ?></h3>
            <small style="color: #86868b;">后台管理系统</small>
        </div>
        <div class="login-body">
            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert" style="background: #fff3cd; border: none; border-radius: 10px;">
                    <i class="bi bi-clock-history me-2"></i>登录已过期，请重新登录
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert" style="background: #f8d7da; border: none; border-radius: 10px;">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo h($error); ?>
                    <?php if (($attempts ?? 0) >= 3): ?>
                        <br><small>剩余尝试次数：<?php echo 5 - ($attempts ?? 0); ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm">
                <?php echo csrfField(); ?>
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600;">用户名</label>
                    <input type="text" class="form-control" name="username" required autofocus placeholder="请输入用户名" maxlength="50">
                </div>
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 600;">密码</label>
                    <input type="password" class="form-control" name="password" required placeholder="请输入密码">
                </div>
                <button type="submit" class="btn btn-login">登录</button>
            </form>
            
            <div class="text-center mt-4">
                <a href="../index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">
                    <i class="bi bi-arrow-left me-1"></i>返回前台
                </a>
            </div>
        </div>
    </div>
</body>
</html>
