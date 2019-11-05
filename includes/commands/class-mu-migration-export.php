<?php
/**
 * @package TenUp\MU_Migration
 */
namespace TenUp\MU_Migration\Commands;

use TenUp\MU_Migration\Helpers;
use Alchemy\Zippy\Zippy;

class ExportCommand extends MUMigrationBase {

	/**
	 * Returns the Headers (first row) for the CSV export file.
	 *
	 * @return array
	 * @internal
	 */
	public static function getCSVHeaders() {
		$headers = array(
			// General Info.
			'ID',
			'user_login',
			'user_pass',
			'user_nicename',
			'user_email',
			'user_url',
			'user_registered',
			'role',
			'user_status',
			'display_name',

			// User Meta.
			'rich_editing',
			'admin_color',
			'show_admin_bar_front',
			'first_name',
			'last_name',
			'nickname',
			'aim',
			'yim',
			'jabber',
			'description',
		);

		$custom_headers = apply_filters( 'mu_migration/export/user/headers', array() );

		if ( ! empty( $custom_headers ) ) {
			$headers = array_merge( $headers, $custom_headers );
		}

		return $headers;
	}

	/**
	 * Exports the site's database table
	 *
	 * ## OPTIONS
	 *
	 * <outputfile>
	 * : The name of the exported sql file
	 *
	 * ## EXAMPLES
	 *
	 *      wp mu-migration export tables output.sql
	 *
	 * @synopsis <outputfile> [--blog_id=<blog_id>] [--tables=<table_list>] [--non-default-tables=<table_list>]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param bool  $verbose
	 */
	public function tables( $args = array(), $assoc_args = array(), $verbose = true ) {
		global $wpdb;

		$this->process_args(
			array(
				0 => '', // output file name
			),
			$args,
			array(
				'blog_id'            => 1,
				'tables'             => '',
				'non-default-tables' => '',
			),
			$assoc_args
		);

		$filename = $this->args[0];

		if ( isset( $this->assoc_args['blog_id'] ) ) {
			$url = get_home_url( (int) $this->assoc_args['blog_id'] );
		}

		/*
		 * If the user hasn't supplied the tables he wants to export, let's get them automatically
		 */
		if ( empty( $this->assoc_args['tables'] ) ) {
			$assoc_args = array( 'format' => 'csv' );

			if ( empty( $this->assoc_args['non-default-tables'] ) && ( $this->assoc_args['blog_id'] != 1 || ! is_multisite() ) ) {
				$assoc_args['all-tables-with-prefix'] = 1;
			}

			$tables = Helpers\runcommand( 'db tables', [], $assoc_args, [ 'url' => $url ] );

			if ( 0 === $tables->return_code ) {
				$tables = $tables->stdout;
				$tables = explode( ',', $tables );

				$tables_to_remove = array(
					$wpdb->prefix . 'users',
					$wpdb->prefix . 'usermeta',
					$wpdb->prefix . 'blog_versions',
					$wpdb->prefix . 'blogs',
					$wpdb->prefix . 'site',
					$wpdb->prefix . 'sitemeta',
					$wpdb->prefix . 'registration_log',
					$wpdb->prefix . 'signups',
					$wpdb->prefix . 'sitecategories',
				);

				foreach ( $tables as $key => &$table ) {
					$table = trim( $table );

					if ( in_array( $table, $tables_to_remove ) ) {
						unset( $tables[ $key ] );
					}
				}
			}

			if ( ! empty( $this->assoc_args['non-default-tables'] ) ) {
				$non_default_tables = explode( ',', $this->assoc_args['non-default-tables'] );

				$tables = array_unique( array_merge( $tables, $non_default_tables ) );
			}
		} else {
			//get the user supplied tables list
			$tables = explode( ',', $this->assoc_args['tables'] );
		}

		if ( is_array( $tables ) && ! empty( $tables ) ) {
			$export = Helpers\runcommand( 'db export', [ $filename ], [ 'tables' => implode( ',', $tables ) ] );

			if ( 0 === $export->return_code ) {
				$this->success( __( 'The export is now complete', 'mu-migration' ), $verbose );
			} else {
				\WP_CLI::error( __( 'Something went wrong while trying to export the database', 'mu-migration' ) );
			}
		} else {
			\WP_CLI::error( __( 'Unable to get the list of tables to be exported', 'mu-migration' ) );
		}
	}

