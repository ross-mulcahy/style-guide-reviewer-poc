import { test, expect, request } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

test( 'save_post drops the cache entry', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );

	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => ( window as any ).wpApiSettings?.nonce );

	await page.goto( '/wp-admin/edit.php?post_type=post' );
	const row = page.locator( 'tr', { hasText: 'Style Guide Reviewer sample post' } ).first();
	const postId = Number( await row.getAttribute( 'id' ).then( ( v ) => v?.replace( 'post-', '' ) ) );

	const api = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
			'X-SGR-STUB-SCENARIO': 'default',
		},
	} );

	const url = `/wp-json/wp-abilities/v1/abilities/${ encodeURIComponent( 'sgr/review-post' ) }/run`;

	// Prime the cache.
	const r1 = await api.post( url, { data: { postId } } );
	expect( r1.headers()[ 'x-sgr-cache' ] ).toBe( 'miss' );

	// Second call is a cache hit.
	const r2 = await api.post( url, { data: { postId } } );
	expect( r2.headers()[ 'x-sgr-cache' ] ).toBe( 'hit' );

	// Update the post via the REST API → should invalidate via save_post.
	const edit = await api.post( `/wp-json/wp/v2/posts/${ postId }`, {
		data: { title: 'SGR sample — edited ' + Date.now() },
	} );
	expect( edit.status() ).toBeLessThan( 400 );

	// Third call is back to a miss.
	const r3 = await api.post( url, { data: { postId } } );
	expect( r3.headers()[ 'x-sgr-cache' ] ).toBe( 'miss' );
} );
