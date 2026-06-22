<?php
// DSJIE.租房管理系统 - 安装脚本
// 访问此页面初始化数据库

$host = 'localhost';
$user = 'root';
$pass = 'root';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = $_POST['db_host'] ?? 'localhost';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        $message = '数据库连接失败: ' . $conn->connect_error;
    } else {
        $conn->set_charset("utf8mb4");
        
        // 创建数据库
        $sql = "CREATE DATABASE IF NOT EXISTS demozhufang DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if ($conn->query($sql)) {
            $conn->select_db('demozhufang');
            
            // 读取并执行SQL文件
            $sql_content = file_get_contents('install.sql');
            
            // 替换密码hash
            $password = '123456';
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_content = str_replace('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', $hashed_password, $sql_content);
            
            // 分割并执行SQL语句
            $statements = array_filter(array_map('trim', explode(';', $sql_content)));
            
            $errors = [];
            foreach ($statements as $sql) {
                if (!empty($sql) && !preg_match('/^--/', $sql)) {
                    if (!$conn->query($sql)) {
                        // 忽略"已存在"的错误
                        if (strpos($conn->error, 'already exists') === false) {
                            $errors[] = $conn->error;
                        }
                    }
                }
            }
            
            if (empty($errors)) {
                $success = true;
                $message = "安装成功！<br><br>管理员账号：<strong>admin</strong><br>管理员密码：<strong>123456</strong><br><br><a href='index.php' class='btn btn-primary'>访问前台</a> <a href='admin/login.php' class='btn btn-success'>进入后台</a>";
            } else {
                $message = '安装过程中出现错误: ' . implode('<br>', $errors);
            }
        } else {
            $message = '创建数据库失败: ' . $conn->error;
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - DSJIE.租房管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f7; min-height: 100vh; display: flex; align-items: center; }
        .install-card { max-width: 500px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card install-card shadow">
            <div class="card-header text-white text-center py-4" style="background: #1d1d1f;">
                <i class="bi bi-building display-4"></i>
                <h3 class="mt-2 mb-0">DSJIE.租房管理系统</h3>
                <small>系统安装向导</small>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="POST">
                    <h5 class="mb-3">数据库配置</h5>
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
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle"></i> 安装将自动创建数据库 <strong>demozhufang</strong> 和所有需要的表。
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2">
                        <i class="bi bi-download"></i> 开始安装
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
