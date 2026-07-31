# DevOps

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第十三阶段（DevOps）

---

## 1. Docker配置

### 1.1 Dockerfile（PHP）

```dockerfile
# docker/php/Dockerfile
FROM php:8.4-fpm-alpine

# 安装依赖
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libzip-dev \
    zip \
    unzip

# 安装PHP扩展
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    bcmath \
    opcache

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 配置OPcache
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# 设置工作目录
WORKDIR /var/www/api

# 复制代码
COPY api/ .

# 安装依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /var/www/api \
    && chmod -R 775 /var/www/api/storage

EXPOSE 9000

CMD ["php-fpm"]
```

### 1.2 Nginx配置

```nginx
# docker/nginx/api.conf
server {
    listen 80;
    server_name api.tcm-health.com;
    root /var/www/api/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
    }
}
```

### 1.3 Docker Compose

```yaml
# docker-compose.yml
version: '3.8'

services:
    nginx:
        image: nginx:1.24-alpine
        ports:
            - "80:80"
            - "443:443"
        volumes:
            - ./docker/nginx/conf.d:/etc/nginx/conf.d
            - ./docker/nginx/ssl:/etc/nginx/ssl
            - ./h5/dist:/usr/share/nginx/html/h5
            - ./admin/dist:/usr/share/nginx/html/admin
        depends_on:
            - php
        restart: always

    php:
        build:
            context: .
            dockerfile: docker/php/Dockerfile
        volumes:
            - ./api:/var/www/api
        environment:
            - APP_ENV=production
            - APP_DEBUG=false
            - DB_HOST=mysql
            - REDIS_HOST=redis
            - QUEUE_CONNECTION=rabbitmq
        depends_on:
            - mysql
            - redis
            - rabbitmq
        restart: always

    mysql:
        image: mysql:8.0
        environment:
            MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
            MYSQL_DATABASE: tcm_health
        volumes:
            - mysql_data:/var/lib/mysql
            - ./docker/mysql/conf.d:/etc/mysql/conf.d
        ports:
            - "3306:3306"
        restart: always

    redis:
        image: redis:7.0-alpine
        command: redis-server --requirepass ${REDIS_PASSWORD} --appendonly yes
        volumes:
            - redis_data:/data
        ports:
            - "6379:6379"
        restart: always

    rabbitmq:
        image: rabbitmq:3.12-management-alpine
        environment:
            RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER}
            RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD}
        volumes:
            - rabbitmq_data:/var/lib/rabbitmq
        ports:
            - "5672:5672"
            - "15672:15672"
        restart: always

    supervisor:
        build:
            context: .
            dockerfile: docker/supervisor/Dockerfile
        volumes:
            - ./api:/var/www/api
        depends_on:
            - rabbitmq
            - redis
        restart: always

volumes:
    mysql_data:
    redis_data:
    rabbitmq_data:
```

### 1.4 Supervisor配置

```ini
# docker/supervisor/supervisord.conf
[supervisord]
nodaemon=true
user=root

[program:analysis-worker]
command=php /var/www/api/artisan queue:work redis --queue=analysis --sleep=3 --tries=3 --max-time=3600
numprocs=5
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/analysis-worker.log

[program:payment-worker]
command=php /var/www/api/artisan queue:work redis --queue=payment --sleep=3 --tries=3 --max-time=3600
numprocs=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/payment-worker.log

[program:sms-worker]
command=php /var/www/api/artisan queue:work redis --queue=sms --sleep=3 --tries=3 --max-time=3600
numprocs=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sms-worker.log
```

---

## 2. CI/CD流程

### 2.1 流程概述

```
代码提交 → GitLab → Jenkins → 构建 → 测试 → 部署
    │
    ├── develop分支 → 开发环境（自动）
    ├── staging分支 → 测试环境（自动）
    └── main分支 → 生产环境（手动审批）
```

### 2.2 Jenkinsfile

