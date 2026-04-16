import type { Page } from '@playwright/test';

/**
 * Playground's default login: admin / password. The `login: true` step in the
 * blueprint leaves us authenticated, but after a storageState reset we need
 * to re-auth in CI.
 */
export async function loginAsAdmin( page: Page ): Promise< void > {
	await page.goto( '/wp-login.php' );
	if ( page.url().includes( 'wp-admin' ) ) {
		return; // Already signed in.
	}
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

/**
 * Set the stub scenario header on every subsequent request in this page's
 * context. Applies to both apiFetch and direct navigation.
 */
export async function useStubScenario(
	page: Page,
	scenario: 'default' | 'no_issues' | 'error' | 'unsupported' | 'rate_limited'
): Promise< void > {
	await page.context().setExtraHTTPHeaders( { 'X-SGR-STUB-SCENARIO': scenario } );
}
