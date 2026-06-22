<?php
require_once 'includes/database.php';

// 获取设置
function getSetting($key) {
    global $conn;
    $sql = "SELECT setting_value FROM settings WHERE setting_key = '$key'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '';
}

// 获取所有房间（含类型信息）
function getRooms() {
    global $conn;
    $sql = "SELECT r.*, rt.name as type_name, rt.price as type_price, rt.area
            FROM rooms r
            LEFT JOIN room_types rt ON r.room_type_id = rt.id
            ORDER BY r.floor, r.room_number";
    return $conn->query($sql);
}

// 获取楼层统计
function getFloorStats() {
    global $conn;
    $sql = "SELECT r.floor, COUNT(*) as total,
            SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN r.status = 'rented' THEN 1 ELSE 0 END) as rented
            FROM rooms r
            GROUP BY r.floor
            ORDER BY r.floor";
    return $conn->query($sql);
}

$rooms = getRooms();
$floorStats = getFloorStats();
$siteName = getSetting('site_name') ?: 'DSJIE.出租房';
$sitePhone = getSetting('site_phone') ?: '13800138000';
$siteAddress = getSetting('site_address') ?: 'XX市XX区XX路XX号';
$bannerTitle = getSetting('banner_title') ?: $siteName;
$bannerSubtitle = getSetting('banner_subtitle') ?: '温馨舒适 · 安全便捷 · 拎包入住';
$bannerBtnText = getSetting('banner_btn_text') ?: '查看房源';
$bannerImg1 = getSetting('banner_img1') ?: '';
$bannerImg2 = getSetting('banner_img2') ?: '';
$aboutTitle = getSetting('about_title') ?: '关于我们';
$aboutContent = getSetting('about_content') ?: 'DSJIE.出租房位于城市中心地带，交通便利，周边配套齐全。我们提供干净整洁的居住环境，让每一位租客都能感受到家的温暖。';
$aboutImgUrl = getSetting('about_img_url') ?: 'https://via.placeholder.com/600x400?text=Apartment';
$mapApiKey = getSetting('map_api_key') ?: '';
$mapSecurityCode = getSetting('map_security_code') ?: '';
$mapLng = getSetting('map_lng') ?: '108.370671';
$mapLat = getSetting('map_lat') ?: '22.824436';
$mapAddress = getSetting('map_address') ?: '';
$advantageTitle = getSetting('advantage_title') ?: '租房优势';
$advantages = getSetting('advantages') ?: '拎包入住|配备基本家具家电，拎包即可入住,价格实惠|月租便宜，水电费透明计算,交通便利|靠近公交地铁，出行方便,安全可靠|智能门禁，24小时监控';
$processTitle = getSetting('process_title') ?: '租房流程';
$processSteps = getSetting('process_steps') ?: '在线看房|浏览房源，选择心仪房间,实地看房|预约实地看房，确认环境,签订合同|确认入住，签订租赁合同,正式入住|缴纳费用，开始新生活';

