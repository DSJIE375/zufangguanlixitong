<?php
/**
 * 租房管理系统 - 安装脚本
 * 访问此文件可以自动创建数据库和初始数据
 * 安装完成后建议删除此文件
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'rental_system';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = $_POST['db_host'] ?? 'localhost';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $dbname = $_POST['db_name'] ?? 'demozhufang';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? '123456';
    $admin_name = $_POST['admin_name'] ?? '管理员';
    
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
        // 移除CREATE DATABASE语句
        $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
        $sql = str_replace('USE demozhufang;', '', $sql);
        
        // 逐条执行SQL
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $conn->query($stmt);
            }
        }
        
        // 更新管理员密码
        $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed', realname='$admin_name' WHERE username='$admin_user'");
        
        // 更新配置文件
        $site_name = htmlspecialchars($_POST['site_name'] ?? 'DSJIE.租房管理系统');
        $config = "<?php\n";
        $config .= "define('DB_HOST', '$host');\n";
        $config .= "define('DB_USER', '$user');\n";
        $config .= "define('DB_PASS', '$pass');\n";
        $config .= "define('DB_NAME', '$dbname');\n";
        $config .= "define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . '/demozhufang');\n";
        $config .= "define('ADMIN_URL', SITE_URL . '/admin');\n";
        $config .= "session_start();\n";
        
        file_put_contents(__DIR__ . '/config.php', $config);
        
        // 更新数据库中的网站名称
        $conn->query("UPDATE settings SET setting_value='$site_name' WHERE setting_key='site_name'");
        
        $success = true;
        $message = '安装成功！请访问 <a href="admin/login.php">后台登录</a> 进入管理系统。';
        $message .= '<br>用户名: ' . htmlspecialchars($admin_user) . '<br>密码: ' . htmlspecialchars($admin_pass);
        $message .= '<br><br><strong>安全提示：安装完成后建议删除 install.php 文件</strong>';
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
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .install-card { max-width: 500px; margin: 0 auto; border: none; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .install-header { background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); color: white; padding: 30px; border-radius: 15px 15px 0 0; text-align: center; }
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
                    <h5 class="mb-3">数据库设置</h5>
                    <div class="mb-3">
                        <label class="form-label">数据库主机</label>
                        <input type="text" name="db_host" class="form-control" value="<?php echo $host; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数据库用户名</label>
                        <input type="text" name="db_user" class="form-control" value="<?php echo $user; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数据库密码</label>
                        <input type="password" name="db_pass" class="form-control" value="<?php echo $pass; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数据库名称</label>
                        <input type="text" name="db_name" class="form-control" value="<?php echo $dbname; ?>">
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">管理员设置</h5>
                    <div class="mb-3">
                        <label class="form-label">管理员用户名</label>
                        <input type="text" name="admin_user" class="form-control" value="admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">管理员密码</label>
                        <input type="password" name="admin_pass" class="form-control" value="123456">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">管理员姓名</label>
                        <input type="text" name="admin_name" class="form-control" value="管理员">
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">网站设置</h5>
                    <div class="mb-3">
                        <label class="form-label">网站名称</label>
                        <input type="text" name="site_name" class="form-control" value="DSJIE.租房管理系统" placeholder="如：XX公寓、XX出租房">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">开始安装</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
