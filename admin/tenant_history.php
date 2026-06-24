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

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    requireCSRF();
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM tenant_history WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    setFlash('success', '历史记录已删除');
    redirect('tenant_history.php');
}

$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    $where .= " AND (room_number LIKE ? OR tenant_name LIKE ? OR tenant_phone LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}
if (!empty($_GET['room'])) {
    $room = '%' . trim($_GET['room']) . '%';
    $where .= " AND room_number LIKE ?";
    $params[] = $room;
    $types .= 's';
}

$sql = "SELECT * FROM tenant_history WHERE $where ORDER BY end_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $history = $stmt->get_result();
    $stmt->close();
} else {
    $history = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>历史租户 - <?php echo h($siteName); ?></title>
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
                    <h4 class="mb-0"><i class="bi bi-clock-history"></i> 历史租户记录</h4>
                    <span class="badge bg-secondary">共 <?php echo $history->num_rows; ?> 条记录</span>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="搜索租客姓名、电话或房间号..." value="<?php echo h($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="room" class="form-control" placeholder="房间号..." value="<?php echo h($_GET['room'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="tenant_history.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php while ($h = $history->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-door-open"></i> <?php echo h($h['room_number']); ?></h5>
                                <span class="badge bg-light text-secondary"><?php echo $h['checkout_reason'] == 'terminated' ? '已退租' : ($h['checkout_reason'] == 'expired' ? '已到期' : '已删除'); ?></span>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="bi bi-person text-muted me-2"></i><strong>租客：</strong><?php echo h($h['tenant_name']); ?></li>
                                    <li class="mb-2"><i class="bi bi-telephone text-muted me-2"></i><strong>电话：</strong><?php echo h($h['tenant_phone']); ?></li>
                                    <li class="mb-2"><i class="bi bi-gender-ambiguous text-muted me-2"></i><strong>性别：</strong><?php echo h($h['tenant_gender'] ?: '-'); ?></li>
                                    <li class="mb-2"><i class="bi bi-credit-card text-muted me-2"></i><strong>身份证：</strong><?php echo h($h['tenant_idcard'] ?: '-'); ?></li>
                                    <li class="mb-2"><i class="bi bi-building text-muted me-2"></i><strong>公司：</strong><?php echo h($h['tenant_company'] ?: '-'); ?></li>
                                    <li class="mb-2"><i class="bi bi-cash text-muted me-2"></i><strong>月租：</strong><span class="text-dark">¥<?php echo number_format($h['monthly_rent'], 2); ?></span></li>
                                    <li class="mb-2"><i class="bi bi-wallet text-muted me-2"></i><strong>押金：</strong>¥<?php echo number_format($h['deposit'], 2); ?></li>
                                    <li class="mb-2"><i class="bi bi-calendar text-muted me-2"></i><strong>入住：</strong><?php echo $h['start_date']; ?></li>
                                    <li class="mb-2"><i class="bi bi-calendar-check text-muted me-2"></i><strong>退租：</strong><?php echo $h['end_date']; ?></li>
                                    <li><i class="bi bi-cash-stack text-muted me-2"></i><strong>累计缴费：</strong><span class="text-dark">¥<?php echo number_format($h['total_paid'], 2); ?></span></li>
                                </ul>
                            </div>
                            <div class="card-footer bg-white">
                                <form method="POST" style="display:inline;" data-confirm="确定要删除这条历史记录吗？">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 删除</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php if ($history->num_rows == 0): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-1 text-muted"></i>
                    <p class="mt-3 text-muted">暂无历史租户记录</p>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php include 'footer.php'; ?>
