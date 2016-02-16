<?php
namespace TenUp\MU_Migration\Commands;

use WP_CLI;

abstract class MUMigrationBase extends \WP_CLI_Command {
	/**
	 * Holds the command arguments
	 *
	 * @var Array
	 */
	protected $args;

	/**
	 * Holds the command assoc arguments
	 *
	 * @var Array
	 */
	protected $assoc_args;

	/**
	 * Run through all posts and executes the provided callback for each post
	 *
	 * @param $query_args
	 * @param $callback
	 */
	protected function all_posts( $query_args, $callback ) {
		if ( ! is_callable( $callback ) ) {
			self::error( __( "The provided callback is invalid", 'mu-migration' ) );
		}

		$default_args = array(
			'post_type'         => 'post',
			'posts_per_page'    => 500,
			'post_status'       => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'paged'             => 1
		);

		/**
		 * Change the default args for querying posts in the all_posts method.
		 *
		 * @since 0.2.0
		 *
		 * @param Array $default_args The default args
		 */
		$default_args = apply_filters( 'mu-migration/all_posts/default_args', $default_args );

		$query_args 	= wp_parse_args( $query_args, $default_args );

		$query      	= new \WP_Query( $query_args );

		$counter   		= 0;

		while( $query->have_posts() ) {
			$query->the_post();

			$callback();

			$counter++;

			if ( 0 === $counter % $query_args['posts_per_page'] ) {
				$query_args['paged']++;
				$query = new \WP_Query( $query_args );
			}
		}

		wp_reset_postdata();

		WP_CLI::success( sprintf(
			__("%d posts were updated", 'mu-migration'),
			$counter
		) );
	}
}