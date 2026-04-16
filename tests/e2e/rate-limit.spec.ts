import { test, expect, request } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

test( 'rate-limit surfaces a 429 after the quota is exhausted', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );

	// Pull the REST nonce.
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => ( window as any ).wpApiSettings?.nonce );
	expect( nonce ).toBeTruthy();

	const api = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
			'X-SGR-STUB-SCENARIO': 'default',
		},
	} );

	// Drop the limit to 2/min via the stub's helper endpoint.
	const setLimit = await api.post( '/wp-json/sgr-test/v1/set-rate-limit', {
		data: { limit: 2 },
	} );
	expect( setLimit.status() ).toBe( 200 );

	// Reset the user's counter.
	await api.post( '/wp-json/sgr-test/v1/reset-rate-limit' );

	// Find the sample post.
	await page.goto( '/wp-admin/edit.php?post_type=post' );
	const row = page.locator( 'tr', { hasText: 'Style Guide Reviewer sample post' } ).first();
	const postId = Number( await row.getAttribute( 'id' ).then( ( v ) => v?.replace( 'post-', '' ) ) );

	const url = `/wp-json/wp-abilities/v1/abilities/${ encodeURIComponent( 'sgr/review-post' ) }/run`;

	// First two requests: allowed. Third: 429. Cache is bypassed by varying
	// the post content via a filter would be ideal, but for this test the
	// rate-limit check runs BEFORE the cache lookup is populated, so fresh
	// calls to the same postId with the same content work because:
	//   - 1st call: cache miss → rate counter = 1 → generate → cache set
	//   - 2nd call: cache hit → returns early (no rate increment)
	// To force the path we clear the cache between calls via a helper.
	const r1 = await api.post( url, { data: { postId } } );
	expect( r1.status() ).toBeLessThan( 500 );

	// Clear cache + rate counter is not cleared (we only reset it once).
	await api.post( '/wp-json/sgr-test/v1/clear-post-cache', { data: { postId } } );
	const r2 = await api.post( url, { data: { postId } } );
	expect( r2.status() ).toBeLessThan( 500 );

	await api.post( '/wp-json/sgr-test/v1/clear-post-cache', { data: { postId } } );
	const r3 = await api.post( url, { data: { postId } } );
	expect( r3.status() ).toBe( 429 );

	// Clean up state so other specs aren't affected.
	await api.post( '/wp-json/sgr-test/v1/reset-rate-limit' );
	await api.post( '/wp-json/sgr-test/v1/set-rate-limit', { data: { limit: 0 } } );
} );
