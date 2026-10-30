<?php
/**
 * Plugin Name:       PIM Schema
 * Description:       Structured data for Etch/ACSS sites: an FAQ block for the block editor (paste from Google Docs), FAQPage schema for every FAQ on the page, entity schema and post-type schema (listings, rentals, people) that extend Rank Math.
 * Version:           0.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Update URI:        https://github.com/PeakIntegratedMarketing/pim-schema
 * Author:            Peak Integrated Marketing
 * Text Domain:       pim-schema
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'PIM_SCHEMA_VERSION', '0.4.0' );
define( 'PIM_SCHEMA_FILE', __FILE__ );
define( 'PIM_SCHEMA_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIM_SCHEMA_URL', plugin_dir_url( __FILE__ ) );

require_once PIM_SCHEMA_DIR . 'includes/class-settings.php';
require_once PIM_SCHEMA_DIR . 'includes/class-updater.php';
require_once PIM_SCHEMA_DIR . 'includes/class-output.php';
require_once PIM_SCHEMA_DIR . 'includes/class-rank-math.php';
require_once PIM_SCHEMA_DIR . 'includes/faq/class-faq-extractor.php';
require_once PIM_SCHEMA_DIR . 'includes/faq/class-faq-blocks.php';
require_once PIM_SCHEMA_DIR . 'includes/faq/class-faq-schema.php';
require_once PIM_SCHEMA_DIR . 'includes/entities/class-entities.php';
require_once PIM_SCHEMA_DIR . 'includes/entities/class-entities-admin.php';
require_once PIM_SCHEMA_DIR . 'includes/types/class-post-types.php';

PIM_Schema_Settings::init();
PIM_Schema_Updater::init();
PIM_Schema_Output::init();
PIM_Schema_Rank_Math::init();
PIM_Schema_FAQ_Blocks::init();
PIM_Schema_FAQ_Schema::init();
PIM_Schema_Entities::init();
PIM_Schema_Entities_Admin::init();
PIM_Schema_Post_Types::init();
