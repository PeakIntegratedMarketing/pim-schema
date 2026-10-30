<?php
/**
 * Updates from GitHub releases: each site's Plugins screen shows "Update available" for new
 * releases of the public repo, so updating is one click (or WordPress auto-updates, if a site
 * turns them on). Uses Plugin Update Checker (lib/plugin-update-checker, MIT).
 *
 * A release must have the built `pim-schema.zip` attached (release.sh does this). Drafts and
 * pre-releases are ignored, so a pre-release can be tried on a staging site by hand first.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Updater {

	const REPO = 'https://github.com/PeakIntegratedMarketing/pim-schema/';

	public static function init() {
		if ( ! file_exists( PIM_SCHEMA_DIR . 'lib/plugin-update-checker/plugin-update-checker.php' ) ) {
			return;
		}
		require_once PIM_SCHEMA_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( self::REPO, PIM_SCHEMA_FILE, 'pim-schema' );
		// Only the built zip (no tests/docs, correct folder name), never GitHub's source archive.
		$checker->getVcsApi()->enableReleaseAssets( '/^pim-schema\.zip$/', \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS );
	}
}
