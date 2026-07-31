<?php

namespace Database\Seeders;

use App\Models\ProductPackage;
use Illuminate\Database\Seeder;

class ProductPackageSeeder extends Seeder
{
    /**
     * 初始化次数包数据
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => '体验套餐',
                'type' => 'all',
                'times' => 3,
                'days' => 30,
                'price' => 9.90,
                'original_price' => 15.00,
                'is_recommend' => 0,
                'is_enabled' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => '基础套餐',
                'type' => 'all',
                'times' => 10,
                'days' => 90,
                'price' => 29.90,
                'original_price' => 45.00,
                'is_recommend' => 0,
                'is_enabled' => 1,
                'sort_order' => 2,
            ],
            [
                'name' => '进阶套餐',
                'type' => 'all',
                'times' => 30,
                'days' => 180,
                'price' => 69.90,
                'original_price' => 120.00,
                'is_recommend' => 1,
                'is_enabled' => 1,
                'sort_order' => 3,
            ],
            [
                'name' => '尊享套餐',
                'type' => 'all',
                'times' => 100,
                'days' => 365,
                'price' => 199.00,
                'original_price' => 399.00,
                'is_recommend' => 0,
                'is_enabled' => 1,
                'sort_order' => 4,
            ],
        ];

        foreach ($packages as $pkg) {
            ProductPackage::updateOrCreate(
                ['name' => $pkg['name']],
                $pkg
            );
        }
    }
}
