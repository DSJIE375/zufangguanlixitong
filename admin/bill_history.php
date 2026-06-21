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

// 搜索
$where = "1=1";
if (!empty($_GET['search'])) {
    $search = sanitize($_GET['search']);
    $where .= " AND (room_number LIKE '%$search%' OR tenant_name LIKE '%$search%' OR tenant_phone LIKE '%$search%')";
}

$history = $conn->query("SELECT * FROM bill_history WHERE $where ORDER BY archived_at DESC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>历史账单 - <?php echo $siteName; ?></title>
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

                <h4 class="mb-4"><i class="bi bi-clock-history"></i> 历史账单</h4>

                <!-- 搜索 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="搜索房间号、租客姓名或电话..." value="<?php echo $_GET['search'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="bill_history.php" class="btn btn-outline-dark">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 账单列表 -->
                <?php if ($history && $history->num_rows > 0): ?>
                <div class="row">
                    <?php $idx = 1; while ($bill = $history->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><small class="text-white-50">#<?php echo $idx++; ?></small> <i class="bi bi-clock-history"></i> <?php echo $bill['room_number']; ?></h5>
                                <span class="badge bg-white text-<?php echo $bill['status'] == 'paid' ? 'success' : 'danger'; ?>"><?php echo $bill['status'] == 'paid' ? '已缴' : '未缴'; ?></span>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <small class="text-muted"><?php echo $bill['bill_month']; ?></small>
                                    <div class="text-muted small"><?php echo $bill['tenant_name']; ?></div>
                                </div>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-1"><i class="bi bi-droplet"></i> <strong>水费：</strong>¥<?php echo number_format($bill['water_amount'], 2); ?></li>
                                    <li class="mb-1"><i class="bi bi-lightning"></i> <strong>电费：</strong>¥<?php echo number_format($bill['elec_amount'], 2); ?></li>
                                    <li class="mb-1"><i class="bi bi-trash"></i> <strong>垃圾费：</strong>¥<?php echo number_format($bill['garbage_fee'], 2); ?></li>
                                    <?php if ($bill['other_fee'] > 0): ?>
                                    <li class="mb-1"><i class="bi bi-plus-circle"></i> <strong><?php echo $bill['other_fee_desc'] ?: '其他'; ?>：</strong>¥<?php echo number_format($bill['other_fee'], 2); ?></li>
                                    <?php endif; ?>
                                    <li class="mb-1"><i class="bi bi-house"></i> <strong>房租：</strong>¥<?php echo number_format($bill['rent_amount'], 2); ?></li>
                                    <li class="pt-2 border-top"><strong>合计：</strong><span class="fw-bold fs-5">¥<?php echo number_format($bill['total_amount'], 2); ?></span></li>
                                </ul>
                                <small class="text-muted d-block mt-2"><i class="bi bi-clock"></i> 归档：<?php echo date('Y-m-d H:i', strtotime($bill['archived_at'])); ?></small>
                                <a href="bill_history_detail.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-outline-dark w-100 mt-2" target="_blank"><i class="bi bi-eye me-1"></i> 查看详情</a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-1 text-muted"></i>
                    <p class="mt-3 text-muted">暂无历史账单</p>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php include 'footer.php'; ?>
