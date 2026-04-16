import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { PanelBody, Button, Notice, ExternalLink } from '@wordpress/components';
import { useState, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import './editor.scss';

const ABILITY_ID =
	( window.sgrEditor && window.sgrEditor.abilityId ) || 'sgr/review-post';
const SEVERITY_ORDER = [ 'critical', 'major', 'minor', 'suggestion' ];

const SEVERITY_LABELS = {
	critical: __( 'Critical', 'style-guide-reviewer' ),
	major: __( 'Major', 'style-guide-reviewer' ),
	minor: __( 'Minor', 'style-guide-reviewer' ),
	suggestion: __( 'Suggestion', 'style-guide-reviewer' ),
};

const VERDICT_LABELS = {
	pass: __( 'Pass', 'style-guide-reviewer' ),
	pass_warnings: __( 'Pass with warnings', 'style-guide-reviewer' ),
	fail: __( 'Fail', 'style-guide-reviewer' ),
};

/**
 * Invoke the `sgr/review-post` ability via the core Abilities REST route.
 *
 * We go through apiFetch directly rather than the @wordpress/abilities client
 * so we can read the X-Sgr-Cache response header in tests. The route shape
 * follows the Abilities API convention: POST to /wp-abilities/v1/abilities/{id}/run.
 *
 * @param {number} postId Post ID to review.
 */
async function executeReviewAbility( postId ) {
	const path = `/wp-abilities/v1/abilities/${ encodeURIComponent(
		ABILITY_ID
	) }/run`;
	return apiFetch( {
		path,
		method: 'POST',
		data: { postId },
		parse: true,
	} );
}

const StyleGuideReviewerPanel = () => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ results, setResults ] = useState( null );
	const [ error, setError ] = useState( null );

	const { postId } = useSelect(
		( select ) => ( {
			postId: select( 'core/editor' ).getCurrentPostId(),
		} ),
		[]
	);

	const aiAvailable = Boolean(
		window.sgrEditor && window.sgrEditor.aiAvailable
	);
	const settingsUrl =
		( window.sgrEditor && window.sgrEditor.settingsUrl ) || '';

	const runReview = useCallback( async () => {
		setIsLoading( true );
		setResults( null );
		setError( null );

		try {
			const response = await executeReviewAbility( postId );
			setResults( response );
		} catch ( err ) {
			setError(
				( err && err.message ) ||
					__(
						'An unexpected error occurred running the review.',
						'style-guide-reviewer'
					)
			);
		} finally {
			setIsLoading( false );
		}
	}, [ postId ] );

	const grouped =
		results && Array.isArray( results.issues )
			? results.issues.reduce( ( acc, issue ) => {
					const severity = SEVERITY_ORDER.includes( issue.severity )
						? issue.severity
						: 'suggestion';
					( acc[ severity ] = acc[ severity ] || [] ).push( issue );
					return acc;
			  }, {} )
			: null;

	return (
		<PanelBody>
			{ ! aiAvailable && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'No AI provider is configured for this site. Install and configure an AI connector plugin to enable reviews.',
						'style-guide-reviewer'
					) }
					{ settingsUrl && (
						<>
							{ ' ' }
							<ExternalLink href={ settingsUrl }>
								{ __(
									'Open settings',
									'style-guide-reviewer'
								) }
							</ExternalLink>
						</>
					) }
				</Notice>
			) }

			<Button
				variant="primary"
				onClick={ runReview }
				disabled={ isLoading || ! aiAvailable || ! postId }
				isBusy={ isLoading }
			>
				{ __( 'Review against Style Guide', 'style-guide-reviewer' ) }
			</Button>

			{ error && (
				<Notice
					className="sgr-notice-error"
					status="error"
					isDismissible
					onRemove={ () => setError( null ) }
				>
					{ error }
				</Notice>
			) }

			{ results && (
				<div className="sgr-results">
					<div
						className={ `sgr-verdict sgr-verdict--${ results.verdict }` }
					>
						{ sprintf(
							/* translators: %s: verdict label. */
							__( 'Verdict: %s', 'style-guide-reviewer' ),
							VERDICT_LABELS[ results.verdict ] || results.verdict
						) }
					</div>

					{ results.truncated && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'The post was too long to review in full. Only the first portion was analysed.',
								'style-guide-reviewer'
							) }
						</Notice>
					) }

					{ Array.isArray( results.issues ) &&
						results.issues.length === 0 && (
							<p className="sgr-empty">
								{ __(
									'No issues found. Great job!',
									'style-guide-reviewer'
								) }
							</p>
						) }

					{ grouped &&
						SEVERITY_ORDER.map(
							( severity ) =>
								grouped[ severity ] && (
									<div
										key={ severity }
										className={ `sgr-issue-group sgr-issue-group--${ severity }` }
									>
										<h3 className="sgr-issue-group__title">
											{ SEVERITY_LABELS[ severity ] } (
											{ grouped[ severity ].length })
										</h3>
										<ul>
											{ grouped[ severity ].map(
												( issue, index ) => (
													<li
														key={ `${ severity }-${ index }` }
														className="sgr-issue"
													>
														<strong className="sgr-issue__rule-id">
															{ issue.ruleId }
														</strong>
														<p className="sgr-issue__message">
															{ issue.message }
														</p>
														{ issue.offendingText && (
															<blockquote className="sgr-issue__offending-text">
																{
																	issue.offendingText
																}
															</blockquote>
														) }
														{ issue.suggestion && (
															<div className="sgr-issue__suggestion">
																<strong>
																	{ __(
																		'Suggestion:',
																		'style-guide-reviewer'
																	) }
																</strong>{ ' ' }
																<span>
																	{
																		issue.suggestion
																	}
																</span>
															</div>
														) }
													</li>
												)
											) }
										</ul>
									</div>
								)
						) }
				</div>
			) }
		</PanelBody>
	);
};

registerPlugin( 'sgr', {
	render: () => (
		<>
			<PluginSidebarMoreMenuItem target="sgr-sidebar" icon="text-page">
				{ __( 'Style Guide Reviewer', 'style-guide-reviewer' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="sgr-sidebar"
				title={ __( 'Style Guide Reviewer', 'style-guide-reviewer' ) }
				icon="text-page"
			>
				<StyleGuideReviewerPanel />
			</PluginSidebar>
		</>
	),
} );
