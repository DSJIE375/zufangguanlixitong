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
$siteName = getSiteName();

// 批量添加
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'batch_add') {
    $start_floor = intval($_POST['start_floor']);
    $end_floor = intval($_POST['end_floor']);
    $start_room = intval($_POST['start_room']);
    $end_room = intval($_POST['end_room']);
    $room_type_id = intval($_POST['room_type_id']);
    
    $added = 0;
    $skipped = 0;
    
    for ($floor = $start_floor; $floor <= $end_floor; $floor++) {
        for ($room = $start_room; $room <= $end_room; $room++) {
            $room_number = $floor . str_pad($room, 2, '0', STR_PAD_LEFT);
            
            // 跳过含4的房间号
            if (strpos($room_number, '4') !== false) {
                $skipped++;
                continue;
            }
            
            // 检查房间是否已存在
            $check = $conn->query("SELECT id FROM rooms WHERE room_number = '$room_number'");
            if ($check->num_rows > 0) {
                $skipped++;
                continue;
            }
            
            $sql = "INSERT INTO rooms (floor, room_number, room_type_id, status) VALUES ($floor, '$room_number', $room_type_id, 'available')";
            if ($conn->query($sql)) {
                $added++;
                logAction('批量添加房间', "添加房间 $room_number ({$floor}楼)");
            }
        }
    }
    
    setFlash('success', "批量添加完成：成功 $added 个，跳过 $skipped 个");
    redirect('rooms_batch.php');
}

// 批量删除
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'batch_delete') {
    $room_ids = $_POST['room_ids'] ?? [];
    
    if (!empty($room_ids)) {
        $ids = implode(',', array_map('intval', $room_ids));
        
        // 获取房间信息用于日志
        $rooms_info = $conn->query("SELECT room_number FROM rooms WHERE id IN ($ids)");
        $room_names = [];
        while ($row = $rooms_info->fetch_assoc()) {
            $room_names[] = $row['room_number'];
        }
        
        $sql = "DELETE FROM rooms WHERE id IN ($ids) AND status = 'available'";
        $conn->query($sql);
        
        logAction('批量删除房间', "批量删除房间: " . implode(', ', $room_names));
        setFlash('success', "已删除 " . count($room_ids) . " 个房间");
    } else {
        setFlash('error', '请选择要删除的房间');
    }
    redirect('rooms_batch.php');
}

