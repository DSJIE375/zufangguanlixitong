<?php
require_once '../includes/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? 'list';
$id = intval($_GET['id'] ?? 0);

function getSetting($key) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $value = $result->fetch_assoc()['setting_value'];
            $stmt->close();
            return $value;
        }
        $stmt->close();
    }
    return '';
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCSRF();
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction == 'add') {
        $contract_id = intval($_POST['contract_id']);
        $bill_month = trim($_POST['bill_month']);
        $water_start = floatval($_POST['water_start']);
        $water_end = floatval($_POST['water_end']);
        $elec_start = floatval($_POST['elec_start']);
        $elec_end = floatval($_POST['elec_end']);
        $garbage_fee = floatval($_POST['garbage_fee'] ?? 0);
        $other_fee = floatval($_POST['other_fee'] ?? 0);
        $other_fee_desc = trim($_POST['other_fee_desc'] ?? '');
        
        $water_price = floatval(getSetting('water_price'));
        $elec_price = floatval(getSetting('electricity_price'));
        
        $water_usage = $water_end - $water_start;
        $water_amount = $water_usage * $water_price;
        
        $elec_usage = $elec_end - $elec_start;
        $elec_amount = $elec_usage * $elec_price;
        
        $stmt = $conn->prepare("SELECT monthly_rent FROM contracts WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $contract_id);
            $stmt->execute();
            $contract = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $rent_amount = $contract ? $contract['monthly_rent'] : 0;
        
        $total_amount = $rent_amount + $water_amount + $elec_amount + $garbage_fee + $other_fee;
        
        $stmt = $conn->prepare("INSERT INTO bills (contract_id, bill_month, water_start, water_end, water_usage, water_price, water_amount, elec_start, elec_end, elec_usage, elec_price, elec_amount, rent_amount, garbage_fee, other_fee, other_fee_desc, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isdddddddddddddds", $contract_id, $bill_month, $water_start, $water_end, $water_usage, $water_price, $water_amount, $elec_start, $elec_end, $elec_usage, $elec_price, $elec_amount, $rent_amount, $garbage_fee, $other_fee, $other_fee_desc, $total_amount);
            if ($stmt->execute()) {
                logAction('创建账单', "创建账单: $bill_month 金额: ¥$total_amount");
                setFlash('success', '账单创建成功');
                redirect('bills.php');
            } else {
                setFlash('error', '创建失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'edit') {
        $id = intval($_POST['id']);
        $water_start = floatval($_POST['water_start']);
        $water_end = floatval($_POST['water_end']);
        $elec_start = floatval($_POST['elec_start']);
        $elec_end = floatval($_POST['elec_end']);
        $garbage_fee = floatval($_POST['garbage_fee'] ?? 0);
        $other_fee = floatval($_POST['other_fee'] ?? 0);
        $other_fee_desc = trim($_POST['other_fee_desc'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['paid', 'unpaid']) ? $_POST['status'] : 'unpaid';
        
        $water_price = floatval(getSetting('water_price'));
        $elec_price = floatval(getSetting('electricity_price'));
        
        $water_usage = $water_end - $water_start;
        $water_amount = $water_usage * $water_price;
        
        $elec_usage = $elec_end - $elec_start;
        $elec_amount = $elec_usage * $elec_price;
        
        $stmt = $conn->prepare("SELECT rent_amount FROM bills WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $bill = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $rent_amount = $bill ? $bill['rent_amount'] : 0;
        $total_amount = $rent_amount + $water_amount + $elec_amount + $garbage_fee + $other_fee;
        
        $paid_at = ($status == 'paid') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $conn->prepare("UPDATE bills SET water_start=?, water_end=?, water_usage=?, water_price=?, water_amount=?, elec_start=?, elec_end=?, elec_usage=?, elec_price=?, elec_amount=?, garbage_fee=?, other_fee=?, other_fee_desc=?, total_amount=?, status=?, paid_at=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("dddddddddddddsssi", $water_start, $water_end, $water_usage, $water_price, $water_amount, $elec_start, $elec_end, $elec_usage, $elec_price, $elec_amount, $garbage_fee, $other_fee, $other_fee_desc, $total_amount, $status, $paid_at, $id);
            if ($stmt->execute()) {
                logAction('修改账单', "修改账单 ID: $id");
                setFlash('success', '账单更新成功');
                redirect('bills.php');
            } else {
                setFlash('error', '更新失败');
            }
            $stmt->close();
        }
    }
    
    if ($postAction == 'delete') {
        $id = intval($_POST['id']);
        
        $stmt = $conn->prepare("SELECT b.*, c.room_id, r.room_number, t.name as tenant_name, t.phone as tenant_phone
            FROM bills b
            JOIN contracts c ON b.contract_id = c.id
            JOIN rooms r ON c.room_id = r.id
            JOIN tenants t ON c.tenant_id = t.id
            WHERE b.id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $bill = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        if (!$bill) {
            setFlash('error', '账单不存在');
            redirect('bills.php');
        }
        
        if ($bill['status'] == 'unpaid') {
            setFlash('error', '该账单未缴清，不能删除！请先标记为已缴费或编辑账单。');
            redirect('bills.php');
        }
        
        // 已缴清的账单，保存到历史记录
        $stmt = $conn->prepare("INSERT INTO bill_history (original_bill_id, contract_id, room_id, room_number, tenant_name, tenant_phone, bill_month, water_start, water_end, water_usage, water_price, water_amount, elec_start, elec_end, elec_usage, elec_price, elec_amount, rent_amount, garbage_fee, other_fee, other_fee_desc, total_amount, status, paid_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $paid_at = $bill['paid_at'] ?: null;
            $stmt->bind_param("iiissssdddddddddddddsss", $bill['id'], $bill['contract_id'], $bill['room_id'], $bill['room_number'], $bill['tenant_name'], $bill['tenant_phone'], $bill['bill_month'], $bill['water_start'], $bill['water_end'], $bill['water_usage'], $bill['water_price'], $bill['water_amount'], $bill['elec_start'], $bill['elec_end'], $bill['elec_usage'], $bill['elec_price'], $bill['elec_amount'], $bill['rent_amount'], $bill['garbage_fee'], $bill['other_fee'], $bill['other_fee_desc'], $bill['total_amount'], $bill['status'], $paid_at);
            $stmt->execute();
            $stmt->close();
        }
        
        // 删除账单
        $stmt = $conn->prepare("DELETE FROM bills WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                logAction('删除账单', "删除账单: {$bill['bill_month']} 金额: ¥{$bill['total_amount']}");
                setFlash('success', '账单已删除，已保存到历史记录');
                redirect('bills.php');
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
    $where .= " AND (r.room_number LIKE ? OR t.name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}
if (!empty($_GET['month'])) {
    $month = trim($_GET['month']);
    $where .= " AND b.bill_month = ?";
    $params[] = $month;
    $types .= 's';
}
if (!empty($_GET['bill_status']) && in_array($_GET['bill_status'], ['paid', 'unpaid'])) {
    $bill_status = $_GET['bill_status'];
    $where .= " AND b.status = ?";
    $params[] = $bill_status;
    $types .= 's';
}

$sql = "SELECT b.*, c.monthly_rent, r.room_number, t.name as tenant_name
        FROM bills b
        JOIN contracts c ON b.contract_id = c.id
        JOIN rooms r ON c.room_id = r.id
        JOIN tenants t ON c.tenant_id = t.id
        WHERE $where
        ORDER BY b.status = 'unpaid' DESC, b.bill_month DESC, r.room_number";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $bills = $stmt->get_result();
    $stmt->close();
} else {
    $bills = $conn->query($sql);
}

$contracts = $conn->query("SELECT c.*, r.room_number, t.name as tenant_name
                          FROM contracts c
                          JOIN rooms r ON c.room_id = r.id
                          JOIN tenants t ON c.tenant_id = t.id
                          WHERE c.status = 'active'
                          ORDER BY r.room_number");

$editBill = null;
if ($action == 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $editBill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$preselect_contract = intval($_GET['contract_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>水电账单 - <?php echo h($siteName); ?></title>
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
                    <h4 class="mb-0">水电账单管理</h4>
                    <a href="bills.php?action=add" class="btn btn-dark"><i class="bi bi-plus"></i> 录入账单</a>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="搜索房间号或租客..." value="<?php echo h($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="month" name="month" class="form-control" value="<?php echo h($_GET['month'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="bill_status" class="form-select">
                                    <option value="">全部状态</option>
                                    <option value="unpaid" <?php echo ($_GET['bill_status'] ?? '') == 'unpaid' ? 'selected' : ''; ?>>未缴</option>
                                    <option value="paid" <?php echo ($_GET['bill_status'] ?? '') == 'paid' ? 'selected' : ''; ?>>已缴</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="bills.php" class="btn btn-outline-secondary">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <?php $idx = 1; while ($bill = $bills->fetch_assoc()): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><small class="text-white-50">#<?php echo $idx++; ?></small> <i class="bi bi-door-open"></i> <?php echo h($bill['room_number']); ?></h5>
                                <span class="badge bg-white text-<?php echo $bill['status'] == 'paid' ? 'success' : 'danger'; ?>"><?php echo $bill['status'] == 'paid' ? '已缴' : '未缴'; ?></span>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <small class="text-muted"><?php echo h($bill['bill_month']); ?></small>
                                    <div class="text-muted small"><?php echo h($bill['tenant_name']); ?></div>
                                </div>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-1"><i class="bi bi-droplet text-dark me-2"></i><strong>水费：</strong><?php echo number_format($bill['water_usage'], 1); ?>吨 = <span class="text-dark">¥<?php echo number_format($bill['water_amount'], 2); ?></span></li>
                                    <li class="mb-1"><i class="bi bi-lightning text-dark me-2"></i><strong>电费：</strong><?php echo number_format($bill['elec_usage'], 1); ?>度 = <span class="text-dark">¥<?php echo number_format($bill['elec_amount'], 2); ?></span></li>
                                    <li class="mb-1"><i class="bi bi-trash text-dark me-2"></i><strong>垃圾费：</strong><span class="text-dark">¥<?php echo number_format($bill['garbage_fee'], 2); ?></span></li>
                                    <?php if ($bill['other_fee'] > 0): ?>
                                    <li class="mb-1"><i class="bi bi-plus-circle text-dark me-2"></i><strong><?php echo h($bill['other_fee_desc'] ?: '其他'); ?>：</strong><span class="text-dark">¥<?php echo number_format($bill['other_fee'], 2); ?></span></li>
                                    <?php endif; ?>
                                    <li class="mb-1"><i class="bi bi-house text-muted me-2"></i><strong>房租：</strong><span class="text-dark">¥<?php echo number_format($bill['rent_amount'], 2); ?></span></li>
                                    <li class="pt-2 border-top"><strong>合计：</strong><span class="text-dark fw-bold fs-5">¥<?php echo number_format($bill['total_amount'], 2); ?></span></li>
                                </ul>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="bill_print.php?id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank"><i class="bi bi-eye"></i> 查看</a>
                                <button type="button" class="btn btn-sm btn-outline-dark" onclick="shareBill(<?php echo $bill['id']; ?>, '<?php echo h($bill['room_number']); ?>', '<?php echo h($bill['bill_month']); ?>', '<?php echo h($bill['tenant_name']); ?>')"><i class="bi bi-share"></i> 分享</button>
                                <a href="bills.php?action=edit&id=<?php echo $bill['id']; ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i> 编辑</a>
                                <?php if ($bill['status'] == 'paid'): ?>
                                <form method="POST" style="display:inline;" data-confirm="确定要删除这个账单吗？">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $bill['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 删除</button>
                                </form>
                                <?php else: ?>
                                <span class="btn btn-sm btn-outline-secondary" title="未缴清，无法删除"><i class="bi bi-lock"></i> 锁定</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><?php echo $action == 'add' ? '录入账单' : '编辑账单'; ?></h4>
                    <a href="bills.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 返回</a>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="<?php echo h($action); ?>">
                                    <?php if ($action == 'edit'): ?>
                                        <input type="hidden" name="id" value="<?php echo $editBill['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">选择合同 <span class="text-dark">*</span></label>
                                            <select name="contract_id" class="form-select" required id="contractSelect" <?php echo $action == 'edit' ? 'disabled' : ''; ?>>
                                                <option value="">请选择合同</option>
                                                <?php while ($contract = $contracts->fetch_assoc()): ?>
                                                <option value="<?php echo $contract['id']; ?>"
                                                        <?php echo ($preselect_contract == $contract['id'] || ($action == 'edit' && $editBill['contract_id'] == $contract['id'])) ? 'selected' : ''; ?>>
                                                    <?php echo h($contract['room_number']); ?> - <?php echo h($contract['tenant_name']); ?> (¥<?php echo number_format($contract['monthly_rent'], 2); ?>/月)
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">账单月份 <span class="text-dark">*</span></label>
                                            <input type="month" name="bill_month" class="form-control" required
                                                   value="<?php echo $action == 'edit' ? $editBill['bill_month'] : date('Y-m'); ?>">
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-3"><i class="bi bi-droplet"></i> 水费</h6>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">上期读数（吨）</label>
                                            <input type="number" name="water_start" class="form-control" step="0.01" required id="water_start"
                                                   value="<?php echo $action == 'edit' ? $editBill['water_start'] : 0; ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">本期读数（吨）</label>
                                            <input type="number" name="water_end" class="form-control" step="0.01" required id="water_end"
                                                   value="<?php echo $action == 'edit' ? $editBill['water_end'] : 0; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">水费单价</label>
                                            <input type="text" class="form-control" disabled
                                                   value="¥<?php echo number_format(floatval(getSetting('water_price')), 2); ?>/吨 (在系统设置中修改)">
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-3"><i class="bi bi-lightning"></i> 电费</h6>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">上期读数（度）</label>
                                            <input type="number" name="elec_start" class="form-control" step="0.01" required id="elec_start"
                                                   value="<?php echo $action == 'edit' ? $editBill['elec_start'] : 0; ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">本期读数（度）</label>
                                            <input type="number" name="elec_end" class="form-control" step="0.01" required id="elec_end"
                                                   value="<?php echo $action == 'edit' ? $editBill['elec_end'] : 0; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">电费单价</label>
                                            <input type="text" class="form-control" disabled
                                                   value="¥<?php echo number_format(floatval(getSetting('electricity_price')), 2); ?>/度 (在系统设置中修改)">
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-3"><i class="bi bi-trash"></i> 垃圾管理费</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">垃圾管理费（元/月）</label>
                                            <input type="number" name="garbage_fee" class="form-control" step="0.01" min="0"
                                                   value="<?php echo $action == 'edit' ? $editBill['garbage_fee'] : number_format(floatval(getSetting('garbage_fee')), 2); ?>">
                                            <small class="text-muted">当前默认：¥<?php echo number_format(floatval(getSetting('garbage_fee')), 2); ?>（可在系统设置中修改）</small>
                                        </div>
                                    </div>
                                    
                                    <h6 class="mt-3 mb-3"><i class="bi bi-plus-circle"></i> 其他费用</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">其他费用名称</label>
                                            <input type="text" name="other_fee_desc" class="form-control" placeholder="如：维修费、清洁费等" maxlength="100"
                                                   value="<?php echo $action == 'edit' ? h($editBill['other_fee_desc']) : ''; ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">其他费用金额（元）</label>
                                            <input type="number" name="other_fee" class="form-control" step="0.01" min="0"
                                                   value="<?php echo $action == 'edit' ? $editBill['other_fee'] : '0'; ?>">
                                        </div>
                                    </div>
                                    
                                    <?php if ($action == 'edit'): ?>
                                    <h6 class="mt-3 mb-3"><i class="bi bi-check-circle"></i> 缴费状态</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <select name="status" class="form-select">
                                                <option value="unpaid" <?php echo $editBill['status'] == 'unpaid' ? 'selected' : ''; ?>>未缴</option>
                                                <option value="paid" <?php echo $editBill['status'] == 'paid' ? 'selected' : ''; ?>>已缴</option>
                                            </select>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <button type="submit" class="btn btn-dark"><i class="bi bi-check-lg"></i> <?php echo $action == 'add' ? '创建' : '保存'; ?></button>
                                    <a href="bills.php" class="btn btn-outline-secondary">取消</a>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle"></i> 费用说明</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="bi bi-droplet text-dark"></i> 水费：输入上期和本期读数，系统自动计算用量和费用
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-lightning text-dark"></i> 电费：输入上期和本期读数，系统自动计算用量和费用
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-house text-dark"></i> 房租：从合同中自动获取月租金
                                    </li>
                                    <li>
                                        <i class="bi bi-calculator text-dark"></i> 合计 = 房租 + 水费 + 电费
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    </script>
<?php include 'footer.php'; ?>
