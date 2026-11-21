<?php

/**
 * Logout Handler
 * Student Attendance Management System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout_admin();

redirect('index.php?msg=logged_out');
