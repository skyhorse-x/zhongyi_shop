<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of package's conventional credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 支付宝配置
    |--------------------------------------------------------------------------
    */
    'alipay' => [
        'app_id' => env('ALIPAY_APP_ID', ''),
        'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
        'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
        'gateway' => env('ALIPAY_GATEWAY', 'https://openapi.alipay.com/gateway.do'),
        'notify_url' => env('ALIPAY_NOTIFY_URL', '/api/v1/payment/notify/alipay'),
        'return_url' => env('ALIPAY_RETURN_URL', '/payment/success'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 微信支付配置
    |--------------------------------------------------------------------------
    */
    'wechat' => [
        'app_id' => env('WECHAT_APP_ID', ''),
        'mch_id' => env('WECHAT_MCH_ID', ''),
        'api_key' => env('WECHAT_API_KEY', ''),
        'cert_path' => env('WECHAT_CERT_PATH', ''),
        'key_path' => env('WECHAT_KEY_PATH', ''),
        'notify_url' => env('WECHAT_NOTIFY_URL', '/api/v1/payment/notify/wechat'),
    ],

];
