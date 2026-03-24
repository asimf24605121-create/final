<?php
/**
 * ClearOrbit — Hostinger Configuration
 * 
 * Update these values with your Hostinger MySQL credentials
 * found in hPanel → Databases → MySQL Databases
 */

define('DB_DRIVER', 'mysql');
define('DB_HOST',   'localhost');
define('DB_NAME',   'u123456789_clearorbit');
define('DB_USER',   'u123456789_admin');
define('DB_PASS',   'YOUR_DATABASE_PASSWORD_HERE');

putenv('DB_DRIVER=' . DB_DRIVER);
putenv('DB_HOST='   . DB_HOST);
putenv('DB_NAME='   . DB_NAME);
putenv('DB_USER='   . DB_USER);
putenv('DB_PASS='   . DB_PASS);

define('SITE_URL', 'https://yourdomain.com');

define('ALLOWED_ORIGINS_LIST', SITE_URL);
putenv('ALLOWED_ORIGINS=' . ALLOWED_ORIGINS_LIST);
