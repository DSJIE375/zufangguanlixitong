<?php
require_once '../includes/database.php';

function getSiteName() {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='site_name'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '我的出租房';
}
$siteName = getSiteName();

if (!isLoggedIn()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction == 'add') {
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);
        $id_card = sanitize($_POST['id_card'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $company = sanitize($_POST['company'] ?? '');
        
        $sql = "INSERT INTO tenants (name, phone, id_card, gender, company) 
                VALUES ('$name', '$phone', '$id_card', '$gender', '$company')";
        
        if ($conn->query($sql)) {
            setFlash('success', '租客添加成功');
            redirect('tenants.php');
        } else {
            setFlash('error', '添加失败: ' . $conn->error);
        }
    }
    
    if ($postAction == 'edit') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);
        $id_card = sanitize($_POST['id_card'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $company = sanitize($_POST['company'] ?? '');
        
        $sql = "UPDATE tenants SET 
                name = '$name',
                phone = '$phone',
                id_card = '$id_card',
                gender = '$gender',
                company = '$company'
                WHERE id = $id";
        
        if ($conn->query($sql)) {
            setFlash('success', '租客信息更新成功');
            redirect('tenants.php');
        } else {
            setFlash('error', '更新失败: ' . $conn->error);
        }
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM tenants WHERE id = $id";
        
        if ($conn->query($sql)) {
            setFlash('success', '租客删除成功');
            redirect('tenants.php');
        } else {
            setFlash('error', '删除失败: ' . $conn->error);
        }
    }
}

// 搜索条件
$where = "1=1";
if (!empty($_GET['search'])) {
    $search = sanitize($_GET['search']);
    $where .= " AND (name LIKE '%$search%' OR phone LIKE '%$search%')";
}

$tenants = $conn->query("SELECT * FROM tenants WHERE $where ORDER BY created_at DESC");

$editTenant = null;
if ($action == 'edit' && $id) {
    $result = $conn->query("SELECT * FROM tenants WHERE id = $id");
    $editTenant = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>租客管理 - <?php echo $siteName; ?></title>
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

                <?php if ($action == 'list'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">租客管理</h4>
                    <a href="tenants.php?action=add" class="btn btn-dark"><i class="bi bi-plus"></i> 添加租客</a>
                </div>

                <!-- 搜索框 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="搜索姓名或电话..." value="<?php echo $_GET['search'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="tenants.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php while ($tenant = $tenants->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-person"></i> <?php echo $tenant['name']; ?></h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="bi bi-telephone text-muted me-2"></i><strong>电话：</strong><?php echo $tenant['phone']; ?></li>
                                    <li class="mb-2"><i class="bi bi-gender-ambiguous text-muted me-2"></i><strong>性别：</strong><?php echo $tenant['gender'] ?: '-'; ?></li>
                                    <li class="mb-2"><i class="bi bi-credit-card text-muted me-2"></i><strong>身份证：</strong><?php echo $tenant['id_card'] ? substr($tenant['id_card'], 0, 6) . '****' . substr($tenant['id_card'], -4) : '-'; ?></li>
                                    <li class="mb-2"><i class="bi bi-building text-muted me-2"></i><strong>公司：</strong><?php echo $tenant['company'] ?: '-'; ?></li>
                                    <li><i class="bi bi-calendar text-muted me-2"></i><strong>添加时间：</strong><?php echo date('Y-m-d', strtotime($tenant['created_at'])); ?></li>
                                </ul>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="tenants.php?action=edit&id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> 编辑</a>
                                <form method="POST" style="display:inline;" data-confirm="确定要删除这个租客吗？">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $tenant['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 删除</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><?php echo $action == 'add' ? '添加租客' : '编辑租客'; ?></h4>
                    <a href="tenants.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 返回</a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $action; ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $editTenant['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">姓名 <span class="text-dark">*</span></label>
                                    <input type="text" name="name" class="form-control" required
                                           value="<?php echo $action == 'edit' ? $editTenant['name'] : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">电话 <span class="text-dark">*</span></label>
                                    <input type="tel" name="phone" class="form-control" required
                                           value="<?php echo $action == 'edit' ? $editTenant['phone'] : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">性别</label>
                                    <select name="gender" class="form-select">
                                        <option value="">请选择</option>
                                        <option value="男" <?php echo ($action == 'edit' && $editTenant['gender'] == '男') ? 'selected' : ''; ?>>男</option>
                                        <option value="女" <?php echo ($action == 'edit' && $editTenant['gender'] == '女') ? 'selected' : ''; ?>>女</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">身份证号</label>
                                    <input type="text" name="id_card" class="form-control" maxlength="18"
                                           value="<?php echo $action == 'edit' ? $editTenant['id_card'] : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">公司/单位</label>
                                    <input type="text" name="company" class="form-control"
                                           value="<?php echo $action == 'edit' ? $editTenant['company'] : ''; ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-dark"><i class="bi bi-check-lg"></i> <?php echo $action == 'add' ? '添加' : '保存'; ?></button>
                            <a href="tenants.php" class="btn btn-outline-secondary">取消</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

<?php include 'footer.php'; ?>
