<?php
// admin/login.php - Admin/Manager Portal Login
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

const PORTAL_ROLE = 'ADMIN';
const PORTAL_USER_TABLE = 'users_admin';
const PORTAL_REDIRECT = '/admin/index.php';
const PORTAL_NAME = 'Admin Portal';
const PORTAL_ICON = 'shield';
const PORTAL_SUBTITLE = 'System administration & management';

include __DIR__ . '/../includes/portal_login_template.php';