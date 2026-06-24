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
        $room_id = intval($_POST['room_id']);
        $tenant_id = intval($_POST['tenant_id']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date'] ?? '');
        $monthly_rent = floatval($_POST['monthly_rent']);
        $deposit = floatval($_POST['deposit'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO contracts (room_id, tenant_id, start_date, end_date, monthly_rent, deposit, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $end_date_val = $end_date ?: null;
            $stmt->bind_param("iissdds", $room_id, $tenant_id, $start_date, $end_date_val, $monthly_rent, $deposit, $notes);
            if ($stmt->execute()) {
                $stmt2 = $conn->prepare("SELECT room_number FROM rooms WHERE id = ?");
                if ($stmt2) {
                    $stmt2->bind_param("i", $room_id);
                    $stmt2->execute();
                    $room = $stmt2->get_result()->fetch_assoc();
                    $stmt2->close();
                }
                logAction('创建合同', "创建合同: {$room['room_number']}");
                $conn->query("UPDATE rooms SET status = 'rented' WHERE id = $room_id");
                setFlash('success', '合同创建成功');
                redirect('contracts.php');
            } else {
                setFlash('error', '创建失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'edit') {
        $id = intval($_POST['id']);
        $room_id = intval($_POST['room_id']);
        $tenant_id = intval($_POST['tenant_id']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date'] ?? '');
        $monthly_rent = floatval($_POST['monthly_rent']);
        $deposit = floatval($_POST['deposit'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'expired', 'terminated']) ? $_POST['status'] : 'active';
        $notes = trim($_POST['notes'] ?? '');
        
        $end_date_val = $end_date ?: null;
        
        $stmt = $conn->prepare("UPDATE contracts SET room_id=?, tenant_id=?, start_date=?, end_date=?, monthly_rent=?, deposit=?, status=?, notes=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("iisdddssi", $room_id, $tenant_id, $start_date, $end_date_val, $monthly_rent, $deposit, $status, $notes, $id);
            if ($stmt->execute()) {
                if ($status == 'expired' || $status == 'terminated') {
                    $stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM bills WHERE contract_id = ? AND status = 'unpaid'");
                    if ($stmt2) {
                        $stmt2->bind_param("i", $id);
                        $stmt2->execute();
                        $checkBills = $stmt2->get_result()->fetch_assoc();
                        $stmt2->close();
                        $unpaidCount = $checkBills['cnt'];
                    }
                    
                    if ($unpaidCount > 0) {
                        $conn->query("UPDATE contracts SET status = 'active' WHERE id = $id");
                        setFlash('error', '该合同还有 ' . $unpaidCount . ' 笔未缴账单，请先结清费用后再退租');
                    } else {
                        // 保存历史记录
                        $stmt3 = $conn->prepare("SELECT c.*, r.room_number, t.name as tname, t.phone as tphone, t.id_card as tidcard, t.gender as tgender, t.company as tcompany
                            FROM contracts c JOIN rooms r ON c.room_id = r.id JOIN tenants t ON c.tenant_id = t.id WHERE c.id = ?");
                        if ($stmt3) {
                            $stmt3->bind_param("i", $id);
                            $stmt3->execute();
                            $hist = $stmt3->get_result()->fetch_assoc();
                            $stmt3->close();
                        }
                        
                        if ($hist) {
                            $stmt4 = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM bills WHERE contract_id = ? AND status='paid'");
                            if ($stmt4) {
                                $stmt4->bind_param("i", $id);
                                $stmt4->execute();
                                $totalPaid = $stmt4->get_result()->fetch_assoc()['total'];
                                $stmt4->close();
                            }
                            
                            $stmt5 = $conn->prepare("INSERT INTO tenant_history (room_id, room_number, tenant_name, tenant_phone, tenant_idcard, tenant_gender, tenant_company, monthly_rent, deposit, start_date, end_date, checkout_reason, total_paid, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt5) {
                                $stmt5->bind_param("issssssddsssds", $hist['room_id'], $hist['room_number'], $hist['tname'], $hist['tphone'], $hist['tidcard'], $hist['tgender'], $hist['tcompany'], $hist['monthly_rent'], $hist['deposit'], $hist['start_date'], $end_date, $status, $totalPaid, $notes);
                                $stmt5->execute();
                                $stmt5->close();
                            }
                        }
                        $conn->query("UPDATE rooms SET status = 'available' WHERE id = $room_id");
                        setFlash('success', '合同已终止，历史记录已保存');
                    }
                } else {
                    setFlash('success', '合同更新成功');
                }
                redirect('contracts.php');
            } else {
                setFlash('error', '更新失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bills WHERE contract_id = ? AND status = 'unpaid'");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $checkBills = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $unpaidCount = $checkBills['cnt'];
        }
        
        if ($unpaidCount > 0) {
            setFlash('error', '该合同还有 ' . $unpaidCount . ' 笔未缴账单，请先结清费用后再删除');
            redirect('contracts.php');
        } else {
            // 保存历史记录
            $stmt = $conn->prepare("SELECT c.*, r.room_number, t.name as tname, t.phone as tphone, t.id_card as tidcard, t.gender as tgender, t.company as tcompany
                FROM contracts c JOIN rooms r ON c.room_id = r.id JOIN tenants t ON c.tenant_id = t.id WHERE c.id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $hist = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            
            if ($hist) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) as total FROM bills WHERE contract_id = ? AND status='paid'");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $totalPaid = $stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();
                }
                
                $end_date = date('Y-m-d');
                $stmt = $conn->prepare("INSERT INTO tenant_history (room_id, room_number, tenant_name, tenant_phone, tenant_idcard, tenant_gender, tenant_company, monthly_rent, deposit, start_date, end_date, checkout_reason, total_paid)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'deleted', ?)");
                if ($stmt) {
                    $stmt->bind_param("issssssdsss", $hist['room_id'], $hist['room_number'], $hist['tname'], $hist['tphone'], $hist['tidcard'], $hist['tgender'], $hist['tcompany'], $hist['monthly_rent'], $hist['deposit'], $hist['start_date'], $end_date, $totalPaid);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // 保存账单到历史记录
                $bills = $conn->query("SELECT * FROM bills WHERE contract_id = $id");
                while ($bill = $bills->fetch_assoc()) {
                    $stmt = $conn->prepare("INSERT INTO bill_history (original_bill_id, contract_id, room_id, room_number, tenant_name, tenant_phone, bill_month, water_start, water_end, water_usage, water_price, water_amount, elec_start, elec_end, elec_usage, elec_price, elec_amount, rent_amount, garbage_fee, other_fee, other_fee_desc, total_amount, status, paid_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $paid_at = $bill['paid_at'] ?: null;
                        $stmt->bind_param("iiissssdddddssssddssss", $bill['id'], $bill['contract_id'], $hist['room_id'], $hist['room_number'], $hist['tname'], $hist['tphone'], $bill['bill_month'], $bill['water_start'], $bill['water_end'], $bill['water_usage'], $bill['water_price'], $bill['water_amount'], $bill['elec_start'], $bill['elec_end'], $bill['elec_usage'], $bill['elec_price'], $bill['elec_amount'], $bill['rent_amount'], $bill['garbage_fee'], $bill['other_fee'], $bill['other_fee_desc'], $bill['total_amount'], $bill['status'], $paid_at);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM contracts WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    if ($hist) {
                        $conn->query("UPDATE rooms SET status = 'available' WHERE id = {$hist['room_id']}");
                    }
                    setFlash('success', '合同已删除，历史记录已保存');
                    logAction('删除合同', "删除合同 ID: $id");
                    redirect('contracts.php');
                }
                $stmt->close();
            }
        }
    }
}

// 搜索条件
$where = "1=1";
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    $where .= " AND (r.room_number LIKE ? OR t.name LIKE ? OR t.phone LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}
if (!empty($_GET['status']) && in_array($_GET['status'], ['active', 'expired', 'terminated'])) {
    $status = $_GET['status'];
    $where .= " AND c.status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql = "SELECT c.*, r.room_number, t.name as tenant_name, t.phone as tenant_phone, rt.name as type_name
        FROM contracts c
        JOIN rooms r ON c.room_id = r.id
        JOIN tenants t ON c.tenant_id = t.id
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
        WHERE $where
        ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $contracts = $stmt->get_result();
    $stmt->close();
} else {
    $contracts = $conn->query($sql);
}

$rooms = $conn->query("SELECT r.*, rt.name as type_name, rt.price as type_price 
                       FROM rooms r 
                       LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                       ORDER BY r.floor, r.room_number");

$tenants = $conn->query("SELECT * FROM tenants ORDER BY name");

$editContract = null;
if ($action == 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM contracts WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editContract = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($action == 'edit' && $editContract) {
    $rooms = $conn->query("SELECT r.*, rt.name as type_name, rt.price as type_price 
                           FROM rooms r 
                           LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                           WHERE r.status = 'available' OR r.id = {$editContract['room_id']}
                           ORDER BY r.floor, r.room_number");
} else {
    $rooms = $conn->query("SELECT r.*, rt.name as type_name, rt.price as type_price 
                           FROM rooms r 
                           LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                           WHERE r.status = 'available'
                           ORDER BY r.floor, r.room_number");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>合同管理 - <?php echo h($siteName); ?></title>
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
                    <h4 class="mb-0">合同管理</h4>
                    <a href="contracts.php?action=add" class="btn btn-dark"><i class="bi bi-plus"></i> 新建合同</a>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="搜索房间号、租客姓名或电话..." value="<?php echo h($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">全部状态</option>
                                    <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>执行中</option>
                                    <option value="expired" <?php echo ($_GET['status'] ?? '') == 'expired' ? 'selected' : ''; ?>>已到期</option>
                                    <option value="terminated" <?php echo ($_GET['status'] ?? '') == 'terminated' ? 'selected' : ''; ?>>已终止</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="contracts.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php while ($contract = $contracts->fetch_assoc()): 
                        $statusClass = 'secondary';
                        $statusText = '未知';
                        switch ($contract['status']) {
                            case 'active': $statusClass = 'success'; $statusText = '执行中'; break;
                            case 'expired': $statusClass = 'warning'; $statusText = '已到期'; break;
                            case 'terminated': $statusClass = 'danger'; $statusText = '已终止'; break;
                        }
                        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bills WHERE contract_id = ? AND status = 'unpaid'");
                        if ($stmt) {
                            $stmt->bind_param("i", $contract['id']);
                            $stmt->execute();
                            $checkBills = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            $hasUnpaid = $checkBills['cnt'] > 0;
                        }
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-<?php echo $statusClass; ?> text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-door-open"></i> <?php echo h($contract['room_number']); ?></h5>
                                <span class="badge bg-white text-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2"><i class="bi bi-person text-muted me-2"></i><strong>租客：</strong><?php echo h($contract['tenant_name']); ?></li>
                                    <li class="mb-2"><i class="bi bi-telephone text-muted me-2"></i><strong>电话：</strong><?php echo h($contract['tenant_phone']); ?></li>
                                    <li class="mb-2"><i class="bi bi-tag text-muted me-2"></i><strong>类型：</strong><?php echo h($contract['type_name']); ?></li>
                                    <li class="mb-2"><i class="bi bi-cash text-muted me-2"></i><strong>月租：</strong><span class="text-dark">¥<?php echo number_format($contract['monthly_rent'], 2); ?></span></li>
                                    <li class="mb-2"><i class="bi bi-wallet text-muted me-2"></i><strong>押金：</strong>¥<?php echo number_format($contract['deposit'], 2); ?></li>
                                    <li class="mb-2"><i class="bi bi-calendar text-muted me-2"></i><strong>入住：</strong><?php echo $contract['start_date']; ?></li>
                                    <li><i class="bi bi-calendar-check text-muted me-2"></i><strong>到期：</strong><?php echo $contract['end_date'] ?: '长期'; ?></li>
                                </ul>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="contracts.php?action=edit&id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> 编辑</a>
                                <a href="contract_template.php?id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="bi bi-file-text"></i> 合同</a>
                                <a href="bills.php?action=add&contract_id=<?php echo $contract['id']; ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt"></i> 录费</a>
                                <?php if (!$hasUnpaid): ?>
                                <form method="POST" style="display:inline;" data-confirm="确定要删除这个合同吗？">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $contract['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 删除</button>
                                </form>
                                <?php else: ?>
                                <span class="btn btn-sm btn-outline-secondary"><i class="bi bi-lock"></i> 锁定</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><?php echo $action == 'add' ? '新建合同' : '编辑合同'; ?></h4>
                    <a href="contracts.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 返回</a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="<?php echo h($action); ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="id" value="<?php echo $editContract['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">选择房间 <span class="text-dark">*</span></label>
                                    <select name="room_id" class="form-select" required id="roomSelect">
                                        <option value="">请选择房间</option>
                                        <?php while ($room = $rooms->fetch_assoc()): ?>
                                        <option value="<?php echo $room['id']; ?>" 
                                                data-price="<?php echo $room['type_price']; ?>"
                                                <?php echo ($action == 'edit' && $editContract['room_id'] == $room['id']) ? 'selected' : ''; ?>>
                                            <?php echo $room['floor']; ?>楼 <?php echo h($room['room_number']); ?> - <?php echo h($room['type_name']); ?> (¥<?php echo number_format($room['type_price'], 2); ?>/月)
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">选择租客 <span class="text-dark">*</span></label>
                                    <select name="tenant_id" class="form-select" required>
                                        <option value="">请选择租客</option>
                                        <?php while ($tenant = $tenants->fetch_assoc()): ?>
                                        <option value="<?php echo $tenant['id']; ?>"
                                                <?php echo ($action == 'edit' && $editContract['tenant_id'] == $tenant['id']) ? 'selected' : ''; ?>>
                                            <?php echo h($tenant['name']); ?> (<?php echo h($tenant['phone']); ?>)
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">起始日期 <span class="text-dark">*</span></label>
                                    <input type="date" name="start_date" class="form-control" required
                                           value="<?php echo $action == 'edit' ? $editContract['start_date'] : date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">结束日期（留空为长期）</label>
                                    <input type="date" name="end_date" class="form-control"
                                           value="<?php echo $action == 'edit' && $editContract['end_date'] ? $editContract['end_date'] : ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">月租金（元）<span class="text-dark">*</span></label>
                                    <input type="number" name="monthly_rent" class="form-control" step="0.01" required id="monthlyRent"
                                           value="<?php echo $action == 'edit' ? $editContract['monthly_rent'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">押金（元）</label>
                                    <input type="number" name="deposit" class="form-control" step="0.01"
                                           value="<?php echo $action == 'edit' ? $editContract['deposit'] : 0; ?>">
                                </div>
                                <?php if ($action == 'edit'): ?>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">合同状态</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?php echo $editContract['status'] == 'active' ? 'selected' : ''; ?>>执行中</option>
                                        <option value="expired" <?php echo $editContract['status'] == 'expired' ? 'selected' : ''; ?>>已到期</option>
                                        <option value="terminated" <?php echo $editContract['status'] == 'terminated' ? 'selected' : ''; ?>>已终止</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">备注</label>
                                    <input type="text" name="notes" class="form-control" maxlength="500"
                                           value="<?php echo $action == 'edit' ? h($editContract['notes']) : ''; ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-dark"><i class="bi bi-check-lg"></i> <?php echo $action == 'add' ? '创建' : '保存'; ?></button>
                            <a href="contracts.php" class="btn btn-outline-secondary">取消</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

<?php include 'footer.php'; ?>
