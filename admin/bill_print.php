<?php
require_once '../includes/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$bill_id = intval($_GET['id'] ?? 0);
if (!$bill_id) {
    redirect('bills.php');
}

// 获取账单详情
$bill = $conn->query("SELECT b.*, c.monthly_rent, c.deposit, r.room_number, r.floor,
    t.name as tenant_name, t.phone as tenant_phone, t.id_card as tenant_idcard,
    rt.name as type_name, rt.area
    FROM bills b
    LEFT JOIN contracts c ON b.contract_id = c.id
    LEFT JOIN rooms r ON c.room_id = r.id
    LEFT JOIN tenants t ON c.tenant_id = t.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    WHERE b.id = $bill_id")->fetch_assoc();

if (!$bill) {
    redirect('bills.php');
}

$siteName = '';
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
if ($result->num_rows > 0) {
    $siteName = $result->fetch_assoc()['setting_value'];
}

$sitePhone = '';
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_phone'");
if ($result->num_rows > 0) {
    $sitePhone = $result->fetch_assoc()['setting_value'];
}

$siteAddress = '';
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_address'");
if ($result->num_rows > 0) {
    $siteAddress = $result->fetch_assoc()['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账单 - <?php echo $bill['room_number']; ?> - <?php echo $bill['bill_month']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { background: #f5f5f7; font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Helvetica Neue", Arial, sans-serif; }
        .bill-paper {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
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
        
        @media print {
            body { background: white; }
            .bill-paper { box-shadow: none; margin: 0; padding: 20px; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- 操作按钮 -->
    <div class="no-print" style="max-width: 800px; margin: 20px auto;">
        <div class="d-flex justify-content-between align-items-center">
            <a href="bills.php" class="btn btn-outline-dark"><i class="bi bi-arrow-left me-1"></i> 返回列表</a>
            <div>
                <button id="btnPDF" onclick="downloadPDF(this)" class="btn btn-outline-dark me-2"><i class="bi bi-file-earmark-pdf me-1"></i> 下载PDF</button>
                <button id="btnImage" onclick="saveAsImage(this)" class="btn btn-outline-dark me-2"><i class="bi bi-image me-1"></i> 保存为图片</button>
                <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer me-1"></i> 打印账单</button>
            </div>
        </div>
    </div>

    <!-- 账单内容 -->
    <div class="bill-paper" id="billContent">
        <!-- 头部 -->
        <div class="bill-header">
            <div class="bill-title"><?php echo $siteName; ?></div>
            <div class="bill-subtitle">水电房租账单</div>
        </div>

        <!-- 基本信息 -->
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

        <!-- 费用明细 -->
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
                    <td><strong>水费</strong></td>
                    <td><?php echo number_format($bill['water_start'], 2); ?> 吨</td>
                    <td><?php echo number_format($bill['water_end'], 2); ?> 吨</td>
                    <td><?php echo number_format($bill['water_usage'], 2); ?> 吨</td>
                    <td>¥<?php echo number_format($bill['water_price'], 2); ?>/吨</td>
                    <td class="text-right">¥<?php echo number_format($bill['water_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td><strong>电费</strong></td>
                    <td><?php echo number_format($bill['elec_start'], 2); ?> 度</td>
                    <td><?php echo number_format($bill['elec_end'], 2); ?> 度</td>
                    <td><?php echo number_format($bill['elec_usage'], 2); ?> 度</td>
                    <td>¥<?php echo number_format($bill['elec_price'], 2); ?>/度</td>
                    <td class="text-right">¥<?php echo number_format($bill['elec_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td><strong>房租</strong></td>
                    <td colspan="4">月租金</td>
                    <td class="text-right">¥<?php echo number_format($bill['rent_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- 费用说明 -->
        <div class="bill-info" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h6 style="font-weight: bold; margin-bottom: 10px;">费用说明：</h6>
            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #555;">
                <li>水费：按实际用水量计算，单价 ¥<?php echo number_format($bill['water_price'], 2); ?>/吨</li>
                <li>电费：按实际用电量计算，单价 ¥<?php echo number_format($bill['elec_price'], 2); ?>/度</li>
                <li>房租：按月固定收取，月租金 ¥<?php echo number_format($bill['rent_amount'], 2); ?></li>
            </ul>
            <hr style="margin: 10px 0;">
            <h6 style="font-weight: bold; margin-bottom: 10px;">本次账单计算明细：</h6>
            <div style="font-size: 13px; color: #333; background: white; padding: 10px; border-radius: 3px;">
                <p style="margin-bottom: 5px;"><strong>水费：</strong><?php echo number_format($bill['water_end'], 2); ?>（本期）- <?php echo number_format($bill['water_start'], 2); ?>（上期）= <?php echo number_format($bill['water_usage'], 2); ?>吨 × ¥<?php echo number_format($bill['water_price'], 2); ?> = <strong style="color: #dc3545;">¥<?php echo number_format($bill['water_amount'], 2); ?></strong></p>
                <p style="margin-bottom: 5px;"><strong>电费：</strong><?php echo number_format($bill['elec_end'], 2); ?>（本期）- <?php echo number_format($bill['elec_start'], 2); ?>（上期）= <?php echo number_format($bill['elec_usage'], 2); ?>度 × ¥<?php echo number_format($bill['elec_price'], 2); ?> = <strong style="color: #dc3545;">¥<?php echo number_format($bill['elec_amount'], 2); ?></strong></p>
                <p style="margin-bottom: 0;"><strong>房租：</strong>¥<?php echo number_format($bill['rent_amount'], 2); ?></p>
                <p style="margin-bottom: 0; margin-top: 8px; font-size: 15px;"><strong>合计：</strong><span style="color: #dc3545; font-size: 18px;">¥<?php echo number_format($bill['total_amount'], 2); ?></span> = 水费 ¥<?php echo number_format($bill['water_amount'], 2); ?> + 电费 ¥<?php echo number_format($bill['elec_amount'], 2); ?> + 房租 ¥<?php echo number_format($bill['rent_amount'], 2); ?></p>
            </div>
        </div>

        <!-- 合计 -->
        <div class="bill-total">
            应缴合计：<span style="color: #dc3545;">¥<?php echo number_format($bill['total_amount'], 2); ?></span>
        </div>

        <!-- 缴费信息 -->
        <div class="bill-info" style="border-top: 1px dashed #ddd; padding-top: 15px;">
            <table>
                <tr>
                    <td>缴费状态：</td>
                    <td><strong style="color: <?php echo $bill['status'] == 'paid' ? '#198754' : '#dc3545'; ?>;">
                        <?php echo $bill['status'] == 'paid' ? '已缴费' : '待缴费'; ?>
                    </strong></td>
                    <?php if ($bill['status'] == 'paid'): ?>
                    <td>缴费时间：</td>
                    <td><?php echo $bill['paid_at']; ?></td>
                    <?php endif; ?>
                </tr>
            </table>
        </div>

        <!-- 页脚 -->
        <div class="bill-footer">
            <p>地址：<?php echo $siteAddress; ?> | 电话：<?php echo $sitePhone; ?></p>
            <p>如有疑问请联系房东，谢谢配合！</p>
        </div>
    </div>

    <script>
    // 打印后自动关闭提示
    window.onafterprint = function() {
        if (confirm('账单已发送到打印机，是否返回列表？')) {
            window.location.href = 'bills.php';
        }
    };

    // 下载PDF
    function downloadPDF(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 生成中...';

        var element = document.getElementById('billContent');
        
        // 临时保存原始样式
        var origWidth = element.style.width;
        var origMinWidth = element.style.minWidth;
        var origTransform = element.style.transform;
        
        // 设置固定宽度用于截图
        element.style.width = '800px';
        element.style.minWidth = '800px';
        element.style.transform = 'none';
        
        setTimeout(function() {
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                width: 800,
                windowWidth: 800
            }).then(function(canvas) {
                var imgData = canvas.toDataURL('image/png');
                var imgWidth = canvas.width;
                var imgHeight = canvas.height;
                
                // A4纸尺寸
                var pdfWidth = 210;
                var pdfHeight = (imgHeight * pdfWidth) / imgWidth;
                
                var pdf = new jspdf.jsPDF('p', 'mm', 'a4');
                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                
                var filename = '账单_<?php echo $bill["room_number"] . "_" . $bill["bill_month"] . "_" . $bill["tenant_name"] . "_" . $bill["tenant_phone"]; ?>.pdf';
                pdf.save(filename);
                
                // 恢复原始样式
                element.style.width = origWidth;
                element.style.minWidth = origMinWidth;
                element.style.transform = origTransform;
                
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> 下载PDF';
                alert('PDF已保存到下载文件夹');
            }).catch(function(err) {
                console.error(err);
                element.style.width = origWidth;
                element.style.minWidth = origMinWidth;
                element.style.transform = origTransform;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> 下载PDF';
                alert('生成PDF失败，请重试');
            });
        }, 100);
    }

    // 保存为图片
    function saveAsImage(btn) {
        var element = document.getElementById('billContent');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 生成中...';

        // 临时保存原始样式
        var origWidth = element.style.width;
        var origMinWidth = element.style.minWidth;
        var origTransform = element.style.transform;
        
        // 设置固定宽度用于截图
        element.style.width = '800px';
        element.style.minWidth = '800px';
        element.style.transform = 'none';
        
        setTimeout(function() {
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                width: 800,
                windowWidth: 800
            }).then(function(canvas) {
                var link = document.createElement('a');
                link.download = '账单_<?php echo $bill["room_number"] . "_" . $bill["bill_month"] . "_" . $bill["tenant_name"] . "_" . $bill["tenant_phone"]; ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                
                // 恢复原始样式
                element.style.width = origWidth;
                element.style.minWidth = origMinWidth;
                element.style.transform = origTransform;
                
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-image"></i> 保存为图片';
                
                alert('图片已保存到下载文件夹');
            }).catch(function(err) {
                element.style.width = origWidth;
                element.style.minWidth = origMinWidth;
                element.style.transform = origTransform;
                alert('保存失败，请重试');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-image"></i> 保存为图片';
            });
        }, 100);
    }
    
    // 如果URL带save=1参数，自动执行保存
    <?php if (isset($_GET['save'])): ?>
    window.onload = function() {
        setTimeout(function() {
            saveAsImage();
        }, 500);
    };
    <?php endif; ?>
    </script>
</body>
</html>
