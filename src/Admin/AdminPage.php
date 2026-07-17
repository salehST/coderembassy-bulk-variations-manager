<?php
/**
 * Admin page hooks.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

namespace BulkVariations\Admin;

class AdminPage {
	public const MENU_SLUG = 'coderembassy-bulk-variations-manager';

	public function register_menu(): void {
		add_menu_page(
			'CoderEmbassy Bulk Variations Manager for WooCommerce',
			'Bulk Variations',
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-screenoptions',
			56
		);
	}

	public function render_page(): void {
		echo '<div id="bv-admin-root" aria-busy="true"></div>';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'bv-admin',
			BV_PLUGIN_URL . 'assets/admin/admin.css',
			array(),
			BV_VERSION
		);
		wp_style_add_data( 'bv-admin', 'rtl', 'replace' );

		$script_bundles = $this->get_script_bundles();
		$script_deps    = array_merge(
			array( 'wp-element', 'wp-i18n', 'wp-data', 'wp-components', 'wp-hooks' ),
			$this->enqueue_script_bundles( $script_bundles )
		);

		wp_enqueue_script(
			'bv-admin',
			BV_PLUGIN_URL . 'assets/admin/dist/index.js',
			array_values( array_unique( $script_deps ) ),
			BV_VERSION,
			true
		);
		wp_set_script_translations( 'bv-admin', 'coderembassy-bulk-variations-manager', BV_PLUGIN_PATH . 'languages' );

		$user = wp_get_current_user();
		wp_localize_script(
			'bv-admin',
			'BulkVariationsAdmin',
			$this->build_admin_globals( $user )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_script_bundles(): array {
		$bundles = apply_filters( 'bv_admin_script_bundles', array() );
		return is_array( $bundles ) ? $bundles : array();
	}

	/**
	 * @param array<int, array<string, mixed>> $bundles Script bundle definitions.
	 * @return array<int, string> Enqueued extension script handles.
	 */
	private function enqueue_script_bundles( array $bundles ): array {
		$handles = array();
		foreach ( $bundles as $bundle ) {
			if ( ! is_array( $bundle ) ) {
				continue;
			}

			$handle  = sanitize_key( (string) ( $bundle['handle'] ?? '' ) );
			$src     = esc_url_raw( (string) ( $bundle['src'] ?? '' ) );
			$deps    = isset( $bundle['deps'] ) && is_array( $bundle['deps'] ) ? array_map( 'sanitize_key', $bundle['deps'] ) : array();
			$version = isset( $bundle['version'] ) ? (string) $bundle['version'] : BV_VERSION;
			$type    = sanitize_key( (string) ( $bundle['type'] ?? '' ) );

			if ( '' === $handle || '' === $src ) {
				continue;
			}

			wp_enqueue_script( $handle, $src, $deps, $version, true );
			$handles[] = $handle;

			if ( '' !== $type ) {
				wp_script_add_data( $handle, 'type', $type );
			}
		}
		return $handles;
	}

	/**
	 * @param \WP_User $user Current user.
	 * @return array<string, mixed>
	 */
	private function build_admin_globals( \WP_User $user ): array {
		return array(
			'rest_url'     => rest_url( 'bv/v1/' ),
			'wp_rest_url'  => rest_url( 'wp/v2/' ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'version'      => BV_VERSION,
			'logo_light'   => BV_PLUGIN_URL . 'assets/admin/logo-light.png',
			'logo_dark'    => BV_PLUGIN_URL . 'assets/admin/logo-dark.png',
			'initial_theme'=> (string) get_user_meta( (int) $user->ID, 'bv_admin_theme', true ),
			'settings'     => array(
				'theme'                    => (string) get_user_meta( (int) $user->ID, 'bv_admin_theme', true ),
				'default_product_id'       => max( 0, (int) get_user_meta( (int) $user->ID, 'bv_default_product_id', true ) ),
				'jobs_per_page'            => (int) get_user_meta( (int) $user->ID, 'bv_jobs_per_page', true ) ?: 50,
				'remove_data_on_uninstall' => (bool) get_option( 'bv_uninstall_remove_data', false ),
			),
			'current_user' => array(
				'id'           => (int) $user->ID,
				'display_name' => (string) $user->display_name,
				'avatar_url'   => (string) get_avatar_url( (int) $user->ID, array( 'size' => 48 ) ),
			),
		);
	}

	public function suppress_third_party_notices(): void {
		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || ! str_contains( (string) $screen->id, self::MENU_SLUG ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	public function append_admin_body_class( string $classes ): string {
		return trim( $classes . ' bv-admin-active' );
	}
}
