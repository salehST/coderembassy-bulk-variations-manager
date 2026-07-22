<?php
/**
 * ErrorResponse unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\REST
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\REST;

use CoderEmbassy\BulkVariationsManager\REST\ErrorResponse;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\REST\ErrorResponse
 */
class ErrorResponseTest extends TestCase {

	/**
	 * make() returns WP_Error with correct shape.
	 *
	 * @return void
	 */
	public function test_make_returns_wp_error(): void {
		$error = ErrorResponse::make( 'coderembassy_bvm_test', 'Test message', 422 );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'coderembassy_bvm_test', $error->get_error_code() );
		$this->assertSame( 'Test message', $error->get_error_message() );
	}

	/**
	 * Data payload includes status and detail.
	 *
	 * @return void
	 */
	public function test_data_includes_status_and_detail(): void {
		$error = ErrorResponse::make( 'coderembassy_bvm_err', 'Something broke', 500 );
		$data  = $error->get_error_data();

		$this->assertIsArray( $data );
		$this->assertSame( 500, $data['status'] );
		$this->assertSame( 'Something broke', $data['detail'] );
	}

	/**
	 * Extra data is merged into payload.
	 *
	 * @return void
	 */
	public function test_extra_data_merged(): void {
		$error = ErrorResponse::make( 'coderembassy_bvm_extra', 'Msg', 400, array( 'field' => 'price' ) );
		$data  = $error->get_error_data();

		$this->assertSame( 400, $data['status'] );
		$this->assertSame( 'Msg', $data['detail'] );
		$this->assertSame( 'price', $data['field'] );
	}

	/**
	 * Default HTTP status is 400.
	 *
	 * @return void
	 */
	public function test_default_status_is_400(): void {
		$error = ErrorResponse::make( 'coderembassy_bvm_default', 'Bad request' );
		$data  = $error->get_error_data();

		$this->assertSame( 400, $data['status'] );
	}
}
