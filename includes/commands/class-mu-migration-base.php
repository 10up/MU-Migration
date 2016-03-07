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
	 * Process the provided arguments
	 *
	 * @sinec 0.2.0
	 *
	 * @param array $default_args
	 * @param array $args
	 * @param array $default_assoc_args
	 * @param array $assoc_args
	 */
	protected function process_args( $default_args = array(), $args = array(), $default_assoc_args = array(), $assoc_args = array() ) {
		$this->args 		= $args + $default_args;
		$this->assoc_args 	= wp_parse_args( $assoc_args, $default_assoc_args );
	}

	/**
	 * Run through all posts and executes the provided callback for each post
	 *
	 * @param $query_args
	 * @param $callback
	 */
	protected function all_posts( $query_args, $callback, $verbose = true ) {
		if ( ! is_callable( $callback ) ) {
			self::error( __( "The provided callback is invalid", 'mu-migration' ) );
		}

        global $wp_filter;

		$default_args = array(
			'post_type'                 => 'post',
			'posts_per_page'            => 1000,
			'post_status'               => array( 'publish', 'pending', 'draft', 'future', 'private' ),
            'cache_results '            => false,
            'update_post_meta_cache'    => false,
            'update_post_term_cache'    => false,
            'offset'                    => 0
		);

		/**
		 * Change the default args for querying posts in the all_posts method.
		 *
		 * @since 0.2.0
		 *
		 * @param Array $default_args The default args
		 */
		$default_args 	= apply_filters( 'mu-migration/all_posts/default_args', $default_args );

		$query_args 	= wp_parse_args( $query_args, $default_args );

		$query      	= new \WP_Query( $query_args );

		$counter   		= 0;

		while( $query->have_posts() ) {
			$query->the_post();

			$callback();

			$counter++;

			if ( 0 === $counter % $query_args['posts_per_page'] ) {
                /*
                 * The WP_Query class hooks a reference to one of its own methods
                 * onto filters if update_post_term_cache or
                 * update_post_meta_cache are true, which prevents PHP's garbage
                 * collector from cleaning up the WP_Query instance on long-
                 * running processes.
                 *
                 * By manually removing these callbacks (often created by things
                 * like get_posts()), we're able to properly unallocate memory
                 * once occupied by a WP_Query object.
                 */
                if ( isset( $wp_filter['get_term_metadata'][10] ) ) {
                    foreach ( $wp_filter['get_term_metadata'][10] as $hook => $content ) {
                        if ( preg_match( '#^[0-9a-f]{32}lazyload_term_meta$#', $hook ) ) {
                            unset( $wp_filter['get_term_metadata'][10][ $hook ] );
                        }
                    }
                }

				$query_args['offset'] += $query_args['posts_per_page'];
				$query = new \WP_Query( $query_args );
			}
		}

		wp_reset_postdata();

		$this->success( sprintf(
			__("%d posts were updated", 'mu-migration'),
			$counter
		), $verbose );
	}


	public function line( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::line( $msg );
		}
	}

	public function log( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::log( $msg );
		}
	}

	public function success( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::success( $msg );
		}
	}

	public function warning( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::warning( $msg );
		}
	}
}
