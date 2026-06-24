<?php
/**
 * 租房管理系统 - 安装脚本
 * 访问此文件可以自动创建数据库和初始数据
 * 安装完成后建议删除此文件
 */

// 检查是否已安装
if (file_exists(__DIR__ . '/config.php')) {
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;">
        <h2>系统已安装</h2>
        <p>如需重新安装，请先删除 config.php 文件</p>
        <a href="admin/login.php">返回登录</a>
    </div>');
}

// CSRF Token
session_start();
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'rental_system';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 验证CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['install_csrf']) {
        $message = '安全验证失败，请重试';
    } else {
        $host = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_POST['db_host'] ?? 'localhost');
        $user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';
        $dbname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['db_name'] ?? 'demozhufang');
        $admin_user = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['admin_user'] ?? 'admin');
        $admin_pass = $_POST['admin_pass'] ?? '123456';
        $admin_name = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s]/u', '', $_POST['admin_name'] ?? '管理员');
        
        // 验证密码强度
        if (strlen($admin_pass) < 6) {
            $message = '管理员密码长度不能少于6位';
        } else {
            $conn = new mysqli($host, $user, $pass);
            
            if ($conn->connect_error) {
                $message = '数据库连接失败: ' . $conn->connect_error;
            } else {
                $conn->set_charset("utf8mb4");
                
                // 创建数据库
                $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $conn->select_db($dbname);
                
                // 创建表
                $sql = file_get_contents(__DIR__ . '/install.sql');
                $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
                $sql = str_replace('USE demozhufang;', '', $sql);
                
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt) && preg_match('/^(CREATE|INSERT|ALTER|SET)/i', $stmt)) {
                        $conn->query($stmt);
                    }
                }
                
                // 更新管理员密码
                $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=?, realname=? WHERE username=?");
                if ($stmt) {
                    $stmt->bind_param("sss", $hashed, $admin_name, $admin_user);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // 更新配置文件
                $site_name = htmlspecialchars($_POST['site_name'] ?? 'DSJIE.租房管理系统');
                $config = "<?php\n";
                $config .= "define('DB_HOST', '$host');\n";
                $config .= "define('DB_USER', '$user');\n";
                $config .= "define('DB_PASS', '" . addslashes($pass) . "');\n";
                $config .= "define('DB_NAME', '$dbname');\n";
                $config .= "define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . '/demozhufang');\n";
                $config .= "define('ADMIN_URL', SITE_URL . '/admin');\n";
                $config .= "session_start();\n";
                
                file_put_contents(__DIR__ . '/config.php', $config);
                
                // 更新数据库中的网站名称
                $conn->query("UPDATE settings SET setting_value='$site_name' WHERE setting_key='site_name'");
                
                // 清除CSRF token
                unset($_SESSION['install_csrf']);
                
                $success = true;
                $message = '安装成功！<br><br>';
                $message .= '<a href="admin/login.php" class="btn btn-dark">进入后台管理</a>';
                $message .= '<br><br><strong>安全提示：安装完成后建议删除 install.php 文件</strong>';
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
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>安装 - 租房管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f7; min-height: 100vh; display: flex; align-items: center; }
        .install-card { max-width: 500px; margin: 0 auto; border: none; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .install-header { background: #1d1d1f; color: white; padding: 30px; border-radius: 15px 15px 0 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card install-card">
            <div class="install-header">
                <img src="images/logo.svg" alt="Logo" height="48" style="margin-bottom: 15px;">
                <h2>租房管理系统</h2>
                <p class="mb-0">系统安装向导</p>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['install_csrf']; ?>">
                    
                    <h5 class="mb-3">数据库设置</h5>
                    <div class="mb-3">
                        <label class="form-label">数据库主机</label>
                        <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($host); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数据库用户名</label>
                        <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($user); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数据库密码</label>
                        <input type="password" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($pass); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数据库名称</label>
                        <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($dbname); ?>" required>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">管理员设置</h5>
                    <div class="mb-3">
                        <label class="form-label">管理员用户名</label>
                        <input type="text" name="admin_user" class="form-control" value="admin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">管理员密码</label>
                        <input type="password" name="admin_pass" class="form-control" value="123456" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">管理员姓名</label>
                        <input type="text" name="admin_name" class="form-control" value="管理员" required>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">网站设置</h5>
                    <div class="mb-3">
                        <label class="form-label">网站名称</label>
                        <input type="text" name="site_name" class="form-control" value="DSJIE.租房管理系统" placeholder="如：XX公寓、XX出租房" required>
                    </div>
                    
                    <button type="submit" class="btn btn-dark w-100 btn-lg">开始安装</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
