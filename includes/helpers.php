<?php
namespace TenUp\MU_Migration\Helpers;

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
function recursiveDelete( $dirPath, $deleteParent = true ){
    foreach(
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dirPath, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $path ) {
        $path->isFile() ? unlink( $path->getPathname() ) : rmdir( $path->getPathname() );
    }

    if( $deleteParent ) {
        rmdir( $dirPath );
    }
}