# 测试设计

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第十阶段（测试设计）

---

## 1. 测试策略

### 1.1 测试金字塔

```
              ┌─────────┐
              │  E2E测试 │  ← 少量（核心流程）
              │   10%   │
            ┌─┴─────────┴─┐
            │  集成测试    │  ← 中等（模块间）
            │    20%      │
          ┌─┴─────────────┴─┐
          │    单元测试       │  ← 大量（函数级）
          │      70%        │
          └─────────────────┘
```

### 1.2 测试类型

| 测试类型 | 工具 | 覆盖率目标 | 说明 |
|---------|------|-----------|------|
| 单元测试 | PHPUnit | ≥ 80% | Service、Model、工具类 |
| 接口测试 | PHPUnit | ≥ 90% | 全部API接口 |
| 集成测试 | PHPUnit | ≥ 70% | 模块间集成 |
| E2E测试 | Playwright | 核心流程 | 关键业务流程 |
| 性能测试 | JMeter | 基准测试 | 并发、压力测试 |
| 安全测试 | OWASP ZAP | 高危项0 | 安全漏洞扫描 |

---

## 2. 单元测试

### 2.1 测试目录结构

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── AuthServiceTest.php
│   │   ├── AnalysisServiceTest.php
│   │   ├── PaymentServiceTest.php
│   │   └── PromoterServiceTest.php
│   ├── Models/
│   │   ├── UserTest.php
│   │   └── OrderTest.php
│   └── Utils/
│       ├── SmsTest.php
│       └── WechatTest.php
├── Feature/
│   ├── AuthTest.php
│   ├── AnalysisTest.php
│   ├── PaymentTest.php
│   └── PromoterTest.php
└── TestCase.php
```

### 2.2 单元测试示例

```php
// tests/Unit/Services/AnalysisServiceTest.php
<?php

namespace Tests\Unit\Services;

use App\Services\AnalysisService;
use App\Services\AiService;
use App\Models\AnalysisTask;
use Tests\TestCase;
use Mockery;

