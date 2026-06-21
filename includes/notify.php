<?php
// 通知功能

// 获取设置值
function getNotifySetting($key) {
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    return '';
}

// 发送邮件通知
function sendEmailNotify($subject, $message) {
    $email = getNotifySetting('notify_email');
    if (empty($email)) return false;
    
    $siteName = getNotifySetting('site_name') ?: 'DSJIE.租房管理系统';
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: $siteName <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    
    $body = "<html><body>";
    $body .= "<h2 style='color:#333;'>$siteName - 新留言通知</h2>";
    $body .= "<p>$message</p>";
    $body .= "<p style='color:#888;font-size:12px;'>此邮件由系统自动发送</p>";
    $body .= "</body></html>";
    
    return mail($email, $subject, $body, $headers);
}

// 发送微信通知（Server酱）
function sendWechatNotify($title, $message) {
    $key = getNotifySetting('notify_wechat_key');
    if (empty($key)) return false;
    
    $url = "https://sctapi.ftqq.com/{$key}.send";
    $data = [
        'title' => $title,
        'desp' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// 发送留言通知
function notifyNewMessage($name, $phone, $content) {
    $siteName = getNotifySetting('site_name') ?: 'DSJIE.租房管理系统';
    $enableEmail = getNotifySetting('notify_email_enable');
    $enableWechat = getNotifySetting('notify_wechat_enable');
    
    $title = "[$siteName] 新留言通知";
    $message = "**新留言**\n\n";
    $message .= "- 姓名：$name\n";
    $message .= "- 电话：$phone\n";
    $message .= "- 内容：$content\n";
    $message .= "- 时间：" . date('Y-m-d H:i:s') . "\n";
    $message .= "\n请及时回复处理。";
    
    $htmlMessage = "<h3>新留言</h3>";
    $htmlMessage .= "<table style='border-collapse:collapse;width:100%;'>";
    $htmlMessage .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;width:80px;'><strong>姓名</strong></td><td style='padding:8px;border:1px solid #ddd;'>$name</td></tr>";
    $htmlMessage .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>电话</strong></td><td style='padding:8px;border:1px solid #ddd;'>$phone</td></tr>";
    $htmlMessage .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>内容</strong></td><td style='padding:8px;border:1px solid #ddd;'>$content</td></tr>";
    $htmlMessage .= "<tr><td style='padding:8px;border:1px solid #ddd;background:#f5f5f5;'><strong>时间</strong></td><td style='padding:8px;border:1px solid #ddd;'>" . date('Y-m-d H:i:s') . "</td></tr>";
    $htmlMessage .= "</table>";
    
    $results = [];
    
    if ($enableEmail == '1') {
        $results['email'] = sendEmailNotify($title, $htmlMessage);
    }
    
    if ($enableWechat == '1') {
        $results['wechat'] = sendWechatNotify($title, $message);
    }
    
    return $results;
}
