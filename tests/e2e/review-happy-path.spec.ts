import { test, expect } from '@playwright/test';
import { loginAsAdmin, useStubScenario } from './helpers/login';

test( 'happy path: issues render grouped by severity', async ( { page } ) => {
	await loginAsAdmin( page );
	await useStubScenario( page, 'default' );

	await page.goto( '/wp-admin/edit.php?post_type=post' );
	await page.locator( 'a.row-title', { hasText: 'Style Guide Reviewer sample post' } ).first().click();
	await page.waitForSelector( '.editor-styles-wrapper', { timeout: 30_000 } );

	// Open sidebar.
	await page.getByRole( 'button', { name: /more tools/i } ).click();
	await page.getByRole( 'menuitem', { name: /style guide reviewer/i } ).click();

	const reviewBtn = page.getByRole( 'button', { name: /review against style guide/i } );
	await expect( reviewBtn ).toBeEnabled();
	await reviewBtn.click();

	// Verdict.
	await expect( page.locator( '.sgr-verdict' ) ).toContainText( /verdict/i, { ignoreCase: true } );

	// One critical + two minor issues from the stub's default payload.
	await expect( page.locator( '.sgr-issue-group--critical .sgr-issue' ) ).toHaveCount( 1 );
	await expect( page.locator( '.sgr-issue-group--minor .sgr-issue' ) ).toHaveCount( 2 );

	// Offending excerpt is rendered verbatim.
	await expect( page.locator( '.sgr-issue__offending-text' ).first() ).toContainText( 'synergy' );
} );