	/**
	 * Exports all users to a .csv file.
	 *
	 * ## OPTIONS
	 *
	 * <outputfile>
	 * : The name of the exported .csv file
	 *
	 * ## EXAMPLES
	 *
	 *      wp mu-migration export users output.dev --blog_id=2 --woocomerce
	 *
	 * @synopsis <outputfile> [--blog_id=<blog_id>] [--woocomerce]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param bool  $verbose
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

		$filename  = $this->args[0];
		$delimiter = ',';

		$file_handler = fopen( $filename, 'w+' );

		if ( ! $file_handler ) {
			\WP_CLI::error( __( 'Impossible to create the file', 'mu-migration' ) );
		}

		$headers = self::getCSVHeaders();

		$users_args = array(
			'fields' => 'all',
		);

		if ( ! empty( $this->assoc_args['blog_id'] ) ) {
			$users_args['blog_id'] = (int) $this->assoc_args['blog_id'];
		}

		$excluded_meta_keys = array(
			'session_tokens' => true,
			'primary_blog'   => true,
			'source_domain'  => true,
		);

		/*
		 * We don't want meta keys that depends on the db prefix
		 *
		 * @see http://stackoverflow.com/a/25316090
		 */
		$excluded_meta_keys_regex = array(
			'/capabilities$/',
			'/user_level$/',
			'/dashboard_quick_press_last_post_id$/',
			'/user-settings$/',
			'/user-settings-time$/',
		);

		$count = 0;
		$users = get_users( $users_args );
		$user_data_arr = array();

		/*
		 * This first foreach will pragmatically find all users meta stored in the usersmeta table.
		 */
		foreach ( $users as $user ) {
			$role = isset( $user->roles[0] ) ? $user->roles[0] : '';

			$user_data = array(
				// General Info.
				$user->data->ID,
				$user->data->user_login,
				$user->data->user_pass,
				$user->data->user_nicename,
				$user->data->user_email,
				$user->data->user_url,
				$user->data->user_registered,
				$role,
				$user->data->user_status,
				$user->data->display_name,

				// User Meta.
				$user->get( 'rich_editing' ),
				$user->get( 'admin_color' ),
				$user->get( 'show_admin_bar_front' ),
				$user->get( 'first_name' ),
				$user->get( 'last_name' ),
				$user->get( 'nickname' ),
				$user->get( 'aim' ),
				$user->get( 'yim' ),
				$user->get( 'jabber' ),
				$user->get( 'description' ),
			);

			/*
			 * Keeping arrays consistent, not all users have the same meta, so it's possible to have some users who
			 * don't even have a given meta key. We must assure that these users have an empty column for these fields.
			 */
			if ( count( $headers ) - count( $user_data ) > 0 ) {
				$user_temp_data_arr = array_fill( 0, count( $headers ) - count( $user_data ), '' );
				$user_data          = array_merge( $user_data, $user_temp_data_arr );
			}

			$user_data = array_combine( $headers, $user_data );

			$user_meta = get_user_meta( $user->data->ID );
			$meta_keys = array_keys( $user_meta );

			/*
			 * Removing all unwanted meta keys.
			 */
			foreach ( $meta_keys as $user_meta_key ) {
				if ( ! isset( $excluded_meta_keys[ $user_meta_key ] ) ) {
					$can_add = true;

					/*
					 * Checking for unwanted meta keys.
					 */
					foreach ( $excluded_meta_keys_regex as $regex ) {
						if ( preg_match( $regex, $user_meta_key ) ) {
							$can_add = false;
						}
					}

					if ( ! $can_add ) {
						unset( $user_meta[ $user_meta_key ] );
					}
				} else {
					unset( $user_meta[ $user_meta_key ] );
				}
			}

			// Get the meta keys again.
			$meta_keys = array_keys( $user_meta );

			foreach ( $meta_keys as $user_meta_key ) {
				$value = $user_meta[ $user_meta_key ];

				// get_user_meta always return an array whe no $key is passed.
				if ( is_array( $value ) && 1 === count( $value ) ) {
					$value = $value[0];
				}

				// If it's still an array or object, then we need to serialize.
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = serialize( $value );
				}

				$user_data[ $user_meta_key ] = $value;
			}

			// Adding the meta_keys that aren't in the $headers variable to the $headers variable.
			$diff    = array_diff( $meta_keys, $headers );
			$headers = array_merge( $headers, $diff );

			/**
			 * Filters the default set of user data to be exported/imported.
			 *
			 * @since 0.1.0
			 *
			 * @param array
			 * @param \WP_User $user The user object.
			 */
			$custom_user_data = apply_filters( 'mu_migration/export/user/data', array(), $user );

			if ( ! empty( $custom_user_data ) ) {
				$user_data = array_merge( $user_data, $custom_user_data );
			}

			if ( count( array_values( $user_data ) ) !== count( $headers ) ) {
				\WP_CLI::error( __( 'The headers and data length are not matching', 'mu-migration' ) );
			}

