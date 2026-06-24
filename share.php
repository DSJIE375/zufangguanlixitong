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

$token = $_GET['token'] ?? '';
$bill_id = intval($_GET['bill_id'] ?? 0);

if (!$token && !$bill_id) {
    header("Location: index.php");
    exit;
}

$bill = null;
$error = '';

if ($token) {
    // 清理token，去除前后空格和特殊字符
    $token = trim($token);
    
    // 通过token访问
    $stmt = $conn->prepare("SELECT * FROM share_links WHERE token=?");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $shareLink = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    if (!$shareLink) {
        $error = '链接无效';
    } elseif ($shareLink['is_active'] != 1) {
        $error = '链接已被禁用';
    } elseif ($shareLink['expire_at'] && strtotime($shareLink['expire_at']) < time()) {
        $error = '链接已过期';
    } else {
        $bill_id = $shareLink['bill_id'];
    }
}

if ($bill_id && !$error) {
    // 通过bill_id直接访问
    $stmt = $conn->prepare("SELECT b.*, c.monthly_rent, r.room_number, r.floor,
        t.name as tenant_name, t.phone as tenant_phone,
        rt.name as type_name, rt.area
        FROM bills b
        LEFT JOIN contracts c ON b.contract_id = c.id
        LEFT JOIN rooms r ON c.room_id = r.id
        LEFT JOIN tenants t ON c.tenant_id = t.id
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $bill_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    
    if (!$bill) {
        $error = '账单不存在';
    }
}

$siteName = getSiteName();
$siteAddress = getSetting('site_address') ?: '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>账单 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Helvetica Neue", Arial, sans-serif; }
        .bill-paper { max-width: 800px; margin: 20px auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .bill-header { text-align: center; border-bottom: 2px solid #1d1d1f; padding-bottom: 20px; margin-bottom: 20px; }
        .bill-title { font-size: 24px; font-weight: 700; margin-bottom: 5px; color: #1d1d1f; }
        .bill-subtitle { font-size: 14px; color: #86868b; }
        .bill-info { margin-bottom: 20px; }
        .bill-info table { width: 100%; }
        .bill-info td { padding: 8px 0; font-size: 14px; }
        .bill-info td:first-child { width: 100px; color: #86868b; font-weight: 500; }
        .bill-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .bill-table th, .bill-table td { border: 1px solid #e5e5e7; padding: 12px; font-size: 14px; text-align: center; }
        .bill-table th { background: #1d1d1f; color: white; font-weight: 600; }
        .bill-table td.text-right { text-align: right; }
        .bill-total { font-size: 20px; font-weight: 700; text-align: right; padding: 15px 0; border-top: 2px solid #1d1d1f; }
        .bill-footer { margin-top: 30px; font-size: 12px; color: #86868b; text-align: center; }
        .error-box { max-width: 500px; margin: 100px auto; text-align: center; }
        .error-box i { font-size: 80px; color: #dc3545; }
    </style>
</head>
<body>
    <?php if (!empty($error)): ?>
    <div class="error-box">
        <i class="bi bi-exclamation-triangle"></i>
        <h2 class="mt-4"><?php echo $error; ?></h2>
        <p class="text-muted">请联系房东获取新的链接</p>
        <a href="index.php" class="btn btn-dark mt-3">返回首页</a>
    </div>
    <?php else: ?>
    <div class="no-print" style="max-width: 800px; margin: 20px auto;">
        <div class="d-flex justify-content-between align-items-center">
            <a href="javascript:history.back()" class="btn btn-outline-dark"><i class="bi bi-arrow-left me-1"></i> 返回</a>
            <div>
                <button id="btnPDF" onclick="downloadPDF(this)" class="btn btn-outline-dark me-2"><i class="bi bi-file-earmark-pdf me-1"></i> 下载PDF</button>
                <button id="btnImage" onclick="saveAsImage(this)" class="btn btn-outline-dark me-2"><i class="bi bi-image me-1"></i> 保存为图片</button>
            </div>
        </div>
    </div>

    <div class="bill-paper" id="billContent">
        <div class="bill-header">
            <div class="bill-title"><?php echo $siteName; ?></div>
            <div class="bill-subtitle">水电房租账单</div>
        </div>

        <div class="bill-info">
            <table>
                <tr>
                    <td>账单月份：</td>
                    <td><strong><?php echo $bill['bill_month']; ?></strong></td>
                    <td>房间号：</td>
                    <td><strong><?php echo $bill['floor']; ?>楼 <?php echo $bill['room_number']; ?></strong></td>
                </tr>
                <tr>
                    <td>租客姓名：</td>
                    <td><?php echo $bill['tenant_name']; ?></td>
                    <td>联系电话：</td>
                    <td><?php echo $bill['tenant_phone']; ?></td>
                </tr>
                <tr>
                    <td>房间类型：</td>
                    <td><?php echo $bill['type_name']; ?></td>
                    <td>房间面积：</td>
                    <td><?php echo $bill['area']; ?>㎡</td>
                </tr>
            </table>
        </div>

        <table class="bill-table">
            <thead>
                <tr>
                    <th>费用项目</th>
                    <th>上期读数</th>
                    <th>本期读数</th>
                    <th>使用量</th>
                    <th>单价</th>
                    <th>金额（元）</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="bi bi-droplet"></i> <strong>水费</strong></td>
                    <td><?php echo number_format($bill['water_start'], 2); ?> 吨</td>
                    <td><?php echo number_format($bill['water_end'], 2); ?> 吨</td>
                    <td><?php echo number_format($bill['water_usage'], 2); ?> 吨</td>
                    <td>¥<?php echo number_format($bill['water_price'], 2); ?>/吨</td>
                    <td class="text-right">¥<?php echo number_format($bill['water_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td><i class="bi bi-lightning"></i> <strong>电费</strong></td>
                    <td><?php echo number_format($bill['elec_start'], 2); ?> 度</td>
                    <td><?php echo number_format($bill['elec_end'], 2); ?> 度</td>
                    <td><?php echo number_format($bill['elec_usage'], 2); ?> 度</td>
                    <td>¥<?php echo number_format($bill['elec_price'], 2); ?>/度</td>
                    <td class="text-right">¥<?php echo number_format($bill['elec_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td><i class="bi bi-trash"></i> <strong>垃圾费</strong></td>
                    <td colspan="4">垃圾管理费</td>
                    <td class="text-right">¥<?php echo number_format($bill['garbage_fee'], 2); ?></td>
                </tr>
                <?php if ($bill['other_fee'] > 0): ?>
                <tr>
                    <td><i class="bi bi-plus-circle"></i> <strong><?php echo $bill['other_fee_desc'] ?: '其他'; ?></strong></td>
                    <td colspan="4"><?php echo $bill['other_fee_desc'] ?: '其他费用'; ?></td>
                    <td class="text-right">¥<?php echo number_format($bill['other_fee'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><i class="bi bi-house"></i> <strong>房租</strong></td>
                    <td colspan="4">月租金</td>
                    <td class="text-right">¥<?php echo number_format($bill['rent_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="bill-info" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h6 style="font-weight: bold; margin-bottom: 10px;">费用说明：</h6>
            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #555;">
                <li><i class="bi bi-droplet"></i> 水费：按实际用水量计算，单价 ¥<?php echo number_format($bill['water_price'], 2); ?>/吨</li>
                <li><i class="bi bi-lightning"></i> 电费：按实际用电量计算，单价 ¥<?php echo number_format($bill['elec_price'], 2); ?>/度</li>
                <li><i class="bi bi-trash"></i> 垃圾管理费：每月固定 ¥<?php echo number_format($bill['garbage_fee'], 2); ?></li>
                <?php if ($bill['other_fee'] > 0): ?>
                <li><i class="bi bi-plus-circle"></i> <?php echo $bill['other_fee_desc'] ?: '其他费用' ?>：¥<?php echo number_format($bill['other_fee'], 2); ?></li>
                <?php endif; ?>
                <li><i class="bi bi-house"></i> 房租：按月固定收取，月租金 ¥<?php echo number_format($bill['rent_amount'], 2); ?></li>
            </ul>
            <hr style="margin: 10px 0;">
            <h6 style="font-weight: bold; margin-bottom: 10px;">本次账单计算明细：</h6>
            <div style="font-size: 13px; color: #333; background: white; padding: 10px; border-radius: 3px;">
                <p style="margin-bottom: 5px;"><i class="bi bi-droplet"></i> <strong>水费：</strong><?php echo number_format($bill['water_end'], 2); ?>（本期）- <?php echo number_format($bill['water_start'], 2); ?>（上期）= <?php echo number_format($bill['water_usage'], 2); ?>吨 × ¥<?php echo number_format($bill['water_price'], 2); ?> = <strong>¥<?php echo number_format($bill['water_amount'], 2); ?></strong></p>
                <p style="margin-bottom: 5px;"><i class="bi bi-lightning"></i> <strong>电费：</strong><?php echo number_format($bill['elec_end'], 2); ?>（本期）- <?php echo number_format($bill['elec_start'], 2); ?>（上期）= <?php echo number_format($bill['elec_usage'], 2); ?>度 × ¥<?php echo number_format($bill['elec_price'], 2); ?> = <strong>¥<?php echo number_format($bill['elec_amount'], 2); ?></strong></p>
                <p style="margin-bottom: 5px;"><i class="bi bi-trash"></i> <strong>垃圾管理费：</strong>每月固定 = <strong>¥<?php echo number_format($bill['garbage_fee'], 2); ?></strong></p>
                <?php if ($bill['other_fee'] > 0): ?>
                <p style="margin-bottom: 5px;"><i class="bi bi-plus-circle"></i> <strong><?php echo $bill['other_fee_desc'] ?: '其他费用' ?>：</strong>= <strong>¥<?php echo number_format($bill['other_fee'], 2); ?></strong></p>
                <?php endif; ?>
                <p style="margin-bottom: 5px;"><i class="bi bi-house"></i> <strong>房租：</strong>月租金 = <strong>¥<?php echo number_format($bill['rent_amount'], 2); ?></strong></p>
                <hr style="margin: 8px 0;">
                <p style="margin-bottom: 0; font-size: 15px;"><strong>合计：</strong><span style="font-size: 18px;">¥<?php echo number_format($bill['total_amount'], 2); ?></span></p>
                <p style="margin-bottom: 0; font-size: 12px; color: #888;">= 水费 ¥<?php echo number_format($bill['water_amount'], 2); ?> + 电费 ¥<?php echo number_format($bill['elec_amount'], 2); ?> + 垃圾费 ¥<?php echo number_format($bill['garbage_fee'], 2); ?><?php if ($bill['other_fee'] > 0): ?> + <?php echo $bill['other_fee_desc'] ?: '其他' ?> ¥<?php echo number_format($bill['other_fee'], 2); ?><?php endif; ?> + 房租 ¥<?php echo number_format($bill['rent_amount'], 2); ?></p>
            </div>
        </div>

        <div class="bill-total">
            应缴合计：<span style="color: #1d1d1f;">¥<?php echo number_format($bill['total_amount'], 2); ?></span>
        </div>

        <div class="bill-info" style="border-top: 1px dashed #ddd; padding-top: 15px;">
            <table>
                <tr>
                    <td>缴费状态：</td>
                    <td><strong style="color: <?php echo $bill['status'] == 'paid' ? '#198754' : '#dc3545'; ?>;">
                        <?php echo $bill['status'] == 'paid' ? '已缴费' : '待缴费'; ?>
                    </strong></td>
                </tr>
            </table>
        </div>

        <div class="bill-footer">
            <p><?php echo $siteAddress; ?></p>
            <p>如有疑问请联系房东，谢谢配合！</p>
        </div>
    </div>

    <script>
    function downloadPDF(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 生成中...';
        var element = document.getElementById('billContent');
        var origWidth = element.style.width;
        var origMinWidth = element.style.minWidth;
        var origTransform = element.style.transform;
        element.style.width = '800px';
        element.style.minWidth = '800px';
        element.style.transform = 'none';
        setTimeout(function() {
            html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff', width: 800, windowWidth: 800 })
            .then(function(canvas) {
                var imgData = canvas.toDataURL('image/png');
                var pdf = new jspdf.jsPDF('p', 'mm', 'a4');
                var pdfHeight = (canvas.height * 210) / canvas.width;
                pdf.addImage(imgData, 'PNG', 0, 0, 210, pdfHeight);
                pdf.save('账单_<?php echo $bill["room_number"] . "_" . $bill["bill_month"] . "_" . $bill["tenant_name"] . "_" . $bill["tenant_phone"]; ?>.pdf');
                element.style.width = origWidth;
                element.style.minWidth = origMinWidth;
                element.style.transform = origTransform;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> 下载PDF';
                alert('PDF已保存');
            });
        }, 100);
    }
    function saveAsImage(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 生成中...';
        var element = document.getElementById('billContent');
        var origWidth = element.style.width;
        var origMinWidth = element.style.minWidth;
        element.style.width = '800px';
        element.style.minWidth = '800px';
        setTimeout(function() {
            html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff', width: 800, windowWidth: 800 })
            .then(function(canvas) {
                var link = document.createElement('a');
                link.download = '账单_<?php echo $bill["room_number"] . "_" . $bill["bill_month"] . "_" . $bill["tenant_name"] . "_" . $bill["tenant_phone"]; ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                element.style.width = origWidth;
                element.style.minWidth = '';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-image"></i> 保存为图片';
                alert('图片已保存');
            });
        }, 100);
    }
    </script>
    <?php endif; ?>
</body>
</html>
