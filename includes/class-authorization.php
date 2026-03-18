<?php

/**
 * Authorizes WordPress to connect to a user's Airtable account
 *
 * @since 0.1.0
 */
class ATC_Authorization {
	/**
	 * Authorizes WordPress to connect to a user's Airtable account
	 *
	 * @since 0.1.0
	 */

	/**
	 * Parent plugin instance
	 *
	 * @var Airtable_Connect_Client
	 * @since 0.1.0
	 */
	private $plugin = null;

	/**
	 * URL for the authorization server
	 *
	 * @var string
	 * @since 0.1.0
	 */
	private $auth_server_url = 'https://airtable.com/oauth2/v1/authorize';

	/**
	 * Endpoint that provides an access token after a successful auth request
	 *
	 * @var string
	 * @since 0.1.0
	 */
	private $token_url = 'https://airtable.com/oauth2/v1/token';

	/**
	 * Route relative to the site that should process the auth token request
	 * response sent by the auth server
	 *
	 * @var string
	 * @since 0.1.0 
	 */
	private $redirect_path = 'airtableconnect/auth';

	/**
	 * Constructor
	 *
	 * @param Airtable_Connect_Client $plugin The parent plugin
	 * @since 0.1.0
	 */
	public function __construct( $plugin ) {
		$this->plugin       = $plugin;
		$this->redirect_uri = site_url( $this->redirect_path );

		$this->hooks();
	}

	/**
	 * Hooks
	 *
	 * @since 0.1.0
	 */
	public function hooks() {
		add_filter( 'query_vars', [ $this, 'register_auth_query_vars' ], 10 );
		add_filter( 'atc_after_options_form', [ $this, 'get_auth_request_link' ], 10, 2 );
		add_action( 'parse_request', [ $this, 'get_access_token' ], 10 );
	}

	/** 
	 * Register the query vars needed to process the auth servers token request
	 * response
	 *
	 * @param array $vars List of registered query vars
	 * @return array The updated list of registered query vars
	 * @since 0.1.0
	 */
	public function register_auth_query_vars( $vars ) {
		$vars[] = 'code';
		$vars[] = 'state';
		$vars[] = 'code_challenge';
		$vars[] = 'code_challenge_method';
		$vars[] = 'error';
		$vars[] = 'error_description';

		return $vars;
	}

	/**
	 * Get an OAuth access token and store it after being authorized to connect to Airtable
	 *
	 * @param WP Current WordPress environment
	 * @since 0.1.0
	 */
	public function get_access_token( $wp ) {
		if ( $wp->request !== $this->redirect_path ) {
			return;
		}

		$options = get_option( 'airtable_connect_settings' );
		$result  = $this->request_token( $wp->query_vars['code'], $options );

		if ( is_wp_error( $result ) ) {
			$options['connection_attempt'] = 'failure';
			$options['connection_result']  = $result;

			update_option( 'airtable_connect_settings', $options );
			wp_redirect( site_url( 'wp-admin/admin.php?page=airtable_connect' ) );
		} else if ( wp_remote_retrieve_response_code( $result ) !== 200 ) {
			$options['connection_attempt'] = 'failure';
			$options['connection_result']  = $result['response'];

			update_option( 'airtable_connect_settings', $options );
			wp_redirect( site_url( 'wp-admin/admin.php?page=airtable_connect' ) );
		} else {
			$options_updated = $this->store_access_token( $result, $options );

			if ( $options_updated ) {
				wp_redirect( site_url( 'wp-admin/admin.php?page=airtable_connect' ) );
			} else {
				$options['connection_attempt'] = 'db_update_failed';

				update_option( 'airtable_connect_settings', $options );
				wp_redirect( site_url( 'wp-admin/admin.php?page=airtable_connect' ) );
			}
		}

		exit;
	}

