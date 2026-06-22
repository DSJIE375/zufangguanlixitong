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

function getSetting($key) {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '';
}

$siteName = getSiteName();
$siteAddress = getSetting('site_address') ?: '';
$sitePhone = getSetting('site_phone') ?: '';

// 获取合同详情
$contract_id = intval($_GET['id'] ?? 0);
if (!$contract_id) {
    redirect('contracts.php');
}

$contract = $conn->query("SELECT c.*, r.room_number, r.floor, t.name as tenant_name, t.phone as tenant_phone,
    t.id_card as tenant_idcard, rt.name as type_name, rt.area
    FROM contracts c
    JOIN rooms r ON c.room_id = r.id
    JOIN tenants t ON c.tenant_id = t.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    WHERE c.id = $contract_id")->fetch_assoc();

if (!$contract) {
    redirect('contracts.php');
}

// 格式化日期
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
        .contract-paper { max-width: 800px; margin: 20px auto; background: white; padding: 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-radius: 16px; }
        .contract-title { text-align: center; font-size: 28px; font-weight: bold; margin-bottom: 30px; }
        .contract-section { margin-bottom: 25px; }
        .contract-section h5 { font-size: 16px; font-weight: bold; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #ddd; }
        .contract-row { display: flex; margin-bottom: 8px; }
        .contract-label { width: 100px; font-weight: 500; }
        .contract-value { flex: 1; }
        .contract-text { text-indent: 2em; line-height: 1.8; margin-bottom: 10px; }
        .sign-area { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; flex-wrap: wrap; gap: 20px; }
        .sign-box { width: 230px; text-align: center; border-top: 1px solid #333; padding-top: 10px; }
        .sign-canvas-container { width: 100%; max-width: 350px; margin: 10px auto; }
        .sign-canvas { width: 100%; height: 120px; border: 2px solid #000; border-radius: 8px; cursor: crosshair; touch-action: none; }
        /* 全屏签名板 */
        .fullscreen-sign { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: white; z-index: 9999; flex-direction: column; }
        .fullscreen-sign.active { display: flex; }
        .sign-header { background: #1d1d1f; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .sign-canvas-area { flex: 1; padding: 20px; display: flex; align-items: center; justify-content: center; }
        .sign-canvas-full { width: 95%; max-width: 700px; height: 250px; border: 2px solid #000; border-radius: 10px; cursor: crosshair; touch-action: none; background: white; }
        .sign-hint { text-align: center; color: #86868b; font-size: 14px; margin-top: 10px; }
        .sign-footer { padding: 15px 20px; background: #f5f5f7; display: flex; gap: 10px; justify-content: center; }
        @media (orientation: landscape) and (max-height: 500px) {
            .sign-canvas-full { height: 180px; }
            .sign-header { padding: 10px 20px; }
            .sign-footer { padding: 10px 20px; }
        }
        @media (orientation: landscape) and (max-height: 500px) {
            .sign-canvas-full { height: 150px; }
        }
    </style>
</head>
<body>
    <!-- 全屏签名板 -->
    <div class="fullscreen-sign" id="fullscreenSign">
        <div class="sign-header">
            <span><i class="bi bi-pencil"></i> 请在下方区域签名</span>
            <button class="btn btn-sm btn-outline-light" onclick="cancelSign()">取消</button>
        </div>
        <div class="sign-canvas-area">
            <div style="text-align: center;">
                <canvas id="fullSignCanvas" class="sign-canvas-full"></canvas>
                <div class="sign-hint">请使用手指或鼠标在上方签名</div>
            </div>
        </div>
        <div class="sign-footer">
            <button class="btn btn-outline-dark" onclick="clearFullSign()"><i class="bi bi-eraser"></i> 清除</button>
            <button class="btn btn-dark" onclick="confirmFullSign()"><i class="bi bi-check-lg"></i> 确认签名</button>
        </div>
    </div>

    <div class="no-print" style="max-width: 800px; margin: 20px auto;">
        <div class="d-flex justify-content-between align-items-center">
            <a href="contracts.php" class="btn btn-outline-dark"><i class="bi bi-arrow-left me-1"></i> 返回</a>
            <div>
                <button onclick="downloadPDF(this)" class="btn btn-outline-dark me-2"><i class="bi bi-file-earmark-pdf me-1"></i> 下载PDF</button>
                <button onclick="window.print()" class="btn btn-dark me-2"><i class="bi bi-printer me-1"></i> 打印合同</button>
                <label for="contractFile" class="btn btn-outline-success"><i class="bi bi-upload me-1"></i> 上传纸质合同</label>
                <input type="file" id="contractFile" style="display:none;" accept="image/*,.pdf" onchange="uploadContract(this, <?php echo $contract_id; ?>)">
            </div>
        </div>
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
            <div class="contract-row"><span class="contract-label">月租金：</span><span class="contract-value">人民币 <?php echo number_format($contract['monthly_rent'], 2); ?> 元（大写：<?php echo numtoChinese($contract['monthly_rent']); ?>）</span></div>
            <div class="contract-row"><span class="contract-label">押金：</span><span class="contract-value">人民币 <?php echo number_format($contract['deposit'], 2); ?> 元（大写：<?php echo numtoChinese($contract['deposit']); ?>）</span></div>
            <div class="contract-row"><span class="contract-label">付款方式：</span><span class="contract-value">按月支付，每月<?php echo date('d', strtotime($contract['start_date'])); ?>日前支付当月租金</span></div>
        </div>

        <div class="contract-section">
            <h5>四、费用说明</h5>
            <p class="contract-text">1. 水费：按实际用量计算，单价按当时公布价格执行。</p>
            <p class="contract-text">2. 电费：按实际用量计算，单价按当时公布价格执行。</p>
            <p class="contract-text">3. 垃圾管理费：每月固定收取。</p>
            <p class="contract-text">4. 其他费用：按实际情况另行约定。</p>
        </div>

        <div class="contract-section">
            <h5>五、双方责任</h5>
            <p class="contract-text">1. 甲方应保证房屋及其附属设施处于正常使用状态。</p>
            <p class="contract-text">2. 乙方应按时支付租金及相关费用，爱护房屋及设施。</p>
            <p class="contract-text">3. 乙方不得擅自改变房屋结构或转租。</p>
            <p class="contract-text">4. 租赁期满，乙方应按时交还房屋，甲方应在验收后退还押金。</p>
        </div>

        <div class="contract-section">
            <h5>六、违约责任</h5>
            <p class="contract-text">1. 乙方逾期支付租金的，每逾期一日按月租金的1%支付违约金。</p>
            <p class="contract-text">2. 任何一方提前解除合同的，应提前30天书面通知对方。</p>
        </div>

        <div class="contract-section" style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
            <p class="contract-text mb-0"><strong>重要声明：</strong>本合同一式两份，甲乙双方各执一份。如电子版与纸质版不一致，<strong>以纸质版合同为准</strong>。</p>
        </div>

        <!-- 已保存的签名 -->
        <?php if (!empty($contract['signature'])): ?>
        <div class="contract-section" style="margin-top: 30px;">
            <h5>乙方签名</h5>
            <img src="../<?php echo $contract['signature']; ?>" style="max-height: 150px; border: 1px solid #ddd; border-radius: 8px;">
        </div>
        <?php endif; ?>

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

            <div class="sign-area">
            <div class="sign-box">
                <p>甲方（签章）</p>
                <div id="ownerSignArea" style="height: 80px; width: 100%; max-width: 230px; border: 1px dashed #ccc; margin: 10px auto; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="autoSignOwner()">
                    <span class="text-muted small">点击自动签名</span>
                </div>
                <p class="text-muted small"><?php echo $siteName; ?></p>
                <p class="small"><input type="text" class="form-control form-control-sm text-center" style="width: 200px; margin: 0 auto;" value="<?php echo date('Y年m月d日'); ?>" id="ownerDate"></p>
            </div>
            <div class="sign-box">
                <p>乙方（签章）</p>
                <div id="tenantSignArea" style="height: 80px; width: 100%; max-width: 230px; border: 1px dashed #ccc; margin: 10px auto; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="startTenantSign()">
                    <span class="text-muted small">点击签名</span>
                </div>
                <p class="text-muted small"><?php echo $contract['tenant_name']; ?></p>
                <p class="small"><input type="text" class="form-control form-control-sm text-center" style="width: 200px; margin: 0 auto;" value="<?php echo date('Y年m月d日'); ?>" id="tenantDate"></p>
            </div>
        </div>
    </div>

    <script>
    // 自动签名（甲方）
    function autoSignOwner() {
        var area = document.getElementById('ownerSignArea');
        area.innerHTML = '<canvas id="ownerCanvas" width="220" height="70" style="border: 1px solid #ddd; width: 100%; max-width: 230px;"></canvas>';
        var canvas = document.getElementById('ownerCanvas');
        var ctx = canvas.getContext('2d');
        ctx.font = '24px 楷体';
        ctx.fillStyle = '#000';
        ctx.fillText('<?php echo $siteName; ?>', 10, 45);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 1;
        ctx.strokeRect(0, 0, 220, 70);
    }
    
    // 全屏签名相关
    var currentSignType = '';
    var fullCanvas, fullCtx, fullDrawing = false;
    
    function startTenantSign() {
        currentSignType = 'tenant';
        document.getElementById('fullscreenSign').classList.add('active');
        initFullCanvas();
    }
    
    function initFullCanvas() {
        fullCanvas = document.getElementById('fullSignCanvas');
        fullCtx = fullCanvas.getContext('2d');
        fullCanvas.width = fullCanvas.offsetWidth * 2;
        fullCanvas.height = fullCanvas.offsetHeight * 2;
        fullCtx.scale(2, 2);
        fullCtx.strokeStyle = '#000';
        fullCtx.lineWidth = 3;
        fullCtx.lineCap = 'round';
        fullCtx.lineJoin = 'round';
        fullDrawing = false;
        
        // 缓存rect避免重复计算
        var cachedRect = null;
        
        function updateRect() {
            cachedRect = fullCanvas.getBoundingClientRect();
        }
        
        // 鼠标事件
        fullCanvas.addEventListener('mousedown', function(e) {
            fullDrawing = true;
            updateRect();
            fullCtx.beginPath();
            fullCtx.moveTo(e.clientX - cachedRect.left, e.clientY - cachedRect.top);
        });
        fullCanvas.addEventListener('mousemove', function(e) {
            if (fullDrawing) {
                updateRect();
                fullCtx.lineTo(e.clientX - cachedRect.left, e.clientY - cachedRect.top);
                fullCtx.stroke();
            }
        });
        fullCanvas.addEventListener('mouseup', function() { fullDrawing = false; });
        fullCanvas.addEventListener('mouseleave', function() { fullDrawing = false; });
        
        // 触摸事件
        fullCanvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            fullDrawing = true;
            updateRect();
            var touch = e.touches[0];
            fullCtx.beginPath();
            fullCtx.moveTo(touch.clientX - cachedRect.left, touch.clientY - cachedRect.top);
        }, { passive: false });
        
        fullCanvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (fullDrawing) {
                updateRect();
                var touch = e.touches[0];
                fullCtx.lineTo(touch.clientX - cachedRect.left, touch.clientY - cachedRect.top);
                fullCtx.stroke();
            }
        }, { passive: false });
        
        fullCanvas.addEventListener('touchend', function() { fullDrawing = false; });
    }
    
    function clearFullSign() {
        if (fullCanvas && fullCtx) {
            fullCtx.clearRect(0, 0, fullCanvas.width, fullCanvas.height);
        }
    }
    
    function confirmFullSign() {
        if (fullCanvas) {
            var dataUrl = fullCanvas.toDataURL('image/png');
            var area = document.getElementById('tenantSignArea');
            
            // 显示签名图片
            area.innerHTML = '<img src="' + dataUrl + '" style="height: 80px; width: 100%; max-width: 230px; border: 1px solid #ddd; border-radius: 8px; cursor: pointer;" onclick="startTenantSign()">' +
                '<input type="hidden" id="tenantSignData" value="' + dataUrl + '">';
            
            // 保存到数据库
            var contractId = <?php echo $contract_id; ?>;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_signature.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        alert('签名已保存！');
                    }
                }
            };
            xhr.send('action=save_signature&contract_id=' + contractId + '&signature_data=' + encodeURIComponent(dataUrl));
        }
        closeFullSign();
    }
    
    function cancelSign() {
        closeFullSign();
    }
    
    function closeFullSign() {
        document.getElementById('fullscreenSign').classList.remove('active');
        if (screen.orientation && screen.orientation.unlock) {
            screen.orientation.unlock().catch(function() {});
        }
    }
    
    // 手动签名（乙方）
    function signTenant() {
        var area = document.getElementById('tenantSignArea');
        area.innerHTML = '<canvas id="tenantCanvas" width="220" height="70" style="border: 1px solid #000; cursor: crosshair;"></canvas><br><small class="text-muted">在上方区域签名，完成后点击"确认"</small><br><button class="btn btn-sm btn-dark mt-1" onclick="confirmTenantSign()">确认签名</button><button class="btn btn-sm btn-outline-secondary mt-1 ms-1" onclick="clearTenantSign()">清除重签</button>';
        
        var canvas = document.getElementById('tenantCanvas');
        var ctx = canvas.getContext('2d');
        var drawing = false;
        
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        canvas.onmousedown = function(e) { drawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); };
        canvas.onmousemove = function(e) { if (drawing) { ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); } };
        canvas.onmouseup = function() { drawing = false; };
        canvas.onmouseleave = function() { drawing = false; };
        
        // 触摸支持
        canvas.ontouchstart = function(e) { e.preventDefault(); drawing = true; var t = e.touches[0]; var rect = canvas.getBoundingClientRect(); ctx.beginPath(); ctx.moveTo(t.clientX - rect.left, t.clientY - rect.top); };
        canvas.ontouchmove = function(e) { e.preventDefault(); if (drawing) { var t = e.touches[0]; var rect = canvas.getBoundingClientRect(); ctx.lineTo(t.clientX - rect.left, t.clientY - rect.top); ctx.stroke(); } };
        canvas.ontouchend = function() { drawing = false; };
    }
    
    function clearTenantSign() {
        var area = document.getElementById('tenantSignArea');
        area.innerHTML = '<canvas id="tenantCanvas" width="220" height="70" style="border: 1px solid #000; cursor: crosshair;"></canvas><br><small class="text-muted">在上方区域签名，完成后点击"确认"</small><br><button class="btn btn-sm btn-dark mt-1" onclick="confirmTenantSign()">确认签名</button><button class="btn btn-sm btn-outline-secondary mt-1 ms-1" onclick="clearTenantSign()">清除重签</button>';
        var canvas = document.getElementById('tenantCanvas');
        var ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#000'; ctx.lineWidth = 2; ctx.lineCap = 'round';
        canvas.onmousedown = function(e) { drawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); };
        canvas.onmousemove = function(e) { if (drawing) { ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); } };
        canvas.onmouseup = function() { drawing = false; };
        canvas.onmouseleave = function() { drawing = false; };
    }
    
    function confirmTenantSign() {
        var canvas = document.getElementById('tenantCanvas');
        var area = document.getElementById('tenantSignArea');
        var dataUrl = canvas.toDataURL();
        area.innerHTML = '<img src="' + dataUrl + '" height="50" style="border: 1px solid #ddd;"><input type="hidden" id="tenantSignData" value="' + dataUrl + '">';
    }
    
    // 上传合同
    function uploadContract(input, contractId) {
        if (input.files && input.files[0]) {
            var formData = new FormData();
            formData.append('action', 'upload_contract');
            formData.append('contract_id', contractId);
            formData.append('contract_file', input.files[0]);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload_contract.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        alert('合同上传成功！');
                    } else {
                        alert('上传失败：' + result.error);
                    }
                }
            };
            xhr.send(formData);
        }
    }
    
    // 下载PDF
    function downloadPDF(btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 生成中...';
        var element = document.getElementById('contractContent');
        element.style.width = '800px';
        element.style.minWidth = '800px';
        setTimeout(function() {
            html2canvas(element, { scale: 2, useCORS: true, backgroundColor: '#ffffff', width: 800, windowWidth: 800 })
            .then(function(canvas) {
                var imgData = canvas.toDataURL('image/png');
                var pdf = new jspdf.jsPDF('p', 'mm', 'a4');
                var pdfHeight = (canvas.height * 210) / canvas.width;
                pdf.addImage(imgData, 'PNG', 0, 0, 210, pdfHeight);
                pdf.save('租赁合同_<?php echo $contract["tenant_name"]; ?>_<?php echo $contract["room_number"]; ?>.pdf');
                element.style.width = '';
                element.style.minWidth = '';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> 下载PDF';
                alert('合同PDF已保存');
            });
        }, 100);
    }
    </script>
