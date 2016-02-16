<?php
/**
 * Plugin Name: MU Migration
 * Plugin URI: http://10up.com
 * Description: This is a set of WP-CLI commands to support the migration of single WordPress instances over to multisite
 * Version: 0.1.0
 * Author: Nícholas André, 10up
 * Author URI: http://10up.com
 * Text Domain: mu-migration
 * Domain Path: /languages
 *
 * @package TenUp\MU_Migration
 */

namespace TenUp\Cleveland_Clinic\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	die( esc_html__( 'Cannot access pages directly.', 'cleveland-clinic-plugin' ) );
}

// Only load this plugin once and bail if WP CLI is not present
if ( defined( 'TENUP_MU_MIGRATION_VERSION' ) || ! defined( 'WP_CLI' ) ) {
	return;
}

define( 'TENUP_MU_MIGRATION_VERSION', '0.1.0' );
define( 'TENUP_MU_MIGRATION_URL', esc_url( plugin_dir_url( __FILE__ ), array( 'http', 'https' ) ) );
define( 'TENUP_MU_MIGRATION_PATH', wp_normalize_path( dirname( __FILE__ ) . '/' ) );
define( 'TENUP_MU_MIGRATION_COMMANDS_PATH', TENUP_MU_MIGRATION_PATH . 'commands/' );

require_once( TENUP_MU_MIGRATION_COMMANDS_PATH . 'class-mu-migration-base.php'      );
require_once( TENUP_MU_MIGRATION_COMMANDS_PATH . 'class-mu-migration-export.php'    );
require_once( TENUP_MU_MIGRATION_COMMANDS_PATH . 'class-mu-migration-import.php'    );
require_once( TENUP_MU_MIGRATION_COMMANDS_PATH . 'class-mu-migration-posts.php'     );
require_once( TENUP_MU_MIGRATION_COMMANDS_PATH . 'class-mu-migration-users.php'     );