<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建默认管理员账号
        Admin::updateOrCreate(
            ['username' => 'admin'],
            [
                'username' => 'admin',
                'password' => Hash::make('123456'),
                'name' => '超级管理员',
                'email' => 'admin@example.com',
                'status' => 1,
            ]
        );
    }
}
