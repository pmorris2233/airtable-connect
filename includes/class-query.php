<?php

/**
 * Build a query for consumption by the Airtable web API
 *
 * @since 0.1.0
 */
class ATC_Query {
	/**
	 * Build a query for consumption by the Airtable web API
	 *
	 * @since 0.1.0
	 */

	/**
	 * A list of parameters for the query
	 *
	 * @var array
	 * @since 0.1.0
	 */
	public $parameters = [];

	/**
	 * The Airtable web API URL to build the query from
	 *
	 * @var string
	 * @since 0.1.0
	 */
	private $airtable_url = null;

	/**
	 * The ID of the Airtable base to query
	 *
	 * @var string
	 * @since 0.1.0
	 */
	private $base = null;

	/**
	 * The ID of the table in the specified Airatble base to query
	 *
	 * @var string
	 * @since 0.1.0
	 */
	private $table = null;

	/**
	 * A list of sub queries to run for related fields; keyed by the related field
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private $related_queries = [];

	/**
	 * Default query options
	 *
	 * @var array
	 * @since 0.1.0
	 */
	private $defaults = [
		'airtable_url' => 'https://api.airtable.com/v0',
		'view'         => null
	];

	/**
	 * Constructor
	 *
	 * @param array $options An array of options to configure the query
	 * @since 0.1.0
	 */
	public function __construct( $options ) {
		$this->set_config( $options );
	}

	/**
	 * Public getter for the related queries list
	 *
	 * @return array The list of related sub queries
	 * @since 0.1.0
	 */
	public function get_related_queries() {
		return $this->related_queries;
	}

	/**
	 * Getter for the full URL endpoint for the Airtable web API
	 *
	 * @return string The endpoint for the query
	 * @since 0.1.0
	 */
	public function get_request_url() {
		return "{$this->airtable_url}/{$this->base}/{$this->table}";
	}

	/**
	 * Adds a filter to the query's list of filters
	 *
	 * @param string $formula The forumla used to filter the results of the request 
	 * 		(see: https://support.airtable.com/docs/airtable-web-api-using-filterbyformula-or-sort-parameters)
	 * @since 0.1.0
	 */
	public function add_filter( $formula ) {
		if ( isset( $this->parameters['filterByFormula'] ) ) {
			if ( preg_match( "`^AND\((.*)\)$`i", $this->parameters['filterByFormula'], $matches ) ) {
				$existing_formulae = $matches[1];
			} else {
				$existing_formulae = $this->parameters['filterByFormula'];
			}

			$this->parameters['filterByFormula'] = "AND({$existing_formulae},{$formula})";
		} else {
			$this->parameters['filterByFormula'] = $formula;
		}
	}

	public function get_records() {
		return $this->run( 'GET' );
	}

	public function create_records( $records ) {
		$this->parameters['records'] = $records;

		return $this->run( 'POST' );
	}

	public function update_record_by_id( $id, $fields ) {
		$this->parameters['fields']    = $fields;
		$this->parameters['record_id'] = $id;

		return $this->run( 'PATCH' );
	}

	public function delete_record( $id ) {
		$this->parameters['record_id'] = $id;

		return $this->run( 'DELETE' );
	}

	/**
	 * Tell the query to populate a linked field with records from the linked table 
	 *
	 * @param string $field The name of the field containing a reference to a record in another table
	 * @param string $table The name of the table containing the linked records
	 * @since 0.1.0
	 */
	public function populate_related_field( $field, $table ) {
		$options = [
			'base' => $this->base,
			'table' => $table
		];

		$related_query = new self( $options );
		$this->related_queries[$field] = $related_query;
	}

	/**
	 * Check if the query has any related sub queries added by #populate_related_field()
	 *
	 * @return bool True if there are any sub queries, false otherwise
	 * @since 0.1.0
	 */
	public function has_related_queries() {
		return !empty( $this->related_queries );
	}

	/**
	 * Merge given options with query defaults and populate instance variables
	 *
	 * @param array $options The options array for the query
	 * @since 0.1.0
	 */
	private function set_config( $options ) {
		$opts_with_defaults = array_merge(
			$this->defaults,
			$options
		);

		$this->airtable_url = $opts_with_defaults['airtable_url'];
		$this->base         = $opts_with_defaults['base'];
		$this->table        = $opts_with_defaults['table'];

		if ( !is_null( $opts_with_defaults['view'] ) ) {
			$this->parameters['view'] = $opts_with_defaults['view'];
		}
	}

	/**
	 * Run the query
	 *
	 * @return mixed An array of records if the request is successful, false otherwise
	 * @since 0.1.0
	 */
	private function run( $http_method ) {
		return Airtable_Connect()->run_query( $this, $http_method );
	}
}
