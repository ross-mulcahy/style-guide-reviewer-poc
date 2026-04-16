import { defineConfig, devices } from '@playwright/test';

const PORT = Number( process.env.SGR_PLAYGROUND_PORT ?? 9400 );
const baseURL = `http://127.0.0.1:${ PORT }`;

export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	expect: { timeout: 10_000 },
	fullyParallel: false, // Playground runs a single-site sandbox.
	workers: 1,
	retries: process.env.CI ? 2 : 0,
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { open: 'never', outputFolder: 'playwright-report' } ] ]
		: [ [ 'list' ], [ 'html', { open: 'never', outputFolder: 'playwright-report' } ] ],
	use: {
		baseURL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
	webServer: {
		command:
			`npx --no-install @wp-playground/cli server ` +
			`--blueprint=tests/playground/blueprint.json ` +
			`--blueprint-may-read-adjacent-files ` +
			`--mount=$PWD:/plugin-src ` +
			`--mount=$PWD/tests/playground:/tests ` +
			`--port=${ PORT }`,
		url: baseURL,
		reuseExistingServer: ! process.env.CI,
		timeout: 180_000,
		stdout: 'pipe',
		stderr: 'pipe',
	},
} );
