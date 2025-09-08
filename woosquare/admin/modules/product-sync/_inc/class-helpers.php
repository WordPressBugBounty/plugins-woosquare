<?php
/**
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates and open the template in the editor.
 *
 * @package Woosquare_Plus
 */

/**
 * Description of Helpers
 */
class Helpers { // phpcs:ignore

	// sync type.
	const SYNC_TYPE_MANUAL    = 0;
	const SYNC_TYPE_AUTOMATIC = 1;

	/**
	 * Stores an array of sync types.
	 *
	 * @var array $sync_types An array of sync types, such as `full` and `partial`.
	 */
	protected $sync_types;

	// sync direction.
	const SYNC_DIRECTION_WOO_TO_SQUARE = 0;
	const SYNC_DIRECTION_SQUARE_TO_WOO = 1;

	/**
	 * Stores an array of target types for synchronization.
	 *
	 * @var array $target_types An array of target types, such as `product`, `order`, and `customer`.
	 */
	protected $sync_directions;

	// target type.
	const TARGET_TYPE_PRODUCT  = 0;
	const TARGET_TYPE_CATEGORY = 1;

	/**
	 * Stores an array of target types for synchronization.
	 *
	 * @var array $target_types An array of target types, such as `product`, `order`, and `customer`.
	 */
	protected $target_types;

	// target status.
	const TARGET_STATUS_FAILURE = 0;
	const TARGET_STATUS_SUCCESS = 1;

	/**
	 * Stores an array of target statuses for synchronization.
	 *
	 * @var array $target_statuses An array of target statuses, such as `active`, `inactive`, and `deleted`.
	 */
	protected $target_statuses;

	// actions.
	const ACTION_SYNC_START = 0;
	const ACTION_ADD        = 1;
	const ACTION_UPDATE     = 2;
	const ACTION_DELETE     = 3;

	/**
	 * Stores an array of sync types.
	 *
	 * @var array $actions An array of sync types, such as `product`, `order`, and `customer`.
	 */
	protected $actions;

	/**
	 * Set class variables
	 */
	public function __construct() {

		$this->sync_types = array(
			self::SYNC_TYPE_MANUAL    => __( 'Manual' ),
			self::SYNC_TYPE_AUTOMATIC => __( 'Automatic' ),
		);

		$this->sync_directions = array(
			self::SYNC_DIRECTION_WOO_TO_SQUARE => __( 'Woo to Square' ),
			self::SYNC_DIRECTION_SQUARE_TO_WOO => __( 'Square to Woo' ),
		);
		$this->target_types    = array(
			self::TARGET_TYPE_PRODUCT  => __( 'Product' ),
			self::TARGET_TYPE_CATEGORY => __( 'Category' ),
		);
		$this->target_statuses = array(
			self::TARGET_STATUS_FAILURE => __( 'Failure' ),
			self::TARGET_STATUS_SUCCESS => __( 'Success' ),
		);

		$this->actions = array(
			self::ACTION_SYNC_START => __( 'Sync start' ),
			self::ACTION_ADD        => __( 'add' ),
			self::ACTION_UPDATE     => __( 'update' ),
			self::ACTION_DELETE     => __( 'delete' ),

		);
	}

	/**
	 * Get an array of synchronization types.
	 *
	 * @return array An array of synchronization types.
	 */
	public function get_sync_types() {
		return $this->sync_types;
	}

	/**
	 * Get an array of synchronization directions.
	 *
	 * @return array An array of synchronization directions.
	 */
	public function get_sync_directions() {
		return $this->sync_directions;
	}

	/**
	 * Get an array of target types.
	 *
	 * @return array An array of target types.
	 */
	public function get_target_types() {
		return $this->target_types;
	}

	/**
	 * Get an array of target statuses.
	 *
	 * @return array An array of target statuses.
	 */
	public function get_target_statuses() {
		return $this->target_statuses;
	}

	/**
	 * Get an array of actions.
	 *
	 * @return array An array of actions.
	 */
	public function get_actions() {
		return $this->actions;
	}

	/**
	 * Get synchronization type message for a specific key.
	 *
	 * @param string $key The key for which to retrieve the synchronization type message.
	 * @return string|null The message corresponding to the given key, NULL if not found.
	 */
	public function get_sync_type( $key ) {
		return isset( $this->sync_types[ $key ] ) ? $this->sync_types[ $key ] : null;
	}

	/**
	 * Get synchronization direction message for a specific key.
	 *
	 * @param string $key The key for which to retrieve the synchronization direction message.
	 * @return string|null The message corresponding to the given key, NULL if not found.
	 */
	public function get_sync_direction( $key ) {
		return isset( $this->sync_directions[ $key ] ) ? $this->sync_directions[ $key ] : null;
	}

	/**
	 * Get synchronization target type message for a specific key.
	 *
	 * @param string $key The key for which to retrieve the target type message.
	 * @return string|null The message corresponding to the given key, NULL if not found.
	 */
	public function get_target_type( $key ) {
		return isset( $this->target_types[ $key ] ) ? $this->target_types[ $key ] : null;
	}

	/**
	 * Get synchronization target status message for a specific key.
	 *
	 * @param string $key The key for which to retrieve the target status message.
	 * @return string|null The message corresponding to the given key, NULL if not found.
	 */
	public function get_target_status( $key ) {
		return isset( $this->target_statuses[ $key ] ) ? $this->target_statuses[ $key ] : null;
	}

	/**
	 * Get synchronization action message for a specific key.
	 *
	 * @param string $key The key for which to retrieve the action message.
	 * @return string|null The message corresponding to the given key, NULL if not found.
	 */
	public function get_action( $key ) {
		return isset( $this->actions[ $key ] ) ? $this->actions[ $key ] : null;
	}

	/**
	 * Searches for an object in a multi-dimensional array based on the value of a specific attribute.
	 *
	 * @param array  $input_array The multi-dimensional array to search.
	 * @param string $attribute The attribute of the objects in the array to search by.
	 * @param mixed  $serchvalue The value to search for.
	 *
	 * @return mixed The object found, or false if no object was found.
	 */
	public static function search_in_multi_dimension_array( $input_array, $attribute, $serchvalue ) {
		$count         = count( $input_array );
		$object_needed = false;
		for ( $i = 0; $i < $count; $i++ ) {
			$object = $input_array[ $i ];
			if ( $object[ $attribute ] === $serchvalue ) {
				$object_needed = $object;
				break;
			}
		}
		return $object_needed;
	}

	/**
	 * Log debug information to a log file.
	 *
	 * @param string $type The type of debug information (e.g., 'INFO', 'ERROR', 'DEBUG').
	 * @param mixed  $data The data to be logged.
	 */
	public static function debug_log( $type, $data ) {
		$print_r = 'print_r';
		error_log( "[$type] [" . gmdate( 'Y-m-d H:i:s' ) . '] ' . $print_r( $data, true ) . "\n", 3, __DIR__ . '/../logs.log' ); // phpcs:ignore
	}
}
