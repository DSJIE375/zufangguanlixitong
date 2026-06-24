# DSJIE.租房管理系统

一个简洁优雅的房屋出租管理系统，采用Apple简约黑白风格设计，支持PC和移动端自适应。

## ✨ 功能特性

### 前台功能
- 🏠 首页展示（轮播图、楼层展示、优势介绍、租房流程、高德地图导航）
- 📋 房间列表（按楼层/状态筛选）
- 📄 房间详情（支持照片展示）
- 💬 在线留言（支持邮件/微信通知）
- 👤 租客登录（查看账单、合同、历史记录）

### 后台管理
- 📊 仪表盘统计（可视化数据卡片、房态看板）
- 🚪 房间管理（增删改查、搜索、查看详情）
- 📦 批量管理（批量添加/删除/修改房间）
- 👥 租客管理（增删改查、紧急联系人）
- 📝 合同管理（签约/终止、合同模板、签名、上传纸质合同）
- 💰 水电账单（录入、打印、PDF导出、图片保存、分享链接）
- 🔗 分享链接（有效期设置、复制/禁用/删除）
- 💬 留言管理（搜索、未读提醒、通知推送）
- 📜 历史租户（自动记录退租信息）
- 📋 历史账单（删除账单自动归档）
- ⚙️ 系统设置（水电价格、网站信息、地图、通知、修改密码）
- 💾 数据备份（导出/恢复）
- 📝 操作日志（记录所有操作）

### 特色功能
- 📱 响应式设计，手机电脑完美适配
- 📄 账单PDF/图片导出
- 🔗 账单分享链接（支持有效期设置）
- 🖊️ 合同签名（全屏手写签名板）
- 🔔 留言通知（邮件+微信推送）
- 🗺️ 高德地图定位（默认电动车导航）
- 🎨 Apple简约黑白风格UI
- 🔒 安全防护（登录限制、Session超时、SQL注入防护）

## 🛠️ 技术栈

- **后端**：PHP 7.4+
- **数据库**：MySQL 5.7+
- **前端**：Bootstrap 5 + 原生JavaScript
- **UI风格**：Apple简约黑白设计

## 📦 安装部署

### 环境要求
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- 支持伪静态的Web服务器（Apache/Nginx）

### 安装步骤

1. **下载项目**
   ```bash
   git clone https://github.com/DSJIE375/zufangguanlixitong.git
   ```

2. **访问安装页面**
   ```
   http://localhost/项目目录/install.php
   ```

3. **填写安装信息**
   - 数据库连接信息
   - 管理员账号密码
   - 网站名称

4. **完成安装**
   - 访问后台：`http://localhost/项目目录/admin/login.php`
   - 建议删除 `install.php` 文件

## 📁 项目结构

```
dsjie-rental-system/
├── index.php              # 前台首页
├── rooms.php              # 房间列表
├── room_detail.php        # 房间详情
├── bill_view.php          # 公开账单查看
├── tenant_login.php       # 租客登录
├── tenant_bills.php       # 租客账单
├── contract_view.php      # 合同查看
├── tenant_history_detail.php # 历史合同查看
├── bill_history_detail.php   # 历史账单查看
├── share.php              # 分享链接页面
├── submit_message.php     # 留言提交
├── install.php            # 安装向导
├── install.sql            # 数据库初始化
├── favicon.svg            # 网站图标
├── images/logo.svg        # Logo
├── css/style.css          # 样式文件
├── js/main.js             # JavaScript
├── includes/
│   ├── database.php       # 数据库连接+工具函数
│   └── notify.php         # 通知功能
└── admin/
    ├── index.php          # 仪表盘
    ├── login.php          # 登录页
    ├── rooms.php          # 房间管理
    ├── rooms_batch.php    # 批量管理房间
    ├── room_types.php     # 房间类型
    ├── photos.php         # 房间照片
    ├── tenants.php        # 租客管理
    ├── contracts.php      # 合同管理
    ├── contract_template.php # 合同模板
    ├── bills.php          # 水电账单
    ├── bill_print.php     # 账单打印
    ├── share_links.php    # 分享链接管理
    ├── messages.php       # 留言管理
    ├── tenant_history.php # 历史租户
    ├── bill_history.php   # 历史账单
    ├── bill_history_detail.php # 历史账单详情
    ├── settings.php       # 系统设置
    ├── backup.php         # 数据备份
    ├── logs.php           # 操作日志
    ├── change_password.php # 修改密码
    ├── create_share_link.php # 创建分享链接
    ├── upload_contract.php # 上传合同
    ├── save_signature.php # 保存签名
    └── get_last_bill.php  # 获取上月账单
```

## 🔐 安全特性

- 登录失败次数限制（5次/5分钟，IP级别）
- Session超时自动退出（30分钟）
- SQL注入防护（全部使用预处理语句）
- XSS防护（h()函数输出转义）
- CSRF防护（所有POST表单Token验证）
- 密码bcrypt加密存储
- 操作日志记录
- 敏感文件保护（.htaccess禁止访问）
- 备份恢复安全检查

## 📝 更新日志

### v1.3 (2025-06)
- ✅ **安全加固**：全面修复SQL注入、XSS、CSRF漏洞
- ✅ **删除租客**：支持级联删除合同账单并保存历史记录
- ✅ **历史租户**：删除租客自动归档到历史租户页面
- ✅ **甲方签名**：合同支持房东签名保存和显示
- ✅ **分享链接**：修复URL路径问题，支持直接bill_id访问
- ✅ **紧急联系人**：租客卡片显示紧急联系人信息
- ✅ **账单分享**：恢复后台账单分享按钮功能
- ✅ **数据库优化**：所有SQL查询使用预处理语句

### v1.2 (2024-06)
- ✅ 完整的前后台功能
- ✅ 租客登录查看账单/合同
- ✅ 批量管理房间（添加/删除/修改）
- ✅ 合同模板（签名、上传纸质合同）
- ✅ 账单分享链接（有效期管理）
- ✅ 数据备份和恢复
- ✅ 操作日志
- ✅ Apple简约黑白风格UI
- ✅ 高德地图导航
- ✅ 微信邮件通知

## 📄 许可证

MIT License

## 📞 联系方式

- GitHub: [DSJIE375](https://github.com/DSJIE375)
