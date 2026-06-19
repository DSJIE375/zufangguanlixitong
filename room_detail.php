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

$siteName = getSetting('site_name') ?: '少丽出租房';
$sitePhone = getSetting('site_phone') ?: '13800138000';
$siteAddress = getSetting('site_address') ?: 'XX市XX区XX路XX号';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: rooms.php");
    exit;
}

// 照片类型
$photoTypes = [
    'door' => '门口',
    'living' => '客室/客厅',
    'bathroom' => '厕所/卫生间',
    'kitchen' => '厨房',
    'balcony' => '阳台',
    'other' => '其他'
];

// 获取房间信息
$room = $conn->query("SELECT r.*, rt.name as type_name, rt.price as type_price, rt.area, rt.description as type_desc
    FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id 
    WHERE r.id = $id")->fetch_assoc();

if (!$room) {
    header("Location: rooms.php");
    exit;
}

// 获取房间照片
$photos = $conn->query("SELECT * FROM room_photos WHERE room_id = $id ORDER BY sort_order, id");

// 按类型分组照片
$groupedPhotos = [];
while ($p = $photos->fetch_assoc()) {
    $groupedPhotos[$p['photo_type']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title><?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?> - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .room-hero { background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); color: white; padding: 40px 0; }
        .photo-section { margin-bottom: 40px; }
        .photo-section h5 { border-left: 4px solid #0d6efd; padding-left: 12px; margin-bottom: 20px; }
        .photo-grid img { width: 100%; height: 250px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: transform 0.3s; }
        .photo-grid img:hover { transform: scale(1.02); }
        .photo-grid .col-md-4 { margin-bottom: 20px; }
        .info-label { color: #6c757d; font-size: 0.85rem; }
        .info-value { font-size: 1.1rem; font-weight: 600; }
        .price-tag { font-size: 2rem; color: #dc3545; font-weight: bold; }
        
        /* Lightbox */
        .lightbox { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; justify-content: center; align-items: center; }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 90%; max-height: 90%; border-radius: 8px; }
        .lightbox-close { position: absolute; top: 20px; right: 30px; color: white; font-size: 2rem; cursor: pointer; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-building"></i> <?php echo $siteName; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">首页</a></li>
                    <li class="nav-item"><a class="nav-link" href="rooms.php">房间列表</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">联系我们</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 房间信息头部 -->
    <section class="room-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="rooms.php" class="text-white-50">房间列表</a></li>
                            <li class="breadcrumb-item active text-white"><?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?></li>
                        </ol>
                    </nav>
                    <h1 class="mb-2"><?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?></h1>
                    <p class="mb-3">
                        <span class="badge bg-light text-dark me-2"><?php echo $room['type_name']; ?></span>
                        <span class="badge <?php echo $room['status'] == 'available' ? 'bg-dark' : 'bg-secondary'; ?>">
                            <?php echo $room['status'] == 'available' ? '可租' : '已租'; ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="price-tag">¥<?php echo number_format($room['type_price'], 2); ?></div>
                    <small class="text-white-50">每月</small>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-5">
        <div class="row">
            <!-- 左侧：照片展示 -->
            <div class="col-lg-8">
                <?php if (!empty($groupedPhotos)): ?>
                    <?php foreach ($groupedPhotos as $type => $typePhotos): ?>
                    <div class="photo-section">
                        <h5><i class="bi bi-camera"></i> <?php echo $photoTypes[$type] ?? $type; ?></h5>
                        <div class="row photo-grid">
                            <?php foreach ($typePhotos as $p): ?>
                            <div class="col-md-4">
                                <img src="<?php echo $p['photo_path']; ?>" 
                                     alt="<?php echo $photoTypes[$type] ?? $type; ?>"
                                     onclick="openLightbox(this.src)">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-light rounded">
                        <i class="bi bi-image display-1 text-muted"></i>
                        <p class="mt-3 text-muted">暂无房间照片</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 右侧：房间信息 -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> 房间信息</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="info-label">房间号</div>
                            <div class="info-value"><?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">房间类型</div>
                            <div class="info-value"><?php echo $room['type_name']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">面积</div>
                            <div class="info-value"><?php echo $room['area']; ?>㎡</div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">月租金</div>
                            <div class="price-tag">¥<?php echo number_format($room['type_price'], 2); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">状态</div>
                            <div class="info-value">
                                <?php if ($room['status'] == 'available'): ?>
                                    <span class="badge bg-dark fs-6">可租</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6">已租</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($room['type_desc']): ?>
                        <div class="mb-3">
                            <div class="info-label">房间描述</div>
                            <div><?php echo $room['type_desc']; ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($room['description']): ?>
                        <div class="mb-3">
                            <div class="info-label">备注</div>
                            <div><?php echo $room['description']; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 联系卡片 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-telephone"></i> 联系房东</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><i class="bi bi-telephone text-dark"></i> 电话：
                            <?php 
                            $phones = array_filter(array_map('trim', explode(',', $sitePhone)));
                            foreach ($phones as $i => $phone): ?>
                                <?php if ($i > 0) echo ' / '; ?>
                                <a href="tel:<?php echo $phone; ?>"><?php echo $phone; ?></a>
                            <?php endforeach; ?>
                            （以上号码均同步微信）
                        </p>
                        <p class="mb-3"><i class="bi bi-geo-alt text-dark"></i> <?php echo $siteAddress; ?></p>
                        <p class="text-muted small"><i class="bi bi-clock"></i> 看房时间：9:00 - 21:00</p>
                        <a href="tel:<?php echo $phones[0]; ?>" class="btn btn-dark w-100">
                            <i class="bi bi-telephone"></i> 立即电话咨询
                        </a>
                    </div>
                </div>

                <a href="rooms.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-left"></i> 返回房间列表
                </a>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img id="lightbox-img" src="" alt="放大照片">
    </div>

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
    <script>
    function openLightbox(src) {
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox').classList.add('active');
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('active');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });
    </script>
</body>
</html>
