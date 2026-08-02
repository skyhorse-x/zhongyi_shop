<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerServiceConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'name',
        'remark',
    ];

    /**
     * 获取配置值
     */
    public static function getValue(string $key, $default = null)
    {
        $config = self::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    /**
     * 设置配置值
     */
    public static function setValue(string $key, $value, string $name = '', string $remark = ''): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'name' => $name,
                'remark' => $remark,
            ]
        );
    }
}
