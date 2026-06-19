<?php
require_once '../includes/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

function getSetting($key) {
    global $conn;
    $sql = "SELECT setting_value FROM settings WHERE setting_key = '$key'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '';
}

$siteName = getSetting('site_name') ?: '我的出租房';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fields = [
        'water_price', 'electricity_price', 'site_name', 'site_phone', 'site_address',
        'banner_title', 'banner_subtitle', 'banner_btn_text',
        'about_title', 'about_content', 'about_img_url',
        'advantage_title', 'advantages',
        'process_title', 'process_steps',
        'map_api_key', 'map_security_code', 'map_lng', 'map_lat', 'map_address',
        'notify_email', 'notify_email_enable', 'notify_wechat_key', 'notify_wechat_enable'
    ];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize($_POST[$field]);
            $check = $conn->query("SELECT id FROM settings WHERE setting_key='$field'");
            if ($check->num_rows > 0) {
                $conn->query("UPDATE settings SET setting_value='$value' WHERE setting_key='$field'");
            } else {
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$field', '$value')");
            }
        }
    }
    
    // 处理轮播图上传
    $uploadDir = __DIR__ . '/../uploads/banner/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    foreach (['banner_img1', 'banner_img2'] as $imgField) {
        if (isset($_FILES[$imgField]) && $_FILES[$imgField]['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES[$imgField]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $newname = $imgField . '_' . time() . '.' . $ext;
                $uploadPath = $uploadDir . $newname;
                if (move_uploaded_file($_FILES[$imgField]['tmp_name'], $uploadPath)) {
                    $value = 'uploads/banner/' . $newname;
                    $check = $conn->query("SELECT id FROM settings WHERE setting_key='$imgField'");
                    if ($check->num_rows > 0) {
                        $conn->query("UPDATE settings SET setting_value='$value' WHERE setting_key='$imgField'");
                    } else {
                        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$imgField', '$value')");
                    }
                }
            }
        } elseif (isset($_POST[$imgField . '_old'])) {
            // 保持旧图片
        }
    }
    
    setFlash('success', '设置保存成功');
    redirect('settings.php');
}

