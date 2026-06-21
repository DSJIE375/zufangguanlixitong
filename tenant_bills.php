<?php
require_once 'includes/database.php';

function getSetting($key) {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '';
}

function getSiteName() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return 'DSJIE.租房管理系统';
}

// 检查登录
if (!isset($_SESSION['tenant_id'])) {
    header("Location: tenant_login.php");
    exit;
}

$siteName = getSiteName();
$tenant_id = $_SESSION['tenant_id'];

// 退出登录
if (isset($_GET['logout'])) {
    unset($_SESSION['tenant_id']);
    unset($_SESSION['tenant_name']);
    header("Location: tenant_login.php");
    exit;
}

// 获取当前合同
$currentContract = $conn->query("SELECT c.*, r.room_number, r.floor, rt.name as type_name, rt.area
    FROM contracts c
    JOIN rooms r ON c.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    WHERE c.tenant_id = $tenant_id AND c.status = 'active'
    LIMIT 1")->fetch_assoc();

// 获取所有账单（当前合同的）
$currentBills = $conn->query("SELECT b.*, r.room_number, r.floor, rt.name as type_name
    FROM bills b
    JOIN contracts c ON b.contract_id = c.id
    JOIN rooms r ON c.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    WHERE c.tenant_id = $tenant_id
    ORDER BY b.bill_month DESC");

// 统计未缴金额
$totalUnpaid = 0;
$totalPaid = 0;
if ($currentBills) {
    while ($b = $currentBills->fetch_assoc()) {
        if ($b['status'] == 'unpaid') {
            $totalUnpaid += $b['total_amount'];
        } else {
            $totalPaid += $b['total_amount'];
        }
    }
    $currentBills->data_seek(0); // 重置指针
}

// 获取历史租户记录（从tenant_history表）
$tenantHistory = $conn->query("SELECT * FROM tenant_history 
    WHERE tenant_name = '" . ($_SESSION['tenant_name'] ?? '') . "'
    ORDER BY end_date DESC");

// 获取历史账单（从bill_history表）
$billHistory = $conn->query("SELECT bh.*, rt.name as type_name 
    FROM bill_history bh
    LEFT JOIN room_types rt ON (SELECT room_type_id FROM rooms WHERE id = bh.room_id) = rt.id
    WHERE bh.tenant_name = '" . ($_SESSION['tenant_name'] ?? '') . "'
    ORDER BY bh.bill_month DESC");

// 统计历史未缴金额
$historyUnpaid = 0;
if ($billHistory) {
    while ($bh = $billHistory->fetch_assoc()) {
        if ($bh['status'] == 'unpaid') {
            $historyUnpaid += $bh['total_amount'];
        }
    }
    $billHistory->data_seek(0);
}

$siteAddress = getSetting('site_address') ?: '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>我的账单 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Helvetica Neue", Arial, sans-serif; }
        .info-card { background: #1d1d1f; color: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .bill-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .bill-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: #1d1d1f;">
        <div class="container">
            <a class="navbar-brand" href="tenant_bills.php"><img src="images/logo.svg" alt="Logo" height="28"></a>
            <div>
                <span class="text-light me-3"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['tenant_name']; ?></span>
                <a href="tenant_bills.php?logout=1" class="btn btn-outline-light btn-sm">退出</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- 租客信息 -->
        <?php if ($currentContract): ?>
        <div class="info-card">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-door-open me-2"></i><?php echo $currentContract['floor']; ?>楼 <?php echo $currentContract['room_number']; ?></h5>
                    <p class="mb-0"><?php echo $currentContract['type_name']; ?> | 月租 ¥<?php echo number_format($currentContract['monthly_rent'], 2); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><small>入住日期：<?php echo $currentContract['start_date']; ?></small></p>
                    <p class="mb-0"><small>到期日期：<?php echo $currentContract['end_date'] ?: '长期'; ?></small></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 费用统计 -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="bill-card text-center">
                    <small class="text-muted">未缴金额</small>
                    <h3 class="text-danger mb-0">¥<?php echo number_format($totalUnpaid + $historyUnpaid, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bill-card text-center">
                    <small class="text-muted">已缴金额</small>
                    <h3 class="text-success mb-0">¥<?php echo number_format($totalPaid, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bill-card text-center">
                    <small class="text-muted">账单总数</small>
                    <h3 class="text-primary mb-0"><?php echo ($currentBills ? $currentBills->num_rows : 0) + ($billHistory ? $billHistory->num_rows : 0); ?> 张</h3>
                </div>
            </div>
        </div>

        <!-- 当前合同详情 -->
        <?php if ($currentContract): ?>
        <h5 class="mb-3"><i class="bi bi-file-text me-2"></i>当前合同</h5>
        <div class="bill-card">
            <div class="row">
                <div class="col-6">
                    <p class="mb-1"><small class="text-muted">房间：</small><strong><?php echo $currentContract['floor']; ?>楼 <?php echo $currentContract['room_number']; ?></strong></p>
                    <p class="mb-1"><small class="text-muted">类型：</small><?php echo $currentContract['type_name']; ?> (<?php echo $currentContract['area']; ?>㎡)</p>
                    <p class="mb-0"><small class="text-muted">月租：</small><strong>¥<?php echo number_format($currentContract['monthly_rent'], 2); ?></strong></p>
                </div>
                <div class="col-6 text-end">
                    <p class="mb-1"><small class="text-muted">入住日期：</small><?php echo $currentContract['start_date']; ?></p>
                    <p class="mb-1"><small class="text-muted">到期日期：</small><?php echo $currentContract['end_date'] ?: '长期'; ?></p>
                    <p class="mb-2"><span class="badge bg-success">执行中</span></p>
                    <a href="contract_view.php?id=<?php echo $currentContract['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="bi bi-file-text me-1"></i> 查看合同</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 当前账单 -->
        <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>当前账单</h5>
        
        <?php if ($currentBills && $currentBills->num_rows > 0): ?>
            <?php while ($bill = $currentBills->fetch_assoc()): ?>
            <div class="bill-card">
                <div class="bill-header">
                    <div>
                        <strong><?php echo $bill['bill_month']; ?></strong>
                        <small class="text-muted ms-2"><?php echo $bill['room_number']; ?> <?php echo $bill['type_name']; ?></small>
                    </div>
                    <div>
                        <?php if ($bill['status'] == 'paid'): ?>
                            <span class="badge bg-success">已缴</span>
                        <?php else: ?>
                            <span class="badge bg-danger">未缴</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-4">
                        <small class="text-muted">水费</small>
                        <div class="fw-bold">¥<?php echo number_format($bill['water_amount'], 2); ?></div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">电费</small>
                        <div class="fw-bold">¥<?php echo number_format($bill['elec_amount'], 2); ?></div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">垃圾费</small>
                        <div class="fw-bold">¥<?php echo number_format($bill['garbage_fee'], 2); ?></div>
                    </div>
                </div>
                <?php if ($bill['other_fee'] > 0): ?>
                <div class="row mt-2">
                    <div class="col-4">
                        <small class="text-muted"><?php echo $bill['other_fee_desc'] ?: '其他'; ?></small>
                        <div class="fw-bold">¥<?php echo number_format($bill['other_fee'], 2); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">房租：¥<?php echo number_format($bill['rent_amount'], 2); ?></small>
                    <div>
                        <strong class="me-3">合计：¥<?php echo number_format($bill['total_amount'], 2); ?></strong>
                        <a href="bill_detail.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="bi bi-eye me-1"></i> 详情</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-3">
                <p class="text-muted">暂无当前账单</p>
            </div>
        <?php endif; ?>

        <!-- 历史合同 -->
        <?php if ($tenantHistory && $tenantHistory->num_rows > 0): ?>
        <h5 class="mb-3 mt-4"><i class="bi bi-clock-history me-2"></i>历史租房记录</h5>
        <?php while ($th = $tenantHistory->fetch_assoc()): ?>
            <div class="bill-card" style="opacity: 0.8;">
                <div class="bill-header">
                    <div>
                        <strong><?php echo $th['room_number']; ?></strong>
                        <small class="text-muted ms-2">月租 ¥<?php echo number_format($th['monthly_rent'], 2); ?></small>
                    </div>
                    <span class="badge bg-secondary"><?php echo $th['checkout_reason'] == 'deleted' ? '已退租' : '已到期'; ?></span>
                </div>
                <div class="row">
                    <div class="col-6"><small class="text-muted">入住：</small><?php echo $th['start_date']; ?></div>
                    <div class="col-6"><small class="text-muted">退租：</small><?php echo $th['end_date']; ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">累计缴费：¥<?php echo number_format($th['total_paid'], 2); ?></small>
                    <a href="tenant_history_detail.php?id=<?php echo $th['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="bi bi-eye me-1"></i> 查看合同</a>
                </div>
            </div>
        <?php endwhile; ?>
        <?php endif; ?>

        <!-- 历史账单 -->
        <?php if ($billHistory && $billHistory->num_rows > 0): ?>
        <h5 class="mb-3 mt-4"><i class="bi bi-clock-history me-2"></i>历史账单</h5>
        <?php while ($bh = $billHistory->fetch_assoc()): ?>
            <div class="bill-card" style="opacity: 0.8;">
                <div class="bill-header">
                    <div>
                        <strong><?php echo $bh['bill_month']; ?></strong>
                        <small class="text-muted ms-2"><?php echo $bh['room_number']; ?></small>
                    </div>
                    <span class="badge bg-secondary"><?php echo $bh['status'] == 'paid' ? '已缴' : '未缴'; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small>水费 ¥<?php echo number_format($bh['water_amount'], 2); ?> + 电费 ¥<?php echo number_format($bh['elec_amount'], 2); ?> + 垃圾费 ¥<?php echo number_format($bh['garbage_fee'], 2); ?> + 房租 ¥<?php echo number_format($bh['rent_amount'], 2); ?></small>
                    <div>
                        <strong class="me-3">¥<?php echo number_format($bh['total_amount'], 2); ?></strong>
                        <a href="bill_history_detail.php?id=<?php echo $bh['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="bi bi-eye me-1"></i> 详情</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <footer class="text-center py-3 text-muted small">
        <p>&copy; 2024 <?php echo $siteName; ?> | <?php echo $siteAddress; ?></p>
    </footer>
</body>
</html>
