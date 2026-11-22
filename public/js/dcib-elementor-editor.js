/**
 * Dynamic Contact Info Blocks - Elementor Editor Integration
 *
 * This script refreshes the Elementor preview when navigating between
 * the editor and ensures dynamic content placeholders are updated.
 *
 * @package DCIB
 */

(function ($) {
	"use strict";

	/**
	 * Refresh Elementor preview when editor is ready.
	 */
	$( window ).on(
		"elementor:init",
		function () {
			// Listen for Elementor preview initialization.
			elementor.on(
				"preview:loaded",
				function () {
					// Force refresh of the preview to pick up any changes.
					elementor.getPanelView().setPage( "menu" );
				}
			);
		}
	);

	/**
	 * Alternative: Listen for when the preview frame is loaded.
	 * This ensures content is refreshed when switching between edit modes.
	 */
	$( window ).on(
		"load",
		function () {
			if (typeof elementor !== "undefined") {
				// Trigger a soft refresh when the editor initializes.
				setTimeout(
					function () {
						if (elementor.$previewContents) {
								// This will cause Elementor to re-render widgets.
								elementor.reloadPreview();
						}
					},
					1000
				);
			}
		}
	);
})( jQuery );
