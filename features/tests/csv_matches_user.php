<?php

if ( ! isset( $args ) ) {
    echo 'No Arguments';
    return;
}

$file = $args[0];

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
    
            $actual_user_data 	= get_userdata( $expected_user_data['ID'] );
            $actual_user_meta 	= get_user_meta( $expected_user_data['ID'] );
    
            foreach( $expected_user_data as $key => $value ) {
                if ( isset( $actual_user_data->$key ) && $actual_user_data->$key != $expected_user_data[ $key ] ) {
                    throw new Exception( sprintf( 'User data does not match: %s -> %s:%s', $key, $actual_user_data->$key, $expected_user_data[ $key ] ) );
                }
    
                if ( isset( $actual_user_meta[ $key ] ) && $actual_user_meta[ $key ][0] != $expected_user_data[ $key ] ) {
                    throw new Exception( sprintf( 'User meta does not match: %s -> %s:%s', $key, $actual_user_meta[ $key ][0], $expected_user_data[ $key ] ) );
                }
            }
    
        }
        fclose($handle);
    }

    echo 'Success';
} catch ( Exception $e ) {
    echo 'Faiure: ' . $e->getMessage();
}