// 批量修改
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'batch_modify') {
    $modify_ids = $_POST['modify_ids'] ?? [];
    $new_type_id = $_POST['new_type_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    
    if (!empty($modify_ids)) {
        $ids = implode(',', array_map('intval', $modify_ids));
        $updates = [];
        
        if ($new_type_id) {
            $conn->query("UPDATE rooms SET room_type_id = $new_type_id WHERE id IN ($ids)");
            $updates[] = "房型";
        }
        if ($new_status) {
            $conn->query("UPDATE rooms SET status = '$new_status' WHERE id IN ($ids)");
            $updates[] = "状态";
        }
        
        if (!empty($updates)) {
            logAction('批量修改房间', "批量修改房间: 修改了 " . implode('+', $updates) . "，共 " . count($modify_ids) . " 个房间");
            setFlash('success', "已修改 " . count($modify_ids) . " 个房间的" . implode('和', $updates));
        } else {
            setFlash('error', '请选择要修改的类型或状态');
        }
    } else {
        setFlash('error', '请选择要修改的房间');
    }
    redirect('rooms_batch.php');
}

// 获取数据
$rooms = $conn->query("SELECT r.*, rt.name as type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.floor, r.room_number");
$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY price");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>批量管理房间 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
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
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
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

                <h4 class="mb-4"><i class="bi bi-grid"></i> 批量管理房间</h4>

                <!-- 当前房间列表 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">当前房间列表（共 <?php echo $rooms->num_rows; ?> 个）</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            $rooms->data_seek(0);
                            while ($room = $rooms->fetch_assoc()): ?>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="badge <?php echo $room['status'] == 'available' ? 'bg-dark' : 'bg-secondary'; ?>" style="padding: 8px 12px; font-size: 0.85rem;">
                                    <?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?>
                                    <small>(<?php echo $room['type_name']; ?>)</small>
                                </span>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- 批量添加 -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 批量添加房间</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">快速添加多个房间，自动跳过含4的房间号</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="batch_add">
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label">起始楼层</label>
                                            <input type="number" name="start_floor" class="form-control" value="1" min="1" required>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label">结束楼层</label>
                                            <input type="number" name="end_floor" class="form-control" value="5" min="1" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label">起始房间号</label>
                                            <input type="number" name="start_room" class="form-control" value="1" min="1" max="99" required>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label">结束房间号</label>
                                            <input type="number" name="end_room" class="form-control" value="5" min="1" max="99" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">房间类型</label>
                                        <select name="room_type_id" class="form-select" required>
                                            <?php while ($type = $roomTypes->fetch_assoc()): ?>
                                            <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?> (¥<?php echo $type['price']; ?>/月)</option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="alert" style="background: #f5f5f7; border: 1px solid #e5e5e7; border-radius: 8px;">
                                        <small><i class="bi bi-info-circle me-1"></i> 预览：将添加 <strong id="preview-count">0</strong> 个房间</small>
                                    </div>
                                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-plus-circle me-1"></i> 批量添加</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 批量删除 -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-trash"></i> 批量删除房间</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">选择要删除的房间（只能删除可租状态的房间）</p>
                                <form method="POST" onsubmit="return confirm('确定要删除选中的房间吗？')">
                                    <input type="hidden" name="action" value="batch_delete">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="toggleAll()">全选/取消</button>
                                        <span class="text-muted ms-2" id="selected-count">已选 0 个</span>
                                    </div>
                                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e5e7; border-radius: 8px; padding: 10px;">
                                        <?php 
                                        $rooms->data_seek(0);
                                        while ($room = $rooms->fetch_assoc()): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="room_ids[]" value="<?php echo $room['id']; ?>" <?php echo $room['status'] == 'rented' ? 'disabled' : ''; ?>>
                                            <label class="form-check-label">
                                                <?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?>
                                                <small class="text-muted">(<?php echo $room['type_name']; ?>)</small>
                                                <?php if ($room['status'] == 'rented'): ?>
                                                <span class="badge bg-secondary ms-1">已租</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-dark w-100"><i class="bi bi-trash me-1"></i> 批量删除选中</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 批量修改 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-pencil"></i> 批量修改房间</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">选择要修改的房间，批量修改房型或状态</p>
                        <form method="POST" onsubmit="return confirm('确定要修改选中的房间吗？')">
                            <input type="hidden" name="action" value="batch_modify">
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-outline-dark" onclick="toggleAllModify()">全选/取消</button>
                                <span class="text-muted ms-2" id="modify-selected-count">已选 0 个</span>
                            </div>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e5e5e7; border-radius: 8px; padding: 10px;">
                                <?php 
                                $rooms->data_seek(0);
                                while ($room = $rooms->fetch_assoc()): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="modify_ids[]" value="<?php echo $room['id']; ?>">
                                    <label class="form-check-label">
                                        <?php echo $room['floor']; ?>楼 <?php echo $room['room_number']; ?>
                                        <small class="text-muted">(<?php echo $room['type_name']; ?>)</small>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">修改房型</label>
                                    <select name="new_type_id" class="form-select form-select-sm">
                                        <option value="">不修改</option>
                                        <?php 
                                        $roomTypes->data_seek(0);
                                        while ($type = $roomTypes->fetch_assoc()): ?>
                                        <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?> (¥<?php echo $type['price']; ?>/月)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">修改状态</label>
                                    <select name="new_status" class="form-select form-select-sm">
                                        <option value="">不修改</option>
                                        <option value="available">可租</option>
                                        <option value="rented">已租</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-check-lg me-1"></i> 批量修改选中</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    
    <script>
    // 实时预览添加数量
    document.querySelectorAll('input[name="start_floor"], input[name="end_floor"], input[name="start_room"], input[name="end_room"]').forEach(function(el) {
        el.addEventListener('change', previewCount);
        el.addEventListener('input', previewCount);
    });
    
    function previewCount() {
        var startFloor = parseInt(document.querySelector('input[name="start_floor"]').value) || 1;
        var endFloor = parseInt(document.querySelector('input[name="end_floor"]').value) || 1;
        var startRoom = parseInt(document.querySelector('input[name="start_room"]').value) || 1;
        var endRoom = parseInt(document.querySelector('input[name="end_room"]').value) || 5;
        
        if (startFloor > endFloor) { var t = startFloor; startFloor = endFloor; endFloor = t; }
        if (startRoom > endRoom) { var t = startRoom; startRoom = endRoom; endRoom = t; }
        
        var count = 0;
        for (var f = startFloor; f <= endFloor; f++) {
            for (var r = startRoom; r <= endRoom; r++) {
                var roomNum = f + String(r).padStart(2, '0');
                if (roomNum.indexOf('4') === -1) count++;
            }
        }
        document.getElementById('preview-count').textContent = count;
    }
    previewCount();
    
    // 一键全选/取消
    function toggleAll() {
        var checkboxes = document.querySelectorAll('input[name="room_ids[]"]');
        var allChecked = Array.from(checkboxes).every(cb => cb.checked || cb.disabled);
        
        checkboxes.forEach(function(cb) {
            if (!cb.disabled) {
                cb.checked = !allChecked;
            }
        });
        updateSelectedCount();
    }
    
    // 更新已选数量
    function updateSelectedCount() {
        var checked = document.querySelectorAll('input[name="room_ids[]"]:checked').length;
        document.getElementById('selected-count').textContent = '已选 ' + checked + ' 个';
    }
    
    // 监听复选框变化
    document.querySelectorAll('input[name="room_ids[]"]').forEach(function(cb) {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    // 批量修改：一键全选/取消
    function toggleAllModify() {
        var checkboxes = document.querySelectorAll('input[name="modify_ids[]"]');
        var allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(function(cb) {
            cb.checked = !allChecked;
        });
        updateModifySelectedCount();
    }
    
    // 批量修改：更新已选数量
    function updateModifySelectedCount() {
        var checked = document.querySelectorAll('input[name="modify_ids[]"]:checked').length;
        document.getElementById('modify-selected-count').textContent = '已选 ' + checked + ' 个';
    }
    
    // 监听批量修改复选框变化
    document.querySelectorAll('input[name="modify_ids[]"]').forEach(function(cb) {
        cb.addEventListener('change', updateModifySelectedCount);
    });
    </script>
