<?php
// config/app_config.local.php
// Local development overrides - copy from app_config.php and modify
// This file is gitignored - never commit local credentials

return [
    // Override database for local development
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rs_erp',
        'user' => 'root',
        'pass' => '', // Set your local DB password
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // Override Paystack with test keys
    'paystack' => [
        'public_key' => 'pk_test_your_test_key',
        'secret_key' => 'sk_test_your_test_key',
        'webhook_secret' => '',
    ],

    // Override mail for local testing
    'mail' => [
        'mailer' => 'smtp',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your_email@gmail.com',
        'password' => 'your_app_password',
        'encryption' => 'tls',
        'from_address' => 'your_email@gmail.com',
        'from_name' => 'Royal Solutions ERP (Local)',
    ],

    // Generate these with: openssl rand -hex 32
    'app' => [
        'key' => '',
    ],
    'session' => [
        'secret' => '',
    ],
];