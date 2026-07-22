import { test, expect } from '@playwright/test';

const ADMIN_USER = process.env.WP_ADMIN_USER ?? 'admin';
const ADMIN_PASS = process.env.WP_ADMIN_PASS ?? 'password';

test.describe( 'Dashboard', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', ADMIN_USER );
		await page.fill( '#user_pass', ADMIN_PASS );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
	} );

	test( 'dashboard route renders KPI shell', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=coderembassy-bulk-variations-manager#/dashboard'
		);
		await expect( page.locator( '#coderembassy-bvm-admin-root' ) ).toBeVisible();
		await expect(
			page.getByRole( 'heading', {
				name: /CoderEmbassy Bulk Variations Manager for WooCommerce/i,
			} )
		).toBeVisible( { timeout: 15_000 } );
		await expect(
			page.getByRole( 'heading', { name: /Quick start/i } )
		).toBeVisible();
	} );

	test( 'seeded jobs appear on dashboard', async ( { page, request } ) => {
		const nonceResponse = await page.goto(
			'/wp-admin/admin.php?page=coderembassy-bulk-variations-manager'
		);
		expect( nonceResponse?.ok() ).toBeTruthy();

		const restNonce = await page.evaluate( () => {
			return (
				window.wpApiSettings?.nonce || window.CoderEmbassyBvmAdmin?.nonce
			);
		} );

		for ( let i = 0; i < 3; i++ ) {
			await request.post( '/wp-json/coderembassy-bvm/v1/jobs', {
				headers: {
					'X-WP-Nonce': restNonce,
					'Content-Type': 'application/json',
				},
				data: {
					type: 'bulk_update',
					source: 'e2e',
					total_items: 10,
				},
			} );
		}

		await page.goto(
			'/wp-admin/admin.php?page=coderembassy-bulk-variations-manager#/dashboard'
		);
		await expect( page.locator( '.bv-dashboard__list-item' ) ).toHaveCount(
			3,
			{ timeout: 15_000 }
		);
	} );
} );
