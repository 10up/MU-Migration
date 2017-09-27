<?php

if ( ! isset( $args ) ) {
    echo 'No Arguments';
    return;
}

$file = $args[0];
$map_file = isset( $args[1] ) ? json_decode( file_get_contents( $args[1] ) ) : false;

try {
    if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
        $line = 0;
        while ( ($data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
            // Read the labels and skip.
            if ( 0 === $line++ ) {
                $headers = $data;
                continue;
            }

            $expected_user_data = array_combine( $headers, $data );

            $user_id = $expected_user_data['ID'];
            if ( $map_file && isset( $map_file->{$user_id} ) ) {
                $user_id = (int) $map_file->{$user_id};
            }
            $actual_user_data 	= get_userdata( $user_id );
            $actual_user_meta 	= get_user_meta( $user_id );
            
            if ( is_multisite() && ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
                throw new Exception( sprintf( 'User does not belong to the exported site: %d:%d', $expected_user_data['ID'],  $user_id ) );
            }

            $skip_fields = array( 'ID', 'user_pass', 'user_registered' );
            foreach( $expected_user_data as $key => $value ) {
                //if the user already existed, let's skip some fields
                if ( $user_id !== $expected_user_data['ID'] && in_array( $key, $skip_fields, true ) ) {
                    continue;
                }
                if ( isset( $actual_user_data->$key ) && $actual_user_data->$key != $expected_user_data[ $key ] ) {
                    throw new Exception( sprintf(
                            'User data does not match: #%d:#%d  %s -> %s:%s',
                            $expected_user_data['ID'],
                            $user_id,
                            $key,
                            $actual_user_data->$key,
                            $expected_user_data[ $key ]
                        )
                    );
                }
    
                if ( isset( $actual_user_meta[ $key ] ) && $actual_user_meta[ $key ][0] != $expected_user_data[ $key ] ) {
                    throw new Exception( sprintf(
                            'User meta does not match: #%d:#%d %s -> %s:%s',
                            $expected_user_data['ID'],
                            $user_id,
                            $key,
                            $actual_user_meta[ $key ][0],
                            $expected_user_data[ $key ]
                        )
                    );
                }
            }
    
        }
        fclose($handle);
    } else {
        throw new Exception( "Cannot open file" );
    }

    echo 'Success';
} catch ( Exception $e ) {
    echo 'Faiure: ' . $e->getMessage();
}
