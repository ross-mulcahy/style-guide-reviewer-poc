import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/editor';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { applyFormat, removeFormat } from '@wordpress/rich-text';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import './highlight-format';
import './editor.scss';

// A "whitelist" of common text-based blocks and the names of their RichText attributes.
// This is a much safer approach than assuming all blocks use 'content'.
const SUPPORTED_BLOCK_ATTRIBUTES = {
	'core/paragraph': ['content'],
	'core/heading': ['content'],
	'core/list': ['values'],
	'core/quote': ['value', 'citation'],
};

const stripHtml = (html) => {
	if (!html) return '';
	const doc = new DOMParser().parseFromString(html, 'text/html');
	return doc.body.textContent || '';
};

const StyleGuideReviewerPanel = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [results, setResults] = useState(null);
	const [error, setError] = useState(null);

	const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
	const blocks = useSelect((select) => select('core/block-editor').getBlocks(), []);
    const { updateBlockAttributes } = useDispatch('core/block-editor');

	// ================================================================= //
	// REWRITTEN: Safer functions that are now "block-aware"             //
	// ================================================================= //
	const clearHighlights = () => {
		blocks.forEach(block => {
			const supportedAttributes = SUPPORTED_BLOCK_ATTRIBUTES[block.name];
			if (supportedAttributes) {
				supportedAttributes.forEach(attributeName => {
					const richTextValue = block.attributes[attributeName];
					if (richTextValue) {
						const newRichTextValue = removeFormat(richTextValue, 'sgr-poc/highlight');
						// Use a dynamic key for the attribute name
						updateBlockAttributes(block.clientId, { [attributeName]: newRichTextValue });
					}
				});
			}
		});
	};

	const applyHighlights = (issues) => {
		// Step 1: Build an accurate map of where text appears in supported blocks.
		let cumulativeCharCount = 0;
		const blockTextMap = [];

		blocks.forEach(block => {
			const supportedAttributes = SUPPORTED_BLOCK_ATTRIBUTES[block.name];
			if (supportedAttributes) {
				supportedAttributes.forEach(attributeName => {
					const html = block.attributes[attributeName] || '';
					const text = stripHtml(html);
					if (text) {
						blockTextMap.push({
							clientId: block.clientId,
							attributeName,
							text,
							start: cumulativeCharCount,
							end: cumulativeCharCount + text.length,
						});
						cumulativeCharCount += text.length + 1; // +1 for separator
					}
				});
			}
		});

		// Step 2: Loop through issues and apply formats to the correct block and attribute.
		issues.forEach(issue => {
			const target = blockTextMap.find(
				block => issue.start >= block.start && issue.start < block.end + 1
			);

			if (target) {
				const blockToUpdate = blocks.find(b => b.clientId === target.clientId);
				const richTextValue = blockToUpdate.attributes[target.attributeName];
				
				if (richTextValue) {
					const localStart = issue.start - target.start;
					const localEnd = issue.end - target.start;

					const newRichTextValue = applyFormat(
						richTextValue,
						{
							type: 'sgr-poc/highlight',
							attributes: {
								class: `sgr-highlight sgr-highlight--${issue.severity}`,
								'data-message': `${issue.ruleId}: ${issue.message}`
							}
						},
						localStart,
						localEnd
					);
					updateBlockAttributes(target.clientId, { [target.attributeName]: newRichTextValue });
				}
			}
		});
	};

	const runReview = () => {
		setIsLoading(true);
		setResults(null);
		setError(null);
		clearHighlights(); // Clear old highlights first

		apiFetch({
			path: '/sgr-poc/v1/review',
			method: 'POST',
			data: { postId },
		})
			.then((response) => {
				setResults(response);
				if (response.issues && response.issues.length > 0) {
					applyHighlights(response.issues);
				}
				setIsLoading(false);
			})
			.catch((err) => {
				setError(err.message || __('An unknown error occurred.', 'sgr-poc'));
				setIsLoading(false);
			});
	};
    
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

					{results.issues && results.issues.length === 0 ? (
						<p>{__('No issues found. Great job!', 'sgr-poc')}</p>
					) : (
						<p>{results.issues.length} {results.issues.length === 1 ? __('issue found', 'sgr-poc') : __('issues found', 'sgr-poc')}. See highlights in the editor.</p>
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