$settings = [];
$result = $conn->query("SELECT * FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="bi bi-building"></i> <?php echo $siteName; ?></a>
            <div class="d-flex align-items-center">
                <span class="me-3" style="color: var(--text-muted);"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['realname']; ?></span>
                <a href="logout.php" class="btn btn-outline-dark btn-sm">退出</a>
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

                <h4 class="mb-4"><i class="bi bi-gear"></i> 系统设置</h4>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- 左列 -->
                        <div class="col-lg-6">
                            <!-- 基本信息 -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0"><i class="bi bi-building"></i> 基本信息</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">网站名称</label>
                                        <input type="text" name="site_name" class="form-control" required value="<?php echo $settings['site_name'] ?? '少丽出租房'; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">联系电话（多个用逗号分隔）</label>
                                        <input type="text" name="site_phone" class="form-control" required value="<?php echo $settings['site_phone'] ?? ''; ?>">
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">公寓地址</label>
                                        <input type="text" name="site_address" class="form-control" required value="<?php echo $settings['site_address'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- 水电价格 -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-dark">
                                    <h5 class="mb-0"><i class="bi bi-cash"></i> 水电价格</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">水费单价（元/吨）</label>
                                        <input type="number" name="water_price" class="form-control" step="0.01" min="0" required value="<?php echo $settings['water_price'] ?? '3.50'; ?>">
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">电费单价（元/度）</label>
                                        <input type="number" name="electricity_price" class="form-control" step="0.01" min="0" required value="<?php echo $settings['electricity_price'] ?? '0.60'; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 右列 -->
                        <div class="col-lg-6">
                            <!-- 首页横幅 -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0"><i class="bi bi-image"></i> 首页轮播图</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">横幅主标题</label>
                                        <input type="text" name="banner_title" class="form-control" value="<?php echo $settings['banner_title'] ?? '少丽出租房'; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">横幅副标题</label>
                                        <input type="text" name="banner_subtitle" class="form-control" value="<?php echo $settings['banner_subtitle'] ?? '温馨舒适 · 安全便捷 · 拎包入住'; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">按钮文字</label>
                                        <input type="text" name="banner_btn_text" class="form-control" value="<?php echo $settings['banner_btn_text'] ?? '查看房源'; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">轮播图1（上传图片）</label>
                                        <input type="file" name="banner_img1" class="form-control" accept="image/*">
                                        <?php if (!empty($settings['banner_img1'])): ?>
                                            <img src="../<?php echo $settings['banner_img1']; ?>" class="mt-2" style="max-height:80px; border-radius:5px;">
                                            <input type="hidden" name="banner_img1_old" value="<?php echo $settings['banner_img1']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">轮播图2（上传图片）</label>
                                        <input type="file" name="banner_img2" class="form-control" accept="image/*">
                                        <?php if (!empty($settings['banner_img2'])): ?>
                                            <img src="../<?php echo $settings['banner_img2']; ?>" class="mt-2" style="max-height:80px; border-radius:5px;">
                                            <input type="hidden" name="banner_img2_old" value="<?php echo $settings['banner_img2']; ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 关于我们 -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> 关于我们</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">标题</label>
                                        <input type="text" name="about_title" class="form-control" value="<?php echo $settings['about_title'] ?? '关于我们'; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">内容</label>
                                        <textarea name="about_content" class="form-control" rows="3"><?php echo $settings['about_content'] ?? '少丽出租房位于城市中心地带，交通便利，周边配套齐全。我们提供干净整洁的居住环境，让每一位租客都能感受到家的温暖。'; ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">图片链接</label>
                                        <input type="text" name="about_img_url" class="form-control" value="<?php echo $settings['about_img_url'] ?? 'https://via.placeholder.com/600x400?text=Apartment'; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 租房优势 -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-star"></i> 租房优势</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">标题</label>
                                <input type="text" name="advantage_title" class="form-control" value="<?php echo $settings['advantage_title'] ?? '租房优势'; ?>">
                            </div>
                            <div class="mb-0">
                                <label class="form-label">优势内容（每条格式：标题|描述，多条用逗号分隔）</label>
                                <textarea name="advantages" class="form-control" rows="4"><?php echo $settings['advantages'] ?? '拎包入住|配备基本家具家电，拎包即可入住,价格实惠|月租便宜，水电费透明计算,交通便利|靠近公交地铁，出行方便,安全可靠|智能门禁，24小时监控'; ?></textarea>
                                <small class="text-muted">示例：拎包入住|配备基本家具家电，拎包即可入住,价格实惠|月租便宜</small>
                            </div>
                        </div>
                    </div>

                    <!-- 租房流程 -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-list-ol"></i> 租房流程</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">标题</label>
                                <input type="text" name="process_title" class="form-control" value="<?php echo $settings['process_title'] ?? '租房流程'; ?>">
                            </div>
                            <div class="mb-0">
                                <label class="form-label">流程步骤（每条格式：标题|描述，多条用逗号分隔）</label>
                                <textarea name="process_steps" class="form-control" rows="4"><?php echo $settings['process_steps'] ?? '在线看房|浏览房源，选择心仪房间,实地看房|预约实地看房，确认环境,签订合同|确认入住，签订租赁合同,正式入住|缴纳费用，开始新生活'; ?></textarea>
                                <small class="text-muted">示例：在线看房|浏览房源，选择心仪房间,实地看房|预约实地看房</small>
                            </div>
                        </div>
                    </div>

                    <!-- 地图设置 -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-map"></i> 高德地图设置</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">高德地图 Key</label>
                                <input type="text" name="map_api_key" class="form-control" value="<?php echo $settings['map_api_key'] ?? ''; ?>" placeholder="请输入JS API Key">
                                <small class="text-muted">在高德控制台 → 应用管理 → 你的应用 → 复制 Key</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">安全密钥 (jscode)</label>
                                <input type="text" name="map_security_code" class="form-control" value="<?php echo $settings['map_security_code'] ?? ''; ?>" placeholder="请输入安全密钥">
                                <small class="text-muted">在高德控制台 → 应用管理 → 你的应用 → 复制 安全密钥</small>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">经度 (lng)</label>
                                    <input type="text" name="map_lng" class="form-control" value="<?php echo $settings['map_lng'] ?? ''; ?>" placeholder="如：108.370671">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">纬度 (lat)</label>
                                    <input type="text" name="map_lat" class="form-control" value="<?php echo $settings['map_lat'] ?? ''; ?>" placeholder="如：22.824436">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">地图显示地址（用于显示，不用于定位）</label>
                                <input type="text" name="map_address" class="form-control" value="<?php echo $settings['map_address'] ?? ''; ?>">
                            </div>
                            <div class="mb-0">
                                <small class="text-muted">
                                    获取经纬度：<a href="https://lbs.amap.com/tools/picker" target="_blank">高德坐标拾取器</a> → 搜索你的地址 → 点击位置 → 复制经纬度
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- 通知设置 -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-bell"></i> 通知设置</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert" style="background: #f8f9fa; border: 1px solid var(--border); border-radius: 10px;">
                                <i class="bi bi-info-circle me-2"></i>开启后，有新留言时会自动发送通知到你的邮箱或微信
                            </div>
                            
                            <h6 class="mb-3"><i class="bi bi-envelope me-2"></i>邮件通知</h6>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">接收邮箱</label>
                                    <input type="email" name="notify_email" class="form-control" value="<?php echo $settings['notify_email'] ?? ''; ?>" placeholder="your@email.com">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">启用邮件通知</label>
                                    <select name="notify_email_enable" class="form-select">
                                        <option value="0" <?php echo ($settings['notify_email_enable'] ?? '0') == '0' ? 'selected' : ''; ?>>关闭</option>
                                        <option value="1" <?php echo ($settings['notify_email_enable'] ?? '0') == '1' ? 'selected' : ''; ?>>开启</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3"><i class="bi bi-wechat me-2"></i>微信通知（Server酱）</h6>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Server酱 SendKey</label>
                                    <input type="text" name="notify_wechat_key" class="form-control" value="<?php echo $settings['notify_wechat_key'] ?? ''; ?>" placeholder="SCT...">
                                    <small class="text-muted">
                                        免费获取：<a href="https://sct.ftqq.com/" target="_blank">https://sct.ftqq.com/</a> → 登录 → 获取 SendKey
                                    </small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">启用微信通知</label>
                                    <select name="notify_wechat_enable" class="form-select">
                                        <option value="0" <?php echo ($settings['notify_wechat_enable'] ?? '0') == '0' ? 'selected' : ''; ?>>关闭</option>
                                        <option value="1" <?php echo ($settings['notify_wechat_enable'] ?? '0') == '1' ? 'selected' : ''; ?>>开启</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="alert" style="background: #e8f5e9; border: none; border-radius: 10px; margin-top: 15px;">
                                <i class="bi bi-lightbulb me-2"></i>
                                <strong>微信通知设置步骤：</strong><br>
                                1. 访问 <a href="https://sct.ftqq.com/" target="_blank">https://sct.ftqq.com/</a><br>
                                2. 使用微信扫码登录<br>
                                3. 点击"获取SendKey"<br>
                                4. 将SendKey粘贴到上方输入框<br>
                                5. 开启微信通知，保存设置
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <button type="submit" class="btn btn-dark btn-lg"><i class="bi bi-check-lg"></i> 保存所有设置</button>
                            <a href="../index.php" class="btn btn-outline-secondary btn-lg ms-2" target="_blank"><i class="bi bi-eye"></i> 查看前台</a>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
<?php include 'footer.php'; ?>
