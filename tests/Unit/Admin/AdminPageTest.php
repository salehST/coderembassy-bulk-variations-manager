<?php
/**
 * AdminPage unit tests.
 *
 * @package CoderEmbassyBulkVariationsManager\Tests\Unit\Admin
 */

declare(strict_types=1);

namespace CoderEmbassy\BulkVariationsManager\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CoderEmbassy\BulkVariationsManager\Admin\AdminPage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoderEmbassy\BulkVariationsManager\Admin\AdminPage
 */
class AdminPageTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'CODEREMBASSY_BVM_VERSION' ) ) {
			define( 'CODEREMBASSY_BVM_VERSION', '0.1.0' );
		}
		if ( ! defined( 'CODEREMBASSY_BVM_PLUGIN_URL' ) ) {
			define( 'CODEREMBASSY_BVM_PLUGIN_URL', 'http://example.test/wp-content/plugins/coderembassy-bulk-variations-manager/' );
		}
		if ( ! defined( 'CODEREMBASSY_BVM_PLUGIN_PATH' ) ) {
			define( 'CODEREMBASSY_BVM_PLUGIN_PATH', dirname( __DIR__, 3 ) . '/' );
		}

		Functions\when( 'wp_style_add_data' )->justReturn( true );
		Functions\when( 'wp_set_script_translations' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'rest_url' )->justReturn( 'http://example.test/wp-json/coderembassy-bvm/v1/' );
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'get_user_meta' )->justReturn( 'auto' );
		Functions\when( 'get_avatar_url' )->justReturn( 'http://example.test/avatar.png' );
		Functions\when( 'current_user_can' )->justReturn( true );
	}

	/**
	 * register_menu() registers the top-level admin page.
	 *
	 * @return void
	 */
	public function test_register_menu_calls_add_menu_page(): void {
		$page = new AdminPage();

		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'CoderEmbassy Bulk Variations Manager for WooCommerce',
				'Bulk Variations',
				'manage_woocommerce',
				AdminPage::MENU_SLUG,
				$this->callback(
					static function ( $callback ): bool {
						return is_array( $callback ) && 'render_page' === ( $callback[1] ?? '' );
					}
				),
				'dashicons-screenoptions',
				56
			);

		$page->register_menu();
		$this->addToAssertionCount( 1 );
	}

	/**
	 * render_page() outputs the app mount root.
	 *
	 * @return void
	 */
	public function test_render_page_outputs_admin_root(): void {
		$page = new AdminPage();
		ob_start();
		$page->render_page();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="coderembassy-bvm-admin-root"', $output );
		$this->assertStringContainsString( 'aria-busy="true"', $output );
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * enqueue_assets() skips unrelated admin screens.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_skips_unrelated_hooks(): void {
		$page = new AdminPage();

		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();

		$page->enqueue_assets( 'edit.php' );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * enqueue_assets() loads assets on plugin screens.
	 *
	 * @return void
	 */
	public function test_enqueue_assets_loads_on_plugin_screen(): void {
		$page   = new AdminPage();
		$styles = 0;
		$scripts = 0;
		$style_sources = array();
		$style_dependencies = array();
		$script_src = '';
		$localized_name = '';
		$localized_data = array();

		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $handle, string $src, array $dependencies = array() ) use ( &$styles, &$style_sources, &$style_dependencies ): bool {
				++$styles;
				$style_sources[ $handle ] = $src;
				$style_dependencies[ $handle ] = $dependencies;
				return true;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			static function ( string $handle, string $src ) use ( &$scripts, &$script_src ): bool {
				unset( $handle );
				++$scripts;
				$script_src = $src;
				return true;
			}
		);
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $handle, string $name, array $data ) use ( &$localized_name, &$localized_data ): bool {
				unset( $handle );
				$localized_name = $name;
				$localized_data = $data;
				return true;
			}
		);

		$user               = new \WP_User();
		$user->ID           = 1;
		$user->display_name = 'Tester';
		Functions\when( 'wp_get_current_user' )->justReturn( $user );

		$page->enqueue_assets( 'toplevel_page_' . AdminPage::MENU_SLUG );

		$this->assertSame( 2, $styles );
		$this->assertGreaterThanOrEqual( 1, $scripts );
		$this->assertStringContainsString( 'assets/admin/dist/index.css', $style_sources['coderembassy-bvm-grid'] );
		$this->assertStringContainsString( 'assets/admin/admin.css', $style_sources['coderembassy-bvm-admin'] );
		$this->assertSame( array( 'coderembassy-bvm-grid' ), $style_dependencies['coderembassy-bvm-admin'] );
		$this->assertStringContainsString( 'assets/admin/dist/index.js', $script_src );
		$this->assertSame( 'CoderEmbassyBvmAdmin', $localized_name );
		$this->assertArrayHasKey( 'rest_url', $localized_data );
		$this->assertArrayHasKey( 'nonce', $localized_data );
		$this->assertArrayHasKey( 'version', $localized_data );
		$this->assertArrayHasKey( 'logo_light', $localized_data );
		$this->assertArrayHasKey( 'logo_dark', $localized_data );
		$this->assertArrayHasKey( 'initial_theme', $localized_data );
		$this->assertArrayHasKey( 'current_user', $localized_data );
	}

	/**
	 * suppress_third_party_notices() clears notice hooks on BV screens.
	 *
	 * @return void
	 */
	public function test_suppress_third_party_notices_on_plugin_screen(): void {
		$page = new AdminPage();

		$screen       = new \stdClass();
		$screen->id   = 'toplevel_page_' . AdminPage::MENU_SLUG;
		$GLOBALS['current_screen'] = $screen;

		Functions\when( 'get_current_screen' )->justReturn( $screen );
		Functions\when( 'remove_all_actions' )->justReturn( true );

		$page->suppress_third_party_notices();

		$this->addToAssertionCount( 1 );
		unset( $GLOBALS['current_screen'] );
	}

	/**
	 * suppress_third_party_notices() is a no-op on unrelated screens.
	 *
	 * @return void
	 */
	public function test_suppress_third_party_notices_skips_other_screens(): void {
		$page = new AdminPage();

		$screen       = new \stdClass();
		$screen->id   = 'options-general';
		$GLOBALS['current_screen'] = $screen;

		Functions\when( 'get_current_screen' )->justReturn( $screen );

		$removed_hooks = array();
		Functions\when( 'remove_all_actions' )->alias(
			static function ( string $hook ) use ( &$removed_hooks ): void {
				$removed_hooks[] = $hook;
			}
		);

		$page->suppress_third_party_notices();

		$this->assertSame( array(), $removed_hooks );

		unset( $GLOBALS['current_screen'] );
	}

	/**
	 * append_admin_body_class() adds the BV body class token.
	 *
	 * @return void
	 */
	public function test_append_admin_body_class(): void {
		$page = new AdminPage();

		$this->assertSame(
			'wp-admin coderembassy-bvm-admin-active',
			$page->append_admin_body_class( 'wp-admin' )
		);
	}
}
