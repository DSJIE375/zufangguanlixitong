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
        $phone = trim($_POST['phone']);
        $id_card = trim($_POST['id_card'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['男', '女', '']) ? $_POST['gender'] : '';
        $company = trim($_POST['company'] ?? '');
        $emergency_name = trim($_POST['emergency_name'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO tenants (name, phone, id_card, gender, company, emergency_name, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssss", $name, $phone, $id_card, $gender, $company, $emergency_name, $emergency_phone);
            if ($stmt->execute()) {
                logAction('添加租客', "添加租客 $name (电话: $phone)");
                setFlash('success', '租客添加成功');
                redirect('tenants.php');
            } else {
                setFlash('error', '添加失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $id_card = trim($_POST['id_card'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['男', '女', '']) ? $_POST['gender'] : '';
        $company = trim($_POST['company'] ?? '');
        $emergency_name = trim($_POST['emergency_name'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');
        
        $stmt = $conn->prepare("UPDATE tenants SET name=?, phone=?, id_card=?, gender=?, company=?, emergency_name=?, emergency_phone=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("sssssssi", $name, $phone, $id_card, $gender, $company, $emergency_name, $emergency_phone, $id);
            if ($stmt->execute()) {
                logAction('修改租客', "修改租客 $name (电话: $phone)");
                setFlash('success', '租客信息更新成功');
                redirect('tenants.php');
            } else {
                setFlash('error', '更新失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        
        // 获取租客信息
        $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        if (!$tenant) {
            setFlash('error', '租客不存在');
            redirect('tenants.php');
        }
        
        // 检查该租客是否有未缴账单
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bills b JOIN contracts c ON b.contract_id = c.id WHERE c.tenant_id = ? AND b.status = 'unpaid'");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($result['cnt'] > 0) {
                setFlash('error', '该租客还有未缴账单，请先结清费用后再删除');
                redirect('tenants.php');
            }
        }
        
        // 获取该租客的所有合同，保存历史后删除
        $stmt = $conn->prepare("SELECT c.*, r.room_number FROM contracts c JOIN rooms r ON c.room_id = r.id WHERE c.tenant_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $contracts = $stmt->get_result();
            $stmt->close();
            
            while ($contract = $contracts->fetch_assoc()) {
                // 计算已缴金额
                $totalPaid = 0;
                $stmt2 = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM bills WHERE contract_id = ? AND status='paid'");
                if ($stmt2) {
                    $stmt2->bind_param("i", $contract['id']);
                    $stmt2->execute();
                    $totalPaid = $stmt2->get_result()->fetch_assoc()['total'];
                    $stmt2->close();
                }
                
                // 保存租户历史记录
                $end_date = date('Y-m-d');
                $stmt2 = $conn->prepare("INSERT INTO tenant_history (room_id, room_number, tenant_name, tenant_phone, tenant_idcard, tenant_gender, tenant_company, monthly_rent, deposit, start_date, end_date, checkout_reason, total_paid, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'deleted', ?, '删除租客时归档')");
                if ($stmt2) {
                    $stmt2->bind_param("isssssssssss", $contract['room_id'], $contract['room_number'], $tenant['name'], $tenant['phone'], $tenant['id_card'], $tenant['gender'], $tenant['company'], $contract['monthly_rent'], $contract['deposit'], $contract['start_date'], $end_date, $totalPaid);
                    $stmt2->execute();
                    $stmt2->close();
                }
                
                // 保存账单历史记录
                $bills = $conn->query("SELECT * FROM bills WHERE contract_id = {$contract['id']}");
                while ($bill = $bills->fetch_assoc()) {
                    $stmt2 = $conn->prepare("INSERT INTO bill_history (original_bill_id, contract_id, room_id, room_number, tenant_name, tenant_phone, bill_month, water_start, water_end, water_usage, water_price, water_amount, elec_start, elec_end, elec_usage, elec_price, elec_amount, rent_amount, garbage_fee, other_fee, other_fee_desc, total_amount, status, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt2) {
                        $paid_at = $bill['paid_at'] ?: null;
                        $stmt2->bind_param("iiisssssdddddddddddddsss", $bill['id'], $bill['contract_id'], $contract['room_id'], $contract['room_number'], $tenant['name'], $tenant['phone'], $bill['bill_month'], $bill['water_start'], $bill['water_end'], $bill['water_usage'], $bill['water_price'], $bill['water_amount'], $bill['elec_start'], $bill['elec_end'], $bill['elec_usage'], $bill['elec_price'], $bill['elec_amount'], $bill['rent_amount'], $bill['garbage_fee'], $bill['other_fee'], $bill['other_fee_desc'], $bill['total_amount'], $bill['status'], $paid_at);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
                
                // 删除该合同的账单
                $conn->query("DELETE FROM bills WHERE contract_id = {$contract['id']}");
                
                // 删除合同
                $conn->query("DELETE FROM contracts WHERE id = {$contract['id']}");
                
                // 更新房间状态为可租
                $conn->query("UPDATE rooms SET status = 'available' WHERE id = {$contract['room_id']}");
            }
        }
        
        // 删除租客
        $stmt = $conn->prepare("DELETE FROM tenants WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logAction('删除租客', "删除租客 {$tenant['name']}，历史记录已保存");
                setFlash('success', '租客删除成功，历史记录已归档');
                redirect('tenants.php');
            } else {
                setFlash('error', '删除失败');
            }
            $stmt->close();
        }
    }
}

// 搜索条件
$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    $where .= " AND (name LIKE ? OR phone LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

$sql = "SELECT * FROM tenants WHERE $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $tenants = $stmt->get_result();
    $stmt->close();
} else {
    $tenants = $conn->query($sql);
}

$editTenant = null;
if ($action == 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editTenant = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>租客管理 - <?php echo h($siteName); ?></title>
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
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
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
                    <h4 class="mb-0">租客管理</h4>
                    <a href="tenants.php?action=add" class="btn btn-dark"><i class="bi bi-plus"></i> 添加租客</a>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="搜索姓名或电话..." value="<?php echo h($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="tenants.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php while ($tenant = $tenants->fetch_assoc()): 
                        // 检查该租客是否有未缴账单
                        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bills b JOIN contracts c ON b.contract_id = c.id WHERE c.tenant_id = ? AND b.status = 'unpaid'");
                        if ($stmt) {
                            $stmt->bind_param("i", $tenant['id']);
                            $stmt->execute();
                            $hasUnpaid = $stmt->get_result()->fetch_assoc()['cnt'] > 0;
                            $stmt->close();
                        } else {
                            $hasUnpaid = false;
                        }
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person"></i> <?php echo h($tenant['name']); ?></h5>
                                <?php if ($hasUnpaid): ?>
                                    <span class="badge bg-danger" title="有未缴账单"><i class="bi bi-lock"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="bi bi-telephone text-muted me-2"></i><strong>电话：</strong><?php echo h($tenant['phone']); ?></li>
                                    <li class="mb-2"><i class="bi bi-gender-ambiguous text-muted me-2"></i><strong>性别：</strong><?php echo h($tenant['gender'] ?: '-'); ?></li>
                                    <li class="mb-2"><i class="bi bi-credit-card text-muted me-2"></i><strong>身份证：</strong><?php echo $tenant['id_card'] ? h(substr($tenant['id_card'], 0, 6) . '****' . substr($tenant['id_card'], -4)) : '-'; ?></li>
                                    <li class="mb-2"><i class="bi bi-building text-muted me-2"></i><strong>公司：</strong><?php echo h($tenant['company'] ?: '-'); ?></li>
                                    <?php if (!empty($tenant['emergency_name']) || !empty($tenant['emergency_phone'])): ?>
                                    <li class="mb-2"><i class="bi bi-person-hearts text-muted me-2"></i><strong>紧急联系人：</strong><?php echo h($tenant['emergency_name'] ?: '-'); ?></li>
                                    <li class="mb-2"><i class="bi bi-phone text-muted me-2"></i><strong>紧急电话：</strong><?php echo h($tenant['emergency_phone'] ?: '-'); ?></li>
                                    <?php endif; ?>
                                    <li><i class="bi bi-calendar text-muted me-2"></i><strong>添加时间：</strong><?php echo date('Y-m-d', strtotime($tenant['created_at'])); ?></li>
                                </ul>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="tenants.php?action=edit&id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> 编辑</a>
                                <?php if ($hasUnpaid): ?>
                                    <span class="btn btn-sm btn-outline-secondary" title="有未缴账单，无法删除"><i class="bi bi-lock"></i> 锁定</span>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;" data-confirm="确定要删除这个租客吗？">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $tenant['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 删除</button>
                                    </form>
                                <?php endif; ?>
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
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="<?php echo h($action); ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $editTenant['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">姓名 <span class="text-dark">*</span></label>
                                    <input type="text" name="name" class="form-control" required maxlength="50"
                                           value="<?php echo $action == 'edit' ? h($editTenant['name']) : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">电话 <span class="text-dark">*</span></label>
                                    <input type="tel" name="phone" class="form-control" required maxlength="20"
                                           value="<?php echo $action == 'edit' ? h($editTenant['phone']) : ''; ?>">
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
                                           value="<?php echo $action == 'edit' ? h($editTenant['id_card']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">公司/单位</label>
                                    <input type="text" name="company" class="form-control" maxlength="100"
                                           value="<?php echo $action == 'edit' ? h($editTenant['company']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">紧急联系人</label>
                                    <input type="text" name="emergency_name" class="form-control" maxlength="50"
                                           value="<?php echo $action == 'edit' ? h($editTenant['emergency_name'] ?? '') : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">紧急联系人电话</label>
                                    <input type="tel" name="emergency_phone" class="form-control" maxlength="20"
                                           value="<?php echo $action == 'edit' ? h($editTenant['emergency_phone'] ?? '') : ''; ?>">
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
