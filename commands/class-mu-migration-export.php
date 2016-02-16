<?php
/**
 *  @package TenUp\MU_Migration
 *
 */
namespace TenUp\MU_Migration\Commands;

class ExportCommand extends MUMigrationBase {

	/**
	 * Returns the Headers (first row) for the CSV export file
	 *
	 * @return array
	 * @internal
	 */
	public static function getCSVHeaders() {
		$headers = array(
			'ID' , 'user_login', 'user_pass', 'user_nicename','user_email', 'user_url', 'user_registered', 'role',
			'user_status', 'display_name',
			// User Meta
			'rich_editing', 'admin_color', 'show_admin_bar_front', 'first_name', 'last_name', 'nickname',
			'aim', 'yim', 'jabber', 'description',
		);

		$custom_headers = apply_filters( 'mu_migration/export/user/headers', array() );

		if ( ! empty( $custom_headers ) ) {
			$headers = array_merge( $headers, $custom_headers );
		}

		return $headers;
	}

	/**
	 * Export the site's table and optionally replaces the database prefix
	 *
	 * ## OPTIONS
	 *
	 * <outputfile>
	 * : The name of the exported sql file
	 *
	 * ## EXAMBLES
	 *
	 *      wp mu-migration export tables output.sql --db_prefix=wp_2_
	 *
	 * @synopsis <outputfile> [--db_prefix=<prefix>]
	 */
	public function tables( $args = array(), $assoc_args = array() ) {
		global $wpdb;
		$rand = rand();

		$default_args = array(
			0 => "_db_{$rand}.sql", // output file name
		);

		$this->args = $args + $default_args;

		$filename = $this->args[0];

		$this->assoc_args = wp_parse_args( $assoc_args,
			array(
				'db_prefix'  => '',
			)
		);

		//test if sed exists
		$sed = \WP_CLI::launch( 'sed --version', false, false );

		if ( 0 !== $sed ) {
			\WP_CLI::error( __( 'sed not present, please install sed', 'mu-migration' ) );
		}


		$tables = \WP_CLI::launch_self(
			'db tables',
			array(),
			array(
				'format' => 'csv',
				'all-tables-with-prefix' => 1
			),
			false,
			true,
			array()
		);

		$tables_to_remove = array(
			$wpdb->prefix . 'users',
			$wpdb->prefix . 'usermeta',
			$wpdb->prefix . 'blog_versions',
			$wpdb->prefix . 'blogs',
			$wpdb->prefix . 'site',
			$wpdb->prefix . 'sitemeta',
			$wpdb->prefix . 'registration_log',
			$wpdb->prefix . 'signups',
		);


		if ( 0 === $tables->return_code) {
			$tables = $tables->stdout;
			$tables = explode( ',', $tables );

			foreach( $tables as $key => &$table ) {
				$table = trim( $table );

				if ( in_array( $table, $tables_to_remove ) ) {
					unset( $tables[$key] );
				}
			}

			$export = \WP_CLI::launch_self(
				"db export",
				array( $filename ),
				array( 'tables' => implode( ',', $tables ) ),
				false,
				false,
				array()
			);

			if ( 0 === $export ) {

				$new_prefix = $this->assoc_args['db_prefix'];

				if ( ! empty( $new_prefix ) ) {
					$mysql_chunks_regex = array(
						'DROP TABLE IF EXISTS',
						'CREATE TABLE',
						'LOCK TABLES',
						'INSERT INTO',
						'CREATE TABLE IF NOT EXISTS',
						'ALTER TABLE',
					);

					//build sed expressions
					$sed_commands = array();
					foreach( $mysql_chunks_regex as $regex ) {
						$sed_commands[] = "s/{$regex} `{$wpdb->prefix}/{$regex} `{$new_prefix}/g";
					}

					foreach( $sed_commands as $sed_command ) {
						$full_command = "sed '$sed_command' -i $filename";
						$sed_result = \WP_CLI::launch( $full_command, false, false );

						if ( 0 !== $sed_result ) {
							\WP_CLI::warning( __( 'Something went wrong while running sed', 'mu-migration' ) );
						}
					}
				}

				\WP_CLI::success( __( 'The export is now complete', 'mu-migration' ) );

			} else {
				\WP_CLI::error( __( 'Something went wrong while trying to export the database', 'mu-migration' ) );
			}

		}


	}

