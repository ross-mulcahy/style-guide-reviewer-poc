import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

test.describe( 'Settings page', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'saves the guide text', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=sgr-settings' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Style Guide Reviewer' );

		const textarea = page.locator( '#sgr_guide_text' );
		await textarea.fill( 'No synergy. No leverage. Spell out numbers below ten.' );

		await page.click( 'input[type="submit"][name="submit"]' );

		// Settings API redirects back with the persisted value.
		await expect( page.locator( '#sgr_guide_text' ) ).toHaveValue(
			/No synergy\. No leverage\./
		);
	} );

	test( 'renders the upload nonce field', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=sgr-settings' );
		// The uploader's dedicated nonce must render alongside the file input.
		await expect( page.locator( 'input[name="sgr_upload_guide_nonce"]' ) ).toHaveCount( 1 );
	} );
} );
