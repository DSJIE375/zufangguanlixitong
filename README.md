# DSJIE.租房管理系统

一个简洁优雅的房屋出租管理系统，采用Apple简约黑白风格设计，支持PC和移动端自适应。

## ✨ 功能特性

### 前台功能
- 🏠 首页展示（轮播图、楼层展示、优势介绍、租房流程）
- 📋 房间列表（按楼层/状态筛选）
- 📄 房间详情（支持照片展示）
- 💬 在线留言（支持邮件/微信通知）
- 🗺️ 高德地图定位

### 后台管理
- 📊 仪表盘统计（可视化数据卡片）
- 🚪 房间管理（增删改查、搜索筛选）
- 👥 租客管理（增删改查）
- 📝 合同管理（签约/终止、欠费保护）
- 💰 水电账单（录入、打印、PDF导出、图片保存）
- 📸 房间照片（按类型上传管理）
- 💬 留言管理（未读提醒、通知推送）
- 📜 历史租户（自动记录退租信息）
- ⚙️ 系统设置（水电价格、网站信息、地图、通知）

### 特色功能
- 📱 响应式设计，手机电脑完美适配
- 📄 账单PDF/图片导出
- 🔗 账单分享链接（租客无需登录即可查看）
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
   git clone https://github.com/DSJIE/dsjie-rental-system.git
   ```

2. **配置数据库**
   - 创建MySQL数据库
   - 导入 `install.sql` 文件

3. **访问安装页面**
   ```
   http://localhost/项目目录/install.php
   ```

4. **填写安装信息**
   - 数据库连接信息
   - 管理员账号密码
   - 网站名称

5. **完成安装**
   - 访问后台：`http://localhost/项目目录/admin/login.php`
   - 建议删除 `install.php` 文件

## 📁 项目结构

```
dsjie-rental-system/
├── index.php              # 前台首页
├── rooms.php              # 房间列表
├── room_detail.php        # 房间详情
├── bill_view.php          # 公开账单查看
├── submit_message.php     # 留言提交
├── config.php             # 数据库配置
├── install.php            # 安装向导
├── install.sql            # 数据库初始化
├── css/style.css          # 样式文件
├── js/main.js             # JavaScript
├── includes/
│   ├── database.php       # 数据库连接
│   └── notify.php         # 通知功能
├── admin/
│   ├── index.php          # 仪表盘
│   ├── login.php          # 登录页
│   ├── rooms.php          # 房间管理
│   ├── room_types.php     # 房间类型
│   ├── photos.php         # 房间照片
│   ├── tenants.php        # 租客管理
│   ├── contracts.php      # 合同管理
│   ├── bills.php          # 水电账单
│   ├── bill_print.php     # 账单打印
│   ├── messages.php       # 留言管理
│   ├── tenant_history.php # 历史租户
│   ├── settings.php       # 系统设置
│   └── footer.php         # 公共底部
└── uploads/               # 上传文件
```

## 🔐 安全特性

- 登录失败次数限制（5次/5分钟）
- Session超时自动退出（30分钟）
- SQL注入防护（参数过滤）
- XSS防护（HTML转码）
- 密码bcrypt加密存储

## 📝 更新日志

### v1.0 (2024-06)
- ✅ 完成基础功能开发
- ✅ 前台页面展示
- ✅ 后台管理系统
- ✅ 水电账单管理
- ✅ 账单PDF/图片导出
- ✅ 账单分享功能
- ✅ 留言通知功能
- ✅ Apple简约黑白风格UI

## 📄 许可证

MIT License

## 📞 联系方式

- GitHub: [DSJIE](https://github.com/DSJIE)