// 解析优势和流程
$advantageList = array_filter(array_map('trim', explode(',', $advantages)));
$processList = array_filter(array_map('trim', explode(',', $processSteps)));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title><?php echo $siteName; ?> - 温馨公寓出租</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.svg" alt="<?php echo $siteName; ?>" height="32">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rooms.php">房间列表</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">联系我们</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tenant_login.php" title="租客查看账单"><i class="bi bi-person me-1"></i>租客登录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php" title="后台管理"><i class="bi bi-gear"></i></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 轮播图 -->
    <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <div class="carousel-item active hero-slide" <?php echo $bannerImg1 ? 'style="background-image: url(' . $bannerImg1 . '); background-size: cover; background-position: center;"' : ''; ?>>
                <div class="hero-overlay"></div>
                <div class="hero-content text-center text-white">
                    <h1 class="display-4 fw-bold"><?php echo $bannerTitle; ?></h1>
                    <p class="lead"><?php echo $bannerSubtitle; ?></p>
                    <a href="rooms.php" class="btn btn-dark btn-lg mt-3"><?php echo $bannerBtnText; ?></a>
                </div>
            </div>
            <div class="carousel-item hero-slide" <?php echo $bannerImg2 ? 'style="background-image: url(' . $bannerImg2 . '); background-size: cover; background-position: center;"' : ''; ?>>
                <div class="hero-overlay"></div>
                <div class="hero-content text-center text-white">
                    <h1 class="display-4 fw-bold"><?php echo $bannerTitle; ?></h1>
                    <p class="lead"><?php echo $bannerSubtitle; ?></p>
                    <a href="rooms.php" class="btn btn-dark btn-lg mt-3"><?php echo $bannerBtnText; ?></a>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    <!-- 公寓简介 -->
    <section class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold text-dark"><?php echo $aboutTitle; ?></h2>
                    <p class="lead">
                        <?php echo $aboutContent; ?>
                    </p>
                </div>
                <div class="col-lg-6">
                    <?php if ($mapApiKey && $mapLng && $mapLat): ?>
                        <div id="mapContainer" style="width: 100%; height: 350px; border-radius: 10px; overflow: hidden;"></div>
                        <p class="text-center mt-2 text-muted small"><i class="bi bi-geo-alt"></i> <?php echo $mapAddress; ?></p>
                    <?php else: ?>
                        <img src="<?php echo $aboutImgUrl; ?>" class="img-fluid rounded shadow" alt="公寓环境">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 房间展示 -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center fw-bold mb-5">房间展示</h2>
            <div class="row">
                <?php while ($floor = $floorStats->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <?php echo $floor['floor']; ?>楼
                                <span class="floor-layers ms-2">
                                    <?php for ($i = 0; $i < $floor['floor']; $i++): ?>
                                        <span class="floor-dot"></span>
                                    <?php endfor; ?>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span>总房间：<strong><?php echo $floor['total']; ?></strong></span>
                                <span>可租：<strong><?php echo $floor['available']; ?></strong></span>
                                <span>已租：<strong><?php echo $floor['rented']; ?></strong></span>
                            </div>
                            <a href="rooms.php?floor=<?php echo $floor['floor']; ?>" class="btn btn-outline-dark w-100">
                                查看<?php echo $floor['floor']; ?>楼房间
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- 租房优势 -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center fw-bold mb-5"><?php echo $advantageTitle; ?></h2>
            <div class="row text-center">
                <?php $icons = ['bi-key', 'bi-cash', 'bi-geo-alt', 'bi-shield-check', 'bi-wifi', 'bi-thermometer-snow']; ?>
                <?php $iconIdx = 0; foreach ($advantageList as $item): 
                    $parts = array_map('trim', explode('|', $item));
                    $title = $parts[0] ?? '';
                    $desc = $parts[1] ?? '';
                ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="advantage-card">
                        <i class="bi <?php echo $icons[$iconIdx % count($icons)]; ?>"></i>
                        <h5><?php echo $title; ?></h5>
                        <p><?php echo $desc; ?></p>
                    </div>
                </div>
                <?php $iconIdx++; endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 租房流程 -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center fw-bold mb-5"><i class="bi bi-list-ol text-dark me-2"></i><?php echo $processTitle; ?></h2>
            <div class="row">
                <?php $stepNum = 1; foreach ($processList as $item): 
                    $parts = array_map('trim', explode('|', $item));
                    $title = $parts[0] ?? '';
                    $desc = $parts[1] ?? '';
                ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="process-card">
                        <div class="process-num"><?php echo $stepNum; ?></div>
                        <h5><?php echo $title; ?></h5>
                        <p><?php echo $desc; ?></p>
                    </div>
                </div>
                <?php $stepNum++; endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 联系我们 -->
    <section id="contact" class="py-5">
        <div class="container">
            <h2 class="text-center fw-bold mb-5">联系我们</h2>
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title text-dark">联系方式</h5>
                            <ul class="list-unstyled">
                                <li class="mb-3">
                                    <i class="bi bi-telephone text-dark me-2"></i>
                                    电话：
                                    <?php 
                                    $phones = array_filter(array_map('trim', explode(',', $sitePhone)));
                                    foreach ($phones as $i => $phone): ?>
                                        <?php if ($i > 0) echo ' / '; ?>
                                        <a href="tel:<?php echo $phone; ?>"><?php echo $phone; ?></a>
                                    <?php endforeach; ?>
                                    （以上号码均同步微信）
                                </li>
                                <li class="mb-3">
                                    <i class="bi bi-geo-alt text-dark me-2"></i>
                                    地址：<a href="https://uri.amap.com/navigation?to=<?php echo $mapLng; ?>,<?php echo $mapLat; ?>&mode=esbike&policy=1" target="_blank" class="text-dark text-decoration-underline"><?php echo $siteAddress; ?></a>
                                    <small class="text-muted ms-1">(点击导航)</small>
                                </li>
                            </ul>
                            <p class="text-muted small">
                                <i class="bi bi-clock me-1"></i> 看房时间：上午9:00 - 晚上9:00
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title text-dark">在线留言</h5>
                            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                                <div class="alert alert-success">留言提交成功！我们会尽快联系您。</div>
                            <?php endif; ?>
                            <form method="POST" action="submit_message.php">
                                <div class="mb-3">
                                    <input type="text" name="name" class="form-control" placeholder="您的姓名" required>
                                </div>
                                <div class="mb-3">
                                    <input type="tel" name="phone" class="form-control" placeholder="联系电话" required>
                                </div>
                                <div class="mb-3">
                                    <textarea name="content" class="form-control" rows="3" placeholder="留言内容" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-dark">提交留言</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 页脚 -->
    <footer>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-1"><i class="bi bi-building me-2"></i><?php echo $siteName; ?></h5>
                    <p class="text-muted mb-0">用心服务每一位租客</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; 2024 <?php echo $siteName; ?></p>
                    <small class="text-muted">All Rights Reserved</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <?php if ($mapApiKey && $mapLng && $mapLat): ?>
    <script>
    window._AMapSecurityConfig = {
        securityJsCode: '<?php echo $mapSecurityCode; ?>',
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var script = document.createElement('script');
        script.src = 'https://webapi.amap.com/maps?v=2.0&key=<?php echo $mapApiKey; ?>';
        script.onload = function() {
            initMap();
        };
        document.head.appendChild(script);
    });
    
    function initMap() {
        var center = [<?php echo $mapLng; ?>, <?php echo $mapLat; ?>];
        
        var map = new AMap.Map('mapContainer', {
            zoom: 16,
            center: center
        });
        
        var marker = new AMap.Marker({
            position: center,
            title: '<?php echo addslashes($siteName); ?>'
        });
        map.add(marker);
        
        var infoWindow = new AMap.InfoWindow({
            content: '<div style="padding: 10px;"><strong><?php echo addslashes($siteName); ?></strong><br><?php echo addslashes($mapAddress); ?></div>',
            offset: new AMap.Pixel(0, -30)
        });
        infoWindow.open(map, center);
    }
    </script>
    <?php endif; ?>
</body>
</html>
