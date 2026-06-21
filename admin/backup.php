<?php
require_once '../includes/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

function getSiteName() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return 'DSJIE.租房管理系统';
}
$siteName = getSiteName();

// 处理备份下载
if (isset($_GET['download'])) {
    logAction('下载备份', '下载数据库备份文件');
    $tables = ['users', 'rooms', 'room_types', 'tenants', 'contracts', 'bills', 'messages', 'share_links', 'tenant_history', 'settings', 'room_photos'];
    
    $sqlContent = "-- 数据库备份 - " . date('Y-m-d H:i:s') . "\n";
    $sqlContent .= "SET NAMES utf8mb4;\n\n";
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT * FROM $table");
        $sqlContent .= "-- 表: $table\n";
        $sqlContent .= "TRUNCATE TABLE $table;\n";
        
        while ($row = $result->fetch_assoc()) {
            $values = array_map(function($v) use ($conn) {
                return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
            }, $row);
            
            $sqlContent .= "INSERT INTO $table VALUES (" . implode(', ', $values) . ");\n";
        }
        $sqlContent .= "\n";
    }
    
    $filename = 'backup_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sqlContent));
    echo $sqlContent;
    exit;
}

// 处理恢复
if (isset($_POST['action']) && $_POST['action'] == 'restore') {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
        $sqlContent = file_get_contents($_FILES['backup_file']['tmp_name']);
        $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
        
        $success = 0;
        $errors = 0;
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^--/', $statement)) {
                if ($conn->query($statement)) {
                    $success++;
                } else {
                    $errors++;
                }
            }
        }
        
        setFlash('success', "恢复完成！成功 $success 条，失败 $errors 条");
        logAction('恢复数据', "恢复数据: 成功 $success 条，失败 $errors 条");
        redirect('backup.php');
    } else {
        setFlash('error', '请选择备份文件');
        redirect('backup.php');
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>数据备份 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><img src="../images/logo.svg" alt="Logo" height="28"></a>
            <div class="d-flex align-items-center">
                <span class="me-3" style="color: var(--text-muted);"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['realname']; ?></span>
                <a href="logout.php" class="btn btn-outline-dark btn-sm">退出</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
        <div class="row">
            <nav class="col-md-2 d-md-block sidebar collapse py-3">
                <?php include 'sidebar.php'; ?>
            </nav>
            <main class="col-md-10 ms-sm-auto main-content">
                <?php $flash = getFlash(); if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <h4 class="mb-4"><i class="bi bi-database"></i> 数据备份</h4>

                <div class="row">
                    <!-- 备份数据 -->
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-download"></i> 备份数据</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">导出数据库为SQL文件，保存到本地。</p>
                                <div class="alert" style="background: #f8f9fa; border: 1px solid var(--border);">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>包含以下数据：</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>用户账号</li>
                                        <li>房间信息</li>
                                        <li>租客信息</li>
                                        <li>合同记录</li>
                                        <li>账单记录</li>
                                        <li>分享链接</li>
                                        <li>系统设置</li>
                                    </ul>
                                </div>
                                <a href="backup.php?download=1" class="btn btn-dark btn-lg w-100">
                                    <i class="bi bi-download me-2"></i> 下载备份文件
                                </a>
                                <small class="text-muted d-block mt-2">文件名格式：backup_2024-06-19_120000.sql</small>
                            </div>
                        </div>
                    </div>

                    <!-- 恢复数据 -->
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="bi bi-upload"></i> 恢复数据</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">从SQL备份文件恢复数据。</p>
                                <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107;">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>警告：</strong>恢复操作将覆盖当前数据！
                                </div>
                                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('确定要恢复数据吗？这将覆盖当前所有数据！')">
                                    <input type="hidden" name="action" value="restore">
                                    <div class="mb-3">
                                        <label class="form-label">选择备份文件</label>
                                        <input type="file" name="backup_file" class="form-control" accept=".sql" required>
                                    </div>
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="bi bi-upload me-2"></i> 恢复数据
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 备份说明 -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> 备份说明</h5>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li><strong>定期备份：</strong>建议每周备份一次数据</li>
                            <li><strong>备份存储：</strong>备份文件应保存在安全的位置（如网盘、U盘）</li>
                            <li><strong>恢复注意：</strong>恢复操作会覆盖当前数据，请谨慎操作</li>
                            <li><strong>文件格式：</strong>备份文件为.sql格式，可用任何SQL工具打开</li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include 'footer.php'; ?>
