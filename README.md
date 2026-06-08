# HowToCookViewer - PHP Edition

这是一个基于 PHP + SQLite3 开发的 **HowToCook** 菜谱浏览器，提供了优雅的网页版界面、强大的查询工具以及全套的用户功能。

---

## ✨ 主要功能

1. **🍽️ 智能菜谱浏览**
   - 快速索引全部 **开源菜谱**。
   - 分类过滤（肉类、蔬菜、主食、水产等）与星级难度筛选。
2. **🔍 多词精准检索**
   - 搜索框支持以空格、中英文逗号分隔的多关键词混合搜索，实现多词联合匹配。
3. **🔍 食材反查工具 (Ingredients Reverse Lookup)**
   - 动态解析所有菜谱中的食材成分，支持按字筛选食材标签。
   - 点击食材标签组合即可通过 AJAX 异步渲染匹配的菜谱列表。
4. **💡 烹饪技巧指南**
   - 自动同步同步仓库中的基础准备、新手入门与高级进阶教程。
5. **🔥 卡路里科学分级**
   - 内置可视化卡路里“温度计”小组件：
     - **低热量** (`<= 300 kcal`，绿色)
     - **中热量** (`300 - 600 kcal`，橙色)
     - **高热量** (`> 600 kcal`，红色)
6. **📶 即时状态排序**
   - 支持“默认”、“按点赞”、“按难度”、“按卡路里”的快速按钮排序，点击可在升序与降序之间直接循环切换。
7. **👤 完备的用户系统**
   - 用户注册、登录、收藏菜谱、评论互动等。
   - 管理员后台一键触发同步与重新索引。
8. **📱 全设备响应式设计**
   - 支持移动端和桌面端。移动端折叠菜单使用半透明毛玻璃特效。

---

## 📂 目录结构

```text
php/
├── public/           # Web 访问根目录 (Document Root)
│   ├── index.php     # 统一入口 (Front Controller)
│   ├── router.php    # 本地内置开发服务器路由
│   ├── css/          # 样式表 (Bootstrap + site.css)
│   └── js/           # 脚本文档 (site.js)
├── views/            # 各个页面的 PHP 模板视图
├── data/             # 存放 SQLite 数据库及同步的菜谱源文件
├── helpers.php       # 全局安全编码与基础辅助函数
├── db.php            # 数据库配置与迁移表结构
├── auth.php          # 用户会话与认证安全模块
└── sync.php          # 菜谱及技巧解析同步爬虫
├── run.bat           # Windows 本地开发环境一键启动脚本
└── stop.bat          # Windows 一键停止服务器脚本
```

---

## 🚀 快速开始

### 1. 环境准备
确保您的电脑上已安装并配置了 **PHP (>= 7.4)** 以及 **SQLite3** 模块。

### 2. 本地一键启动 (Windows)
1. 双击运行 `run.bat`。
2. 脚本会自动检测数据库是否存在。如果不存在，会自动从开源仓库克隆并解析同步最新的菜谱数据。
3. 同步完成后，会自动在本地启动开发服务器并监听 **`http://localhost:8080`**。
4. 用浏览器打开即可访问使用。
5. 双击运行 `stop.bat` 即可一键安全停止本地的 PHP 服务进程。

### 3. 服务器部署指南（Apache / Nginx / 宝塔面板）

本项目已提供完整的 Apache 和 Nginx 配置文件，根据您的服务器类型按需选用即可。

---

#### 🔵 Apache 虚拟主机（推荐绑定 `/public` 为运行目录）

**最安全方式**：在控制面板或 `httpd-vhosts.conf` 中将 `DocumentRoot` 指向项目内的 `public/` 子目录：

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/HowToCookViewer/public

    <Directory /var/www/HowToCookViewer/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

#### 🔵 Apache 虚拟主机（直接上传到根目录 · 备选方案）

如果无法修改运行目录，将所有文件上传到 `public_html/`，并在 **项目根目录**（与 `public/` 同级）放置项目自带的 `.htaccess` 文件，无需额外配置，访问域名即可：

```apache
# 已内置于 .htaccess，直接上传使用即可
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^public/ - [L]
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

> ⚠️ **安全提示**：`data/` 目录内已内置 `.htaccess` 文件（`Deny from all`），可防止外网直接下载数据库文件，确保该文件一并上传。

---

#### 🟢 Nginx 虚拟主机（推荐绑定 `/public` 为 Web Root）

使用项目自带的 `nginx.conf` 文件（内含两套配置模板），**模板 A** 适用于将 Web 根目录绑定到 `public/` 的情况：

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/HowToCookViewer/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问敏感目录
    location ~ /\.ht { deny all; }
    location ~ ^/data/ { deny all; }
}
```

---

#### 🟢 Nginx 虚拟主机（项目放在根目录 · 备选方案）

如 Web Root 指向项目根目录（`/var/www/HowToCookViewer/`），使用 `nginx.conf` 中的**模板 B**：

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/HowToCookViewer;
    index public/index.php;

    # 🔒 关键：禁止访问数据库
    location ~ ^/data/ { deny all; }

    # 静态资源透明转发到 public/
    location ~ ^/(css|js|recipe-images)/ {
        rewrite ^/(.*)$ /public/$1 last;
    }

    # 路由转发给 public/index.php
    location / {
        try_files $uri $uri/ /public/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

> 📝 完整配置文件位于项目根目录的 `nginx.conf`，请将 `server_name`、`root`、`fastcgi_pass` 路径替换为您的实际环境。

---

#### 🟡 宝塔面板（BT Panel）一键部署

1. 新建网站，填写域名，PHP 版本选 **7.4 或以上**。
2. **将网站根目录的"运行目录"修改为 `/public`**（网站设置 → 网站目录 → 运行目录）。
3. 上传所有项目文件到网站根目录。
4. 确认 `data/` 目录有**写权限（755）**。
5. 在浏览器打开域名，用管理员账号登录后台 `/admin`，点击"同步菜谱"完成初始化。

> 💡 **数据同步提示**：首次部署完成后，使用默认管理员账号登录后台 `/admin`，点击"同步菜谱"和"同步技巧"即可自动克隆并初始化数据。

---

## 📝 默认管理员账户
在首次运行并初始化数据库后，系统会自动创建一个默认的管理员账号以便登录后台，账号密码可在后台进行修改：
*   **邮箱**：`admin@default.com`
*   **密码**：`Admin@123456!`

---

## 🔒 安全性设计

*   **公有目录隔离**：通过设置 `public/` 为 Web 根目录，所有的敏感 PHP 代码文件及 `howtocook.db` 数据库均存放在外层，杜绝了直接被恶意下载的安全风险。
*   **输入防 XSS 注入**：所有用户输入在页面渲染时全部通过 `e()` 助手函数进行转义过滤。
*   **安全 HTTP 头**：每个响应均默认发送 `Content-Security-Policy`、`X-Frame-Options`、`X-Content-Type-Options` 等硬化安全响应头。
*   **防 CSRF 攻击**：所有修改数据状态的 POST 操作均需经过 CSRF Token 强制验证。
*   **密码哈希加密**：用户密码使用 `bcrypt` 算法加密后安全存储。