	/**
	 * Export all users to a .csv file
	 *
	 * ## OPTIONS
	 *
	 * <outputfile>
	 * : The name of the exported .csv file
	 *
	 * ## EXAMBLES
	 *
	 *      wp mu-migration export users output.dev --blog_id=2 --woocomerce
	 *
	 * @synopsis <outputfile> [--blog_id=<blog_id>] [--woocomerce]
	 */
	public function users( $args = array(), $assoc_args = array() ) {
		$default_args = array(
			0 => 'users.csv', // output file name
		);

		$this->args = $args + $default_args;

		$filename = $this->args[0];
		$delimiter = ',';

		$this->assoc_args = wp_parse_args( $assoc_args,
			array(
				'blog_id'  => '',
			)
		);

		$file_handler = fopen( $filename , 'w+' );

		$woocomerce = isset( $this->assoc_args['woocomerce'] ) ? true : false;

		if ( $woocomerce ) {
			add_filter( 'mu_migration/export/user/headers', function( $custom_headers ) {
				$new_headers = array(
					'billing_first_name',
					'billing_last_name',
					'billing_company',
					'billing_address_1',
					'billing_address_2',
					'billing_city',
					'billing_postcode',
					'billing_state',
					'billing_country',
					'billing_phone',
					'shipping_first_name',
					'shipping_last_name',
					'shipping_company',
					'shipping_address_1',
					'shipping_address_2',
					'shipping_city',
					'shipping_postcode',
					'shipping_state',
					'shipping_country',
					'woocommerce_generate_api_key'
				);

				return array_merge( $custom_headers, $new_headers );

			}, 10, 1 );

			add_filter( 'mu_migration/export/user/data', function( $custom_data, $user ) {
				$new_data = array(
					$user->get( 'billing_first_name' ),
					$user->get( 'billing_last_name' ),
					$user->get( 'billing_company' ),
					$user->get( 'billing_address_1' ),
					$user->get( 'billing_address_2'),
					$user->get( 'billing_city' ),
					$user->get( 'billing_postcode' ),
					$user->get( 'billing_state' ),
					$user->get( 'billing_country' ),
					$user->get( 'billing_phone' ),
					$user->get( 'shipping_first_name' ),
					$user->get( 'shipping_last_name' ),
					$user->get( 'shipping_company' ),
					$user->get( 'shipping_address_1' ),
					$user->get( 'shipping_address_2' ),
					$user->get( 'shipping_city' ),
					$user->get( 'shipping_postcode' ),
					$user->get( 'shipping_state' ),
					$user->get( 'shipping_country' ),
					$user->get( 'woocommerce_generate_api_key' ),
				);

				return array_merge( $custom_data, $new_data );
			}, 10, 2);
		}


		$headers = self::getCSVHeaders();

		fputcsv( $file_handler, $headers, $delimiter );

		$users_args = array(
			'fields' => 'all'
		);

		if ( ! empty( $this->assoc_args['blog_id'] ) ) {
			$users_args['blog_id'] = (int) $this->assoc_args['blog_id'];
		}

		$count = 0;
		$users = get_users( $users_args );
		foreach( $users as $user ) {
			$role = isset( $user->roles[0] ) ? $user->roles[0] : '';

			$data = array(
				// General Info
				$user->data->ID, $user->data->user_login, $user->data->user_pass, $user->data->user_nicename,
				$user->data->user_email, $user->data->user_url, $user->data->user_registered,  $role, $user->data->user_status,
				$user->data->display_name,

				//User Meta
				$user->get( 'rich_editing' ), $user->get( 'admin_color' ), $user->get( 'show_admin_bar_front' ),
				$user->get( 'first_name' ), $user->get( 'last_name' ), $user->get( 'nickname' ), $user->get( 'aim' ),
				$user->get( 'yim' ), $user->get( 'jabber' ), $user->get( 'description' ),
			);

			$custom_user_data = apply_filters( 'mu_migration/export/user/data', array(), $user );

			if ( ! empty( $custom_user_data ) ) {
				$data = array_merge( $data, $custom_user_data );
			}

			if ( count( $data ) !== count( $headers ) ) {
				\WP_CLI::error( __( 'The headers and data length are not matching', 'mu-migration' ) );
			}

			fputcsv( $file_handler, $data, $delimiter );
			$count++;
		}

		fclose( $file_handler );

		\WP_CLI::success( sprintf(
			__( '%d users have been exported', 'mu_migration' ),
			absint( $count )
		) );

	}
}

\WP_CLI::add_command( 'mu-migration export', __NAMESPACE__ . '\\ExportCommand' );