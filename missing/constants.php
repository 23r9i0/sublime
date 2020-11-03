<?php
/**
 * Include Missing WordPress Constants
 * like WP_SITEURL, WP_HOME, ...
 */

/**
 * Allows the WordPress address (URL) to be defined.
 */
define( 'WP_SITEURL', '' );

/**
 *  Similar to WP_SITEURL, WP_HOME overrides the wp_options table
 *  value for home but does not change it permanently.
 */
define( 'WP_HOME', '' );

/**
 * CUSTOM_USER_TABLE and CUSTOM_USER_META_TABLE
 * are used to designate that the user and usermeta tables normally utilized by WordPress are not used,
 * instead these values/tables are used to store your user information.
 */
define( 'CUSTOM_USER_TABLE', '' );
define( 'CUSTOM_USER_META_TABLE', '' );

/**
 * Save queries for analysis
 */
define( 'SAVEQUERIES', true );

/**
 *  Forces the filesystem method.
 */
define( 'FS_METHOD', '' );

/**
 * FTP_BASE is the full path to the "base"(ABSPATH) folder of the WordPress installation.
 */
define( 'FTP_BASE', '' );

/**
 * FTP_CONTENT_DIR is the full path to the wp-content folder of the WordPress installation.
 */
define( 'FTP_CONTENT_DIR', '' );

/**
 * FTP_PLUGIN_DIR is the full path to the plugins folder of the WordPress installation.
 */
define( 'FTP_PLUGIN_DIR', '' );

/**
 * FTP_PUBKEY is the full path to your SSH public key.
 */
define( 'FTP_PUBKEY', '' );

/**
 * FTP_PRIKEY is the full path to your SSH private key.
 */
define( 'FTP_PRIKEY', '' );

/**
 * FTP_USER is either user FTP or SSH username. Most likely these are the same, but use the appropriate one for the type of update you wish to do.
 */
define( 'FTP_USER', '' );

/**
 * FTP_PASS is the password for the username entered for FTP_USER. If you are using SSH public key authentication this can be omitted.
 */
define( 'FTP_PASS', '' );

/**
 * FTP_HOST is the hostname:port combination for your SSH/FTP server. The default FTP port is 21 and the default SSH port is 22. These do not need to be mentioned.
 */
define( 'FTP_HOST', '' );

/**
 * FTP_SSL TRUE for SSL-connection if supported by the underlying transport (not available on all servers). This is for "Secure FTP" not for SSH SFTP.
 */
define( 'FTP_SSL', false );

/**
 * Alternative Cron
 */
define( 'ALTERNATE_WP_CRON', true );

/**
 * Disable the cron
 */
define( 'DISABLE_WP_CRON', true );

/**
 * Automatic database optimizing
 */
define( 'WP_ALLOW_REPAIR', true );

/**
 * Do not upgrade global tables
 */
define( 'DO_NOT_UPGRADE_GLOBAL_TABLES', true );

/**
 * Disable the Plugin and Theme Editor
 */
define( 'DISALLOW_FILE_EDIT', true );

/**
 * Block External URL Requests
 */
define( 'WP_HTTP_BLOCK_EXTERNAL', true );

/**
 * Allow additional hosts to go through for requests
 */
define( 'WP_ACCESSIBLE_HOSTS', '' );

/**
 * Disable all automatic updates
 */
define( 'AUTOMATIC_UPDATER_DISABLED', true );

/**
 * Disable all core updates
 */
define( 'WP_AUTO_UPDATE_CORE', false );

/**
 * Cleanup Image Edits
 */
define( 'IMAGE_EDIT_OVERWRITE', true );

/**
 * Database Charset to use in creating database tables.
 *
 * wp-config.php file, not is defined on others files
 */
define('DB_CHARSET', 'utf8');

/**
 * The Database Collate type. Don't change this if in doubt.
 *
 * wp-config.php file, not is defined on others files
 */
define('DB_COLLATE', '');

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'AUTH_KEY', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'SECURE_AUTH_KEY', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'LOGGED_IN_KEY', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'NONCE_KEY', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'AUTH_SALT', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'SECURE_AUTH_SALT', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'LOGGED_IN_SALT', 'phrase' );

/**
 * Authentication Unique Key
 *
 * wp-config.php file, not is defined on others files
 */
define( 'NONCE_SALT', 'phrase' );

/**
 * To enabled or disabled the error handler.
 *
 * True if the fatal error handler is enabled, false otherwise.
 */
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );

/**
 * Network
 */
define( 'BLOG_ID_CURRENT_SITE', 1 );

/**
 * Network
 */
define( 'DOMAIN_CURRENT_SITE', 'hostname' );

/**
 * Network
 */
define( 'PATH_CURRENT_SITE', 'base' );

/**
 * Network
 */
define( 'SITE_ID_CURRENT_SITE', 1 );

/**
 * Network
 */
define( 'WP_ALLOW_MULTISITE', 1 );

/**
 * Compression of CSS
 */
define( 'COMPRESS_CSS', true );

/**
 * Compression of scripts
 */
define( 'COMPRESS_SCRIPTS', true );

/**
 * Compression and concatenation of scripts and CSS
 */
define( 'CONCATENATE_SCRIPTS', true );

/**
 * Forces gzip for compression
 */
define( 'ENFORCE_GZIP', true );

/**
 * Will prevent the hosts in this list from going through the proxy.
 */
define( 'WP_PROXY_BYPASS_HOSTS', 'localhost, www.example.com, *.wordpress.org' );

/**
 * Enable proxy support and host for connecting.
 */
define( 'WP_PROXY_HOST', '192.168.84.101' );

/**
 * Proxy port for connection. No default, must be defined.
 */
define( 'WP_PROXY_PORT', '8080' );
