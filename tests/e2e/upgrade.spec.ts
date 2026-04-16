import { test, expect, request } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

test( 'upgrade-from-POC migrates options and deletes the legacy API-key option', async ( { page, baseURL } ) => {
	await loginAsAdmin( page );

	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => ( window as any ).wpApiSettings?.nonce );

	const api = await request.newContext( {
		baseURL,
		extraHTTPHeaders: {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
		},
	} );

	// Seed 1.x-shaped state.
	const seeded = await api.post( '/wp-json/sgr-test/v1/legacy-options', {
		data: { guide: 'Legacy guide body.', apiKey: 'sk-should-be-deleted' },
	} );
	expect( seeded.status() ).toBe( 200 );

	// Trigger the upgrade by hitting the admin dashboard — plugins_loaded runs
	// maybe_upgrade, which bumps the version option after running migrations.
	await page.goto( '/wp-admin/' );

	const read = async ( name: string ) => {
		const resp = await api.get( `/wp-json/sgr-test/v1/option?name=${ name }` );
		return ( await resp.json() ).value;
	};

	expect( await read( 'sgr_guide_text' ) ).toBe( 'Legacy guide body.' );
	expect( await read( 'sgr_poc_guide_text' ) ).toBeNull();
	expect( await read( 'sgr_poc_openai' ) ).toBeNull();
	expect( await read( 'sgr_plugin_version' ) ).toBe( '2.0.0' );
} );
