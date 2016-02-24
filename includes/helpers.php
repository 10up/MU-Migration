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

    return $parsed_url['host'] . $parsed_url['path'];
}

/**
 * Recursively removes a directory and its files
 *
 * @param $dirPath Dir Path to delete
 * @param bool $deleteParent
 */
function delete_folder( $dirPath, $deleteParent = true ){
    foreach(
        new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dirPath, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $path ) {
        $path->isFile() ? unlink( $path->getPathname() ) : rmdir( $path->getPathname() );
    }

    if( $deleteParent ) {
        rmdir( $dirPath );
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
    switch_to_blog( $blog_id );
    $new_db_prefix = $wpdb->prefix;
    restore_current_blog();

    return $new_db_prefix;
}