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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        $bill_id = intval($_POST['bill_id']);
        $expire_type = $_POST['expire_type'] ?? 'permanent';
        $token = bin2hex(random_bytes(16));
        
        if ($expire_type == 'permanent') {
            $stmt = $conn->prepare("INSERT INTO share_links (bill_id, token, expire_at) VALUES (?, ?, NULL)");
            if ($stmt) {
                $stmt->bind_param("is", $bill_id, $token);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $expire_hours = intval($_POST['expire_hours'] ?? 24);
            $expireAt = date('Y-m-d H:i:s', strtotime("+{$expire_hours} hours"));
            $stmt = $conn->prepare("INSERT INTO share_links (bill_id, token, expire_at) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iss", $bill_id, $token, $expireAt);
                $stmt->execute();
                $stmt->close();
            }
        }
        logAction('创建分享链接', "创建分享链接 ID: $bill_id");
        setFlash('success', '分享链接创建成功');
        redirect('share_links.php');
    }
    
    if ($action == 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM share_links WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        logAction('删除分享链接', "删除分享链接 ID: $id");
        setFlash('success', '分享链接已删除');
        redirect('share_links.php');
    }
    
    if ($action == 'toggle') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE share_links SET is_active = IF(is_active=1, 0, 1) WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
        logAction('切换分享链接状态', "切换分享链接状态 ID: $id");
        setFlash('success', '状态已更新');
        redirect('share_links.php');
    }
    
    if ($action == 'update_expire') {
        $id = intval($_POST['id']);
        $newHours = $_POST['new_expire_hours'] ?? '';
        
        if ($newHours === '' || $newHours === '0') {
            $stmt = $conn->prepare("UPDATE share_links SET expire_at = NULL WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            }
            setFlash('success', '已设为永久有效');
        } else {
            $hours = intval($newHours);
            if ($hours > 0) {
                $expireAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
                $stmt = $conn->prepare("UPDATE share_links SET expire_at = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $expireAt, $id);
                    $stmt->execute();
                    $stmt->close();
                }
                setFlash('success', "已设置为{$hours}小时后过期");
            }
        }
        redirect('share_links.php');
    }
}

// 搜索
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

$sql = "SELECT sl.*, b.bill_month, r.room_number, t.name as tenant_name
    FROM share_links sl
    JOIN bills b ON sl.bill_id = b.id
    JOIN contracts c ON b.contract_id = c.id
    JOIN rooms r ON c.room_id = r.id
    JOIN tenants t ON c.tenant_id = t.id
    WHERE $where
    ORDER BY sl.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $links = $stmt->get_result();
    $stmt->close();
} else {
    $links = $conn->query($sql);
}

$allBills = $conn->query("SELECT b.id, b.bill_month, r.room_number, t.name as tenant_name
    FROM bills b
    JOIN contracts c ON b.contract_id = c.id
    JOIN rooms r ON c.room_id = r.id
    JOIN tenants t ON c.tenant_id = t.id
    ORDER BY b.bill_month DESC, r.room_number");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>分享链接管理 - <?php echo h($siteName); ?></title>
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

                <h4 class="mb-4"><i class="bi bi-share"></i> 分享链接管理</h4>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" placeholder="搜索房间号、租客姓名..." value="<?php echo h($_GET['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-dark me-2"><i class="bi bi-search"></i> 搜索</button>
                                <a href="share_links.php" class="btn btn-outline-dark">重置</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 创建分享链接</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="create">
                                    <div class="mb-3">
                                        <label class="form-label">选择账单</label>
                                        <select name="bill_id" class="form-select" required>
                                            <option value="">请选择账单</option>
                                            <?php while ($bill = $allBills->fetch_assoc()): ?>
                                            <option value="<?php echo $bill['id']; ?>"><?php echo h($bill['room_number']); ?> - <?php echo h($bill['tenant_name']); ?> (<?php echo h($bill['bill_month']); ?>)</option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">有效期</label>
                                        <select name="expire_type" class="form-select" id="expireType" onchange="toggleExpireHours()">
                                            <option value="permanent">永久有效</option>
                                            <option value="limited">限时有效</option>
                                        </select>
                                    </div>
                                    <div class="mb-3" id="expireHoursDiv" style="display: none;">
                                        <label class="form-label">有效时间（小时）</label>
                                        <input type="number" name="expire_hours" class="form-control" value="24" min="1">
                                    </div>
                                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-link-45deg"></i> 创建链接</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0">已创建的链接</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($links && $links->num_rows > 0): ?>
                                <div class="row">
                                    <?php while ($link = $links->fetch_assoc()): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 <?php echo !$link['is_active'] ? 'border-secondary' : ''; ?>">
                                            <div class="card-header d-flex justify-content-between align-items-center <?php echo !$link['is_active'] ? 'bg-secondary' : 'bg-dark'; ?> text-white">
                                                <span><i class="bi bi-door-open"></i> <?php echo h($link['room_number']); ?> - <?php echo h($link['tenant_name']); ?></span>
                                                <span class="badge <?php echo $link['is_active'] ? 'bg-success' : 'bg-light text-dark'; ?>"><?php echo $link['is_active'] ? '有效' : '已禁用'; ?></span>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-2"><strong>账单月份：</strong><?php echo h($link['bill_month']); ?></div>
                                                <div class="mb-2">
                                                    <strong>有效期：</strong>
                                                    <?php if ($link['expire_at']): ?>
                                                        <?php $expireTime = strtotime($link['expire_at']); $now = time(); ?>
                                                        <?php if ($expireTime < $now): ?>
                                                            <span class="text-danger">已过期</span>
                                                        <?php else: ?>
                                                            <span><?php echo date('Y-m-d H:i', $expireTime); ?></span>
                                                            <small class="text-muted">(<?php echo ceil(($expireTime - $now) / 3600); ?>小时后)</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-success">永久有效</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-3">
                                                    <form method="POST" class="d-inline">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="update_expire">
                                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                                        <input type="number" name="new_expire_hours" class="form-control form-control-sm d-inline-block" style="width: 80px;" placeholder="小时">
                                                        <button type="submit" class="btn btn-sm btn-outline-dark" title="修改有效期"><i class="bi bi-clock"></i></button>
                                                    </form>
                                                    <button class="btn btn-sm btn-outline-dark" onclick="copyLink('<?php echo h($link['token']); ?>')" title="复制链接"><i class="bi bi-clipboard"></i></button>
                                                    <form method="POST" style="display:inline;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-dark" title="<?php echo $link['is_active'] ? '禁用' : '启用'; ?>">
                                                            <i class="bi bi-<?php echo $link['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display:inline;" data-confirm="确定要删除这个链接吗？">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-muted text-center">暂无分享链接</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    function toggleExpireHours() {
        var type = document.getElementById('expireType').value;
        document.getElementById('expireHoursDiv').style.display = type == 'limited' ? 'block' : 'none';
    }
    function copyLink(token) {
        var path = window.location.pathname;
        var basePath = path.substring(0, path.indexOf('/admin/'));
        var url = window.location.origin + basePath + '/share.php?token=' + token;
        navigator.clipboard.writeText(url).then(function() {
            alert('链接已复制！');
        });
    }
    </script>
    <?php include 'footer.php'; ?>
