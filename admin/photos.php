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

if (!isLoggedIn()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$room_id = $_GET['room_id'] ?? null;

// 照片类型
$photoTypes = [
    'door' => '门口',
    'living' => '客室/客厅',
    'bathroom' => '厕所/卫生间',
    'kitchen' => '厨房',
    'balcony' => '阳台',
    'other' => '其他'
];

// 处理上传
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction == 'upload') {
        $room_id = intval($_POST['room_id']);
        $photo_type = sanitize($_POST['photo_type']);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $newname = 'room_' . $room_id . '_' . time() . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/rooms/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $uploadPath = $uploadDir . $newname;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                    $photoPath = 'uploads/rooms/' . $newname;
                    $sql = "INSERT INTO room_photos (room_id, photo_path, photo_type, sort_order) VALUES ($room_id, '$photoPath', '$photo_type', $sort_order)";
                    if ($conn->query($sql)) {
                        logAction('上传照片', "上传房间照片 ID: $room_id 类型: $photo_type");
                        setFlash('success', '照片上传成功');
                    } else {
                        setFlash('error', '保存失败: ' . $conn->error);
                    }
                } else {
                    setFlash('error', '文件上传失败');
                }
            } else {
                setFlash('error', '不支持的文件格式，请上传 jpg/png/gif/webp');
            }
        } else {
            setFlash('error', '请选择照片文件');
        }
        redirect("photos.php?room_id=$room_id");
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        $rid = intval($_POST['room_id']);
        $photo = $conn->query("SELECT photo_path FROM room_photos WHERE id=$id")->fetch_assoc();
        if ($photo && file_exists(__DIR__ . '/../' . $photo['photo_path'])) {
            unlink(__DIR__ . '/../' . $photo['photo_path']);
        }
        $conn->query("DELETE FROM room_photos WHERE id=$id");
        logAction('删除照片', "删除房间照片 ID: $id");
        setFlash('success', '照片已删除');
        redirect("photos.php?room_id=$rid");
    }
    
    if ($postAction == 'update_sort') {
        $rid = intval($_POST['room_id']);
        $sorts = $_POST['sort_order'] ?? [];
        foreach ($sorts as $pid => $order) {
            $conn->query("UPDATE room_photos SET sort_order=" . intval($order) . " WHERE id=" . intval($pid) . " AND room_id=$rid");
        }
        setFlash('success', '排序已更新');
        redirect("photos.php?room_id=$rid");
    }
}

$rooms = $conn->query("SELECT r.*, rt.name as type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.floor, r.room_number");

$photos = null;
$selectedRoom = null;
if ($room_id) {
    $selectedRoom = $conn->query("SELECT r.*, rt.name as type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id=" . intval($room_id))->fetch_assoc();
    $photos = $conn->query("SELECT * FROM room_photos WHERE room_id=" . intval($room_id) . " ORDER BY sort_order, id");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>房间照片管理 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .photo-card { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .photo-card img { width: 100%; height: 200px; object-fit: cover; }
        .photo-card .card-body { padding: 10px; }
        .photo-type-badge { font-size: 0.75rem; }
    </style>
</head>
<body>
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

                <h4 class="mb-4"><i class="bi bi-camera"></i> 房间照片管理</h4>

                <!-- 选择房间 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">选择房间</label>
                                <select name="room_id" class="form-select" required id="roomSelect">
                                    <option value="">请选择房间</option>
                                    <?php while ($r = $rooms->fetch_assoc()): ?>
                                    <option value="<?php echo $r['id']; ?>" <?php echo $room_id == $r['id'] ? 'selected' : ''; ?>>
                                        <?php echo $r['floor']; ?>楼 <?php echo $r['room_number']; ?> (<?php echo $r['type_name']; ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i> 查看照片</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selectedRoom): ?>
                <!-- 上传照片 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-upload"></i> 上传照片 - <?php echo $selectedRoom['floor']; ?>楼 <?php echo $selectedRoom['room_number']; ?></h5></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">照片类型 <span class="text-dark">*</span></label>
                                    <select name="photo_type" class="form-select" required>
                                        <?php foreach ($photoTypes as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">选择照片</label>
                                    <input type="file" name="photo" class="form-control" accept="image/*" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">排序</label>
                                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-upload"></i> 上传</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 照片列表 -->
                <?php if ($photos && $photos->num_rows > 0): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">已上传照片 (<?php echo $photos->num_rows; ?>张)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_sort">
                            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                            <div class="row">
                                <?php while ($p = $photos->fetch_assoc()): ?>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                    <div class="photo-card card h-100">
                                        <img src="../<?php echo $p['photo_path']; ?>" alt="<?php echo $photoTypes[$p['photo_type']] ?? $p['photo_type']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-dark photo-type-badge"><?php echo $photoTypes[$p['photo_type']] ?? $p['photo_type']; ?></span>
                                                <input type="number" name="sort_order[<?php echo $p['id']; ?>]" value="<?php echo $p['sort_order']; ?>" class="form-control form-control-sm" style="width:60px; display:inline;">
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted"><?php echo date('m-d H:i', strtotime($p['created_at'])); ?></small>
                                                <form method="POST" style="display:inline;" data-confirm="确定要删除这张照片吗？">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <button type="submit" class="btn btn-dark"><i class="bi bi-save"></i> 保存排序</button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-image display-1 text-muted"></i>
                    <p class="mt-3 text-muted">暂无照片，请上传</p>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
<?php include 'footer.php'; ?>
