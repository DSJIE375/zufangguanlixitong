<?php
// 数据库初始化脚本
$host = 'localhost';
$user = 'dsjzhufang';
$pass = 'dsjzhufang';
$dbname = 'dsjzhufang';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$sql = file_get_contents(__DIR__ . '/../install.sql');
$conn->multi_query($sql);

// 等待执行完毕
do {
    if ($next = $conn->next_result()) {
        $next->close();
    }
} while ($conn->more_results());

// 更新管理员密码为 admin123
$hashed = password_hash('admin123', PASSWORD_DEFAULT);
$conn->query("UPDATE users SET password='$hashed' WHERE username='admin'");

echo "数据库初始化完成！<br>";
echo "管理员账号: admin<br>";
echo "管理员密码: admin123<br>";
echo "<a href='login.php'>点击登录后台</a>";
