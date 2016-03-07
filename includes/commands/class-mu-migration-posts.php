<?php
/**
 *  @package TenUp\MU_Migration
 *
 */
namespace TenUp\MU_Migration\Commands;
use TenUp\MU_Migration\Helpers;
use WP_CLI;

class PostsCommand extends MUMigrationBase {

	/**
	 * Updates all post_author values in all wp_posts records that have post_author != 0
	 *
	 * It uses a map_file, containing the new user ID for each old user ID. This map files should be passed to the
	 * command as an argument
	 *
	 * ## OPTIONS
	 *
	 * <inputfile>
	 * : The name of the json map file
	 *
	 * ## EXAMBLES
	 *
	 *   wp mu-migration posts update_author map_users.json --blog_id=2
	 *
	 * @synopsis <inputfile> --blog_id=<blog_id>
	 */
	public function update_author( $args = array(), $assoc_args = array(), $verbose = true ) {
		$this->process_args(
			array(
				0 => '', // .json map file
			),
			$args,
			array(
				'blog_id'  => '',
			),
			$assoc_args
		);

		$filename = $this->args[0];

		if ( empty( $filename ) || ! file_exists( $filename ) ) {
			WP_CLI::error( __( "Invalid input file", 'mu-migration' ) );
		}

		if ( empty( $this->assoc_args['blog_id'] ) ) {
			WP_CLI::error( __( "Please, provide a blog id", 'mu-migration' ) );
		}

		switch_to_blog( (int) $this->assoc_args['blog_id'] );

		$ids_map = json_decode( file_get_contents( $filename ) );

		if ( NULL === $ids_map ) {
			WP_CLI::error(
				__( 'An error has occurred when parsing the json file', 'mu-migration' )
			);
		}


		$equals_id 			= array();
		$author_not_found 	= array();

		$posts_args = array(
			'post_type' => get_post_types()
		);

		if (  Helpers\is_woocomnerce_active() ) {
			$posts_args['post_type'] 		= array_merge( $posts_args['post_type'], wc_get_order_types() );
			$posts_args['post_status'] 		= array_keys( wc_get_order_statuses() );
		}

		$this->all_posts(
			$posts_args,
			function() use ( &$equals_id, &$author_not_found, $ids_map, $verbose ) {
				$author = get_the_author_meta( 'ID' );
				if ( isset( $ids_map->{$author} ) ) {
					if ( $author != $ids_map->{$author} ) {
						global $wpdb;

						$wpdb->update( $wpdb->posts,
							array(
								'post_author' => $ids_map->{$author}
							),
							array(
								'ID' => get_the_ID()
							),
							array(
								'%d'
							),
							array(
								'%d'
							)
						);


						WP_CLI::log( sprintf(
							__( 'Updated post_author for "%s" (ID #%d)', 'mu-migration' ),
							get_the_title(),
							absint( get_the_ID() )
						) );

					} else {
						WP_CLI::log( sprintf(
							__( '#%d New user ID equals to the old user ID'),
							get_the_ID()
						) );
						$equals_id[] = absint( get_the_ID() );
					}
				} else {
					$this->log( sprintf(
						__( "#%d New user ID not found or it's already been updated", 'mu-migration'),
						absint( get_the_ID() )
					), $verbose );

					$author_not_found[] = absint( get_the_ID() );
				}

				if ( Helpers\is_woocomnerce_active() ) {
					$old_customer_user = get_post_meta( get_the_ID(), '_customer_user', true );

					if ( isset( $ids_map->{$old_customer_user} ) && $old_customer_user != $ids_map->{$old_customer_user} ) {
						$new_customer_user = $ids_map->{$old_customer_user};

						update_post_meta( get_the_ID(), '_customer_user', $new_customer_user );

						$this->log( sprintf(
							__( 'Updated customer_user for "%s" (ID #%d)', 'mu-migration' ),
							get_the_title(),
							absint( get_the_ID() )
						), $verbose );
					}
				}
			}
		);

		//Report
		if ( ! empty( $author_not_found ) ) {
			$this->warning( sprintf(
				__( '%d records failed to update its post_author %s', 'mu-migration' ),
				count( $author_not_found ),
				implode( ',', $author_not_found )
			), $verbose );
		}

		if ( ! empty( $equals_id ) ) {
			$this->warning( sprintf(
				__( 'The following records have the new ID equal to the old ID', 'mu-migration' ),
				implode( ',', $equals_id )
			), $verbose );
		}

		restore_current_blog();

	}
}

WP_CLI::add_command( 'mu-migration posts', __NAMESPACE__ . '\\PostsCommand' );
