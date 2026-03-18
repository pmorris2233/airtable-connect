<?php
/**
 * Connect and make requests to Airtable
 *
 * @since 0.1.0
 */

class ATC_Connect {
	/**
	 * Connect and make requests to Airtable
	 *
	 * @since 0.1.0
	 */

	/**
	 * The parent plugin
	 *
	 * @var Airtable_Connect_Client
	 * @since 0.1.0
	 */
	private $plugin = null;


	/**
	 * Constructor
	 *
	 * @param Airtable_Connect_Client $plugin The parent plugin
	 * @since 0.1.0
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function run_query( $query, $http_method ) {
		$plugin_settings = get_option( 'airtable_connect_settings' );
		$access_token    = $plugin_settings['access_token'];
		$token_expires   = $plugin_settings['access_token_expires'];
		$query_callback  = $this->get_query_type( $http_method );

		if ( $token_expires > current_datetime() ) {
			return call_user_func( $query_callback, $query, $access_token );
		} else {
			$token_refreshed = $this->renew_access_token();

			if ( $token_refreshed ) {
				return call_user_func( $query_callback, $query, $token_refreshed );
			} else {
				return false;
			}
		}
	}

	private function get_query_type( $http_method ) {
		switch ( $http_method ) {
			case 'GET':
				return [$this, 'get_records'];
			case 'POST':
				return [$this, 'create_records'];
			case 'PATCH':
				return [$this, 'update_record'];
			case 'DELETE':
				return [$this, 'delete_record'];
		}
	}

	/**
	 * Does the actual work of making the GET requests using our stored access token
	 *
	 * @param ATC_Query $query The constructed query object
	 * @param string $token The access token needed to make a request to the Airtable web API
	 * @return mixed An array of records if the request is successful, false otherwise
	 * @since 0.1.0
	 */
	private function get_records( $query, $access_token ) {
		$http_params = $query->parameters;

		$offset  = '';
		$records = [];

		while ( !is_null( $offset ) ) {
			$http_params['offset'] = $offset;
			$request_url           = $query->get_request_url() . '/?' . http_build_query( $http_params );

			$args = [
				'headers' => [
					'Authorization' => "Bearer {$access_token}",
					'Content-Type'  => 'application/json'
				],
				'timeout' => 60
			];

			$response      = wp_remote_get( $request_url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( is_wp_error( $response ) ) {
				return false;
			} else if ( $response_code !== 200 ) {
				return false;
			} else {
				$body = isset( $response['body'] ) ? json_decode( $response['body'], true ) : [];

				if ( isset( $body['records'] ) ) {
					$records = array_merge( $records, $body['records'] );
					$offset  = ( isset( $body['offset'] ) ) ? $body['offset'] : null;
				} else {
					$records = $body;
					$offset  = null;
				}
			}
		}

		$related_results = [];

		if ( $query->has_related_queries() ) {
			$records = $this->populate_related_fields( $query, $records, $access_token );
		}

		return $records;
	}

	private function create_records( $query, $access_token ) {
		$request_url = $query->get_request_url();
		$records     = $query->parameters['records'];

		$formatted_records = array_map( function ( $record ) {
			return ['fields' => $record];
		}, $records );

		$body = [
			'records' => $formatted_records
		];

		$args = [
			'method' => 'POST',
			'headers' => [
				'Authorization' => "Bearer {$access_token}",
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( $body )
		];

		$response      = wp_remote_post( $request_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			return false;
		} else if ( $response_code !== 200 ) {
			return false;
		} else {
			$body = isset( $response['body'] ) ? json_decode( $response['body'], true ) : [];
		}

		return $body['records'];
	}

	private function update_record( $query, $access_token ) {
		$request_url = $query->get_request_url() . '/' . $query->parameters['record_id'];

		$body = [
			'fields' => $query->parameters['fields'],
			'typecast' => true
		];

		$args = [
			'method' => 'PATCH',
			'headers' => [
				'Authorization' => "Bearer {$access_token}",
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( $body )
		];

		$response      = wp_remote_request( $request_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			return false;
		} else if ( $response_code !== 200 ) {
			return false;
		} else {
			$body = isset( $response['body'] ) ? json_decode( $response['body'], true ) : [];
		}

		return $body;
	}

	private function delete_record( $query, $access_token ) {
		$request_url = $query->get_request_url() . '/' . $query->parameters['record_id'];

		$args = [
			'method' => 'DELETE',
			'headers' => [
				'Authorization' => "Bearer {$access_token}",
				'Content-Type' => 'application/json',
			]
		];

		$response      = wp_remote_request( $request_url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			return false;
		} else if ( $response_code !== 200 ) {
			return false;
		} else {
			$body = isset( $response['body'] ) ? json_decode( $response['body'], true ) : [];
		}

		return $body;
	}

	/**
	 * Given a query with related sub queries, run those sub queries and populate
	 * the appropriate fields in the results
	 *
	 * @param ATC_Query $query A query object with related sub queries
	 * @param array $records The array of records from the main query
	 * @param string $access_token The access token needed to make requests to the Airtable API
	 * @return array The array of records from the main query with linked fields populated
	 * @since 0.1.0
	 */
	private function populate_related_fields( $query, $records, $access_token ) {
		$related_results = [];

		foreach ( $query->get_related_queries() as $field_name => $related_query ) {
			$related_results[$field_name] = $this->get_records( $related_query, $access_token );	
		}

		if ( !empty( $related_results ) ) {
			$records = array_map( function ( $record ) use ( $related_results ) {
				foreach ( $related_results as $field_name => $related_result ) {
					if ( isset( $record['fields'][$field_name] ) ) {
						$related_value_index					 = array_search( $record['fields'][$field_name][0], array_column( $related_result, 'id' ) );
						$record['fields'][$field_name] = $related_result[$related_value_index];
					}
				}

				return $record;
			}, $records );
		}

		return $records;
	}

	/**
	 * Wrapper to call the plugin's #renew_access_token() method
	 *
	 * @return mixed The new access token as a string if successful, false otherwise
	 * @since 0.1.0
	 */
	private function renew_access_token() {
		return $this->plugin->renew_access_token();
	}
}
