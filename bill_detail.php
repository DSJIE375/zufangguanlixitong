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

$bill_id = intval($_GET['id'] ?? 0);
if (!$bill_id) {
    header("Location: tenant_bills.php");
    exit;
}

$siteName = getSiteName();
$siteAddress = getSetting('site_address') ?: '';

// 获取账单详情（只允许查看自己的账单）
$bill = $conn->query("SELECT b.*, c.monthly_rent, r.room_number, r.floor, rt.name as type_name, rt.area,
    t.name as tenant_name, t.phone as tenant_phone
    FROM bills b
    JOIN contracts c ON b.contract_id = c.id
    JOIN rooms r ON c.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    JOIN tenants t ON c.tenant_id = t.id
    WHERE b.id = $bill_id AND c.tenant_id = " . $_SESSION['tenant_id'])->fetch_assoc();

if (!$bill) {
    header("Location: tenant_bills.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账单详情 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Helvetica Neue", Arial, sans-serif; }
        .bill-paper { max-width: 800px; margin: 20px auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .bill-header { text-align: center; border-bottom: 2px solid #1d1d1f; padding-bottom: 20px; margin-bottom: 20px; }
        .bill-title { font-size: 24px; font-weight: 700; color: #1d1d1f; }
        .bill-subtitle { font-size: 14px; color: #86868b; }
        .bill-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .bill-table th, .bill-table td { border: 1px solid #e5e5e7; padding: 12px; text-align: center; }
        .bill-table th { background: #1d1d1f; color: white; }
        .bill-total { font-size: 20px; font-weight: 700; text-align: right; padding: 15px 0; border-top: 2px solid #1d1d1f; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: #1d1d1f;">
        <div class="container">
            <a class="navbar-brand" href="tenant_bills.php"><img src="images/logo.svg" alt="Logo" height="28"></a>
            <a href="tenant_bills.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> 返回</a>
        </div>
    </nav>

    <div class="bill-paper" id="billContent">
        <div class="bill-header">
            <div class="bill-title"><?php echo $siteName; ?></div>
            <div class="bill-subtitle">水电房租账单</div>
        </div>

        <table style="width:100%; margin-bottom:20px;">
            <tr><td style="width:100px; color:#86868b;">账单月份：</td><td><strong><?php echo $bill['bill_month']; ?></strong></td></tr>
            <tr><td style="color:#86868b;">房间号：</td><td><strong><?php echo $bill['floor']; ?>楼 <?php echo $bill['room_number']; ?></strong></td></tr>
            <tr><td style="color:#86868b;">房间类型：</td><td><?php echo $bill['type_name']; ?> (<?php echo $bill['area']; ?>㎡)</td></tr>
            <tr><td style="color:#86868b;">租客姓名：</td><td><?php echo $bill['tenant_name']; ?></td></tr>
            <tr><td style="color:#86868b;">联系电话：</td><td><?php echo $bill['tenant_phone']; ?></td></tr>
        </table>

        <table class="bill-table">
            <thead><tr><th>费用项目</th><th>上期读数</th><th>本期读数</th><th>使用量</th><th>单价</th><th>金额</th></tr></thead>
            <tbody>
                <tr><td>水费</td><td><?php echo $bill['water_start']; ?>吨</td><td><?php echo $bill['water_end']; ?>吨</td><td><?php echo $bill['water_usage']; ?>吨</td><td>¥<?php echo $bill['water_price']; ?>/吨</td><td>¥<?php echo number_format($bill['water_amount'], 2); ?></td></tr>
                <tr><td>电费</td><td><?php echo $bill['elec_start']; ?>度</td><td><?php echo $bill['elec_end']; ?>度</td><td><?php echo $bill['elec_usage']; ?>度</td><td>¥<?php echo $bill['elec_price']; ?>/度</td><td>¥<?php echo number_format($bill['elec_amount'], 2); ?></td></tr>
                <tr><td>垃圾费</td><td colspan="4">垃圾管理费</td><td>¥<?php echo number_format($bill['garbage_fee'], 2); ?></td></tr>
                <?php if ($bill['other_fee'] > 0): ?>
                <tr><td><?php echo $bill['other_fee_desc'] ?: '其他'; ?></td><td colspan="4"><?php echo $bill['other_fee_desc'] ?: '其他费用'; ?></td><td>¥<?php echo number_format($bill['other_fee'], 2); ?></td></tr>
                <?php endif; ?>
                <tr><td>房租</td><td colspan="4">月租金</td><td>¥<?php echo number_format($bill['rent_amount'], 2); ?></td></tr>
            </tbody>
        </table>

        <div class="bill-total">应缴合计：¥<?php echo number_format($bill['total_amount'], 2); ?></div>
    </div>
</body>
</html>
