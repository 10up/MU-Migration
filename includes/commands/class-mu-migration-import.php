<?php
/**
 *  @package TenUp\MU_Migration
 */
namespace TenUp\MU_Migration\Commands;

use WP_CLI;

class ImportCommand extends MUMigrationBase {

	/**
	 * Imports all users from .csv file
	 * This command will create a map file containing the new user_id for each user, we do this because with this map file
	 * we can update the post_author of all posts with the corresponding new user ID.
	 *
	 * ## OPTIONS
	 *
	 * <inputfile>
	 * : The name of the exported .csv file
	 *
	 * ## EXAMBLES
	 *
	 *   wp mu-migration import users users.csv --map_file=ids_maps.json
	 *
	 * @synopsis <inputfile> --map_file=<map> --blog_id=<blog_id>
	 */
	public function users( $args = array(), $assoc_args = array() ) {
		$this->process_args(
			array(
				0 => '', // .csv to import users
			),
			$args,
			array(
				'blog_id' => '',
				'map_file' => 'ids_maps.json',
			),
			$assoc_args
		);


		$filename = $this->args[0];

		if ( empty( $filename ) || ! file_exists( $filename ) ) {
			WP_CLI::error( __( "Invalid input file", 'mu-migration') );
		}

		if ( empty( $this->assoc_args[ 'blog_id' ]) ) {
			WP_CLI::error( __( 'Please, provide a blog_id ', 'mu-migration') );
		}

		if ( ! is_multisite() ) {
			WP_CLI::error( __( 'You should be running multisite in order to run this command', 'mu-migration' ) );
		}

		$input_file_handler = fopen( $filename, 'r');

		$delimiter = ',';

		/**
		 * This array will hold the new id for each old id
		 *
		 * Ex:
		 * array(
		 *  'OLD_ID' => 'NEW_ID'
		 * )
		 *
		 */
		$ids_maps = array();
		$count = 0;
		$existing_users = 0;
		$labels = array();
		if ( false !== $input_file_handler ) {
			WP_CLI::line( sprintf( "Parsing %s...", $filename ) );

			$line = 0;
			while( ( $data = fgetcsv( $input_file_handler, 0, $delimiter ) ) !== false ) {
				//read the labels and skip
				if ( $line++ == 0 ) {
					$labels = $data;
					continue;
				}

				$user_data = array_combine( $labels, $data );
				$old_id = $user_data['ID'];
				unset($user_data['ID']);

				$user_exists = get_user_by( 'login', $user_data['user_login'] );

				if ( false === $user_exists ) {
					$new_id = wp_insert_user( $user_data );
					global $wpdb;
					$wpdb->update( $wpdb->users, array( 'user_pass' => $user_data['user_pass'] ), array( 'ID' => $new_id ) );
					if ( ! is_wp_error( $new_id ) ) {
						$user = new \WP_User( $new_id );

						/**
						 * Fires an action before exporting the custom user data
						 *
						 * @sinec 0.1.0
						 *
						 * @param Array $user_data The $user_data array
						 * @param WP_User $user The user object
						 */
						do_action( 'mu_migration/import/user/custom_data_before', $user_data, $user );

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
							foreach( $custom_user_data as $meta_key => $meta_value ) {
								if ( isset( $user_data[$meta_key] ) ) {
									update_user_meta( $new_id, $meta_key, sanitize_text_field( $meta_value ) );
								}
							}
						}

						/**
						 * Fires an action after exporting the custom user data
						 *
						 * @sinec 0.1.0
						 *
						 * @param Array $user_data The $user_data array
						 * @param WP_User $user The user object
						 */
						do_action( 'mu_migration/import/user/custom_data_after', $user_data, $user );

						$count++;
						$ids_maps[ $old_id ] = $new_id;
						add_user_to_blog( $this->assoc_args[ 'blog_id' ], $new_id, $user_data['role'] );
					} else {
						WP_CLI::warning( sprintf(
							__( 'An error has occurred when inserting %s: %s.', 'mu-migration'),
							$user_data['user_login'] ,
							implode( ', ', $new_id->get_error_messages() )
						) );
					}
				} else {
					WP_CLI::warning( sprintf(
						__( '%s exists, using his ID...', 'mu-migration'),
						$user_data['user_login']
					) );

					$existing_users++;
					$ids_maps[ $old_id ] = $user_exists->ID;
					add_user_to_blog( $this->assoc_args[ 'blog_id' ], $user_exists->ID, $user_data['role'] );
				}

			}

			if ( ! empty( $ids_maps ) ) {
				//Saving the ids_maps to a file
				$output_file_handler = fopen( $this->assoc_args['map_file'], 'w+' );
				fwrite( $output_file_handler, json_encode( $ids_maps ) );
				fclose( $output_file_handler );

				WP_CLI::success( sprintf(
					__( 'A map file has been created: %s', 'mu-migration' ),
					$this->assoc_args['map_file']
				) );
			}

			WP_CLI::success( sprintf(
				__( '%d users have been imported and %d users already existed', 'mu-migration' ),
				absint( $count ),
				absint( $existing_users )
			) );
		} else {
			WP_CLI::error( sprintf(
				__( 'Can not read the file %s', 'mu-migration' ),
				$filename
			) );
		}
	}

	/**
	 * Imports the tables from a single site instance
	 *
	 * This command will perform the search-replace as well as the necessary updates to make the new tables work with
	 * multisite
	 *
	 * ## OPTIONS
	 *
	 * <inputfile>
	 * : The name of the exported .sql file
	 *
	 * ## EXAMBLES
	 *
	 *   wp mu-migration import tables site.sql --old_prefix=wp_ --old_url=old_domain.com --new_url=new_domain.com
	 *
	 * @synopsis <inputfile> --blog_id=<blog_id> --old_prefix=<old>  [--old_url=<olddomain>] [--new_url=<newdomain>]
	 */
	public function tables( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		$this->process_args(
			array(
				0 => '', // .sql file to import
			),
			$args,
			array(
				'blog_id'       => '',
				'old_url'       => '',
				'new_url'       => '',
				'old_prefix'    => $wpdb->prefix,
			),
			$assoc_args
		);


		$filename = $this->args[0];

		if ( empty( $filename ) || ! file_exists( $filename ) ) {
			WP_CLI::error( __( "Invalid input file", 'mu-migration') );
		}


		if ( empty( $this->assoc_args[ 'blog_id' ]) ) {
			WP_CLI::error( __( 'Please, provide a blog_id ', 'mu-migration') );
		}

		if ( ! is_multisite() ) {
			WP_CLI::error( __( 'You should be running multisite in order to run this command', 'mu-migration' ) );
		}

		//terminates the script if sed is not installed
		$this->check_for_sed_presence( true );

		//replaces the db prefix and saves back the modifications to the sql file
		$this->replace_db_prefix( $filename, $this->assoc_args['old_prefix'] );

		$import = \WP_CLI::launch_self(
			"db import",
			array( $filename ),
			array(),
			false,
			false,
			array()
		);

		if ( 0 === $import ) {
			WP_CLI::log( __( 'Database imported', 'mu-migration' ) );

			//perform search and replace
			if ( ! empty( $this->assoc_args['old_url'] ) && ! empty( $this->assoc_args['new_url'] ) ) {
				WP_CLI::log( __( 'Running search-replace', 'mu-migration' ) );

				$urls = array( $this->assoc_args['new_url'], $this->assoc_args['old_url'] );
				$url  = '';
				//Try to run with both new_url or old_url and save the right one
				do {
					$url = array_pop( $urls );

					$search_replace = \WP_CLI::launch_self(
						"search-replace",
						array( $this->assoc_args['old_url'], $this->assoc_args['new_url'] ),
						array( 'url' => $url ),
						false,
						false,
						array()
					);


				} while( $search_replace !== 0 && count( $urls ) > 0 );


				if ( 0 === $search_replace ) {
					WP_CLI::log( __( 'Search and Replace has been successfully executed', 'mu-migration' ) );
				}

				$search_replace = \WP_CLI::launch_self(
					"search-replace",
					array( 'wp-content/uploads', 'wp-content/uploads/sites/' . $this->assoc_args['blog_id'] ),
					array( 'url' => $this->assoc_args['new_url'] ),
					false,
					false,
					array()
				);

				if ( 0 === $search_replace ) {
					WP_CLI::log( __( 'Uploads paths have been successfully executed', 'mu-migration' ) );
				}
			}

			switch_to_blog( (int) $this->assoc_args['blog_id'] );

			//Update the new tables to work properly with Multisite

			$new_wp_roles_option_key = $wpdb->prefix . 'user_roles';
			$old_wp_roles_option_key = $this->assoc_args['old_prefix'] . 'user_roles';

			//Updating user_roles option key
			$wpdb->update(
				$wpdb->options,
				array(
					'option_name' => $new_wp_roles_option_key
				),
				array(
					'option_name' => $old_wp_roles_option_key
				),
				array(
					'%s'
				),
				array(
					'%s'
				)
			);

			restore_current_blog();
		}
	}

	/**
	 * Replaces the db_prefix with a new one using sed
	 *
	 * @param $filename The filename of the sql file to which the db prefix should be replaced
	 * @param $old_db_prefix The db prefix to be replaced
	 * @param $new_db_prefix The new db prefix
	 */
	private function replace_db_prefix( $filename, $old_db_prefix, $new_db_prefix ) {
		$new_prefix = $new_db_prefix;

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
				$sed_commands[] = "s/{$regex} `{$old_db_prefix}/{$regex} `{$new_prefix}/g";
			}

			foreach( $sed_commands as $sed_command ) {
				$full_command = "sed '$sed_command' -i $filename";
				$sed_result = \WP_CLI::launch( $full_command, false, false );

				if ( 0 !== $sed_result ) {
					\WP_CLI::warning( __( 'Something went wrong while running sed', 'mu-migration' ) );
				}
			}
		}
	}

	/**
	 * Checks whether sed is available or not
	 *
	 * @param bool $exit_on_error If set to true the script will be terminated if sed is not available
	 * @return bool True if sed is available, false otherwise
	 */
	private function check_for_sed_presence( $exit_on_error = false ) {
		//test if sed exists
		$sed = \WP_CLI::launch( 'sed --version', false, false );

		if ( 0 !== $sed ) {
			if ( $exit_on_error ) {
				\WP_CLI::error( __( 'sed not present, please install sed', 'mu-migration' ) );
			}

			return false;
		}

		return true;
	}
}

WP_CLI::add_command( 'mu-migration import', __NAMESPACE__ . '\\ImportCommand' );