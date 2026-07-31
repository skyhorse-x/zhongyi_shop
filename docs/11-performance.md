# 性能设计

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第九阶段（性能设计）

---

## 1. 缓存策略

### 1.1 缓存层级

```
┌─────────────────────────────────────────────────────────┐
│                      CDN缓存                             │
│              静态资源、图片、JS/CSS                      │
├─────────────────────────────────────────────────────────┤
│                    Nginx缓存                             │
│              页面缓存、代理缓存                          │
├─────────────────────────────────────────────────────────┤
│                    Redis缓存                             │
│              数据缓存、会话、限流                        │
├─────────────────────────────────────────────────────────┤
│                  应用缓存                                │
│              配置缓存、路由缓存                          │
├─────────────────────────────────────────────────────────┤
│                  数据库缓存                              │
│              查询缓存、连接池                            │
└─────────────────────────────────────────────────────────┘
```

### 1.2 缓存数据清单

| 数据 | 缓存方式 | 过期时间 | 更新策略 |
|------|---------|---------|---------|
| 用户信息 | Redis Hash | 1小时 | 更新时清除 |
| 分析报告 | Redis String | 24小时 | 生成时写入 |
| AI分析结果(图片MD5) | Redis String | 7天 | 分析时写入 |
| 系统配置 | Redis String | 永久 | 更新时覆盖 |
| 推广员信息 | Redis Hash | 30分钟 | 更新时清除 |
| 文章列表 | Redis List | 10分钟 | 发布时清除 |
| Banner列表 | Redis List | 30分钟 | 更新时清除 |

### 1.3 缓存实现

```php
// app/Services/CacheService.php
class CacheService
{
    /**
     * 获取用户信息（带缓存）
     */
    public function getUserInfo(int $userId): array
    {
        $cacheKey = 'user:info:' . $userId;
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $user = User::with('profile')->find($userId);
        $info = $user->toArray();
        
        Cache::put($cacheKey, $info, 3600);
        
        return $info;
    }

    /**
     * 清除用户缓存
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget('user:info:' . $userId);
    }
}
```

---

## 2. 数据库优化

### 2.1 索引优化

| 表名 | 索引 | 场景 |
|------|------|------|
| users | idx_mobile | 手机号登录 |
| users | idx_openid | 微信登录 |
| analysis_tasks | idx_image_md5 | 图片缓存查询 |
| analysis_tasks | idx_user_id_created_at | 历史记录查询 |
| orders | idx_user_id_status | 订单列表查询 |
| commissions | idx_promoter_id_status | 佣金查询 |

### 2.2 慢查询优化

```sql
-- 开启慢查询日志
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;

-- 分析慢查询
EXPLAIN SELECT * FROM analysis_tasks 
WHERE user_id = 10001 
AND created_at > '2026-07-01' 
ORDER BY created_at DESC;
```

### 2.3 读写分离

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['192.168.1.2'],
    ],
    'write' => [
        'host' => ['192.168.1.1'],
    ],
    'sticky' => true,
],
```

---

## 3. 异步处理

### 3.1 队列配置

| 队列名称 | 用途 | 并发数 |
|---------|------|--------|
| analysis | AI分析任务 | 5 |
| payment | 支付通知 | 3 |
| commission | 佣金结算 | 2 |
| sms | 短信发送 | 3 |
| withdraw | 提现处理 | 2 |

### 3.2 Supervisor配置

```ini
// /etc/supervisor/conf.d/analysis.conf
[program:analysis-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/api/artisan queue:work redis --queue=analysis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=5
redirect_stderr=true
stdout_logfile=/var/log/supervisor/analysis-worker.log
```

---

## 4. 对象存储优化

### 4.1 图片处理

| 操作 | 方案 |
|------|------|
| 压缩 | 前端压缩至500KB以下 |
| 裁剪 | 支持用户裁剪 |
| 缩略图 | 生成100x100缩略图 |
| 格式 | 统一转为WebP |

### 4.2 CDN加速

| 资源类型 | CDN配置 |
|---------|---------|
| 静态资源 | 缓存30天 |
| 用户图片 | 缓存7天 |
| 推广海报 | 缓存1天 |
| API响应 | 不缓存 |

---

## 5. 性能目标

### 5.1 接口性能

| 接口 | 目标响应时间 | 说明 |
|------|-------------|------|
| 首页 | ≤ 200ms | 含缓存 |
| 登录 | ≤ 300ms | 含短信验证 |
| 提交分析 | ≤ 500ms | 仅创建任务 |
| 查询状态 | ≤ 100ms | 含缓存 |
| 查看报告 | ≤ 200ms | 含缓存 |
| 创建订单 | ≤ 300ms | 含支付参数 |
| 支付回调 | ≤ 500ms | 含业务处理 |

### 5.2 系统性能

| 指标 | 目标 |
|------|------|
| 首页加载 | ≤ 2s |
| 接口响应（P95） | ≤ 500ms |
| AI分析（含排队） | ≤ 30s |
| 并发支持 | ≥ 1000 QPS |
| 系统可用性 | ≥ 99.9% |

---

## 6. 容量规划

### 6.1 初期（0-1万用户）

| 资源 | 配置 | 月成本 |
|------|------|--------|
| 应用服务器 | 2核4G × 1 | ¥200 |
| MySQL | 2核4G × 1 | ¥300 |
| Redis | 1G × 1 | ¥100 |
| 对象存储 | 50GB | ¥50 |
| CDN | 100GB | ¥50 |
| **合计** | | **¥700** |

### 6.2 成长期（1-10万用户）

| 资源 | 配置 | 月成本 |
|------|------|--------|
| 应用服务器 | 4核8G × 2 | ¥800 |
| MySQL | 4核8G × 1（主从） | ¥600 |
| Redis | 2G × 1（主从） | ¥200 |
| 对象存储 | 500GB | ¥200 |
| CDN | 1TB | ¥200 |
| **合计** | | **¥2000** |

### 6.3 成熟期（10-100万用户）

| 资源 | 配置 | 月成本 |
|------|------|--------|
| 应用服务器 | 8核16G × 4 | ¥3200 |
| MySQL | 8核16G × 1（主从） | ¥1200 |
| Redis | 4G × 1（集群） | ¥400 |
| 对象存储 | 5TB | ¥500 |
| CDN | 10TB | ¥1000 |
| **合计** | | **¥6300** |

---

## 7. 性能监控

### 7.1 监控指标

| 指标 | 工具 | 告警阈值 |
|------|------|---------|
| CPU使用率 | Prometheus | > 80% |
| 内存使用率 | Prometheus | > 80% |
| 接口响应时间 | Prometheus | > 1s |
| 错误率 | Sentry | > 1% |
| MySQL慢查询 | Prometheus | > 1s |
| Redis内存 | Prometheus | > 80% |
| RabbitMQ队列长度 | Prometheus | > 1000 |

### 7.2 性能优化清单

- [ ] 开启OPcache
- [ ] 开启Laravel配置缓存
- [ ] 开启路由缓存
- [ ] 开启数据库查询缓存
- [ ] 开启CDN加速
- [ ] 开启Gzip压缩
- [ ] 开启HTTP/2
- [ ] 图片懒加载
- [ ] 接口分页查询
- [ ] 数据库索引优化

---

> **相关文档**：
> - [系统架构设计](03-architecture.md)
> - [后端设计](08-backend.md)
> - [DevOps](15-devops.md)
