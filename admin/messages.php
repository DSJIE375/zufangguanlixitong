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

// 删除留言
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM messages WHERE id=$id");
        setFlash('success', '留言已删除');
    }
    if ($_POST['action'] == 'read') {
        $id = intval($_POST['id']);
        $conn->query("UPDATE messages SET is_read=1 WHERE id=$id");
    }
    if ($_POST['action'] == 'delete_all') {
        $conn->query("DELETE FROM messages");
        setFlash('success', '所有留言已清空');
    }
    redirect('messages.php');
}

$messages = $conn->query("SELECT * FROM messages ORDER BY created_at DESC");
$unreadCount = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE is_read=0")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>留言管理 - <?php echo $siteName; ?></title>
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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="bi bi-chat-dots"></i> 留言管理 <?php if ($unreadCount > 0): ?><span class="badge bg-dark"><?php echo $unreadCount; ?> 条未读</span><?php endif; ?></h4>
                    <form method="POST" data-confirm="确定要清空所有留言吗？">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> 清空所有</button>
                    </form>
                </div>

                <?php if ($messages->num_rows > 0): ?>
                <div class="row">
                    <?php while ($m = $messages->fetch_assoc()): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm <?php echo !$m['is_read'] ? 'border-primary' : ''; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center <?php echo !$m['is_read'] ? 'bg-dark bg-opacity-10' : ''; ?>">
                                <div>
                                    <strong><?php echo $m['name']; ?></strong>
                                    <?php if (!$m['is_read']): ?><span class="badge bg-dark ms-2">新</span><?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($m['created_at'])); ?></small>
                            </div>
                            <div class="card-body">
                                <p><i class="bi bi-telephone text-muted"></i> <?php echo $m['phone']; ?></p>
                                <p class="mb-0"><?php echo nl2br($m['content']); ?></p>
                            </div>
                            <div class="card-footer bg-white d-flex justify-content-between">
                                <?php if (!$m['is_read']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="read">
                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i> 标为已读</button>
                                </form>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" data-confirm="确定删除？">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-chat-dots display-1 text-muted"></i>
                    <p class="mt-3 text-muted">暂无留言</p>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
<?php include 'footer.php'; ?>
