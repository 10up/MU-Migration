<?php
/**
 * @package TenUp\MU_Migration
 */
namespace TenUp\MU_Migration\Commands;

use WP_CLI;

class MUMigration extends \WP_CLI_Command {

	/**
	 * Displays General Info about MU-Migration and WordPress
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args ) {
		\cli\line( "MU-Migration version: %Yv" . TENUP_MU_MIGRATION_VERSION  . '%n');
		\cli\line();
		\cli\line( "Created by Nícholas André at 10up");
		\cli\line("Github: https://github.com/10up/MU-Migration");
		\cli\line();
	}
}

WP_CLI::add_command( 'mu-migration info', __NAMESPACE__ . '\\MUMigration' );
