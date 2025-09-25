import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/editor';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import './editor.scss';

const StyleGuideReviewerPanel = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [results, setResults] = useState(null);
	const [error, setError] = useState(null);

	const { postId } = useSelect((select) => ({
		postId: select('core/editor').getCurrentPostId(),
	}), []);
	
	const runReview = async () => {
		setIsLoading(true);
		setResults(null);
		setError(null);

		try {
			const response = await apiFetch({
				path: '/sgr-poc/v1/review',
				method: 'POST',
				data: { postId },
			});
			setResults(response);
		} catch (err) {
			setError(err.message || __('An unknown error occurred.', 'sgr-poc'));
		} finally {
			setIsLoading(false);
		}
	};

	const groupedIssues = results?.issues?.reduce((acc, issue) => {
		const severity = issue.severity || 'suggestion';
		if (!acc[severity]) acc[severity] = [];
		acc[severity].push(issue);
		return acc;
	}, {});

	const severityOrder = ['critical', 'major', 'minor', 'suggestion'];
    
	return (
		<PanelBody>
			<Button variant="primary" onClick={runReview} disabled={isLoading} isBusy={isLoading}>
				{__('Review against Style Guide', 'sgr-poc')}
			</Button>

			{error && <Notice status="error" isDismissible={true} onRemove={() => setError(null)}>{error}</Notice>}

			{results && (
				<div className="sgr-results">
					<div className={`sgr-verdict sgr-verdict--${results.verdict}`}>
						{__('Verdict:', 'sgr-poc')} {results.verdict.replace('_', ' ')}
					</div>

					{results.issues && results.issues.length === 0 && (
						<p>{__('No issues found. Great job!', 'sgr-poc')}</p>
					)}

					{groupedIssues &&
						severityOrder.map(severity =>
							groupedIssues[severity] && (
								<div key={severity} className={`sgr-issue-group sgr-issue-group--${severity}`}>
									<h3 className="sgr-issue-group__title">
										{severity} ({groupedIssues[severity].length})
									</h3>
									<ul>
										{groupedIssues[severity].map((issue, index) => (
											<li key={index} className="sgr-issue">
												<strong className="sgr-issue__rule-id">{issue.ruleId}</strong>
												<p>{issue.message}</p>
												
												{/* UPDATED: Show offending text instead of a button */}
												<blockquote className="sgr-issue__offending-text">
													{issue.offendingText}
												</blockquote>

												{issue.suggestion && (
													<div className="sgr-issue__suggestion">
														<strong>{__('Suggestion:', 'sgr-poc')}</strong>
														<p>{issue.suggestion}</p>
													</div>
												)}
											</li>
										))}
									</ul>
								</div>
							)
						)}
				</div>
			)}
		</PanelBody>
	);
};

registerPlugin('sgr-poc', {
	render: () => (
        <PluginSidebar
            name="sgr-poc-sidebar"
            title={__('Style Guide Reviewer', 'sgr-poc')}
            icon="text-page"
        >
            <StyleGuideReviewerPanel />
        </PluginSidebar>
    ),
});