import { test, expect } from '@playwright/test';
import { loginAsAdmin, useStubScenario } from './helpers/login';

test( 'second review on unchanged content is served from cache', async ( { page } ) => {
	await loginAsAdmin( page );
	await useStubScenario( page, 'default' );

	await page.goto( '/wp-admin/edit.php?post_type=post' );
	await page.locator( 'a.row-title', { hasText: 'Style Guide Reviewer sample post' } ).first().click();
	await page.waitForSelector( '.editor-styles-wrapper', { timeout: 30_000 } );

	await page.getByRole( 'button', { name: /more tools/i } ).click();
	await page.getByRole( 'menuitem', { name: /style guide reviewer/i } ).click();

	const reviewBtn = page.getByRole( 'button', { name: /review against style guide/i } );

	// First call: expect cache miss.
	const firstResp = page.waitForResponse(
		( r ) => r.url().includes( '/sgr/review-post/run' ) || r.url().includes( 'review-post%2Frun' )
	);
	await reviewBtn.click();
	const r1 = await firstResp;
	expect( r1.headers()[ 'x-sgr-cache' ] ).toBe( 'miss' );

	// Second call: expect cache hit.
	const secondResp = page.waitForResponse(
		( r ) => r.url().includes( '/sgr/review-post/run' ) || r.url().includes( 'review-post%2Frun' )
	);
	await reviewBtn.click();
	const r2 = await secondResp;
	expect( r2.headers()[ 'x-sgr-cache' ] ).toBe( 'hit' );
} );