			$user_data_arr[] = $user_data;
			$count++;
		}

		/*
		 * Now that we have all users meta keys, we can save everything into a csv file.
		 */
		fputcsv( $file_handler, $headers, $delimiter );

		foreach ( $user_data_arr as $user_data ) {
			if ( count( $headers ) - count( $user_data ) > 0 ) {
				$user_temp_data_arr = array_fill( 0, count( $headers ) - count( $user_data ), '' );
				$user_data          = array_merge( array_values( $user_data ), $user_temp_data_arr );
			}
			fputcsv( $file_handler, $user_data, $delimiter );
		}

		fclose( $file_handler );

		$this->success( sprintf(
			__( '%d users have been exported', 'mu-migration' ),
			absint( $count )
		), $verbose );

	}

	/**
	 * Exports the whole site into a zip file.
	 *
	 * ## OPTIONS
	 *
	 * <outputfile>
	 * : The name of the exported .zip file
	 *
	 * ## EXAMPLES
	 *
	 *      wp mu-migration export all site.zip
	 *
	 * @synopsis [<zipfile>] [--blog_id=<blog_id>] [--tables=<table_list>] [--non-default-tables=<table_list>] [--plugins] [--themes] [--uploads] [--verbose]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function all( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		$switched = false;

		if ( isset( $assoc_args['blog_id'] ) ) {
			Helpers\maybe_switch_to_blog( (int) $assoc_args['blog_id'] );
			$switched = true;
		}

		$verbose = false;

		if ( isset( $assoc_args['verbose'] ) ) {
			$verbose = true;
		}

		$site_data = array(
			'url'           	=> esc_url( home_url() ),
			'name'          	=> sanitize_text_field( get_bloginfo( 'name' ) ),
			'admin_email'   	=> sanitize_text_field( get_bloginfo( 'admin_email' ) ),
			'site_language' 	=> sanitize_text_field( get_bloginfo( 'language' ) ),
			'db_prefix'     	=> $wpdb->prefix,
			'plugins'       	=> get_plugins(),
			'blog_plugins' 		=> get_option( 'active_plugins' ),
			'network_plugins' 	=> is_multisite() ? get_site_option( 'active_sitewide_plugins' ) : array(),
			'blog_id'       	=> 1
		);

		if ( isset( $assoc_args['blog_id'] ) ) {
			$site_data['blog_id'] = get_current_blog_id();
		}

		$this->process_args(
			array(
				0 => 'mu-migration-' . sanitize_title( $site_data['name'] ) . '.zip',
			),
			$args,
			array(
				'blog_id'            => false,
				'tables'             => '',
				'non-default-tables' => '',
			),
			$assoc_args
		);

		$zip_file = $this->args[0];

		$include_plugins = isset( $this->assoc_args['plugins'] ) ? true : false;
		$include_themes  = isset( $this->assoc_args['themes'] ) ? true : false;
		$include_uploads = isset( $this->assoc_args['uploads'] ) ? true : false;

		$users_assoc_args  = array();
		$tables_assoc_args = array(
			'tables'             => $this->assoc_args['tables'],
			'non-default-tables' => $this->assoc_args['non-default-tables'],
		);

		if ( $this->assoc_args['blog_id'] ) {
			$users_assoc_args['blog_id']  = (int) $this->assoc_args['blog_id'];
			$tables_assoc_args['blog_id'] = (int) $this->assoc_args['blog_id'];
		}

		$rand = rand();

		/*
		 * Adding rand() to the temporary file names to guarantee uniqueness.
		 */
		$users_file     = 'mu-migration-' . $rand . sanitize_title( $site_data['name'] ) . '.csv';
		$tables_file    = 'mu-migration-' . $rand . sanitize_title( $site_data['name'] ) . '.sql';
		$meta_data_file = 'mu-migration-' . $rand . sanitize_title( $site_data['name'] ) . '.json';

		\WP_CLI::log( __( 'Exporting site meta data...', 'mu-migration' ) );
		file_put_contents( $meta_data_file, wp_json_encode( $site_data ) );

		\WP_CLI::log( __( 'Exporting users...', 'mu-migration' ) );
		$this->users( array( $users_file ), $users_assoc_args, $verbose );

		\WP_CLI::log( __( 'Exporting tables', 'mu-migration' ) );
		$this->tables( array( $tables_file ), $tables_assoc_args, $verbose );

		$zip = null;

		/*
		 * Removing previous $zip_file, if any.
		 */
		if ( file_exists( $zip_file ) ) {
			unlink( $zip_file );
		}

		$files_to_zip = array(
			$users_file     => $users_file,
			$tables_file    => $tables_file,
			$meta_data_file => $meta_data_file,
		);

		if ( $include_plugins ) {
			$files_to_zip['wp-content/plugins'] = WP_PLUGIN_DIR;
		}

		if ( $include_themes ) {
			$theme_dir = get_template_directory();
			$files_to_zip[ 'wp-content/themes/' . basename( $theme_dir ) ] = $theme_dir;
			if ( is_child_theme() ) {
				$child_theme_dir = get_stylesheet_directory();
				$files_to_zip[ 'wp-content/themes/' . basename( $child_theme_dir ) ] = $child_theme_dir;
			}
		}

		if ( $include_uploads ) {
			$upload_dir = wp_upload_dir();
			$files_to_zip['wp-content/uploads'] = $upload_dir['basedir'];
		}

		try {
			\WP_CLI::log( __( 'Zipping files....', 'mu-migration' ) );
			$zip = Helpers\zip( $zip_file, $files_to_zip );
		} catch ( \Exception $e ) {
			\WP_CLI::warning( $e->getMessage() );
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

		if ( $switched ) {
			Helpers\maybe_restore_current_blog();
		}
	}

}

\WP_CLI::add_command( 'mu-migration export', __NAMESPACE__ . '\\ExportCommand' );
