<?php
// staff/login.php - Staff Portal Login
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

const PORTAL_ROLE = 'STAFF';
const PORTAL_USER_TABLE = 'users_staff';
const PORTAL_REDIRECT = '/staff/index.php';
const PORTAL_NAME = 'Staff Portal';
const PORTAL_ICON = 'user-cog';
const PORTAL_SUBTITLE = 'Manage courses, students & grading';

include __DIR__ . '/../includes/portal_login_template.php';