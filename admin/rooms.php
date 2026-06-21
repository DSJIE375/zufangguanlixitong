<?php
require_once '../includes/database.php';

function getSiteName() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return 'DSJIE.租房管理系统';
}
$siteName = getSiteName();

// 检查登录
if (!isLoggedIn()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction == 'add') {
        $floor = intval($_POST['floor']);
        $room_number = sanitize($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        $status = sanitize($_POST['status']);
        $description = sanitize($_POST['description'] ?? '');
        
        $sql = "INSERT INTO rooms (floor, room_number, room_type_id, status, description) 
                VALUES ($floor, '$room_number', $room_type_id, '$status', '$description')";
        
        if ($conn->query($sql)) {
            logAction('添加房间', "添加房间 $room_number ({$floor}楼)");
            setFlash('success', '房间添加成功');
            redirect('rooms.php');
        } else {
            setFlash('error', '添加失败: ' . $conn->error);
        }
    }
    
    if ($postAction == 'edit') {
        $id = intval($_POST['id']);
        $floor = intval($_POST['floor']);
        $room_number = sanitize($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        $status = sanitize($_POST['status']);
        $description = sanitize($_POST['description'] ?? '');
        
        $sql = "UPDATE rooms SET 
                floor = $floor,
                room_number = '$room_number',
                room_type_id = $room_type_id,
                status = '$status',
                description = '$description'
                WHERE id = $id";
        
        if ($conn->query($sql)) {
            logAction('修改房间', "修改房间 $room_number ({$floor}楼)");
            setFlash('success', '房间更新成功');
            redirect('rooms.php');
        } else {
            setFlash('error', '更新失败: ' . $conn->error);
        }
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        $room = $conn->query("SELECT room_number, floor FROM rooms WHERE id = $id")->fetch_assoc();
        $sql = "DELETE FROM rooms WHERE id = $id";
        
        if ($conn->query($sql)) {
            logAction('删除房间', "删除房间 {$room['room_number']} ({$room['floor']}楼)");
            setFlash('success', '房间删除成功');
            redirect('rooms.php');
        } else {
            setFlash('error', '删除失败: ' . $conn->error);
        }
    }
}

// 获取房间类型
$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY price");

// 搜索条件
$where = "1=1";
if (!empty($_GET['search'])) {
    $search = sanitize($_GET['search']);
    $where .= " AND r.room_number LIKE '%$search%'";
}
if (!empty($_GET['floor'])) {
    $floor = intval($_GET['floor']);
    $where .= " AND r.floor = $floor";
}
if (!empty($_GET['status'])) {
    $status = sanitize($_GET['status']);
    $where .= " AND r.status = '$status'";
}

// 获取房间列表
$rooms = $conn->query("SELECT r.*, rt.name as type_name, rt.price as type_price 
                       FROM rooms r 
                       LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                       WHERE $where
                       ORDER BY r.floor, r.room_number");

// 编辑时获取房间信息
$editRoom = null;
if ($action == 'edit' && $id) {
    $result = $conn->query("SELECT * FROM rooms WHERE id = $id");
    $editRoom = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>房间管理 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><img src="../images/logo.svg" alt="Logo" height="28"></a>
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
            <!-- 侧边栏 -->
            <nav class="col-md-2 d-md-block sidebar collapse py-3">
<?php include 'sidebar.php'; ?>
            </nav>

            <!-- 主内容区 -->
            <main class="col-md-10 ms-sm-auto main-content">
                <?php $flash = getFlash(); if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">房间管理</h4>
                    <a href="rooms.php?action=add" class="btn btn-dark">
                        <i class="bi bi-plus"></i> 添加房间
                    </a>
                </div>

                <!-- 搜索框 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="搜索房间号..." value="<?php echo $_GET['search'] ?? ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="floor" class="form-select">
                                    <option value="">全部楼层</option>
                                    <?php for ($i = 2; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($_GET['floor'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?>楼</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">全部状态</option>
                                    <option value="available" <?php echo ($_GET['status'] ?? '') == 'available' ? 'selected' : ''; ?>>可租</option>
                                    <option value="rented" <?php echo ($_GET['status'] ?? '') == 'rented' ? 'selected' : ''; ?>>已租</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="rooms.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>房间号</th>
                                        <th>楼层</th>
                                        <th>类型</th>
                                        <th>月租</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($room = $rooms->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $room['room_number']; ?></strong></td>
                                        <td><?php echo $room['floor']; ?>楼</td>
                                        <td><?php echo $room['type_name']; ?></td>
                                        <td>¥<?php echo number_format($room['type_price'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $room['status'] == 'available' ? 'badge-available' : 'badge-rented'; ?>">
                                                <?php echo $room['status'] == 'available' ? '可租' : '已租'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="rooms.php?action=view&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-outline-info" title="查看详情">
                                                <i class="bi bi-eye"></i> 详情
                                            </a>
                                            <a href="rooms.php?action=edit&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-outline-dark">
                                                <i class="bi bi-pencil"></i> 编辑
                                            </a>
                                            <form method="POST" style="display:inline;" data-confirm="确定要删除这个房间吗？">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> 删除
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($action == 'view' && $id): 
                    $viewRoom = $conn->query("SELECT r.*, rt.name as type_name, rt.price as type_price, rt.area, rt.description as type_desc
                        FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                        WHERE r.id = $id")->fetch_assoc();
                    
                    // 获取当前租客和合同信息
                    $tenantInfo = $conn->query("SELECT c.*, t.name as tenant_name, t.phone as tenant_phone, t.id_card as tenant_idcard, t.gender as tenant_gender, t.company as tenant_company
                        FROM contracts c
                        JOIN tenants t ON c.tenant_id = t.id
                        WHERE c.room_id = $id AND c.status = 'active'
                        LIMIT 1")->fetch_assoc();
                    
                    // 获取历史账单
                    $roomBills = $conn->query("SELECT b.*, t.name as tenant_name
                        FROM bills b
                        JOIN contracts c ON b.contract_id = c.id
                        JOIN tenants t ON c.tenant_id = t.id
                        WHERE c.room_id = $id
                        ORDER BY b.bill_month DESC
                        LIMIT 5");
                    
                    // 获取房间照片
                    $roomPhotos = $conn->query("SELECT * FROM room_photos WHERE room_id = $id ORDER BY sort_order, id");
                ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="bi bi-eye"></i> 房间详情 - <?php echo $viewRoom['floor']; ?>楼 <?php echo $viewRoom['room_number']; ?></h4>
                    <a href="rooms.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 返回列表
                    </a>
                </div>

                <div class="row">
                    <!-- 基本信息 -->
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-door-open"></i> 房间信息</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless mb-0">
                                    <tr><td style="width:120px; color:#666;">房间号</td><td><strong><?php echo $viewRoom['floor']; ?>楼 <?php echo $viewRoom['room_number']; ?></strong></td></tr>
                                    <tr><td style="color:#666;">房间类型</td><td><?php echo $viewRoom['type_name']; ?></td></tr>
                                    <tr><td style="color:#666;">面积</td><td><?php echo $viewRoom['area']; ?>㎡</td></tr>
                                    <tr><td style="color:#666;">月租金</td><td class="text-dark fw-bold">¥<?php echo number_format($viewRoom['type_price'], 2); ?></td></tr>
                                    <tr><td style="color:#666;">状态</td><td><span class="badge <?php echo $viewRoom['status'] == 'available' ? 'badge-available' : 'badge-rented'; ?>"><?php echo $viewRoom['status'] == 'available' ? '可租' : '已租'; ?></span></td></tr>
                                    <?php if ($viewRoom['description']): ?>
                                    <tr><td style="color:#666;">备注</td><td><?php echo $viewRoom['description']; ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 租客信息 -->
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header <?php echo $tenantInfo ? 'bg-dark' : 'bg-secondary'; ?> text-white">
                                <h5 class="mb-0"><i class="bi bi-person"></i> <?php echo $tenantInfo ? '当前租客' : '暂无租客'; ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if ($tenantInfo): ?>
                                <table class="table table-borderless mb-0">
                                    <tr><td style="width:100px; color:#666;">姓名</td><td><strong><?php echo $tenantInfo['tenant_name']; ?></strong></td></tr>
                                    <tr><td style="color:#666;">电话</td><td><a href="tel:<?php echo $tenantInfo['tenant_phone']; ?>"><?php echo $tenantInfo['tenant_phone']; ?></a></td></tr>
                                    <tr><td style="color:#666;">性别</td><td><?php echo $tenantInfo['tenant_gender'] ?: '-'; ?></td></tr>
                                    <tr><td style="color:#666;">身份证</td><td><?php echo $tenantInfo['tenant_idcard'] ?: '-'; ?></td></tr>
                                    <tr><td style="color:#666;">公司/单位</td><td><?php echo $tenantInfo['tenant_company'] ?: '-'; ?></td></tr>
                                    <tr><td style="color:#666;">入住日期</td><td><?php echo $tenantInfo['start_date']; ?></td></tr>
                                    <tr><td style="color:#666;">月租金</td><td class="text-dark fw-bold">¥<?php echo number_format($tenantInfo['monthly_rent'], 2); ?></td></tr>
                                    <tr><td style="color:#666;">押金</td><td>¥<?php echo number_format($tenantInfo['deposit'], 2); ?></td></tr>
                                </table>
                                <?php else: ?>
                                <p class="text-muted text-center py-3">该房间目前空置，可出租</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 房间照片 -->
                <?php if ($roomPhotos && $roomPhotos->num_rows > 0): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-camera"></i> 房间照片</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <?php while ($photo = $roomPhotos->fetch_assoc()): ?>
                            <div class="col-md-3 mb-3">
                                <img src="../<?php echo $photo['photo_path']; ?>" class="img-fluid rounded" style="height:150px; width:100%; object-fit:cover;">
                                <small class="text-muted"><?php echo $photo['photo_type']; ?></small>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 最近账单 -->
                <?php if ($roomBills && $roomBills->num_rows > 0): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-receipt"></i> 最近账单</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr><th>月份</th><th>水费</th><th>电费</th><th>房租</th><th>合计</th><th>状态</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($bill = $roomBills->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $bill['bill_month']; ?></td>
                                        <td>¥<?php echo number_format($bill['water_amount'], 2); ?></td>
                                        <td>¥<?php echo number_format($bill['elec_amount'], 2); ?></td>
                                        <td>¥<?php echo number_format($bill['rent_amount'], 2); ?></td>
                                        <td class="fw-bold">¥<?php echo number_format($bill['total_amount'], 2); ?></td>
                                        <td><span class="badge <?php echo $bill['status'] == 'paid' ? 'bg-dark' : 'bg-dark'; ?>"><?php echo $bill['status'] == 'paid' ? '已缴' : '未缴'; ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><?php echo $action == 'add' ? '添加房间' : '编辑房间'; ?></h4>
                    <a href="rooms.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 返回列表
                    </a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $editRoom['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">楼层</label>
                                    <select name="floor" class="form-select" required>
                                        <option value="">请选择楼层</option>
                                        <?php for ($i = 2; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($action == 'edit' && $editRoom['floor'] == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>楼
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">房间号</label>
                                    <input type="text" name="room_number" class="form-control" required
                                           value="<?php echo $action == 'edit' ? $editRoom['room_number'] : ''; ?>"
                                           placeholder="如: 201, 202">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">房间类型</label>
                                    <select name="room_type_id" class="form-select" required>
                                        <option value="">请选择类型</option>
                                        <?php while ($type = $roomTypes->fetch_assoc()): ?>
                                        <option value="<?php echo $type['id']; ?>" 
                                                <?php echo ($action == 'edit' && $editRoom['room_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                            <?php echo $type['name']; ?> - ¥<?php echo number_format($type['price'], 2); ?>/月
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">状态</label>
                                    <select name="status" class="form-select" required>
                                        <option value="available" <?php echo ($action == 'edit' && $editRoom['status'] == 'available') ? 'selected' : ''; ?>>可租</option>
                                        <option value="rented" <?php echo ($action == 'edit' && $editRoom['status'] == 'rented') ? 'selected' : ''; ?>>已租</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">描述（可选）</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo $action == 'edit' ? $editRoom['description'] : ''; ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-dark">
                                    <i class="bi bi-check-lg"></i> <?php echo $action == 'add' ? '添加' : '保存'; ?>
                                </button>
                                <a href="rooms.php" class="btn btn-outline-secondary">取消</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

<?php include 'footer.php'; ?>
