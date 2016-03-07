<?php
/**
 *  @package TenUp\MU_Migration
 *
 */
namespace TenUp\MU_Migration\Commands;
use TenUp\MU_Migration\Helpers;
use Alchemy\Zippy\Zippy;


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
	 *      wp mu-migration export tables output.sql
	 *
	 * @synopsis <outputfile>
	 */
	public function tables( $args = array(), $assoc_args = array(), $verbose = true ) {
		global $wpdb;

		$this->process_args(
			array(
				0 => '', // output file name
			),
			$args,
			array(
				'db_prefix'  => '',
			),
			$assoc_args
		);

		$filename = $this->args[0];


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


		if ( 0 === $tables->return_code ) {
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
				$this->success( __( 'The export is now complete', 'mu-migration' ), $verbose );
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
	public function users( $args = array(), $assoc_args = array(), $verbose = true ) {
		$this->process_args(
			array(
				0 => 'users.csv',
			),
			$args,
			array(
				'blog_id' => '',
			),
			$assoc_args
		);

		$filename = $this->args[0];
		$delimiter = ',';

		$file_handler = fopen( $filename , 'w+' );

		if ( ! $file_handler ) {
			\WP_CLI::error( __( 'Impossible to create the file', 'mu-migration' ) );
		}

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

			/**
			 * Modify the default set of user data to be exported/imported
			 *
			 * @since 0.1.0
			 *
			 * @param Array
			 * @param WP_User $user object for the current user
			 */
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

		$this->success( sprintf(
			__( '%d users have been exported', 'mu-migration' ),
			absint( $count )
		), $verbose );

	}

	/**
	 * Export the whole site onto a zip file
	 *
	 * ## OPTIONS
	 *
	 * <outputfile>
	 * : The name of the exported .zip file
	 *
	 * ## EXAMBLES
	 *
	 *      wp mu-migration export all site.zip
	 *
	 * @synopsis [<zipfile>] [--blog_id=<blog_id>] [--plugins] [--themes] [--uploads]
	 */
	public function all( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		$site_data = array(
			'url' 			=> esc_url( home_url() ),
			'name'			=> sanitize_text_field( get_bloginfo( 'name' ) ),
			'admin_email'	=> sanitize_text_field( get_bloginfo( 'admin_email' ) ),
			'site_language' => sanitize_text_field( get_bloginfo( 'language' ) ),
			'db_prefix'		=> $wpdb->prefix,
			'plugins'		=> get_plugins()
		);

		$this->process_args(
			array(
				0 => 'mu-migration-' . sanitize_title( $site_data['name'] ) . '.zip',
			),
			$args,
			array(),
			$assoc_args
		);

		$zip_file = $this->args[0];

		$include_plugins 	= isset( $this->assoc_args['plugins'] ) ? true : false;
		$include_themes 	= isset( $this->assoc_args['themes'] ) 	? true : false;
		$include_uploads 	= isset( $this->assoc_args['uploads'] ) ? true : false;

		$users_assoc_args = array();

		if ( Helpers\is_woocomnerce_active() ) {
			$users_assoc_args = array(
				'woocomerce' => true
			);
		}

		$rand = rand();

		/*
		 * Adding rand() to the temporary file names to guarantee uniqueness
		 */
		$users_file 	= 'mu-migration-' . $rand . sanitize_title( $site_data['name'] ) . '.csv';
		$tables_file 	= 'mu-migration-' . $rand . sanitize_title( $site_data['name'] ) . '.sql';
		$meta_data_file = 'mu-migration-' . $rand . sanitize_title( $site_data['name'] ) . '.json';

		\WP_CLI::log( __( 'Exporting site meta data...', 'mu-migration' ) );
		file_put_contents( $meta_data_file, json_encode( $site_data ) );

		\WP_CLI::log( __( 'Exporting users...', 'mu-migration' ) );
		$this->users( array( $users_file ), $users_assoc_args, false );

		\WP_CLI::log( __( 'Exporting tables', 'mu-migration' ) );
		$this->tables( array( $tables_file ), array(), false );

		$zippy = Zippy::load();

		$zip = null;

		/*
		 * Removing previous $zip_file, if any
		 */
		if ( file_exists( $zip_file ) ) {
			unlink( $zip_file );
		}

		$files_to_zip = array(
			$users_file,
			$tables_file,
			$meta_data_file
		);

		if ( $include_plugins ) {
			$files_to_zip[] = WP_PLUGIN_DIR;
		}

		if ( $include_themes ) {
			$files_to_zip[] = get_theme_root();
		}

		if ( $include_uploads ) {
			$upload_dir = wp_upload_dir();
			$files_to_zip[] = $upload_dir['basedir'];
		}

		try{
			\WP_CLI::log( __( 'Zipping files....', 'mu-migration' ) );
			$zip = $zippy->create( $zip_file , $files_to_zip, true );
		} catch(\Exception $e) {
			\WP_CLI::warning( __( 'Unable to create the zip file', 'mu-migration' ) );
		}

		if ( file_exists( $users_file ) ) {
			unlink( $users_file );
		}

		if ( file_exists( $tables_file ) ) {
			unlink( $tables_file );
		}

		if ( file_exists( $meta_data_file ) ) {
			unlink( $meta_data_file );
		}

		if ( $zip !== null ) {
			\WP_CLI::success( sprintf( __( 'A zip file named %s has been created', 'mu-migration' ), $zip_file ) );
		}
	}

}

\WP_CLI::add_command( 'mu-migration export', __NAMESPACE__ . '\\ExportCommand' );
