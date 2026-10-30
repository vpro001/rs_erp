<?php
// marketer/login.php - Marketer Portal Login (accounts are created by an admin)
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

const PORTAL_ROLE = 'MARKETER';
const PORTAL_USER_TABLE = 'users_marketer';
const PORTAL_REDIRECT = '/marketer/index.php';
const PORTAL_NAME = 'Marketer Portal';
const PORTAL_ICON = 'megaphone';
const PORTAL_SUBTITLE = 'Share your link, refer students & track commissions';

include __DIR__ . '/../includes/portal_login_template.php';
