import { registerFormatType, toggleFormat } from '@wordpress/rich-text';
import { __ } from '@wordpress/i18n';

const FORMAT_NAME = 'sgr-poc/highlight';

registerFormatType(FORMAT_NAME, {
	title: __('Style Guide Issue', 'sgr-poc'),
	tagName: 'mark',
	className: 'sgr-highlight',
	edit({ isActive, value, onChange }) {
		// This format will be applied programmatically, so we don't need a toolbar button.
		// This 'edit' function is the minimum required to register the format.
		return null;
	},
});