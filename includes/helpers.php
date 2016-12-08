<?php
namespace TenUp\MU_Migration\Helpers;
use Alchemy\Zippy\Zippy;

/**
 * Checks for the presence of Woocomerce
 *
 * @return bool True if WooComerce is active, false otherwise
 */
function is_woocomnerce_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters( 'active_plugins', get_option('active_plugins') )
    );
}

/**
 * Checks if $filename is a zip file by checking it's first few bytes sequence
 *
 * @param $filename The filename to check for
 * @return bool
 */
function is_zip_file( $filename ) {
    $fh = fopen( $filename, 'r' );

    if ( ! $fh ) {
        return false;
    }

    $blob = fgets( $fh, 5 );

    fclose( $fh );

    if ( strpos( $blob, 'PK' ) !== false ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Parses a url for use in search-replace by removing protocol
 *
 * @param $url
 * @return string
 */
function parse_url_for_search_replace( $url ) {
    $parsed_url = parse_url( esc_url( $url ) );

    $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';

    return $parsed_url['host'] . $path;
}

/**
 * Recursively removes a directory and its files
 *
 * @param $dirPath Dir Path to delete
 * @param bool $deleteParent
 */
function delete_folder( $dirPath, $deleteParent = true ){
    $limit = 0;

    /*
     * We may hit the recursion depth,
     * so let's keep trying until everything has been deleted.
     *
     * The limit check avoids infinite loops
     */
    while( file_exists( $dirPath ) && $limit++ < 10 ) {
        foreach(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $dirPath, \FilesystemIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::CHILD_FIRST
            ) as $path ) {
            $path->isFile() ? @unlink( $path->getPathname() ) : @rmdir( $path->getPathname() );
        }

        if( $deleteParent ) {
            rmdir( $dirPath );
        }
    }
}

/**
 * Recursively copies a directory and its files
 *
 * @param $source   The source folder
 * @param $dest     The destination folder
 */
function move_folder( $source, $dest ) {
    if ( ! file_exists( $dest ) ) {
        mkdir( $dest );
    }

    foreach (
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST ) as $item) {
        if ( $item->isDir() ) {
            $dir = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ( ! file_exists( $dir ) ) {
                mkdir( $dir );
            }
        } else {
			$dest_file = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
			if ( ! file_exists( $dest_file ) ) {
				rename( $item, $dest_file );
			}
        }
    }

}

/**
 * Extracts a zip file to the dest_dir
 *
 * @uses Zippy
 *
 * @param $filename
 * @param $dest_dir
 */
function extract( $filename, $dest_dir ) {
    $zippy = Zippy::load();

    $site_package = $zippy->open( $filename );
    mkdir( $dest_dir );
    $site_package->extract( $dest_dir );
}

/**
 * Retrieves the db prefix based on the blog_id
 *
 * @uses wpdb
 *
 * @param $blog_id
 * @return string
 */
function get_db_prefix( $blog_id ) {
    global $wpdb;

	if ( $blog_id > 1 ) {
		$new_db_prefix = $wpdb->prefix . $blog_id . '_';
	} else {
		$new_db_prefix = $wpdb->prefix;
	}

    return $new_db_prefix;
}

/**
 * Does the same thing that add_user_to_blog does, but without calling switch_to_blog()
 *
 * @param $blog_id
 * @param $user_id
 * @param $role
 * @return WP_Error
 */
function light_add_user_to_blog( $blog_id, $user_id, $role ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		restore_current_blog();
		return new WP_Error( 'user_does_not_exist', __( 'The requested user does not exist.' ) );
	}

	if ( ! get_user_meta( $user_id, 'primary_blog', true ) ) {
		update_user_meta( $user_id, 'primary_blog', $blog_id );
		$details = get_blog_details( $blog_id );
		update_user_meta( $user_id, 'source_domain', $details->domain );
	}

	$user->set_role( $role );

	/**
	 * Fires immediately after a user is added to a site.
	 *
	 * @since MU
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    User role.
	 * @param int    $blog_id Blog ID.
	 */
	do_action( 'add_user_to_blog', $user_id, $role, $blog_id );
	wp_cache_delete( $user_id, 'users' );
	wp_cache_delete( $blog_id . '_user_count', 'blog-details' );
}

/**
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
function stop_the_insanity() {
	global $wpdb, $wp_actions, $wp_filter, $wp_version;

	//reset queries
	$wpdb->queries = array();
	// Prevent wp_actions from growing out of control
	$wp_actions = array();

	/*
	 * WordPress 4.7 has a new Hook infrastructure, so we need to make sure we're accessing the global array properly
	 */
	if ( isset( $wp_filter['get_term_metadata'] ) && $wp_filter['get_term_metadata'] instanceof \WP_Hook ) {
		$filter_callbacks   = &$wp_filter['get_term_metadata']->callbacks;
	} else {
		$filter_callbacks   = &$wp_filter['get_term_metadata'];
	}

	if ( isset( $filter_callbacks[10] ) ) {
		foreach ( $filter_callbacks[10] as $hook => $content ) {
			if ( preg_match( '#^[0-9a-f]{32}lazyload_term_meta$#', $hook ) ) {
				unset( $filter_callbacks[10][ $hook ] );
			}
		}
	}
}
