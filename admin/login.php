<?php
require_once '../includes/database.php';

// 获取网站名称
function getSiteName() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return 'DSJIE.租房管理系统';
}
$siteName = getSiteName();

// 如果已登录，跳转到后台首页
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

// 检查登录尝试次数
function checkLoginAttempts() {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_last_attempt'] = time();
    }
    
    // 5分钟内最多尝试5次
    if (time() - $_SESSION['login_last_attempt'] > 300) {
        $_SESSION['login_attempts'] = 0;
    }
    
    return $_SESSION['login_attempts'];
}

// 记录登录尝试
function recordLoginAttempt() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 检查是否被锁定
    $attempts = checkLoginAttempts();
    if ($attempts >= 5) {
        $error = '登录尝试次数过多，请5分钟后再试';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // 验证码验证（如果启用）
        if (isset($_POST['captcha']) && $_SESSION['captcha'] ?? '' !== strtolower($_POST['captcha'])) {
            $error = '验证码错误';
        } else {
            $sql = "SELECT * FROM users WHERE username = '$username'";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // 登录成功，清除尝试次数
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['login_last_attempt']);
                    
                    // 重新生成session ID防止会话固定攻击
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['realname'] = $user['realname'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    redirect('index.php');
                } else {
                    recordLoginAttempt();
                    $error = '密码错误';
                }
            } else {
                recordLoginAttempt();
                $error = '用户名或密码错误'; // 统一提示，防止用户名枚举
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
    <title>后台登录 - <?php echo $siteName; ?></title>
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
            <img src="../images/logo.svg" alt="<?php echo $siteName; ?>" height="48" style="margin-bottom: 15px;">
            <h3><?php echo $siteName; ?></h3>
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
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                    <?php if (($attempts ?? 0) >= 3): ?>
                        <br><small>剩余尝试次数：<?php echo 5 - ($attempts ?? 0); ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600;">用户名</label>
                    <input type="text" class="form-control" name="username" required autofocus placeholder="请输入用户名">
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
