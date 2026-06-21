<?php
$unreadMsg = getUnreadMessages();
$roomCount = getCount('rooms');
$tenantCount = getCount('tenants');
$contractCount = getCount('contracts', "status='active'");
$billCount = getCount('bills', "status='unpaid'");
$typeCount = getCount('room_types');
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<ul class="nav flex-column">
    <li class="nav-item mb-2"><a class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" href="index.php"><i class="bi bi-speedometer2"></i> 仪表盘</a></li>
    
    <li class="nav-item mt-3 mb-1"><small class="text-uppercase" style="color: rgba(255,255,255,0.4); padding-left: 20px;">房屋管理</small></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'rooms.php' ? 'active' : ''; ?>" href="rooms.php"><i class="bi bi-door-open"></i> 房间管理 <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo $roomCount; ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'room_types.php' ? 'active' : ''; ?>" href="room_types.php"><i class="bi bi-tag"></i> 房间类型 <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo $typeCount; ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'photos.php' ? 'active' : ''; ?>" href="photos.php"><i class="bi bi-camera"></i> 房间照片</a></li>
    
    <li class="nav-item mt-3 mb-1"><small class="text-uppercase" style="color: rgba(255,255,255,0.4); padding-left: 20px;">租户管理</small></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'tenants.php' ? 'active' : ''; ?>" href="tenants.php"><i class="bi bi-people"></i> 租客管理 <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo $tenantCount; ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'contracts.php' ? 'active' : ''; ?>" href="contracts.php"><i class="bi bi-file-text"></i> 合同管理 <span class="badge" style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo $contractCount; ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'tenant_history.php' ? 'active' : ''; ?>" href="tenant_history.php"><i class="bi bi-clock-history"></i> 历史租户</a></li>
    
    <li class="nav-item mt-3 mb-1"><small class="text-uppercase" style="color: rgba(255,255,255,0.4); padding-left: 20px;">费用管理</small></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'bills.php' ? 'active' : ''; ?>" href="bills.php"><i class="bi bi-receipt"></i> 水电账单 <?php if ($billCount > 0): ?><span class="badge" style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo $billCount; ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'bill_history.php' ? 'active' : ''; ?>" href="bill_history.php"><i class="bi bi-clock-history"></i> 历史账单</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'share_links.php' ? 'active' : ''; ?>" href="share_links.php"><i class="bi bi-share"></i> 分享链接</a></li>
    
    <li class="nav-item mt-3 mb-1"><small class="text-uppercase" style="color: rgba(255,255,255,0.4); padding-left: 20px;">系统管理</small></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'messages.php' ? 'active' : ''; ?>" href="messages.php"><i class="bi bi-chat-dots"></i> 留言管理 <?php if ($unreadMsg > 0): ?><span class="badge" style="background: rgba(255,255,255,0.2); color: #fff;"><?php echo $unreadMsg; ?></span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>" href="settings.php"><i class="bi bi-gear"></i> 系统设置</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'backup.php' ? 'active' : ''; ?>" href="backup.php"><i class="bi bi-database"></i> 数据备份</a></li>
    <li class="nav-item"><a class="nav-link <?php echo $currentPage == 'logs.php' ? 'active' : ''; ?>" href="logs.php"><i class="bi bi-clock-history"></i> 操作日志</a></li>
</ul>
