<?php
/**
 * @package TenUp\MU_Migration
 */
namespace TenUp\MU_Migration\Commands;

use WP_CLI;
use TenUp\MU_Migration\Helpers;

abstract class MUMigrationBase extends \WP_CLI_Command {
	/**
	 * Holds the command arguments
	 *
	 * @var array
	 */
	protected $args;

	/**
	 * Holds the command assoc arguments
	 *
	 * @var array
	 */
	protected $assoc_args;

	/**
	 * Process the provided arguments
	 *
	 * @since 0.2.0
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
	 * Run through all posts and execute the provided callback for each post
	 *
	 * @param array    $query_args
	 * @param callable $callback
	 * @param bool     $verbose
	 */
	protected function all_posts( $query_args, $callback, $verbose = true ) {
		if ( ! is_callable( $callback ) ) {
			self::error( __( "The provided callback is invalid", 'mu-migration' ) );
		}

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
		 * @param array $default_args The default args
		 */
		$default_args 	= apply_filters( 'mu-migration/all_posts/default_args', $default_args );

		$query_args 	= wp_parse_args( $query_args, $default_args );

		$query      	= new \WP_Query( $query_args );

		$counter   		= 0;

		$found_posts 	= 0;
		while( $query->have_posts() ) {
			$query->the_post();

			$callback();

			if ( 0 === $counter ) {
				$found_posts = $query->found_posts;
			}

			$counter++;

			if ( 0 === $counter % $query_args['posts_per_page'] ) {
				Helpers\stop_the_insanity();

				$this->log( sprintf( __( 'Posts Updated: %d/%d', 'mu-migration' ), $counter, $found_posts ), true );
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

	/**
	 * Run through all records on a specific table
	 *
	 * @param string   $message
	 * @param string   $table
	 * @param callable $callback
	 * @return bool
	 */
	protected function all_records( $message, $table, $callback ) {
		global $wpdb;

		$offset = 0;
		$step = 1000;

		$found_posts = $wpdb->get_col( "SELECT COUNT(ID) FROM {$table}");

		if ( ! $found_posts ) {
			return false;
		}

		$found_posts = $found_posts[0];

		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf("[%d] %s", $found_posts, $message ), (int) $found_posts, 1 );
		$progress_bar->display();

		do {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} LIMIT %d OFFSET %d", array(
					$step,
					$offset
				) )
			);

			if ( $results ) {
				foreach ( $results as $result ) {
					$callback( $result );
					$progress_bar->tick();
				}
			}

			$offset += $step;

		} while( $results );
	}

	/**
	 * Output a line
	 *
	 * @param string $msg
	 * @param bool   $verbose
	 */
	protected function line( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::line( $msg );
		}
	}

	/**
	 * Output a log message
	 *
	 * @param string $msg
	 * @param bool   $verbose
	 */
	protected function log( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::log( $msg );
		}
	}

	/**
	 * Output a success message
	 *
	 * @param string $msg
	 * @param bool   $verbose
	 */
	protected function success( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::success( $msg );
		}
	}

	/**
	 * Output a warning
	 *
	 * @param string $msg
	 * @param bool   $verbose
	 */
	protected function warning( $msg, $verbose ) {
		if ( $verbose ) {
			WP_CLI::warning( $msg );
		}
	}
}
