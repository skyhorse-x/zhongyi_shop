<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users 表统一基线
 *
 * 目的：
 *   1. 兜底：所有未运行历史 migration 的环境，可一次性跑通
 *   2. 索引统一：补齐所有缺失的索引（外键、状态、手机号、parent_id）
 *   3. 字段补齐：补齐代码中用到但 migrations 漏掉的字段
 *
 * 设计原则：
 *   - 全部操作幂等（Schema::hasColumn / hasIndex 守卫）
 *   - 不会重复执行或破坏现有数据
 *   - 生产环境运行也是安全的
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. 字段兜底
        Schema::table('users', function (Blueprint $table) {
            $this->addIfMissing($table, 'nickname', fn () => $table->string('nickname')->default('用户')->after('id')->comment('昵称'));
            $this->addIfMissing($table, 'mobile',  fn () => $table->string('mobile', 20)->nullable()->unique()->after('nickname')->comment('手机号'));
            $this->addIfMissing($table, 'username', fn () => $table->string('username', 50)->nullable()->unique()->after('mobile')->comment('用户名'));
            $this->addIfMissing($table, 'avatar',  fn () => $table->string('avatar')->nullable()->after('password')->comment('头像'));
            $this->addIfMissing($table, 'gender',  fn () => $table->tinyInteger('gender')->default(0)->after('avatar')->comment('性别:0未知 1男 2女'));
            $this->addIfMissing($table, 'birthday', fn () => $table->date('birthday')->nullable()->after('gender')->comment('生日'));
            $this->addIfMissing($table, 'is_promoter', fn () => $table->tinyInteger('is_promoter')->default(0)->after('birthday')->comment('是否推广员'));
            $this->addIfMissing($table, 'status', fn () => $table->tinyInteger('status')->default(1)->after('is_promoter')->comment('状态:1正常 0禁用'));
            $this->addIfMissing($table, 'parent_id', fn () => $table->unsignedBigInteger('parent_id')->nullable()->after('status')->comment('推荐人ID'));
            $this->addIfMissing($table, 'parent_locked', fn () => $table->boolean('parent_locked')->default(false)->after('parent_id')->comment('邀请关系是否锁定'));
            $this->addIfMissing($table, 'parent_locked_at', fn () => $table->timestamp('parent_locked_at')->nullable()->after('parent_locked')->comment('锁定时间'));
            $this->addIfMissing($table, 'analysis_times', fn () => $table->integer('analysis_times')->default(0)->after('parent_locked_at')->comment('剩余分析次数'));
            $this->addIfMissing($table, 'balance', fn () => $table->decimal('balance', 10, 2)->default(0)->after('analysis_times')->comment('账户余额（元）'));
            $this->addIfMissing($table, 'user_registered_granted', fn () => $table->boolean('user_registered_granted')->default(false)->after('balance')->comment('是否已发放过注册试用次数（防重复）'));
        });

        // 2. 索引补齐（幂等）
        $this->addIndexIfMissing('users', ['status'], 'idx_status');
        $this->addIndexIfMissing('users', ['parent_id'], 'idx_parent_id');
        $this->addIndexIfMissing('users', ['is_promoter'], 'idx_is_promoter');
        $this->addIndexIfMissing('users', ['created_at'], 'idx_created_at');
        $this->addIndexIfMissing('users', ['parent_id', 'created_at'], 'idx_parent_created');

        // 3. 外键补齐（parent_id → users.id, set null）
        // 注意：MySQL 上需先检查约束是否存在
        $this->addForeignKeyIfMissing('users', 'users_parent_id_foreign', ['parent_id'], 'id', 'users', 'set null');
    }

    public function down(): void
    {
        // 故意不提供 down：基线不可逆
    }

    // ===== 辅助方法 =====

    private function addIfMissing(Blueprint $table, string $column, \Closure $callback): void
    {
        if (!Schema::hasColumn('users', $column)) {
            $callback();
        }
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?",
            [$table, $indexName]
        );
        if ((int)($exists->c ?? 0) === 0) {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        }
    }

    private function addForeignKeyIfMissing(string $table, string $fkName, array $columns, string $refColumn, string $refTable, string $onDelete): void
    {
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE()
               AND table_name = ?
               AND constraint_name = ?",
            [$table, $fkName]
        );
        if ((int)($exists->c ?? 0) > 0) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $refColumn, $refTable, $onDelete, $fkName) {
            $t->foreign($columns, $fkName)->references($refColumn)->on($refTable)->onDelete($onDelete);
        });
    }
};
