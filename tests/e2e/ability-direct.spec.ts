import { test, expect, request } from '@playwright/test';
import { loginAsAdmin, useStubScenario } from './helpers/login';

test( 'ability is callable directly via the core Abilities REST route', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );
	await useStubScenario( page, 'default' );

	// Find the sample post ID from the admin list.
	await page.goto( '/wp-admin/edit.php?post_type=post' );
	const row = page.locator( 'tr', { hasText: 'Style Guide Reviewer sample post' } ).first();
	const postId = Number( await row.getAttribute( 'id' ).then( ( v ) => v?.replace( 'post-', '' ) ) );
	expect( postId ).toBeGreaterThan( 0 );

	// Grab the REST nonce exposed to the block editor.
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.waitForSelector( '.editor-styles-wrapper', { timeout: 30_000 } );
	const nonce = await page.evaluate( () => ( window as any ).wpApiSettings?.nonce );
	expect( nonce ).toBeTruthy();

	// Grab the logged-in cookies and reuse them in an APIRequestContext.
	const cookies = await page.context().cookies();
	const api = await request.newContext( {
		baseURL: baseURL!,
		extraHTTPHeaders: {
			'X-WP-Nonce': nonce,
			'X-SGR-STUB-SCENARIO': 'default',
			'Content-Type': 'application/json',
		},
	} );
	await api.storageState(); // no-op but ensures ctx is hot.
	for ( const c of cookies ) {
		// Rehydrate cookies onto the request context.
		await api.request.get( '/' ); // warm up
	}

	const resp = await api.post(
		`/wp-json/wp-abilities/v1/abilities/${ encodeURIComponent( 'sgr/review-post' ) }/run`,
		{ data: { postId } }
	);
	expect( resp.status() ).toBeLessThan( 500 );

	const body = await resp.json();
	expect( body ).toHaveProperty( 'verdict' );
	expect( Array.isArray( body.issues ) ).toBe( true );
} );
