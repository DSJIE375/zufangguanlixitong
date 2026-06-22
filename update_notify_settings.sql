-- 添加通知设置
INSERT INTO settings (setting_key, setting_value, description) VALUES
('notify_email', '', '通知接收邮箱'),
('notify_email_enable', '0', '是否开启邮件通知'),
('notify_wechat_key', '', 'Server酱SendKey'),
('notify_wechat_enable', '0', '是否开启微信通知'),
('auto_bill_day', '1', '自动生成账单日期');