	/**
	 * Use the stored refresh token to renew the OAuth access token
	 *
	 * @return bool True if a new access token was created, false otherwise
	 * @since 0.1.0
	 */
	public function renew_access_token() {
		$settings      = get_option( 'airtable_connect_settings' );
		$refresh_token = $settings['refresh_token'];
		$client_id     = $settings['client_id'];
		$args          = [
			'headers' => [
				'Content-type' => 'application/x-www-form-urlencoded'
			],
			'body' => [
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
				'client_id'     => $client_id
			]
		];

		$response      = wp_remote_post( $this->token_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			return false;
		} else if ( $response_code !== 200 ) {
			return false;
		} else {
			$this->store_access_token( $response, $settings );

			return get_option( 'airtable_connect_settings' )['access_token'];
		}
	}

	/**
	 * Get markup for a button that will make an authorization request
	 *
	 * @return string The auth button markup
	 * @since 0.1.0
	*/
	public function get_auth_request_link( $after_form, $plugin_settings ) {
		if ( isset( $plugin_settings['client_id'] ) && ! empty( $plugin_settings['client_id'] ) ) {
			$request_url = $this->build_auth_request_url( $plugin_settings );
			ob_start();
			?>
			<a class="btn" href="<?php echo $request_url; ?>">Connect to Airtable</a>
			<?php

			return ob_get_clean();
		}
	}

	/**
	 * Store an access token in the database
	 *
	 * @param array $response The HTTP response array from a successful request to the OAuth server
	 * @param array $plugin_settings The plugin settings
	 * @return bool True if the token was stored, false otherwise
	 * @since 0.1.0
	 */
	private function store_access_token( $response, $plugin_settings ) {
		$access_token_expires                    = current_datetime()->add( DateInterval::createFromDateString( '45 minutes' ) );
		$data                                    = json_decode( $response['body'], true );
		$plugin_settings['access_token']         = $data['access_token'];
		$plugin_settings['access_token_expires'] = $access_token_expires;
		$plugin_settings['refresh_token']        = $data['refresh_token'];
		$plugin_settings['connection_attempt']   = 'success';

		return update_option( 'airtable_connect_settings', $plugin_settings );
	}

	/**
	 * Request an access token from the auth server
	 *
	 * @param string $auth_code The authorization code used to retrieve an access token
	 * @param array $plugin_settings The plugin settings
	 * @return mixed The auth server's response as an array if successful, WP_Error if it fails
	 * @since 0.1.0
	 */
	private function request_token( $auth_code, $plugin_settings ) {
		$body = [
			'client_id'     => $plugin_settings['client_id'],
			'redirect_uri'  => $plugin_settings['redirect_uri'],
			'code'          => $auth_code,
			'code_verifier' => $plugin_settings['code_verifier'],
			'grant_type'    => 'authorization_code'
		];

		$args = [
			'headers' => [
				'Content-type' => 'application/x-www-form-urlencoded'
			],
			'body' => $body
		];

		return wp_remote_post( $this->token_url, $args );
	}

	/**
	 * Build the URL and query params needed to make a successful authorization 
	 * request to the auth server
	 *
	 * @return string The URL suitable for a GET request to the auth server
	 * @since 0.1.0
	 */
	private function build_auth_request_url( $options ) {
		$verifier_bytes           = bin2hex( random_bytes( 22 ) );
		$code_verifier            = rtrim( strtr( base64_encode( $verifier_bytes ), "+/", "-_" ), "=" );
		$hash                     = hash( 'sha256', $code_verifier, true );
		$options['code_verifier'] = $code_verifier;
		$options['redirect_uri']  = $this->redirect_uri;

		update_option( 'airtable_connect_settings', $options );

		$query = [
			'response_type'         => 'code',
			'client_id'             => $options['client_id'],
			'redirect_uri'          => $options['redirect_uri'],
			'scope'                 => 'data.records:read data.records:write',
			'state'                 => bin2hex( random_bytes( 16 ) ),
			'code_challenge'        => rtrim( strtr( base64_encode( $hash ), "+/", "-_" ), "=" ),
			'code_challenge_method' => 'S256'
		];

		$url = $this->auth_server_url . '?' . http_build_query( $query );

		return $url;
	}
}
