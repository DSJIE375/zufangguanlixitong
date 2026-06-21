<?php
require_once '../includes/database.php';

// 检查登录
if (!isLoggedIn()) {
    redirect('login.php');
}

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

// 获取统计数据
function getStats() {
    global $conn;
    
    $stats = [];
    
    // 总房间数
    $sql = "SELECT COUNT(*) as total FROM rooms";
    $result = $conn->query($sql);
    $stats['total_rooms'] = $result->fetch_assoc()['total'];
    
    // 可租房间数
    $sql = "SELECT COUNT(*) as total FROM rooms WHERE status = 'available'";
    $result = $conn->query($sql);
    $stats['available_rooms'] = $result->fetch_assoc()['total'];
    
    // 已租房间数
    $stats['rented_rooms'] = $stats['total_rooms'] - $stats['available_rooms'];
    
    // 租客总数
    $sql = "SELECT COUNT(*) as total FROM tenants";
    $result = $conn->query($sql);
    $stats['total_tenants'] = $result->fetch_assoc()['total'];
    
    // 活跃合同数
    $sql = "SELECT COUNT(*) as total FROM contracts WHERE status = 'active'";
    $result = $conn->query($sql);
    $stats['active_contracts'] = $result->fetch_assoc()['total'];
    
    // 未缴账单数
    $sql = "SELECT COUNT(*) as total FROM bills WHERE status = 'unpaid'";
    $result = $conn->query($sql);
    $stats['unpaid_bills'] = $result->fetch_assoc()['total'];
    
    // 本月收入
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE status = 'paid' AND MONTH(paid_at) = MONTH(CURRENT_DATE())";
    $result = $conn->query($sql);
    $stats['monthly_income'] = $result->fetch_assoc()['total'];
    
    return $stats;
}

$stats = getStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?php echo $siteName; ?></title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="../images/logo.svg" alt="<?php echo $siteName; ?>" height="28">
            </a>
            <div class="d-flex align-items-center">
                <span class="me-3" style="color: var(--text-muted);">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['realname']; ?>
                </span>
                <a href="logout.php" class="btn btn-outline-dark btn-sm">
                    退出
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- 手机端菜单按钮和遮罩 -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-2 d-md-block sidebar collapse py-3">
                <?php include 'sidebar.php'; ?>
            </nav>

            <!-- 主内容区 -->
            <main class="col-md-10 ms-sm-auto main-content">
                <h4 class="mb-4">仪表盘</h4>
                
                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="rooms.php" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">总房间数</h6>
                                            <h3 class="mb-0"><?php echo $stats['total_rooms']; ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-door-open"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="rooms.php?status=available" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">可租房间</h6>
                                            <h3 class="mb-0"><?php echo $stats['available_rooms']; ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="rooms.php?status=rented" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">已租房间</h6>
                                            <h3 class="mb-0"><?php echo $stats['rented_rooms']; ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-key"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="tenants.php" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">租客总数</h6>
                                            <h3 class="mb-0"><?php echo $stats['total_tenants']; ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-people"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <a href="contracts.php" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">活跃合同</h6>
                                            <h3 class="mb-0"><?php echo $stats['active_contracts']; ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-file-text"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <a href="bills.php?bill_status=unpaid" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">未缴账单</h6>
                                            <h3 class="mb-0"><?php echo $stats['unpaid_bills']; ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <a href="bills.php?bill_status=paid" class="text-decoration-none">
                            <div class="card stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">本月收入</h6>
                                            <h3 class="mb-0">¥<?php echo number_format($stats['monthly_income'], 2); ?></h3>
                                        </div>
                                        <div class="stat-icon">
                                            <i class="bi bi-cash"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- 快捷操作 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-lightning-fill me-2"></i>快捷操作</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 col-md-3 mb-3">
                                <a href="rooms.php?action=add" class="quick-action">
                                    <i class="bi bi-plus-circle"></i>
                                    <span class="fw-bold">添加房间</span>
                                </a>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <a href="tenants.php?action=add" class="quick-action">
                                    <i class="bi bi-person-plus"></i>
                                    <span class="fw-bold">添加租客</span>
                                </a>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <a href="contracts.php?action=add" class="quick-action">
                                    <i class="bi bi-file-earmark-plus"></i>
                                    <span class="fw-bold">新建合同</span>
                                </a>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <a href="bills.php?action=add" class="quick-action">
                                    <i class="bi bi-receipt"></i>
                                    <span class="fw-bold">录入账单</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
    function toggleSidebar() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }
    </script>
</body>
</html>