```groovy
// Jenkinsfile
pipeline {
    agent any

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                sh 'cd api && composer install --no-dev'
                sh 'cd h5 && npm ci'
                sh 'cd admin && npm ci'
            }
        }

        stage('Run Tests') {
            parallel {
                stage('Backend Tests') {
                    steps {
                        sh 'cd api && ./vendor/bin/phpunit --coverage-text'
                    }
                }
                stage('Frontend Build') {
                    steps {
                        sh 'cd h5 && npm run build'
                        sh 'cd admin && npm run build'
                    }
                }
            }
        }

        stage('Build Docker') {
            steps {
                sh 'docker-compose build'
            }
        }

        stage('Deploy') {
            when {
                branch 'main'
            }
            steps {
                input message: '确认部署到生产环境？', ok: '确认'
                sh 'docker-compose up -d'
                sh 'docker-compose exec -t php php artisan migrate --force'
            }
        }
    }

    post {
        success {
            emailext subject: '部署成功', body: '项目部署成功'
        }
        failure {
            emailext subject: '部署失败', body: '项目部署失败，请检查'
        }
    }
}
```

---

## 3. 环境配置

### 3.1 环境变量

```bash
# .env.production
APP_NAME=TCM-AI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tcm-health.com

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tcm_health
DB_USERNAME=tcm_user
DB_PASSWORD=your_secure_password

REDIS_HOST=redis
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=admin
RABBITMQ_PASSWORD=your_rabbitmq_password

COS_KEY=your_cos_key
COS_SECRET=your_cos_secret
COS_REGION=ap-guangzhou
COS_BUCKET=tcm-health-prod

WECHAT_APPID=your_wechat_appid
WECHAT_SECRET=your_wechat_secret
WECHAT_MCHID=your_mch_id
WECHAT_KEY=your_wechat_key

JWT_SECRET=your_jwt_secret
```

### 3.2 环境对照表

| 环境 | 域名 | 数据库 | 用途 |
|------|------|--------|------|
| 开发环境 | dev.api.tcm-health.com | tcm_dev | 开发自测 |
| 测试环境 | test.api.tcm-health.com | tcm_test | 功能测试 |
| 预发布环境 | staging.api.tcm-health.com | tcm_staging | 验收测试 |
| 生产环境 | api.tcm-health.com | tcm_prod | 正式上线 |

---

## 4. 监控与告警

### 4.1 监控工具

| 工具 | 用途 | 部署方式 |
|------|------|---------|
| Prometheus | 指标采集 | Docker |
| Grafana | 可视化面板 | Docker |
| Sentry | 错误追踪 | SaaS |
| ELK | 日志分析 | Docker |

### 4.2 监控指标

| 指标 | 告警阈值 | 告警方式 |
|------|---------|---------|
| CPU使用率 | > 80% | 邮件+短信 |
| 内存使用率 | > 80% | 邮件+短信 |
| 磁盘使用率 | > 85% | 邮件+短信 |
| 接口响应时间 | > 1s | 邮件 |
| 错误率 | > 1% | 邮件+短信 |
| MySQL慢查询 | > 1s | 邮件 |
| Redis内存 | > 80% | 邮件 |
| RabbitMQ队列长度 | > 1000 | 邮件+短信 |

### 4.3 Prometheus配置

```yaml
# docker/prometheus/prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'laravel'
    static_configs:
      - targets: ['php:9000']
    metrics_path: /metrics

  - job_name: 'mysql'
    static_configs:
      - targets: ['mysqld-exporter:9104']

  - job_name: 'redis'
    static_configs:
      - targets: ['redis-exporter:9121']

  - job_name: 'nginx'
    static_configs:
      - targets: ['nginx-exporter:9113']
```

---

## 5. 日志管理

### 5.1 日志分类

| 日志类型 | 存储位置 | 保留时间 |
|---------|---------|---------|
| 应用日志 | storage/logs/laravel.log | 30天 |
| Nginx日志 | /var/log/nginx/ | 30天 |
| MySQL慢日志 | /var/log/mysql/ | 7天 |
| 队列日志 | /var/log/supervisor/ | 14天 |

### 5.2 日志格式

```json
{
    "timestamp": "2026-07-28T10:00:00+08:00",
    "level": "info",
    "message": "用户登录成功",
    "context": {
        "user_id": 10001,
        "mobile": "138****5678",
        "ip": "192.168.1.1"
    },
    "request_id": "req_abc123"
}
```

---

## 6. 备份恢复

### 6.1 备份策略

