<?php
// Copy this file to config.local.php and fill in real values.
// config.local.php is gitignored and must exist on every environment.

// Database
define('DB_HOSTNAME', 'localhost');
define('DB_DATABASE', 'your_database');
define('DB_USERNAME', 'your_user');
define('DB_PASSWORD', 'your_password');

// LINE Messaging API (https://developers.line.biz/)
define('LINE_BROADCAST_TOKEN', '');   // channel access token used for broadcast messages
define('LINE_PUSH_TOKEN', '');        // channel access token used for push messages (legacy book.php)
define('LINE_ADMIN_USER_ID', '');     // LINE user ID that receives push messages
define('LINE_TEST_USER_ID', '');      // LINE user ID used by line-api.php for testing

// SMSMKT (https://portal-otp.smsmkt.com/)
define('SMSMKT_API_KEY', '');
define('SMSMKT_SECRET_KEY', '');
define('SMSMKT_SENDER', 'Lesmashclub');
