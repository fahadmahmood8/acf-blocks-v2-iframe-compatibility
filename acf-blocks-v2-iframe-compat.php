<?php
/**
 * Plugin Name:       ACF Blocks V2 Iframe Compatibility
 * Plugin URI:        https://advancedcustomfields.com
 * Description:       Forces the WordPress block editor to not use the iframe canvas, if there's an ACF v2 block on the site. This plugin is intended to give site owners more time to migrate to the iframe experience provided by core. It should not be used permanently.
 * Version:           1.0.1
 * Requires PHP:      7.4
 * Author:            ACF
 * Author URI:        https://advancedcustomfields.com
 * Text Domain:       abic
 * Requires Plugins:  advanced-custom-fields-pro
 */

defined( 'ABSPATH' ) || exit;

// No-op below WP 7.1 so this plugin can be installed ahead of the upgrade and activate on its own.
if ( version_compare( get_bloginfo( 'version' ), '7.1', '<' ) ) {
	return;
}

if ( defined( 'ABIC_LOADED' ) ) {
	return;
}

define( 'ABIC_LOADED', true );
define( 'ABIC_VERSION', '1.0.1' );

/**
 * Cap ACF's default block version for blocks registered without an explicit
 * ACF block version.
 *
 * ACF bumps its default to 3 on WordPress 7.1+, which changes the type of any
 * block registered without acf_block_version set. Users of this plugin want to
 * stay on the pre-7.1 defaults without touching their block registrations, so
 * mirror them: PHP-registered blocks (acf_register_block_type) get v1, JSON-
 * registered blocks (block.json) get v2. Blocks that explicitly opt into v3
 * are unaffected; the filter default only applies when no version is set.
 *
 * @param integer     $version The default block version ACF would have used.
 * @param array|null  $context The block settings/metadata array (unused).
 * @param string|null $source  Registration source: 'php', 'json', or null on
 *                             ACF versions that don't pass this arg — in which
 *                             case both paths fall through to v2.
 * @return integer
 */
add_filter( 'acf/blocks/default_block_version', 'abic_cap_default_block_version', 10, 3 );
function abic_cap_default_block_version( $version, $context = null, $source = null ) {
	return 'php' === $source ? 1 : 2;
}

/**
 * Check if any registered ACF blocks are using ACF Block Version 2 (or lower).
 *
 * @return bool
 */
function abic_has_acf_v2_blocks(): bool {
	if ( ! function_exists( 'acf_get_block_types' ) ) {
		return false;
	}

	$blocks = acf_get_block_types();

	if ( empty( $blocks ) ) {
		return false;
	}

	foreach ( $blocks as $block ) {
		if ( empty( $block['acf_block_version'] ) || $block['acf_block_version'] < 3 ) {
			return true;
		}
	}

	return false;
}

/**
 * Enqueue the script that forces the editor canvas into an iframe.
 */
add_action( 'enqueue_block_editor_assets', 'abic_enqueue', 1 );
function abic_enqueue(): void {
	if ( ! abic_has_acf_v2_blocks() ) {
		return;
	}

	$path = __DIR__ . '/assets/js/force-iframeless-canvas.js';

	if ( ! is_file( $path ) ) {
		return;
	}

	$js = (string) file_get_contents( $path );

	if ( wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
		wp_add_inline_script( 'react-jsx-runtime', $js, 'after' );
		return;
	}

	if ( wp_script_is( 'wp-element', 'registered' ) ) {
		wp_add_inline_script( 'wp-element', $js, 'after' );
	}
}
	
add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'post', 'page' ), true ) ) {
        // Also covers custom post types via $screen->is_block_editor()
        if ( ! $screen || ! $screen->is_block_editor() ) {
            return;
        }
    }
	?>
<style>
/* Only affect ACF block modals */
.acf-expanded-editor-panel-overlay {
  /* Remove the dark backdrop so the editor behind stays visible */
  background: transparent !important;
  pointer-events: none; /* let clicks pass through to the editor */

  /* Pin the overlay to the left edge of the editor area */
  position: fixed !important;
  top: var(--wp-admin--admin-bar--height, 32px) !important;
  left: 160px !important; /* WP admin menu width, adjust if collapsed */
  right: 280px !important; /* leave room for the right sidebar */
  bottom: 0 !important;
  width: auto !important;
  height: auto !important;
  display: flex !important;
  align-items: stretch !important;
  justify-content: flex-start !important;
  z-index: 100000 !important;
}

/* The modal frame itself: make it wide and left-aligned */
.acf-expanded-editor-panel-overlay .acf-block-form-modal {
  pointer-events: auto; /* modal is clickable again */
  position: relative !important;
  margin: 0 !important;
  width: 100% !important;
  max-width: 100% !important;
  height: 100% !important;
  max-height: 100% !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  border-right: 1px solid #ddd !important;
  display: flex !important;
  flex-direction: column !important;
}

/* Let the inner content scroll within the frame */
.acf-expanded-editor-panel-overlay .components-modal__content {
  flex: 1 1 auto !important;
  overflow-y: auto !important;
  max-height: 100% !important;
}

/* Keep the header (with "Done" button) sticky at top */
.acf-expanded-editor-panel-overlay .components-modal__header {
  position: sticky !important;
  top: 0 !important;
  z-index: 2 !important;
  background: #fff !important;
}

/* Optional: give the form container some breathing room */
.acf-expanded-editor-panel-overlay .acf-modal-block-form-container {
  padding: 16px 20px !important;
}

/* Repeater table: make sure it can use the full width */
.acf-expanded-editor-panel-overlay .acf-repeater .acf-table {
  width: 100% !important;
}
.acf-expanded-editor-panel-overlay .acf-repeater .acf-row td.acf-fields {
  width: auto !important;
}

/* If the admin menu is collapsed (mobile / focus mode), override left offset */
body.folded .acf-expanded-editor-panel-overlay {
  left: 36px !important;
}
@media (max-width: 782px) {
  .acf-expanded-editor-panel-overlay {
    left: 0 !important;
    right: 0 !important;
  }
}
</style>    
    <?php
});