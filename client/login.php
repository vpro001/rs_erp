<?php
// client/login.php - Client Portal Login
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/db_connection.php';

const PORTAL_ROLE = 'CLIENT';
const PORTAL_USER_TABLE = 'users_client';
const PORTAL_REDIRECT = '/client/index.php';
const PORTAL_NAME = 'Client Portal';
const PORTAL_ICON = 'briefcase';
const PORTAL_SUBTITLE = 'Access your projects, proposals & documents';
const PORTAL_ALLOW_REGISTER = true;

include __DIR__ . '/../includes/portal_login_template.php';