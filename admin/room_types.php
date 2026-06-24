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
$id = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCSRF();
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction == 'add') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $area = floatval($_POST['area']);
        
        $stmt = $conn->prepare("INSERT INTO room_types (name, description, price, area) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssdd", $name, $description, $price, $area);
            if ($stmt->execute()) {
                logAction('添加房间类型', "添加类型: $name 月租: ¥$price");
                setFlash('success', '房间类型添加成功');
                redirect('room_types.php');
            } else {
                setFlash('error', '添加失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $area = floatval($_POST['area']);
        
        $stmt = $conn->prepare("UPDATE room_types SET name=?, description=?, price=?, area=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssddi", $name, $description, $price, $area, $id);
            if ($stmt->execute()) {
                logAction('修改房间类型', "修改类型: $name");
                setFlash('success', '房间类型更新成功');
                redirect('room_types.php');
            } else {
                setFlash('error', '更新失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM room_types WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logAction('删除房间类型', "删除类型 ID: $id");
                setFlash('success', '房间类型删除成功');
                redirect('room_types.php');
            } else {
                setFlash('error', '删除失败');
            }
            $stmt->close();
        }
    }
}

$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    $where .= " AND name LIKE ?";
    $params[] = $search;
    $types .= 's';
}

$sql = "SELECT * FROM room_types WHERE $where ORDER BY price";
$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $types = $stmt->get_result();
    $stmt->close();
} else {
    $types = $conn->query($sql);
}

$editType = null;
if ($action == 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM room_types WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editType = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>房间类型管理 - <?php echo h($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><img src="../images/logo.svg" alt="Logo" height="28"></a>
            <div class="d-flex align-items-center">
                <span class="me-3" style="color: var(--text-muted);"><i class="bi bi-person-circle"></i> <?php echo h($_SESSION['realname']); ?></span>
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
                    <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible fade show">
                        <?php echo h($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">房间类型管理</h4>
                    <a href="room_types.php?action=add" class="btn btn-dark"><i class="bi bi-plus"></i> 添加类型</a>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="搜索类型名称..." value="<?php echo h($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="room_types.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php while ($type = $types->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-tag"></i> <?php echo h($type['name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo h($type['description'] ?: '暂无描述'); ?></p>
                                <ul class="list-unstyled">
                                    <li><strong>月租：</strong><span class="text-dark">¥<?php echo number_format($type['price'], 2); ?></span></li>
                                    <li><strong>面积：</strong><?php echo $type['area']; ?>㎡</li>
                                </ul>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="room_types.php?action=edit&id=<?php echo $type['id']; ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> 编辑</a>
                                <form method="POST" style="display:inline;" data-confirm="确定要删除这个房间类型吗？">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 删除</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><?php echo $action == 'add' ? '添加房间类型' : '编辑房间类型'; ?></h4>
                    <a href="room_types.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 返回</a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="<?php echo h($action); ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $editType['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">类型名称</label>
                                    <input type="text" name="name" class="form-control" required maxlength="50"
                                           value="<?php echo $action == 'edit' ? h($editType['name']) : ''; ?>"
                                           placeholder="如: 单人间、双人间">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">月租金（元）</label>
                                    <input type="number" name="price" class="form-control" required step="0.01" min="0"
                                           value="<?php echo $action == 'edit' ? $editType['price'] : ''; ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">面积（㎡）</label>
                                    <input type="number" name="area" class="form-control" required step="0.01" min="0"
                                           value="<?php echo $action == 'edit' ? $editType['area'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">描述</label>
                                <textarea name="description" class="form-control" rows="3" maxlength="500"><?php echo $action == 'edit' ? h($editType['description']) : ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-dark"><i class="bi bi-check-lg"></i> <?php echo $action == 'add' ? '添加' : '保存'; ?></button>
                            <a href="room_types.php" class="btn btn-outline-secondary">取消</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

<?php include 'footer.php'; ?>
