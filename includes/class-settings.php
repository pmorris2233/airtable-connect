<?php

/**
 * Settings for Airtable Connect
 *
 * @since 0.1.0
 */
class ATC_Settings {
	/**
	 * Settings for Airtable Connect
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
	 * The stored options for the authorization & token requests
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private $options = null;

	/**
	 * Constructor
	 *
	 * @var Airtable_Connect_Client $plugin The parent plugin
	 * @since 0.1.0
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();
	}

	/**
	 * Hooks
	 *
	 * @since 0.1.0
	 */
	public function hooks() {
		add_action( 'admin_init', [ $this, 'settings_init' ], 10 );
		add_action( 'admin_menu', [ $this, 'menu_page_init' ], 10 );
		add_action( 'admin_notices', [ $this, 'connection_info_notice' ], 10 );
	}

	/**
	 * Initialize plugin settings
	 *
	 * @since 0.1.0
	 */
	public function settings_init() {
		register_setting( 'airtable_connect', 'airtable_connect_settings' );

		add_settings_section(
			'airtable_connect_auth_settings',
			'Airtable Authorization',
			[ $this, 'render_auth_settings_content' ],
			'airtable_connect',
			[]
		);

		add_settings_field(
			'airtable_connect_auth_settings_request_fields',
			'Client ID',
			[$this, 'render_auth_settings_request_fields'],
			'airtable_connect',
			'airtable_connect_auth_settings'
		);
	}

	/**
	 * Adds a menu page for our plugin
	 *
	 * @since 0.1.0
	 */
	public function menu_page_init() {
		add_menu_page(
			'Airtable Connect Client Settings',
			'Airtable Connect',
			'manage_options',
			'airtable_connect',
			[$this, 'render_menu_page_content']
		);
	}

	/**
	 * Display the content for the plugin's menu page
	 *
	 * @since 0.1.0
	 */
	public function render_menu_page_content() {
		$this->options = get_option( 'airtable_connect_settings' );

		?>
		<h1>Airtable Connect - Client Settings</h1>
		<form action="options.php" method="post">
			<?php

			settings_fields( 'airtable_connect' );
			do_settings_sections( 'airtable_connect' );
			submit_button();
			?>
		</form>
		<?php

		echo apply_filters( 'atc_after_options_form', '', $this->options );
	}

	/**
	 * Display the content for the authorization settings
	 *
	 * @since 0.1.0
	 */
	public function render_auth_settings_content() {
		if ( $this->client_id_exists() ) {
			echo '<p>Connect to Airtable</p>';
		} else { 
			echo '<p>Provide your Airtable client ID to get started</p>';
		}
	}

	/**
	 * Display the fields for the authorization settings form
	 *
	 * @since 0.1.0
	 */
	public function render_auth_settings_request_fields() {
		$client_id = $this->options['client_id'] ?? '';
		?>
			<input type="text" name="airtable_connect_settings[client_id]" value="<?php echo $client_id; ?>">
		<?php
	}

	/**
	 * Display notices telling the user if the plugin successfully connected to
	 * Airtable
	 *
	 * @since 0.1.0
	 */
	public function connection_info_notice() {
		$options = get_option( 'airtable_connect_settings' );

		if ( isset( $options['connection_attempt'] ) ) {
			switch ( $options['connection_attempt'] ) {
				case 'success':
					?>
						<div class="notice notice-success is-dismissible"><?php _e( 'Successfully connected to Airtable', 'airtable-connect' ); ?></div>
					<?php
					break;
				case 'failure':
					?>
						<div class="notice notice-failure is-dismissible"><?php _e( 'Could not connect to Airtable', 'airtable-connect' ); ?></div>
					<?php
					break;
				case 'db_update_failed':
					?>
						<div class="notice notice-failure is-dismissible"><?php _e( 'Connected to Airtable, but a problem occurred when attempting to save settings', 'airtable-connect' ); ?></div>
					<?php
					break;
			}

			$options['connection_attempt'] = null;
			update_option( 'airtable_connect_settings', $options );
		}
	}

	/**
	 * Check if the Airtable client ID is stored in the plugin options
	 *
	 * @return bool True if the client ID is stored, false if not
	 * @since 0.1.0
	 */
	private function client_id_exists() {
		return ( isset( $this->options['client_id'] ) && ! empty( $this->options['client_id'] ) );
	}
}
