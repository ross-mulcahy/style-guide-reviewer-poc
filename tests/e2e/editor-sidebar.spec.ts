import { test, expect } from '@playwright/test';
import { loginAsAdmin, useStubScenario } from './helpers/login';

async function openSampleInEditor( page: import('@playwright/test').Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=post' );
	await page.locator( 'a.row-title', { hasText: 'Style Guide Reviewer sample post' } ).first().click();
	// Wait for the block editor to mount.
	await page.waitForSelector( '.edit-post-layout, .editor-styles-wrapper', { timeout: 30_000 } );
}

test.describe( 'Editor sidebar', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'button disabled when AI provider is unavailable', async ( { page } ) => {
		await useStubScenario( page, 'unsupported' );
		await openSampleInEditor( page );

		// Open the sidebar from the more-menu.
		await page.getByRole( 'button', { name: /more tools/i } ).click();
		await page.getByRole( 'menuitem', { name: /style guide reviewer/i } ).click();

		await expect(
			page.getByRole( 'button', { name: /review against style guide/i } )
		).toBeDisabled();
		await expect( page.getByText( /No AI provider is configured/i ) ).toBeVisible();
	} );

	test( 'button enabled under default scenario', async ( { page } ) => {
		await useStubScenario( page, 'default' );
		await openSampleInEditor( page );

		await page.getByRole( 'button', { name: /more tools/i } ).click();
		await page.getByRole( 'menuitem', { name: /style guide reviewer/i } ).click();

		await expect(
			page.getByRole( 'button', { name: /review against style guide/i } )
		).toBeEnabled();
	} );
} );
