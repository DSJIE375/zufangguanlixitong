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

$contract_id = intval($_GET['id'] ?? 0);
if (!$contract_id) {
    header("Location: tenant_bills.php");
    exit;
}

$siteName = getSiteName();
$siteAddress = getSetting('site_address') ?: '';

// 获取合同详情
$contract = $conn->query("SELECT c.*, r.room_number, r.floor, rt.name as type_name, rt.area, t.name as tenant_name, t.phone as tenant_phone
    FROM contracts c
    JOIN rooms r ON c.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    JOIN tenants t ON c.tenant_id = t.id
    WHERE c.id = $contract_id AND c.tenant_id = " . $_SESSION['tenant_id'])->fetch_assoc();

if (!$contract) {
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
    <title>租赁合同 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
        .sign-area { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; }
        .sign-box { width: 250px; text-align: center; border-top: 1px solid #333; padding-top: 10px; }
        @media print { body { background: white; } .contract-paper { box-shadow: none; margin: 0; padding: 30px; } .no-print { display: none !important; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: #1d1d1f;">
        <div class="container">
            <a class="navbar-brand" href="tenant_bills.php"><img src="images/logo.svg" alt="Logo" height="28"></a>
            <div>
                <a href="tenant_bills.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> 返回</a>
            </div>
        </div>
    </nav>

    <div class="no-print" style="max-width: 800px; margin: 20px auto;">
        <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer me-1"></i> 打印合同</button>
    </div>

    <div class="contract-paper" id="contractContent">
        <div class="contract-title">房屋租赁合同</div>
        
        <div class="contract-section">
            <p class="contract-text">甲方（出租方）：<?php echo $siteName; ?></p>
            <p class="contract-text">乙方（承租方）：<?php echo $contract['tenant_name']; ?></p>
            <p class="contract-text">根据《中华人民共和国合同法》及相关法律法规，甲乙双方在平等、自愿、公平和诚实信用的原则基础上，就房屋租赁相关事宜达成如下协议：</p>
        </div>

        <div class="contract-section">
            <h5>一、房屋信息</h5>
            <div class="contract-row"><span class="contract-label">房屋地址：</span><span class="contract-value"><?php echo $siteAddress; ?></span></div>
            <div class="contract-row"><span class="contract-label">房间号：</span><span class="contract-value"><?php echo $contract['floor']; ?>楼 <?php echo $contract['room_number']; ?></span></div>
            <div class="contract-row"><span class="contract-label">房屋类型：</span><span class="contract-value"><?php echo $contract['type_name']; ?></span></div>
            <div class="contract-row"><span class="contract-label">房屋面积：</span><span class="contract-value"><?php echo $contract['area']; ?>平方米</span></div>
        </div>

        <div class="contract-section">
            <h5>二、租赁期限</h5>
            <div class="contract-row"><span class="contract-label">起始日期：</span><span class="contract-value"><?php echo formatDate($contract['start_date']); ?></span></div>
            <div class="contract-row"><span class="contract-label">终止日期：</span><span class="contract-value"><?php echo formatDate($contract['end_date']); ?></span></div>
        </div>

        <div class="contract-section">
            <h5>三、租金及押金</h5>
            <div class="contract-row"><span class="contract-label">月租金：</span><span class="contract-value">人民币 <?php echo number_format($contract['monthly_rent'], 2); ?> 元</span></div>
            <div class="contract-row"><span class="contract-label">押金：</span><span class="contract-value">人民币 <?php echo number_format($contract['deposit'], 2); ?> 元</span></div>
            <div class="contract-row"><span class="contract-label">付款方式：</span><span class="contract-value">按月支付</span></div>
        </div>

        <div class="contract-section">
            <h5>四、费用说明</h5>
            <p class="contract-text">1. 水费：按实际用量计算，单价按当时公布价格执行。</p>
            <p class="contract-text">2. 电费：按实际用量计算，单价按当时公布价格执行。</p>
            <p class="contract-text">3. 垃圾管理费：每月固定收取。</p>
        </div>

        <div class="contract-section">
            <h5>五、双方责任</h5>
            <p class="contract-text">1. 甲方应保证房屋及其附属设施处于正常使用状态。</p>
            <p class="contract-text">2. 乙方应按时支付租金及相关费用，爱护房屋及设施。</p>
            <p class="contract-text">3. 乙方不得擅自改变房屋结构或转租。</p>
        </div>

        <div class="contract-section">
            <h5>六、违约责任</h5>
            <p class="contract-text">1. 乙方逾期支付租金的，每逾期一日按月租金的1%支付违约金。</p>
            <p class="contract-text">2. 任何一方提前解除合同的，应提前30天书面通知对方。</p>
        </div>

        <div class="contract-section" style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
            <p class="contract-text mb-0"><strong>重要声明：</strong>本合同一式两份，甲乙双方各执一份。如电子版与纸质版不一致，<strong>以纸质版合同为准</strong>。</p>
        </div>

        <div class="sign-area">
            <div class="sign-box">
                <p>甲方（签章）</p>
                <p class="text-muted small"><?php echo $siteName; ?></p>
                <p class="text-muted small">日期：______年______月______日</p>
            </div>
            <div class="sign-box">
                <p>乙方（签章）</p>
                <p class="text-muted small"><?php echo $contract['tenant_name']; ?></p>
                <p class="text-muted small">日期：______年______月______日</p>
            </div>
        </div>

        <!-- 纸质合同 -->
        <?php if (!empty($contract['contract_file'])): ?>
        <div class="contract-section" style="margin-top: 30px;">
            <h5>纸质合同扫描件</h5>
            <?php 
            $fileExt = strtolower(pathinfo($contract['contract_file'], PATHINFO_EXTENSION));
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                <img src="../<?php echo $contract['contract_file']; ?>" class="img-fluid rounded" style="max-height: 600px;">
            <?php elseif ($fileExt == 'pdf'): ?>
                <iframe src="../<?php echo $contract['contract_file']; ?>" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 8px;"></iframe>
            <?php else: ?>
                <a href="../<?php echo $contract['contract_file']; ?>" class="btn btn-dark" target="_blank"><i class="bi bi-download me-1"></i> 下载合同文件</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
