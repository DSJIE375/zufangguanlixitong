# DSJIE.租房管理系统

一个简洁优雅的房屋出租管理系统，采用Apple简约黑白风格设计，支持PC和移动端自适应。

## ✨ 功能特性

### 前台功能
- 🏠 首页展示（轮播图、楼层展示、优势介绍、租房流程）
- 📋 房间列表（按楼层/状态筛选）
- 📄 房间详情（支持照片展示）
- 💬 在线留言（支持邮件/微信通知）
- 🗺️ 高德地图定位
- 👤 租客登录（查看账单、合同）

### 后台管理
- 📊 仪表盘统计（可视化数据卡片，可点击跳转）
- 🚪 房间管理（增删改查、搜索筛选）
- 👥 租客管理（增删改查）
- 📝 合同管理（签约/终止、合同模板、签名、上传纸质合同）
- 💰 水电账单（录入、打印、PDF导出、图片保存）
- 🔗 分享链接（设置有效期、复制链接）
- 💬 留言管理（未读提醒、通知推送）
- 📜 历史租户（自动记录退租信息）
- 📋 历史账单（删除账单自动归档）
- ⚙️ 系统设置（水电价格、网站信息、地图、通知）
- 🔐 修改密码
- 💾 数据备份（导出/恢复）
- 📝 操作日志（记录所有操作）

### 租客端
- 👤 租客登录（姓名+电话）
- 📄 查看当前合同和账单
- 📜 查看历史租房记录
- 📋 查看历史账单

### 特色功能
- 📱 响应式设计，手机电脑完美适配
- 📄 账单PDF/图片导出
- 🔗 账单分享链接（支持有效期设置）
- 🔔 留言通知（邮件+微信推送）
- 🗺️ 高德地图定位
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
├── config.php             # 数据库配置
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
    ├── logout.php         # 退出登录
    ├── sidebar.php        # 侧边栏
    ├── footer.php         # 底部脚本
    ├── rooms.php          # 房间管理
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
    └── create_share_link.php # 创建分享链接
```

## 🔐 安全特性

- 登录失败次数限制（5次/5分钟）
- Session超时自动退出（30分钟）
- SQL注入防护（参数过滤）
- XSS防护（HTML转码）
- 密码bcrypt加密存储
- 操作日志记录

## 📝 更新日志

### v1.0 (2024-06)
- ✅ 完成基础功能开发
- ✅ 前台页面展示
- ✅ 后台管理系统
- ✅ 水电账单管理
- ✅ 账单PDF/图片导出
- ✅ 账单分享功能（支持有效期）
- ✅ 留言通知功能
- ✅ 租客登录查看账单
- ✅ 合同模板（支持签名和上传）
- ✅ 历史租户和历史账单
- ✅ 数据备份和恢复
- ✅ 操作日志记录
- ✅ Apple简约黑白风格UI

## 📄 许可证

MIT License

## 📞 联系方式

- GitHub: [DSJIE375](https://github.com/DSJIE375)
