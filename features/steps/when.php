<?php

use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode,
    WP_CLI\Process;

function invoke_proc( $proc, $mode ) {
	$map = array(
		'run' => 'run_check',
		'try' => 'run'
	);
	$method = $map[ $mode ];

	return $proc->$method();
}

function capture_email_sends( $stdout ) {
	$stdout = preg_replace( '#WP-CLI test suite: Sent email to.+\n?#', '', $stdout, -1, $email_sends );
	return array( $stdout, $email_sends );
}

$steps->When( '/^I launch in the background `([^`]+)`$/',
	function ( $world, $cmd ) {
		$world->background_proc( $cmd );
	}
);

$steps->When( '/^I (run|try) `([^`]+)`$/',
	function ( $world, $mode, $cmd ) {
		$cmd = $world->replace_variables( $cmd );
		$world->result = invoke_proc( $world->proc( $cmd ), $mode );
		list( $world->result->stdout, $world->email_sends ) = capture_email_sends( $world->result->stdout );
	}
);

$steps->When( "/^I (run|try) `([^`]+)` from '([^\s]+)'$/",
	function ( $world, $mode, $cmd, $subdir ) {
		$cmd = $world->replace_variables( $cmd );
		$world->result = invoke_proc( $world->proc( $cmd, array(), $subdir ), $mode );
		list( $world->result->stdout, $world->email_sends ) = capture_email_sends( $world->result->stdout );
	}
);

$steps->When( '/^I (run|try) the previous command again$/',
	function ( $world, $mode ) {
		if ( !isset( $world->result ) )
			throw new \Exception( 'No previous command.' );

		$proc = Process::create( $world->result->command, $world->result->cwd, $world->result->env );
		$world->result = invoke_proc( $proc, $mode );
		list( $world->result->stdout, $world->email_sends ) = capture_email_sends( $world->result->stdout );
	}
);

