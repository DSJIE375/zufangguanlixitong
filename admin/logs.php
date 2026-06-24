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

// 清空日志（需要POST请求）
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clear_logs') {
    requireCSRF();
    $conn->query("TRUNCATE TABLE operation_logs");
    logAction('清空日志', '清空所有操作日志');
    setFlash('success', '日志已清空');
    redirect('logs.php');
}

// 获取日志
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = $conn->query("SELECT COUNT(*) as cnt FROM operation_logs")->fetch_assoc()['cnt'];
$totalPages = ceil($total / $perPage);

$logs = $conn->query("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>操作日志 - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><img src="../images/logo.svg" alt="Logo" height="28"></a>
            <div class="d-flex align-items-center">
                <span class="me-3" style="color: var(--text-muted);"><i class="bi bi-person-circle"></i> <?php echo h($_SESSION['realname']); ?></span>
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
                    <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible fade show">
                        <?php echo h($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="bi bi-clock-history"></i> 操作日志</h4>
                    <form method="POST" data-confirm="确定要清空所有日志吗？">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash"></i> 清空日志
                        </button>
                    </form>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if ($logs && $logs->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>时间</th>
                                        <th>用户</th>
                                        <th>操作</th>
                                        <th>详情</th>
                                        <th>IP地址</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($log = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small></td>
                                        <td><?php echo h($log['username']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo strpos($log['action'], '删除') !== false ? 'danger' : 
                                                (strpos($log['action'], '添加') !== false ? 'success' : 
                                                (strpos($log['action'], '修改') !== false ? 'warning' : 'secondary'));
                                            ?>"><?php echo h($log['action']); ?></span>
                                        </td>
                                        <td><small><?php echo h($log['detail']); ?></small></td>
                                        <td><small><?php echo h($log['ip_address']); ?></small></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="logs.php?page=<?php echo $page - 1; ?>">上一页</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="logs.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="logs.php?page=<?php echo $page + 1; ?>">下一页</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <small class="text-muted">共 <?php echo $total; ?> 条记录</small>
                        <?php else: ?>
                        <p class="text-muted text-center">暂无操作日志</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include 'footer.php'; ?>
