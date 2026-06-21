<?php
require_once 'includes/database.php';

function getSetting($key) {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
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
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitize($_POST['phone'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    
    if ($phone && $name) {
        // 查找租客（包括已退租但一个月内的）
        $tenant = $conn->query("SELECT t.*,
            (SELECT COUNT(*) FROM contracts c WHERE c.tenant_id = t.id AND c.status = 'active') as active_contracts,
            (SELECT COUNT(*) FROM tenant_history th WHERE th.tenant_name = t.name AND th.tenant_phone = t.phone) as history_count,
            (SELECT DATEDIFF(NOW(), MAX(th.end_date)) FROM tenant_history th WHERE th.tenant_name = t.name AND th.tenant_phone = t.phone) as days_after_checkout
            FROM tenants t
            WHERE t.phone = '$phone' AND t.name = '$name'
            LIMIT 1")->fetch_assoc();
        
        if ($tenant) {
            // 检查是否可以登录
            $isRecentlyActive = $tenant['active_contracts'] > 0;
            $hasHistory = $tenant['history_count'] > 0;
            $isWithinMonth = ($tenant['days_after_checkout'] !== null && $tenant['days_after_checkout'] <= 30);
            
            if ($isRecentlyActive || ($hasHistory && $isWithinMonth)) {
                $_SESSION['tenant_id'] = $tenant['id'];
                $_SESSION['tenant_name'] = $tenant['name'];
                header("Location: tenant_bills.php");
                exit;
            } else {
                $error = '您的租房信息已超过一个月，如需查看请联系房东';
            }
        } else {
            $error = '未找到您的租房信息，请检查姓名和电话是否正确';
        }
    } else {
        $error = '请填写姓名和电话';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>租客登录 - <?php echo $siteName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f7; min-height: 100vh; display: flex; align-items: center; font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "Helvetica Neue", Arial, sans-serif; }
        .login-card { max-width: 400px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .login-header { background: #1d1d1f; color: white; padding: 40px 30px; text-align: center; }
        .login-body { padding: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <img src="images/logo.svg" alt="Logo" height="48" style="margin-bottom: 15px;">
                <h3>租客登录</h3>
                <small style="color: #86868b;">查看您的账单信息</small>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">姓名</label>
                        <input type="text" name="name" class="form-control" required placeholder="请输入租房时登记的姓名">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" style="font-weight: 600;">电话</label>
                        <input type="tel" name="phone" class="form-control" required placeholder="请输入租房时登记的电话">
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2" style="font-weight: 600;">登录查看账单</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> 返回首页
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
