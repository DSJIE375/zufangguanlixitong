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

// 检查租客登录
if (!isset($_SESSION['tenant_id'])) {
    header("Location: tenant_login.php");
    exit;
}

$history_id = intval($_GET['id'] ?? 0);
if (!$history_id) {
    header("Location: tenant_bills.php");
    exit;
}

$siteName = getSiteName();
$siteAddress = getSetting('site_address') ?: '';

// 获取历史租房记录
$history = $conn->query("SELECT * FROM tenant_history 
    WHERE id = $history_id AND tenant_name = '" . ($_SESSION['tenant_name'] ?? '') . "'")->fetch_assoc();

if (!$history) {
    header("Location: tenant_bills.php");
    exit;
}

function formatDate($date) {
    if (!$date) return '长期';
    $d = new DateTime($date);
    return $d->format('Y年m月d日');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>历史租房合同 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Microsoft YaHei", sans-serif; }
        .contract-paper { max-width: 800px; margin: 20px auto; background: white; padding: 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .contract-title { text-align: center; font-size: 28px; font-weight: bold; margin-bottom: 30px; }
        .contract-section { margin-bottom: 25px; }
        .contract-section h5 { font-size: 16px; font-weight: bold; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #ddd; }
        .contract-row { display: flex; margin-bottom: 8px; }
        .contract-label { width: 100px; font-weight: 500; }
        .contract-value { flex: 1; }
        .contract-text { text-indent: 2em; line-height: 1.8; margin-bottom: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: #1d1d1f;">
        <div class="container">
            <a class="navbar-brand" href="tenant_bills.php"><img src="images/logo.svg" alt="Logo" height="28"></a>
            <a href="tenant_bills.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> 返回</a>
        </div>
    </nav>

    <div class="contract-paper">
        <div class="contract-title">房屋租赁合同（历史记录）</div>
        
        <div class="contract-section">
            <p class="contract-text">甲方（出租方）：<?php echo $siteName; ?></p>
            <p class="contract-text">乙方（承租方）：<?php echo $history['tenant_name']; ?></p>
        </div>

        <div class="contract-section">
            <h5>一、房屋信息</h5>
            <div class="contract-row"><span class="contract-label">房屋地址：</span><span class="contract-value"><?php echo $siteAddress; ?></span></div>
            <div class="contract-row"><span class="contract-label">房间号：</span><span class="contract-value"><?php echo $history['room_number']; ?></span></div>
        </div>

        <div class="contract-section">
            <h5>二、租赁期限</h5>
            <div class="contract-row"><span class="contract-label">起始日期：</span><span class="contract-value"><?php echo formatDate($history['start_date']); ?></span></div>
            <div class="contract-row"><span class="contract-label">终止日期：</span><span class="contract-value"><?php echo formatDate($history['end_date']); ?></span></div>
        </div>

        <div class="contract-section">
            <h5>三、租金</h5>
            <div class="contract-row"><span class="contract-label">月租金：</span><span class="contract-value">人民币 <?php echo number_format($history['monthly_rent'], 2); ?> 元</span></div>
            <div class="contract-row"><span class="contract-label">押金：</span><span class="contract-value">人民币 <?php echo number_format($history['deposit'], 2); ?> 元</span></div>
        </div>

        <div class="contract-section" style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
            <p class="contract-text mb-0"><strong>退租信息：</strong>退租原因：<?php echo $history['checkout_reason'] == 'deleted' ? '主动退租' : '合同到期'; ?>，累计缴费：¥<?php echo number_format($history['total_paid'], 2); ?></p>
        </div>
    </div>
</body>
</html>