| 数据 | 备份方式 | 频率 | 保留时间 |
|------|---------|------|---------|
| MySQL | 全量+增量 | 每天全量，每小时增量 | 30天 |
| Redis | RDB | 每小时 | 7天 |
| 文件 | 跨区域复制 | 实时 | 永久 |
| 配置 | Git提交 | 每次变更 | 永久 |

### 6.2 MySQL备份脚本

```bash
#!/bin/bash
# scripts/backup-mysql.sh

BACKUP_DIR="/backup/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="tcm_health"
DB_USER="root"
DB_PASS="your_password"

# 全量备份
mysqldump -u${DB_USER} -p${DB_PASS} \
    --single-transaction \
    --routines \
    --triggers \
    ${DB_NAME} | gzip > ${BACKUP_DIR}/full_${DATE}.sql.gz

# 删除7天前的备份
find ${BACKUP_DIR} -name "*.sql.gz" -mtime +7 -delete

echo "备份完成: ${BACKUP_DIR}/full_${DATE}.sql.gz"
```

### 6.3 恢复流程

```bash
# MySQL恢复
gunzip < full_20260728_020000.sql.gz | mysql -u root -p tcm_health

# 文件恢复（从COS下载）
aws s3 sync s3://tcm-health-backup/images/ /data/images/
```

---

## 7. 部署流程

### 7.1 首次部署

```bash
# 1. 克隆代码
git clone git@gitlab.com:tcm-health/api.git
cd api

# 2. 配置环境变量
cp .env.example .env.production
vim .env.production

# 3. 启动Docker
docker-compose up -d

# 4. 执行迁移
docker-compose exec php php artisan migrate --force

# 5. 执行种子
docker-compose exec php php artisan db:seed --force

# 6. 生成密钥
docker-compose exec php php artisan key:generate

# 7. 生成JWT密钥
docker-compose exec php php artisan jwt:secret

# 8. 清除缓存
docker-compose exec php php artisan config:cache
docker-compose exec php php artisan route:cache
```

### 7.2 日常部署

```bash
# 1. 拉取最新代码
git pull origin main

# 2. 构建镜像
docker-compose build

# 3. 重启服务
docker-compose up -d

# 4. 执行迁移
docker-compose exec php php artisan migrate --force

# 5. 清除缓存
docker-compose exec php php artisan config:cache
```

### 7.3 回滚流程

```bash
# 1. 回退代码
git revert HEAD

# 2. 重新构建
docker-compose build

# 3. 重启服务
docker-compose up -d

# 4. 回退迁移（如有）
docker-compose exec php php artisan migrate:rollback
```

---

## 8. 高可用方案

### 8.1 架构设计

```
                        ┌─────────────┐
                        │    CDN      │
                        └──────┬──────┘
                               │
                        ┌──────┴──────┐
                        │   SLB       │
                        │ (负载均衡)   │
                        └──────┬──────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
        ┌─────┴─────┐    ┌─────┴─────┐    ┌─────┴─────┐
        │  Nginx 1  │    │  Nginx 2  │    │  Nginx 3  │
        └─────┬─────┘    └─────┬─────┘    └─────┬─────┘
              │                │                │
              └────────────────┼────────────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
        ┌─────┴─────┐    ┌─────┴─────┐    ┌─────┴─────┐
        │  PHP 1    │    │  PHP 2    │    │  PHP 3    │
        └─────┬─────┘    └─────┬─────┘    └─────┬─────┘
              │                │                │
              └────────────────┼────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
  ┌─────┴─────┐          ┌─────┴─────┐          ┌─────┴─────┐
  │   MySQL   │          │   Redis   │          │  RabbitMQ │
  │  主从集群  │          │  Sentinel │          │  镜像队列  │
  └───────────┘          └───────────┘          └───────────┘
```

### 8.2 容灾方案

| 故障场景 | 应对方案 |
|---------|---------|
| 单机故障 | 负载均衡自动剔除，其他机器接管 |
| MySQL主库故障 | 从库提升为主库，VIP切换 |
| Redis主库故障 | Sentinel自动故障转移 |
| 机房故障 | 跨区域容灾，DNS切换到备机房 |

---

> **相关文档**：
> - [系统架构设计](03-architecture.md)
> - [性能设计](11-performance.md)
> - [测试设计](12-test.md)
> - [开发计划与路线图](13-roadmap.md)
