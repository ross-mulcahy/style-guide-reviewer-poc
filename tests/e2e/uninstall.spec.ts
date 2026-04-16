import { test, expect, request } from '@playwright/test';
import { loginAsAdmin } from './helpers/login';

/**
 * Simulate the uninstall path by running uninstall.php via runPHP. The real
 * plugins.php delete flow goes through an AJAX confirmation that's clumsy to
 * drive here, and uninstall.php is a pure function of the DB state so
 * invoking it directly exercises the same code.
 */
test( 'uninstall.php removes all plugin data', async ( { page, baseURL } ) => {
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

	// Seed a guide so there's something to clean up.
	await api.post( '/wp-json/wp/v2/settings', {
		data: { sgr_guide_text: 'to-be-removed' },
	} );

	// Run uninstall.php in the Playground process. We use the stub mu-plugin's
	// "option" helper to observe state after.
	const runUninstall = await api.post( '/wp-json/sgr-test/v1/run-uninstall' );
	expect( runUninstall.status() ).toBe( 200 );

	const resp = await api.get( '/wp-json/sgr-test/v1/option?name=sgr_guide_text' );
	const { value } = await resp.json();
	expect( value ).toBeNull();
} );
