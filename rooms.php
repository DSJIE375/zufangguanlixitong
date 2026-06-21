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

// 获取房间（含类型信息）
function getRooms($floor = null, $status = null) {
    global $conn;
    $sql = "SELECT r.*, rt.name as type_name, rt.price as type_price, rt.area
            FROM rooms r
            LEFT JOIN room_types rt ON r.room_type_id = rt.id
            WHERE 1=1";
    
    if ($floor) {
        $sql .= " AND r.floor = " . intval($floor);
    }
    if ($status) {
        $sql .= " AND r.status = '" . sanitize($status) . "'";
    }
    
    $sql .= " ORDER BY r.floor, r.room_number";
    return $conn->query($sql);
}

$floor = isset($_GET['floor']) ? intval($_GET['floor']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$rooms = getRooms($floor, $status);
$siteName = getSetting('site_name') ?: 'DSJIE.租房管理系统';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>房间列表 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-building"></i> <?php echo $siteName; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">首页</a></li>
                    <li class="nav-item"><a class="nav-link active" href="rooms.php">房间列表</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">联系我们</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 页面标题 -->
    <div class="page-header">
        <div class="container">
            <h2 class="mb-0"><i class="bi bi-door-open me-2"></i>房间列表</h2>
            <small class="opacity-75">共 <?php echo $rooms->num_rows; ?> 个房间</small>
        </div>
    </div>

    <!-- 筛选区域 -->
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">选择楼层</label>
                        <select name="floor" class="form-select">
                            <option value="">全部楼层</option>
                            <?php for ($i = 2; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $floor == $i ? 'selected' : ''; ?>><?php echo $i; ?>楼</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">房间状态</label>
                        <select name="status" class="form-select">
                            <option value="">全部状态</option>
                            <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>可租</option>
                            <option value="rented" <?php echo $status == 'rented' ? 'selected' : ''; ?>>已租</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark me-2">
                            <i class="bi bi-search"></i> 筛选
                        </button>
                        <a href="rooms.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> 重置
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 房间列表 -->
    <div class="container mt-4 mb-5">
        <h4 class="mb-4">
            <i class="bi bi-list-ul"></i> 房间列表
            <?php if ($floor): ?>
                <span class="badge bg-dark"><?php echo $floor; ?>楼</span>
            <?php endif; ?>
        </h4>
        
        <div class="row">
            <?php while ($room = $rooms->fetch_assoc()): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100 shadow-sm room-card">
                    <div class="card-header <?php echo $room['status'] == 'available' ? 'bg-dark' : 'bg-secondary'; ?> text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-door-open"></i> <?php echo $room['room_number']; ?></span>
                            <span class="badge <?php echo $room['status'] == 'available' ? 'bg-light text-dark' : 'bg-light text-secondary'; ?>">
                                <?php echo $room['status'] == 'available' ? '可租' : '已租'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-dark"><?php echo $room['type_name']; ?></h5>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-layers text-muted me-2"></i>
                                楼层：<?php echo $room['floor']; ?>楼
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-rulers text-muted me-2"></i>
                                面积：<?php echo $room['area']; ?>㎡
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-cash text-muted me-2"></i>
                                月租：<strong class="text-dark">¥<?php echo number_format($room['type_price'], 2); ?></strong>/月
                            </li>
                        </ul>
                        <?php if ($room['description']): ?>
                            <p class="text-muted small"><?php echo $room['description']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="room_detail.php?id=<?php echo $room['id']; ?>" class="btn btn-outline-dark w-100 mb-2">
                            <i class="bi bi-eye"></i> 查看详情/照片
                        </a>
                        <?php if ($room['status'] == 'available'): ?>
                            <a href="index.php#contact" class="btn btn-dark w-100">
                                <i class="bi bi-telephone"></i> 立即咨询
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="bi bi-lock"></i> 已出租
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php if ($rooms->num_rows == 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="mt-3 text-muted">暂无符合条件的房间</p>
            </div>
        <?php endif; ?>
    </div>

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
</body>
</html>
