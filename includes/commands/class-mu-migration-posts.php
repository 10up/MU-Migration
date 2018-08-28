<?php
/**
 * @package TenUp\MU_Migration
 */
namespace TenUp\MU_Migration\Commands;

use TenUp\MU_Migration\Helpers;
use WP_CLI;

class PostsCommand extends MUMigrationBase {

	/**
	 * Updates all post_author values in all wp_posts records that have post_author != 0.
	 *
	 * It uses a map_file, containing the new user ID for each old user ID. This map files should be passed to the
	 * command as an argument.
	 *
	 * ## OPTIONS
	 *
	 * <inputfile>
	 * : The name of the json map file
	 *
	 * ## EXAMPLES
	 *
	 *   wp mu-migration posts update_author map_users.json --blog_id=2 --uid_fields=_content_audit_owner
	 *
	 * @synopsis <inputfile> --blog_id=<blog_id> [--uid_fields=<uid_fields>]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param bool  $verbose
	 */
	public function update_author( $args = array(), $assoc_args = array(), $verbose = true ) {
		global $wpdb;

		$this->process_args(
			array(
				0 => '', // .json map file
			),
			$args,
			array(
				'blog_id'    => '',
				'uid_fields' => '',
			),
			$assoc_args
		);

		$filename = $this->args[0];

		$is_multisite = is_multisite();

		if ( empty( $filename ) || ! file_exists( $filename ) ) {
			WP_CLI::error( __( 'Invalid input file', 'mu-migration' ) );
		}

		if ( $is_multisite ) {
			switch_to_blog( (int) $this->assoc_args['blog_id'] );
		}

		$is_woocommerce = Helpers\is_woocommerce_active();

		$ids_map = json_decode( file_get_contents( $filename ) );

		if ( null === $ids_map ) {
			WP_CLI::error(
				__( 'An error has occurred when parsing the json file', 'mu-migration' )
			);
		}

		$equals_id        = array();
		$author_not_found = array();

		$this->all_records(
			__( 'Updating posts authors', 'mu-migration' ),
			$wpdb->posts,
			function ( $result ) use ( &$equals_id, &$author_not_found, $ids_map, $verbose, $is_woocommerce ) {
				$author = $result->post_author;

				if ( isset( $ids_map->{$author} ) ) {
					if ( $author != $ids_map->{$author} ) {
						global $wpdb;

						$wpdb->update( $wpdb->posts,
							array( 'post_author' => $ids_map->{$author} ),
							array( 'ID' => $result->ID ),
							array( '%d' ),
							array( '%d' )
						);

						$this->log( sprintf(
							__( 'Updated post_author for "%s" (ID #%d)', 'mu-migration' ),
							$result->post_title,
							absint( $result->ID )
						), $verbose );

					} else {
						$this->log( sprintf(
							__( '#%d New user ID equals to the old user ID' ),
							$result->ID
						), $verbose );
						$equals_id[] = absint( $result->ID );
					}
				} else {
					$this->log( sprintf(
						__( "#%d New user ID not found or it's already been updated", 'mu-migration' ),
						absint( $result->ID )
					), $verbose );

					$author_not_found[] = absint( $result->ID );
				}

				// Parse uid_fields
				$uid_fields = explode( ',', $this->assoc_args['uid_fields'] );
				// Automatically add Woocommerce user id field
				if ( $is_woocommerce ) {
					$uid_fields[] = '_customer_user';
				}
				// Iterate over fields and update them.
				foreach ( array_filter( $uid_fields ) as $f ) {
					$f = trim( $f );
					$old_user = get_post_meta( (int) $result->ID, $f, true );

					if ( isset( $ids_map->{$old_user} ) && $old_user != $ids_map->{$old_user} ) {
						$new_user = $ids_map->{$old_user};

						update_post_meta( (int) $result->ID, $f, $new_user );

						$this->log( sprintf(
							__( 'Updated %s for "%s" (ID #%d)', 'mu-migration' ),
							$f,
							$result->post_title,
							absint( $result->ID )
						), $verbose );
					}
				}
			}
		);

		// Report.
		if ( ! empty( $author_not_found ) ) {
			$this->warning( sprintf(
				__( '%d records failed to update its post_author: %s', 'mu-migration' ),
				count( $author_not_found ),
				implode( ',', $author_not_found )
			), $verbose );
		}

		if ( ! empty( $equals_id ) ) {
			$this->warning( sprintf(
				__( 'The following records have the new ID equal to the old ID: %s', 'mu-migration' ),
				implode( ',', $equals_id )
			), $verbose );
		}

		if ( $is_multisite ) {
			restore_current_blog();
		}
	}
}

WP_CLI::add_command( 'mu-migration posts', __NAMESPACE__ . '\\PostsCommand' );
