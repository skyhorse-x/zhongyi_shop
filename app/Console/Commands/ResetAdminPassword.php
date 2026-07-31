<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset
                            {username=admin : 管理员账号}
                            {password=123456 : 新密码}';

    protected $description = '重置后台管理员密码（忘记密码时使用）';

    public function handle(): int
    {
        $username = $this->argument('username');
        $password = $this->argument('password');

        $admin = Admin::where('username', $username)->first();
        if (!$admin) {
            $this->error("管理员 [{$username}] 不存在");
            $this->line('');
            $this->line('如需创建：先注册后用 SQL 改 role，或运行 admin:create');
            return self::FAILURE;
        }

        $admin->update(['password' => Hash::make($password)]);
        $this->info("✓ 已重置 [{$username}] 的密码");

        return self::SUCCESS;
    }
}
