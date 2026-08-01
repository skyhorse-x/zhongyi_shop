# Nginx 伪静态规则说明

> 本项目为 Laravel 后端 + Vue 3 前端单域名部署。本文档给出 Nginx 的 `location` 伪静态规则，使用 `alias` 直读前端构建目录，前后端完全解耦。
> 适用场景：Laravel public 在 `public/`，前端构建产物在 `web/dist/`，**不需要**把 dist 拷贝到 public。

---

## 一、部署目录约定

```bash
/www/wwwroot/www.qilewl.net/
├── public/                  # Laravel public（含 index.php、.htaccess）
└── web/
    └── dist/                # 前端构建产物（含 index.html、assets/、favicon.svg）
```

- `root` 指向 Laravel 的 `public/`（用于 `/api`、`/storage`、PHP 处理）
- 前端目录通过 `alias` 在具体 `location` 中指定，**零拷贝**

---

## 二、location 块

> 前端目录 = `/www/wwwroot/www.qilewl.net/web/dist/`
> 把下面所有块放进你的 `server { ... }` 内，`alias` 路径按实际情况改。

```nginx
# ===== 1. 后端 API：走 PHP =====
location /api {
    try_files $uri $uri/ /index.php?$query_string;
}


# ===== 2. 存储文件：直出 =====
location /storage {
    try_files $uri $uri/ /index.php?$query_string;
    # 如果 storage 是符号链接到 storage/app/public，需要开启
    disable_symlinks off;
}


# ===== 3. Laravel 自身资源（罕见但保留） =====
location = /favicon.ico { access_log off; log_not_found off; }
location = /robots.txt  { access_log off; log_not_found off; }


# ===== 4. 前端静态资源：直出，带缓存 =====
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    alias /www/wwwroot/www.qilewl.net/web/dist/;
    expires 30d;
    add_header Cache-Control "public, max-age=2592000";
    access_log off;
    try_files $uri =404;
}


# ===== 5. 前端 SPA：History 模式 fallback（关键） =====
# 除上面四类外的所有请求，都回退到 index.html
location / {
    alias /www/wwwroot/www.qilewl.net/web/dist/;
    try_files $uri $uri/ /index.html;
}
```

---

## 三、关键点解释

### ① 为什么要 `^~` 修饰 `/api` 和 `/storage`

Nginx 匹配顺序：**最长前缀 → 正则（前缀没 `^~` 时按顺序匹配）→ 回退到前缀**。

不加 `^~` 的隐患：

```
请求 /api/v1/users.js

1. 最长前缀匹配 → /api
2. 继续检查正则 → ~* \.(js|css|...)$ 命中！
3. 正则赢了 → 被当成"前端静态资源"，从 web_dist 取，结果 404
```

加 `^~` 后：

```
请求 /api/v1/users.js

1. 最长前缀匹配 → /api（带 ^~）
2. ^~ 告诉 nginx：不用再检查正则了，直接用我
3. 进入 /api 的 try_files：/api/v1/users.js 不存在 → 回退到 /index.php
```

### ② `alias` 路径必须以 `/` 结尾

```nginx
set $web_dist /www/wwwroot/www.qilewl.net/web/dist/;   # ✅ 正确
set $web_dist /www/wwwroot/www.qilewl.net/web/dist;    # ❌ 会导致 404
```

### ③ 正则 location 里的 `alias`

`location ~* \.(js|css|...)$ { alias /path/; }`：
- 匹配整段 URI（不是前缀替换）
- nginx 把"完整 URI"拼到 alias 后面
- 所以 `alias /path/` + 请求 `/assets/main.js` → 文件 `/path/assets/main.js` ✓

### ④ SPA fallback 工作流程

Vue Router 使用 `createWebHistory()`（无 `#`），刷新 `/admin/users` 时浏览器会请求该路径，但服务器没有该文件。

```
location / 内：
1. try_files $uri             → 有静态文件就返回（如不存在于 dist 的）
2. try_files $uri/            → 有目录就返回索引
3. try_files ... /index.html   → 没有就回退到 dist/index.html
   （Vue Router 在客户端解析 URL）
```

---

## 四、验证步骤

```bash
# 1. 构建前端（不用拷贝，alias 直读）
cd /www/wwwroot/www.qilewl.net/web && npm run build

# 2. 检查配置语法
nginx -t

# 3. 重新加载
nginx -s reload

# 4. 验证 API
curl -I https://www.qilewl.net/api/v1/auth/login
# 期望：200 / 405（路由存在），不是 404

# 5. 验证前端入口
curl -I https://www.qilewl.net/
# 期望：200，Content-Type: text/html

# 6. 验证前端静态资源（带缓存头）
curl -I https://www.qilewl.net/assets/xxxx.js
# 期望：200，Cache-Control: max-age=2592000

# 7. 验证 SPA fallback
curl -I https://www.qilewl.net/admin/users
# 期望：200，Content-Type: text/html（不是 404）
```

---

## 五、路径归属速查

| 路径 | 归属 | 命中 location | 文件来源 |
|------|------|---------------|----------|
| `/api/*` | Laravel | `location /api` | `public/index.php` |
| `/storage/*` | Laravel | `location /storage` | `public/index.php` → `storage/app/public` |
| `/favicon.ico`、`/robots.txt` | 静态 | `location =` | `public/`（Laravel 自带） |
| `/assets/*`、`*.js`、`*.css`、`*.png` … | 前端 | `location ~* \.(...)$` | `web/dist/` via `alias` |
| `/`、`/index.html`、`/admin/*`、`/analysis/*`、`/qa/*`、`/member/*` … | Vue SPA | `location /` | `web/dist/index.html` via `alias` |

---

## 六、宝塔面板操作提示

宝塔用户可以直接把第二节的 location 块粘到站点"配置文件"→`server { ... }` 内任意位置（一般放在 `include` 之前），注意事项：

1. **确认已有 `root`**：保证 `server` 块里有 `root /www/wwwroot/www.qilewl.net/public;`。
2. **修改 `alias` 路径**：把第 4、5 块里的 `/www/wwwroot/www.qilewl.net/web/dist/` 改成你实际的 dist 路径，**必须以 `/` 结尾**。
3. **PHP 处理**：确保 `server` 块里已有 `location ~ \.php$ { ... fastcgi_pass ...; }`。
4. **配置 HTTPS**：在 `listen 80;` 后加一行 `listen 443 ssl;` + `ssl_certificate` / `ssl_certificate_key`，并把 80 端口重定向到 443。
