<?php
/**
 *  @package TenUp\MU_Migration
 *
 */
namespace TenUp\MU_Migration\Commands;

use WP_CLI;

class UsersCommand extends MUMigrationBase {

	/**
	 * Updates/resets users passwords and optionally send a email reset link
	 *
	 *
	 * ## OPTIONS
	 *
	 * <newpassword>
	 * : The new password for the users set
	 *
	 * ## EXAMPLES
	 *
	 *   wp mu-migration users update_passwords --reset --blog_id=2 --send_email
	 *
	 * @synopsis [<newpassword>] [--blog_id=<blog_id>] [--reset] [--send_email] [--include=<users_id>]  [--exclude=<users_id>]
	 */
	public function update_passwords( $args = array(), $assoc_args = array() ) {
		$this->process_args(
			array(
				0 => '' //new password
			),
			$args,
			array(
				'blog_id'   => '',
				'role'      => '',
				'exclude'   => '',
				'include'   => '',
			),
			$assoc_args
		);

		$new_password = $this->args[0];

		$reset_passwords = false;

		if ( isset( $this->assoc_args['reset'] ) ) {
			$reset_passwords = true;
		}

		if ( ! $reset_passwords && empty( $new_password ) ) {
			WP_CLI::error( __( 'Please, provide a new password for the users', 'mu-migration' ) );
		}

		$send_email = false;

		if ( isset( $this->assoc_args['send_email'] ) ) {
			$send_email = true;
		}

		$users_args = array(
			'fields'    => 'all',
			'role'      => $this->assoc_args['role'],
			'include'   => ! empty( $this->assoc_args['include'] ) ? explode( ',', $this->assoc_args['include'] ) : array(),
			'exclude'   => ! empty( $this->assoc_args['exclude'] ) ? explode( ',', $this->assoc_args['exclude'] ) : array()
		);

		if ( ! empty( $this->assoc_args['blog_id'] ) ) {
			$users_args['blog_id'] = (int) $this->assoc_args['blog_id'];
		}

		$users = get_users( $users_args );

		foreach( $users as $user ) {

			if ( $reset_passwords ) {
				$new_password = wp_generate_password( 12, false );
			}

			wp_set_password( $new_password, $user->data->ID );

			WP_CLI::log( sprintf( __( 'Password updated for user #%d:%s', 'mu-migration' ), $user->data->ID, $user->data->user_login ) );

			if ( $send_email ) {
				$this->send_reset_link( $user->data );
			}
		}

	}

	/**
	 * Based on retrieve_passwords
	 *
	 * @param $user_data
	 *
	 * @return bool|string|\WP_Error
	 */
	private function send_reset_link( $user_data ) {

		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
		$key = get_password_reset_key( $user_data );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$message = __('A password reset has been requested for the following account:') . "\r\n\r\n";
		$message .= network_home_url( '/' ) . "\r\n\r\n";
		$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
		$message .= __('In order to log in again you have to reset your password.') . "\r\n\r\n";
		$message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
		$message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";

		if ( is_multisite() )
			$blogname = $GLOBALS['current_site']->site_name;
		else
			/*
			 * The blogname option is escaped with esc_html on the way into the database
			 * in sanitize_option we want to reverse this for the plain text arena of emails.
			 */
			$blogname = wp_specialchars_decode( get_option('blogname'), ENT_QUOTES );

		$title = sprintf( __('[%s] Password Reset'), $blogname );

		/**
		 * Filter the subject of the password reset email.
		 *
		 * @since 2.8.0
		 * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $title      Default email title.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

		/**
		 * Filter the message body of the password reset mail.
		 *
		 * @since 2.8.0
		 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $message    Default mail message.
		 * @param string  $key        The activation key.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

		if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			WP_CLI::log( __( 'The email could not be sent', 'mu-migration' ) );
		}

		return true;
	}
}

WP_CLI::add_command( 'mu-migration users', __NAMESPACE__ . '\\UsersCommand' );