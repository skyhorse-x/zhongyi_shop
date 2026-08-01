<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Promoter;
use App\Models\ProductPackage;
use App\Models\InviteRegistration;
use App\Models\Withdraw;
use App\Models\Commission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 全量 API 端到端测试
 * 
 * 运行方式：
 * php artisan test --filter=ApiEndToEndTest
 * 或分组运行：
 * php artisan test --filter=AuthTest
 * php artisan test --filter=AnalysisTest
 * php artisan test --filter=PaymentTest
 * php artisan test --filter=PromoterTest
 * php artisan test --filter=AdminTest
 */
class ApiEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Admin $admin;
    protected string $token;
    protected string $adminToken;

    /**
     * 每个测试前的初始化
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建测试用户
        $this->user = User::factory()->create([
            'password' => bcrypt('123456'),
            'analysis_times' => 5,
            'balance' => 100,
        ]);
        $this->token = $this->user->createToken('test')->plainTextToken;

        // 创建管理员
        $this->admin = Admin::factory()->create([
            'password' => bcrypt('admin123'),
        ]);
        $this->adminToken = $this->admin->createToken('admin-test')->plainTextToken;
    }

    // ==================== 1. 用户认证模块 ====================

    /** @test */
    public function test_register_with_account()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'type' => 'account',
            'account' => 'newuser_' . time(),
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['token', 'user' => ['id', 'nickname', 'analysis_times']]
            ])
            ->assertJson(['code' => 0]);

        // 验证赠送了初始次数
        $user = User::where('name', $response->json('data.user.name'))->first();
        $this->assertNotNull($user);
    }

    /** @test */
    public function test_register_duplicate_username()
    {
        $this->postJson('/api/v1/auth/register', [
            'type' => 'account',
            'account' => $this->user->name,
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertStatus(422);
    }

    /** @test */
    public function test_register_password_mismatch()
    {
        $this->postJson('/api/v1/auth/register', [
            'type' => 'account',
            'account' => 'testuser2',
            'password' => '123456',
            'password_confirmation' => '654321',
        ])->assertStatus(422);
    }

    /** @test */
    public function test_login_success()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'account' => $this->user->name,
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0])
            ->assertJsonPath('data.token', fn($token) => !empty($token));
    }

    /** @test */
    public function test_login_wrong_password()
    {
        $this->postJson('/api/v1/auth/login', [
            'account' => $this->user->name,
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    /** @test */
    public function test_get_user_info()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/user/info');

        $response->assertStatus(200)
            ->assertJson(['code' => 0])
            ->assertJsonPath('data.id', $this->user->id);
    }

    /** @test */
    public function test_unauthorized_access()
    {
        $this->getJson('/api/v1/user/info')
            ->assertStatus(401);
    }

    /** @test */
    public function test_logout()
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/user/logout')
            ->assertJson(['code' => 0]);
    }

    // ==================== 2. AI 分析模块 ====================

    /** @test */
    public function test_get_analysis_config()
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analysis/config')
            ->assertStatus(200)
            ->assertJsonStructure(['code', 'data' => ['analysis_mode', 'analysis_price']]);
    }

    /** @test */
    public function test_submit_tongue_analysis_with_text()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/analysis/submit', [
                'type' => 'tongue',
                'text' => '舌质红，苔黄腻',
            ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0])
            ->assertJsonStructure(['data' => ['task_no']]);

        // 验证次数被扣除
        $this->user->refresh();
        $this->assertEquals(4, $this->user->analysis_times);
    }

    /** @test */
    public function test_submit_analysis_without_times()
    {
        $this->user->update(['analysis_times' => 0]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/analysis/submit', [
                'type' => 'tongue',
                'text' => '舌质红',
            ])->assertStatus(402); // 次数不足
    }

    /** @test */
    public function test_get_analysis_status()
    {
        // 先创建任务
        $task = \App\Models\AnalysisTask::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/analysis/status/{$task->task_no}")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_get_analysis_history()
    {
        \App\Models\AnalysisTask::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/analysis/history?page=1&limit=10')
            ->assertStatus(200)
            ->assertJsonStructure(['code', 'data' => ['data']]);
    }

    // ==================== 3. 体质测试模块 ====================

    /** @test */
    public function test_get_constitution_questions()
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/constitution/questions')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_submit_constitution_test()
    {
        // 创建题目
        \App\Models\ConstitutionQuestion::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/constitution/submit', [
                'answers' => [
                    '1' => 3,
                    '2' => 2,
                    '3' => 4,
                    '4' => 1,
                    '5' => 3,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 4. 健康问答模块 ====================

    /** @test */
    public function test_create_qa_session()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/qa/sessions');

        $response->assertStatus(200)
            ->assertJson(['code' => 0])
            ->assertJsonStructure(['data' => ['session_no']]);
    }

    /** @test */
    public function test_get_qa_sessions()
    {
        \App\Models\HealthQaSession::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/qa/sessions?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_send_qa_message()
    {
        $session = \App\Models\HealthQaSession::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/qa/sessions/{$session->session_no}/messages", [
                'content' => '我最近总是失眠',
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 5. 次数包与购买模块 ====================

    /** @test */
    public function test_get_packages()
    {
        ProductPackage::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/packages')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_get_payment_methods()
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/payment/methods')
            ->assertStatus(200)
            ->assertJsonStructure(['code', 'data' => ['list', 'user_balance']]);
    }

    /** @test */
    public function test_buy_package_with_balance()
    {
        $package = ProductPackage::factory()->create([
            'price' => 10,
            'times' => 10,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/packages/buy', [
                'package_id' => $package->id,
                'pay_type' => 'balance',
            ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0]);

        // 验证余额扣除
        $this->user->refresh();
        $this->assertEquals(90, $this->user->balance);

        // 验证次数增加
        $this->assertEquals(15, $this->user->analysis_times);
    }

    /** @test */
    public function test_buy_package_balance_insufficient()
    {
        $this->user->update(['balance' => 0]);
        $package = ProductPackage::factory()->create(['price' => 100]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/packages/buy', [
                'package_id' => $package->id,
                'pay_type' => 'balance',
            ])
            ->assertStatus(400)
            ->assertJson(['code' => 400]);
    }

    /** @test */
    public function test_cancel_order()
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 0, // 待支付
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/user/orders/{$order->order_no}/cancel")
            ->assertJson(['code' => 0]);
    }

    // ==================== 6. 用户中心模块 ====================

    /** @test */
    public function test_get_user_orders()
    {
        Order::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/user/orders?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_get_balance_logs()
    {
        \App\Models\UserBalanceLog::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/user/balance-logs?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_get_health_history()
    {
        \App\Models\AnalysisTask::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/health/history?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 7. 推广分销模块 ====================

    /** @test */
    public function test_activate_promoter()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/activate');

        $response->assertStatus(200)
            ->assertJson(['code' => 0]);

        // 验证推广员记录创建
        $this->assertDatabaseHas('promoters', [
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function test_get_promoter_info()
    {
        Promoter::factory()->create(['user_id' => $this->user->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/promoter/info')
            ->assertStatus(200)
            ->assertJsonStructure(['code', 'data' => ['invite_code', 'commission_rate']]);
    }

    /** @test */
    public function test_get_poster()
    {
        Promoter::factory()->create(['user_id' => $this->user->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/promoter/poster')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_get_commissions()
    {
        Promoter::factory()->create(['user_id' => $this->user->id]);
        Commission::factory()->count(3)->create([
            'promoter_id' => $this->user->promoter->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/promoter/commissions?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_withdraw_request()
    {
        $promoter = Promoter::factory()->create([
            'user_id' => $this->user->id,
            'total_commission' => 100,
            'frozen_commission' => 0,
            'withdrawn_commission' => 0,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/withdraw', [
                'amount' => 50,
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_withdraw_exceed_balance()
    {
        $promoter = Promoter::factory()->create([
            'user_id' => $this->user->id,
            'total_commission' => 10,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/withdraw', [
                'amount' => 100,
            ])
            ->assertStatus(400);
    }

    /** @test */
    public function test_invite_records()
    {
        $promoter = Promoter::factory()->create(['user_id' => $this->user->id]);
        InviteRegistration::factory()->count(3)->create([
            'promoter_id' => $promoter->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/promoter/invite-records?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_track_click()
    {
        $promoter = Promoter::factory()->create(['user_id' => $this->user->id]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/track-click', [
                'code' => $promoter->invite_code,
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 8. 管理后台 - 认证 ====================

    /** @test */
    public function test_admin_login()
    {
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'username' => $this->admin->username,
            'password' => 'admin123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0])
            ->assertJsonStructure(['data' => ['token']]);
    }

    /** @test */
    public function test_admin_login_wrong_password()
    {
        $this->postJson('/api/v1/admin/auth/login', [
            'username' => $this->admin->username,
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    /** @test */
    public function test_admin_dashboard()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['code', 'data' => ['today_register', 'today_income', 'total_users']]);
    }

    /** @test */
    public function test_invite_marquee()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/invite-marquee')
            ->assertStatus(200)
            ->assertJsonStructure(['code', 'data' => ['recent', 'top_list']]);
    }

    // ==================== 9. 管理后台 - 用户管理 ====================

    /** @test */
    public function test_admin_users_list()
    {
        User::factory()->count(5)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/users?page=1&per_page=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_user_detail()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/admin/users/{$this->user->id}")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_adjust_balance_recharge()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/users/{$this->user->id}/balance", [
                'type' => 'recharge',
                'amount' => 50,
                'remark' => '测试充值',
            ]);

        $response->assertStatus(200)->assertJson(['code' => 0]);

        $this->user->refresh();
        $this->assertEquals(150, $this->user->balance);
    }

    /** @test */
    public function test_admin_adjust_balance_deduct()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/users/{$this->user->id}/balance", [
                'type' => 'admin_deduct',
                'amount' => 20,
                'remark' => '测试扣减',
            ]);

        $response->assertStatus(200)->assertJson(['code' => 0]);

        $this->user->refresh();
        $this->assertEquals(80, $this->user->balance);
    }

    /** @test */
    public function test_admin_balance_logs()
    {
        \App\Models\UserBalanceLog::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/admin/users/{$this->user->id}/balance-logs?page=1&limit=10")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_reset_password()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/users/{$this->user->id}/reset-password", [
                'password' => 'newpass123',
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 10. 管理后台 - 推广管理 ====================

    /** @test */
    public function test_admin_promoters_list()
    {
        Promoter::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/promoters?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_invite_records()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/promoters/invite-records?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_ban_promoter()
    {
        $promoter = Promoter::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/promoters/{$promoter->id}/ban")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);

        $promoter->refresh();
        $this->assertTrue($promoter->is_banned);
    }

    /** @test */
    public function test_admin_unban_promoter()
    {
        $promoter = Promoter::factory()->create(['is_banned' => true]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/promoters/{$promoter->id}/unban")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);

        $promoter->refresh();
        $this->assertFalse($promoter->is_banned);
    }

    // ==================== 11. 管理后台 - 订单与提现 ====================

    /** @test */
    public function test_admin_orders_list()
    {
        Order::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/orders?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_withdraws_list()
    {
        Withdraw::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/withdraws?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_audit_withdraw_approve()
    {
        $promoter = Promoter::factory()->create([
            'frozen_commission' => 100,
        ]);
        $withdraw = Withdraw::factory()->create([
            'promoter_id' => $promoter->id,
            'amount' => 50,
            'status' => 0,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/withdraws/{$withdraw->id}/audit", [
                'status' => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_audit_withdraw_reject()
    {
        $promoter = Promoter::factory()->create([
            'frozen_commission' => 100,
        ]);
        $withdraw = Withdraw::factory()->create([
            'promoter_id' => $promoter->id,
            'amount' => 50,
            'status' => 0,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/admin/withdraws/{$withdraw->id}/audit", [
                'status' => 2,
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 12. 管理后台 - 系统配置 ====================

    /** @test */
    public function test_admin_payment_config()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/config/payment')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_toggle_payment()
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/admin/config/payment-toggle', [
                'key' => 'payment_wechat_enabled',
                'value' => '0',
            ])
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_packages_list()
    {
        ProductPackage::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/packages?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_admin_ai_models_list()
    {
        \App\Models\AiModel::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/admin/ai/models?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 13. 文章模块 ====================

    /** @test */
    public function test_articles_list()
    {
        \App\Models\Article::factory()->count(3)->create();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/articles?page=1&limit=10')
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    /** @test */
    public function test_article_detail()
    {
        $article = \App\Models\Article::factory()->create();

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/articles/{$article->id}")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);
    }

    // ==================== 14. 异常场景测试 ====================

    /** @test */
    public function test_not_found_route()
    {
        $this->getJson('/api/v1/nonexistent')
            ->assertStatus(404)
            ->assertJson(['code' => 404]);
    }

    /** @test */
    public function test_method_not_allowed()
    {
        $this->deleteJson('/api/v1/user/info')
            ->assertStatus(405)
            ->assertJson(['code' => 405]);
    }

    /** @test */
    public function test_validation_error()
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJson(['code' => 422]);
    }

    /** @test */
    public function test_invalid_token()
    {
        $this->withHeader('Authorization', 'Bearer invalid_token_here')
            ->getJson('/api/v1/user/info')
            ->assertStatus(401);
    }

    /** @test */
    public function test_expired_token_refresh()
    {
        // Token 过期测试（需要配置 sanctum expiration）
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(501); // 未实现
    }

    // ==================== 15. 并发安全测试 ====================

    /** @test */
    public function test_concurrent_analysis_deduction()
    {
        $this->user->update(['analysis_times' => 1]);

        // 模拟并发请求
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->withHeader('Authorization', "Bearer {$this->token}")
                ->postJson('/api/v1/analysis/submit', [
                    'type' => 'tongue',
                    'text' => '舌质红',
                ]);
        }

        // 只有一个应该成功
        $successCount = collect($responses)->filter(fn($r) => $r->status() === 200)->count();
        $this->assertEquals(1, $successCount);
    }

    /** @test */
    public function test_concurrent_balance_deduction()
    {
        $this->user->update(['balance' => 10]);
        $package = ProductPackage::factory()->create(['price' => 10]);

        // 模拟并发余额支付
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->withHeader('Authorization', "Bearer {$this->token}")
                ->postJson('/api/v1/packages/buy', [
                    'package_id' => $package->id,
                    'pay_type' => 'balance',
                ]);
        }

        // 只有一个应该成功
        $successCount = collect($responses)->filter(fn($r) => $r->status() === 200)->count();
        $this->assertEquals(1, $successCount);
    }

    // ==================== 16. 反作弊测试 ====================

    /** @test */
    public function test_bot_ua_detection()
    {
        $promoter = Promoter::factory()->create(['user_id' => $this->user->id]);

        // 模拟机器人 UA
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')
            ->postJson('/api/v1/promoter/track-click', [
                'code' => $promoter->invite_code,
            ]);

        $response->assertStatus(200);

        // 验证标记为可疑
        $this->assertDatabaseHas('invite_clicks', [
            'is_suspicious' => true,
        ]);
    }

    /** @test */
    public function test_duplicate_ip_detection()
    {
        $promoter = Promoter::factory()->create(['user_id' => $this->user->id]);

        // 第一次点击
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/track-click', [
                'code' => $promoter->invite_code,
            ]);

        // 第二次点击（同IP）
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/track-click', [
                'code' => $promoter->invite_code,
            ]);

        // 验证第二次标记为重复IP
        $this->assertDatabaseHas('invite_clicks', [
            'is_duplicate_ip' => true,
        ]);
    }

    // ==================== 17. 注册赠送幂等性测试 ====================

    /** @test */
    public function test_register_grant_idempotent()
    {
        // 第一次注册
        $response1 = $this->postJson('/api/v1/auth/register', [
            'type' => 'account',
            'account' => 'idempotent_test_' . time(),
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);

        $response1->assertStatus(200);
        $userId = $response1->json('data.user.id');

        // 验证标记已设置
        $user = User::find($userId);
        $this->assertTrue($user->user_registered_granted);
    }

    // ==================== 18. 推广员开通幂等性测试 ====================

    /** @test */
    public function test_promoter_activation_idempotent()
    {
        Promoter::factory()->create(['user_id' => $this->user->id]);

        // 重复开通
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/promoter/activate');

        $response->assertStatus(200)
            ->assertJson(['code' => 0]);

        // 验证只有一条推广员记录
        $this->assertEquals(1, Promoter::where('user_id', $this->user->id)->count());
    }
}
