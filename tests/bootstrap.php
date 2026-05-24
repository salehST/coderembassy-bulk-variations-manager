<?php
/**
 * PHPUnit bootstrap.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-cli.php';
require_once __DIR__ . '/stubs/wp-cli-utils.php';

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * Minimal WP_User stub for unit tests.
	 */
	class WP_User { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
		/**
		 * @var int
		 */
		public int $ID = 0;

		/**
		 * @var string
		 */
		public string $display_name = '';
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	/**
	 * Minimal WP_Term stub for unit tests.
	 */
	class WP_Term { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
		/**
		 * @var string
		 */
		public $slug = '';

		/**
		 * @var string
		 */
		public $name = '';

		/**
		 * @param object|null $term Term fields (matches WordPress WP_Term constructor).
		 */
		public function __construct( $term = null ) {
			if ( ! is_object( $term ) ) {
				return;
			}

			if ( isset( $term->slug ) ) {
				$this->slug = (string) $term->slug;
			}

			if ( isset( $term->name ) ) {
				$this->name = (string) $term->name;
			}
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for unit tests.
	 */
	class WP_Error { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
		/**
		 * @var string
		 */
		private string $code;

		/**
		 * @var string
		 */
		private string $message;

		/**
		 * @var array<string, mixed>
		 */
		private array $data;

		/**
		 * @param string               $code    Error code.
		 * @param string               $message Message.
		 * @param array<string, mixed> $data    Data.
		 */
		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * @return array<string, mixed>
		 */
		public function get_error_data(): array {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	/**
	 * @param string               $hook  Hook.
	 * @param array<int, mixed>    $args  Args.
	 * @param string               $group Group.
	 * @return int
	 */
	function as_enqueue_async_action( string $hook, array $args = array(), string $group = '' ): int {
		$GLOBALS['bv_test_as_queue'][] = array(
			'hook'  => $hook,
			'args'  => $args,
			'group' => $group,
			'time'  => null,
		);
		return (int) ( $GLOBALS['bv_test_as_enqueue_result'] ?? 1 );
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	/**
	 * @param string            $hook  Hook.
	 * @param array<int, mixed> $args  Args.
	 * @param string            $group Group.
	 * @return int|false
	 */
	function as_next_scheduled_action( string $hook, array $args = array(), string $group = '' ) {
		foreach ( $GLOBALS['bv_test_as_queue'] ?? array() as $queued ) {
			if (
				( $queued['hook'] ?? '' ) === $hook
				&& ( $queued['args'] ?? array() ) === $args
				&& ( '' === $group || ( $queued['group'] ?? '' ) === $group )
			) {
				return 1;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	/**
	 * @param int                  $timestamp Timestamp.
	 * @param string               $hook      Hook.
	 * @param array<int, mixed>    $args      Args.
	 * @param string               $group     Group.
	 * @return int
	 */
	function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '' ): int {
		$GLOBALS['bv_test_as_queue'][] = array(
			'hook'  => $hook,
			'args'  => $args,
			'group' => $group,
			'time'  => $timestamp,
		);
		return 1;
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request stub for unit tests.
	 */
	class WP_REST_Request { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
		/**
		 * @var array<string, mixed>
		 */
		private array $params = array();

		/**
		 * @param string $method HTTP method (unused stub).
		 * @param string $route  Route (unused stub).
		 */
		public function __construct( string $method = '', string $route = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			unset( $method, $route );
		}

		/**
		 * @param string $key   Param key.
		 * @param mixed  $value Param value.
		 * @return void
		 */
		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
		}

		/**
		 * @param string $key Param key.
		 * @return mixed
		 */
		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal WP_REST_Response stub for unit tests.
	 */
	class WP_REST_Response { // phpcs:ignore Generic.Classes.DuplicateClassName, Squiz.Classes.ValidClassName.NotCamelCaps
		/**
		 * @param mixed $data Response data.
		 */
		public function __construct( private mixed $data = null ) {
		}

		/**
		 * @return mixed
		 */
		public function get_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	/**
	 * @return int
	 */
	function as_schedule_recurring_action( int $timestamp, int $interval, string $hook, array $args = array(), string $group = '' ): int { // phpcs:ignore Generic.Files.LineLength
		unset( $timestamp, $interval, $hook, $args, $group );
		return 1;
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	/**
	 * @return false
	 */
	function as_next_scheduled_action( string $hook, array $args = array(), string $group = '' ) {
		unset( $hook, $args, $group );
		return false;
	}
}
