<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 添加缺失的外键约束和索引
     */
    public function up(): void
    {
        // analysis_tasks.user_id -> users.id
        $this->addForeignKeyIfNotExists('analysis_tasks', 'user_id', 'users', 'id', 'cascade');

        // promoters.user_id -> users.id
        $this->addForeignKeyIfNotExists('promoters', 'user_id', 'users', 'id', 'cascade');

        // health_qa_sessions.user_id -> users.id
        $this->addForeignKeyIfNotExists('health_qa_sessions', 'user_id', 'users', 'id', 'cascade');

        // health_qa_messages.session_id -> health_qa_sessions.id
        $this->addForeignKeyIfNotExists('health_qa_messages', 'session_id', 'health_qa_sessions', 'id', 'cascade');

        // ai_logs 外键（注意：model_id 是 integer 类型，与 ai_models.id(bigint unsigned) 类型不匹配，跳过）
        // $this->addForeignKeyIfNotExists('ai_logs', 'model_id', 'ai_models', 'id', 'cascade');
        $this->addForeignKeyIfNotExists('ai_logs', 'user_id', 'users', 'id', 'set null');
        $this->addForeignKeyIfNotExists('ai_logs', 'task_id', 'analysis_tasks', 'id', 'set null');

        // 添加缺失的索引
        $this->addIndexIfNotExists('health_qa_messages', 'role');
        $this->addIndexIfNotExists('health_qa_messages', 'created_at');

        // 复合索引
        $this->addCompositeIndexIfNotExists('analysis_tasks', ['user_id', 'status']);
        $this->addCompositeIndexIfNotExists('promoters', ['user_id', 'status']);
        $this->addCompositeIndexIfNotExists('health_qa_sessions', ['user_id', 'status']);
        $this->addIndexIfNotExists('orders', 'type');
        $this->addCompositeIndexIfNotExists('orders', ['user_id', 'type']);
        $this->addCompositeIndexIfNotExists('payments', ['user_id', 'status']);
    }

    public function down(): void
    {
        $tables = [
            'analysis_tasks' => ['user_id'],
            'promoters' => ['user_id'],
            'health_qa_sessions' => ['user_id'],
            'health_qa_messages' => ['session_id'],
            'ai_logs' => ['user_id', 'task_id'],
        ];

        foreach ($tables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropForeign([$column]);
                }
            });
        }
    }

    /**
     * 检查外键约束是否存在
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        try {
            // SQLite 使用 PRAGMA，MySQL/PostgreSQL 使用 information_schema
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $foreignKeys = DB::select("PRAGMA foreign_key_list({$table})");
                foreach ($foreignKeys as $fk) {
                    if (isset($fk->from) && $fk->from === $column) {
                        return true;
                    }
                }
                return false;
            }

            $database = DB::getDatabaseName();
            $tableName = $table;
            // 生成 Laravel 默认的外键名称: table_column_foreign
            $fkName = "{$table}_{$column}_foreign";

            $result = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
                [$database, $tableName, $fkName]
            );

            return !empty($result);
        } catch (\Exception $e) {
            // 如果检查失败，尝试添加（Schema::table 会处理重复的情况）
            return false;
        }
    }

    /**
     * 安全添加外键约束
     */
    private function addForeignKeyIfNotExists(
        string $table,
        string $column,
        string $referencesTable,
        string $referencesColumn = 'id',
        string $onDelete = 'cascade'
    ): void {
        if (!$this->foreignKeyExists($table, $column)) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $referencesTable, $referencesColumn, $onDelete) {
                $fk = $blueprint->foreign($column)
                    ->references($referencesColumn)
                    ->on($referencesTable);

                if ($onDelete === 'cascade') {
                    $fk->onDelete('cascade');
                } elseif ($onDelete === 'set null') {
                    $fk->onDelete('set null');
                }
            });
        }
    }

    /**
     * 检查索引是否存在
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                $indexes = DB::select("PRAGMA index_list({$table})");
                foreach ($indexes as $index) {
                    if (isset($index->name) && $index->name === $indexName) {
                        return true;
                    }
                }
                return false;
            }

            $database = DB::getDatabaseName();
            $result = DB::select(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [$database, $table, $indexName]
            );

            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 安全添加单列索引
     */
    private function addIndexIfNotExists(string $table, string $column): void
    {
        $indexName = "{$table}_{$column}_index";
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->index($column);
            });
        }
    }

    /**
     * 安全添加复合索引
     */
    private function addCompositeIndexIfNotExists(string $table, array $columns): void
    {
        $indexName = $table . '_' . implode('_', $columns) . '_index';
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        }
    }
};