</body>
</html>
<?php
// 数字转中文大写
function numtoChinese($num) {
    $num = round($num, 2);
    $intPart = intval($num);
    $decPart = round(($num - $intPart) * 100);
    
    $chineseNums = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
    $chineseUnits = ['', '拾', '佰', '仟', '万', '拾', '佰', '仟', '亿'];
    
    $result = '';
    $intStr = strval($intPart);
    $len = strlen($intStr);
    
    for ($i = 0; $i < $len; $i++) {
        $digit = intval($intStr[$i]);
        $unit = $chineseUnits[$len - $i - 1];
        
        if ($digit == 0) {
            if ($result && substr($result, -1) != '零') {
                $result .= '零';
            }
        } else {
            $result .= $chineseNums[$digit] . $unit;
        }
    }
    
    $result = preg_replace('/零+/', '零', $result);
    $result = preg_replace('/零(拾|佰|仟|万|亿)/', '$1', $result);
    $result = rtrim($result, '零');
    
    if (empty($result)) $result = '零';
    
    $result .= '元';
    
    if ($decPart > 0) {
        $jiao = intval($decPart / 10);
        $fen = $decPart % 10;
        $result .= $chineseNums[$jiao] . '角';
        if ($fen > 0) {
            $result .= $chineseNums[$fen] . '分';
        }
    } else {
        $result .= '整';
    }
    
    return $result;
}
?>
