<?php
// config/app_config.php
// Application configuration - all settings in PHP (no .env file needed)

return [
    // Application
    'app' => [
        'env' => 'development',
        'debug' => true,
        'url' => 'http://localhost/rs_erp',
        'key' => '', // Generate with: openssl rand -hex 32
        'timezone' => 'Africa/Lagos',
    ],

    // Database
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rs_erp',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // Paystack (Get from https://dashboard.paystack.com/)
    'paystack' => [
        'public_key' => 'pk_test_xxxxxxxxxxxxxxxx',
        'secret_key' => 'sk_test_xxxxxxxxxxxxxxxx',
        'webhook_secret' => '',
    ],

    // Email/SMTP
    'mail' => [
        'mailer' => 'smtp',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your_email@gmail.com',
        'password' => 'your_app_password',
        'encryption' => 'tls',
        'from_address' => 'your_email@gmail.com',
        'from_name' => 'Royal Solutions ERP',
    ],

    // Session
    'session' => [
        'driver' => 'file',
        'lifetime' => 120,
        'secret' => '', // Generate with: openssl rand -hex 32
    ],

    // Security
    'security' => [
        'bcrypt_rounds' => 12,
        'csrf_token_name' => 'csrf_token',
    ],

    // File Uploads
    'uploads' => [
        'max_size' => '10M',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip'],
    ],

    // Pagination
    'pagination' => [
        'per_page' => 15,
    ],
];