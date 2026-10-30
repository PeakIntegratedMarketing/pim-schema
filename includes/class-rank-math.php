<?php
/**
 * Rank Math compatibility.
 *
 * With a post type's Schema Type set to "None", Rank Math also drops its base graph
 * (Organization, WebSite, WebPage) on those posts, so pages and CPT singles end up with no
 * WebPage for our modules to extend. The `rm_global_entities` setting keeps the base graph
 * everywhere (Rank Math's own `rank_math/schema/add_global_entities` filter), and removes the
 * author Person it then adds when nothing on the page references it (e.g. "admin" on pages).
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Rank_Math {

	public static function init() {
		add_filter( 'rank_math/schema/add_global_entities', array( __CLASS__, 'global_entities' ), 20 );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'drop_unused_author' ), 100, 2 );
	}

	private static function enabled() {
		return (bool) PIM_Schema_Settings::get( 'rm_global_entities' );
	}

	public static function global_entities( $can_add ) {
		return $can_add || ( self::enabled() && is_singular() );
	}

	public static function drop_unused_author( $data, $jsonld = null ) {
		if ( ! self::enabled() || ! is_array( $data ) || is_author() || empty( $data['ProfilePage']['@id'] ) ) {
			return $data;
		}
		$id    = $data['ProfilePage']['@id'];
		$other = $data;
		unset( $other['ProfilePage'] );
		if ( false === strpos( (string) wp_json_encode( $other, JSON_UNESCAPED_SLASHES ), '"' . $id . '"' ) ) {
			unset( $data['ProfilePage'] );
		}
		return $data;
	}
}
