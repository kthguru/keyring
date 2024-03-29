<?php

/**
 * Twitter service definition for Keyring. Clean implementation of OAuth1
 */

class Keyring_Service_Twitter extends Keyring_Service_OAuth1 {
	const NAME  = 'twitter';
	const LABEL = 'Twitter';

	function __construct() {
		parent::__construct();

		// Enable "basic" UI for entering key/secret
		if ( ! KEYRING__HEADLESS_MODE )
			add_action( 'keyring_twitter_manage_ui', array( $this, 'basic_ui' ) );

		$this->authorization_header = true;
		$this->authorization_realm  = "twitter.com";

		$this->set_endpoint( 'request_token', 'https://twitter.com/oauth/request_token', 'POST' );
		$this->set_endpoint( 'authorize',     'https://twitter.com/oauth/authorize',     'GET' );
		$this->set_endpoint( 'access_token',  'https://twitter.com/oauth/access_token',  'POST' );
		$this->set_endpoint( 'verify',        'https://api.twitter.com/1.1/account/verify_credentials.json', 'GET' );

		if (
			defined( 'KEYRING__TWITTER_ID' )
		&&
			defined( 'KEYRING__TWITTER_KEY' )
		&&
			defined( 'KEYRING__TWITTER_SECRET' )
		) {
			$this->app_id  = KEYRING__TWITTER_ID;
			$this->key     = KEYRING__TWITTER_KEY;
			$this->secret  = KEYRING__TWITTER_SECRET;
		} else if ( $creds = $this->get_credentials() ) {
			$this->app_id  = $creds['app_id'];
			$this->key     = $creds['key'];
			$this->secret  = $creds['secret'];
		}

		$this->consumer = new OAuthConsumer( $this->key, $this->secret, $this->callback_url );
		$this->signature_method = new OAuthSignatureMethod_HMAC_SHA1;

		$this->requires_token( true );
	}

	function parse_response( $response ) {
		return json_decode( $response );
	}

	function build_token_meta( $token ) {
		// Set the token so that we can make requests using it
		$this->set_token(
			new Keyring_Access_Token(
				$this->get_name(),
				new OAuthToken(
					$token['oauth_token'],
					$token['oauth_token_secret']
				)
			)
		);

		$response = $this->request( $this->verify_url, array( 'method' => $this->verify_method ) );
		if ( Keyring_Util::is_error( $response ) ) {
			$meta = array();
		} else {
			$meta = array(
				'user_id'    => $token['user_id'],
				'username'   => $token['screen_name'],
				'name'       => $response->name,
				'picture'    => str_replace( '_normal.', '.', $response->profile_image_url ),
				'_classname' => get_called_class(),
			);
		}

		return apply_filters( 'keyring_access_token_meta', $meta, 'twitter', $token, $response, $this );
	}

	function get_display( Keyring_Access_Token $token ) {
		return '@' . $token->get_meta( 'username' );
	}

	function test_connection() {
			$res = $this->request( 'http://api.twitter.com/1/account/verify_credentials.json' );
			if ( !Keyring_Util::is_error( $res ) )
				return true;

			return $res;
	}
}

add_action( 'keyring_load_services', array( 'Keyring_Service_Twitter', 'init' ) );