class AnalysisServiceTest extends TestCase
{
    protected AnalysisService $service;
    protected $aiServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiServiceMock = Mockery::mock(AiService::class);
        $this->service = new AnalysisService($this->aiServiceMock);
    }

    /**
     * 测试提交分析任务
     */
    public function test_submit_analysis_task(): void
    {
        // Arrange
        $userId = 1;
        $type = 'tongue';
        $imageUrl = 'https://example.com/image.jpg';

        // Act
        $task = $this->service->submit($userId, $type, $imageUrl);

        // Assert
        $this->assertInstanceOf(AnalysisTask::class, $task);
        $this->assertEquals($userId, $task->user_id);
        $this->assertEquals($type, $task->type);
        $this->assertEquals(0, $task->status);
    }

    /**
     * 测试获取报告-未支付
     */
    public function test_get_report_not_paid(): void
    {
        // Arrange
        $task = AnalysisTask::factory()->create(['status' => 2]);
        AnalysisReport::factory()->create([
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'is_paid' => false,
        ]);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('请先支付后查看完整报告');

        // Act
        $this->service->getReport($task->user_id, $task->task_no);
    }

    /**
     * 测试获取报告-已支付
     */
    public function test_get_report_paid(): void
    {
        // Arrange
        $task = AnalysisTask::factory()->create(['status' => 2]);
        $report = AnalysisReport::factory()->create([
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'is_paid' => true,
        ]);

        // Act
        $result = $this->service->getReport($task->user_id, $task->task_no);

        // Assert
        $this->assertEquals($report->id, $result->id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

---

## 3. 接口测试

### 3.1 接口测试示例

```php
// tests/Feature/AuthTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * 测试发送验证码
     */
    public function test_send_sms_code(): void
    {
        $response = $this->postJson('/api/v1/auth/sms-code', [
            'mobile' => '1*********0',
            'type' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'message' => '验证码已发送',
            ]);
    }

    /**
     * 测试注册
     */
    public function test_register(): void
    {
        // 先发送验证码
        $this->postJson('/api/v1/auth/sms-code', [
            'mobile' => '1*********0',
            'type' => 'register',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'mobile' => '1*********0',
            'code' => '123456',
            'password' => 'abc123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'message' => '注册成功',
            ])
            ->assertJsonStructure([
                'data' => ['user_id', 'token', 'refresh_token'],
            ]);
    }

    /**
     * 测试登录
     */
    public function test_login(): void
    {
        User::factory()->create([
            'mobile' => '1*********0',
            'password' => bcrypt('abc123456'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'mobile' => '1*********0',
            'password' => 'abc123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'message' => '登录成功',
            ]);
    }

    /**
     * 测试登录-密码错误
     */
    public function test_login_wrong_password(): void
    {
        User::factory()->create([
            'mobile' => '1*********0',
            'password' => bcrypt('abc123456'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'mobile' => '1*********0',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'code' => 2003,
                'message' => '账号或密码错误',
            ]);
    }

    /**
     * 测试获取用户信息-未登录
     */
    public function test_get_user_info_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/user/info');

        $response->assertStatus(401);
    }

    /**
     * 测试获取用户信息-已登录
     */
    public function test_get_user_info_authorized(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/info');

        $response->assertStatus(200)
            ->assertJson([
                'code' => 0,
                'data' => [
                    'user_id' => $user->id,
                    'mobile' => $user->mobile,
                ],
            ]);
    }
}
```

---

## 4. E2E测试

### 4.1 E2E测试场景

| 场景 | 步骤 | 预期结果 |
|------|------|---------|
| 用户注册登录 | 发送验证码→注册→登录 | 登录成功，跳转首页 |
| AI舌诊分析 | 上传照片→提交→等待→查看摘要 | 显示分析摘要 |
| 支付查看报告 | 选择支付→完成支付→查看报告 | 显示完整报告 |
| 推广员申请 | 登录→申请推广员→获取推广码 | 显示推广码 |
| 提现 | 申请提现→填写金额→提交 | 申请提交成功 |

### 4.2 E2E测试示例

```javascript
// tests/e2e/analysis.spec.ts
import { test, expect } from '@playwright/test'

test.describe('AI舌诊分析', () => {
    test('完整分析流程', async ({ page }) => {
        // 登录
        await page.goto('/auth/login')
        await page.fill('input[name="mobile"]', '1*********0')
        await page.fill('input[name="password"]', 'abc123456')
        await page.click('button[type="submit"]')
        
        // 跳转首页
        await expect(page).toHaveURL('/home')
        
        // 点击立即分析
        await page.click('text=立即分析')
        
        // 上传图片
        await page.setInputUtils('input[type="file"]', 'test-image.jpg')
        
        // 点击开始分析
        await page.click('text=开始分析')
        
        // 等待分析完成
        await expect(page.locator('text=分析中')).toBeVisible()
        await expect(page.locator('text=分析结果')).toBeVisible({ timeout: 30000 })
        
        // 查看摘要
        await expect(page.locator('.summary')).toBeVisible()
        
        // 点击支付
        await page.click('text=查看完整报告')
        
        // 选择微信支付
        await page.click('text=微信支付')
        
        // 模拟支付成功（测试环境）
        await page.click('text=模拟支付成功')
        
        // 查看完整报告
        await expect(page.locator('.report-detail')).toBeVisible()
    })
})
```

---

## 5. 性能测试

### 5.1 测试场景

| 场景 | 并发数 | 持续时间 | 目标 |
|------|--------|---------|------|
| 首页加载 | 100 | 5分钟 | P95 ≤ 500ms |
| 用户登录 | 50 | 5分钟 | P95 ≤ 300ms |
| AI分析提交 | 20 | 5分钟 | P95 ≤ 500ms |
| 支付下单 | 30 | 5分钟 | P95 ≤ 300ms |
| 混合场景 | 100 | 10分钟 | P95 ≤ 500ms |

### 5.2 JMeter测试计划

```xml
<!-- tests/performance/login-test.jmx -->
<TestPlan>
    <ThreadGroup testname="用户登录" num_threads="50" ramp_up="10">
        <HTTPSampler testname="登录接口">
            <stringProp name="HTTPSampler.path">/api/v1/auth/login</stringProp>
            <stringProp name="HTTPSampler.method">POST</stringProp>
        </HTTPSampler>
    </ThreadGroup>
</TestPlan>
```

---

## 6. 安全测试

### 6.1 测试项

| 测试项 | 工具 | 预期结果 |
|--------|------|---------|
| SQL注入 | SQLMap | 无注入点 |
| XSS攻击 | XSStrike | 无XSS漏洞 |
| CSRF攻击 | CSRFTester | 有CSRF防护 |
| 敏感信息泄露 | 手动检查 | 无明文密码 |
| 权限越权 | 手动检查 | 有权限控制 |
| 文件上传 | 手动检查 | 拒绝危险文件 |

---

## 7. 测试覆盖率目标

| 模块 | 单元测试 | 接口测试 | E2E测试 |
|------|---------|---------|---------|
| 用户认证 | ≥ 90% | 100% | ✅ |
| AI分析 | ≥ 80% | 100% | ✅ |
| 支付系统 | ≥ 90% | 100% | ✅ |
| 推广系统 | ≥ 80% | 100% | ✅ |
| 管理后台 | ≥ 70% | ≥ 80% | ❌ |
| **整体** | **≥ 80%** | **≥ 90%** | **核心流程** |

---

## 8. 测试环境

| 环境 | 用途 | 数据 |
|------|------|------|
| 开发环境 | 开发自测 | 模拟数据 |
| 测试环境 | 功能测试 | 测试数据 |
| 预发布环境 | 验收测试 | 生产快照 |
| 生产环境 | 正式上线 | 真实数据 |

---

> **相关文档**：
> - [后端设计](08-backend.md)
> - [安全设计](10-security.md)
> - [DevOps](15-devops.md)
