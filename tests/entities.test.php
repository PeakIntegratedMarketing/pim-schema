<?php
// Run: php tests/entities.test.php  (minimal WordPress stubs below)
define( 'ABSPATH', __DIR__ );
define( 'RANK_MATH_VERSION', 'test' );
$GLOBALS['opts'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['ctx']  = array( 'singular' => true, 'front' => false, 'id' => 10 );
function home_url( $p = '' ) { return 'https://example.com' . $p; }
function esc_url_raw( $u ) { return preg_match( '#^https?://#', $u ) ? $u : ''; }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function apply_filters( $tag, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function get_post_meta( $id, $k, $single ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function wp_get_attachment_image_url( $id ) { return 'https://example.com/img-' . $id . '.jpg'; }
function is_singular() { return $GLOBALS['ctx']['singular']; }
function is_front_page() { return $GLOBALS['ctx']['front']; }
function get_queried_object_id() { return $GLOBALS['ctx']['id']; }
class PIM_Schema_Output { static function current_url() { return 'https://example.com/page/'; } }
require __DIR__ . '/../includes/entities/class-entities.php';

$fail = 0;
function check( $label, $ok ) { global $fail; echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fail++; } }

$list = PIM_Schema_Entities::sanitize_entities( array(
	array( 'name' => 'Example Village', 'type' => 'GatedResidenceCommunity', 'latitude' => '10.5', 'longitude' => '-84.5', 'containedInPlace' => 'sample-town',
		'streetAddress' => '100 Main Street', 'addressCountry' => 'CR', 'amenities' => "Pool\nGym\n", 'sameAs' => "https://maps.google.com/?cid=1\nnot a url", 'orgLocation' => '1', 'image' => '42' ),
	array( 'name' => 'Sample Town', 'key' => 'Sample Town', 'type' => 'City<script>', 'containedInPlace' => 'sample-country', 'latitude' => 'abc' ),
	array( 'name' => 'Duplicate', 'key' => 'example-village' ),
	array( 'name' => '' ),
) );
check( 'sanitize: 2 kept (dupe + empty dropped)', 2 === count( $list ) );
check( 'key from name', 'example-village' === $list[0]['key'] && 'sample-town' === $list[1]['key'] );
check( 'type letters only', 'Cityscript' === $list[1]['type'] );
check( 'bad lat dropped, list fields split', '' === $list[1]['latitude'] && array( 'Pool', 'Gym' ) === $list[0]['amenities'] && 1 === count( $list[0]['sameAs'] ) );

$GLOBALS['opts']['pim_schema_entities'] = $list;
$all  = PIM_Schema_Entities::all();
$node = PIM_Schema_Entities::node( $all['example-village'], $all );
check( 'node @id', 'https://example.com/#entity-example-village' === $node['@id'] );
check( 'geo floats', 10.5 === $node['geo']['latitude'] );
check( 'address', 'PostalAddress' === $node['address']['@type'] && 'CR' === $node['address']['addressCountry'] );
check( 'containedInPlace ref', 'https://example.com/#entity-sample-town' === $node['containedInPlace']['@id'] );
check( 'amenityFeature', 'Gym' === $node['amenityFeature'][1]['name'] && true === $node['amenityFeature'][1]['value'] );
check( 'image from media id', 'https://example.com/img-42.jpg' === $node['image'] );
$node = PIM_Schema_Entities::node( $all['sample-town'], $all );
check( 'unknown parent omitted', ! isset( $node['containedInPlace'] ) );

// Page about Example Village, mentions Sample Town + an unknown key.
$GLOBALS['meta'][10] = array( '_pim_schema_about' => array( 'example-village' ), '_pim_schema_mentions' => array( 'sample-town', 'nope' ) );
$nodes = PIM_Schema_Entities::add_nodes( array(), '' );
$types = array_map( function ( $n ) { return is_array( $n['@type'] ) ? implode( ',', $n['@type'] ) : $n['@type']; }, $nodes );
check( 'page nodes: WebPage, Organization, 2 entities (' . implode( ' ', $types ) . ')', array( 'WebPage', 'Organization', 'GatedResidenceCommunity', 'Cityscript' ) === $types );
check( 'WebPage about/mentions refs', 'https://example.com/page/#webpage' === $nodes[0]['@id'] && 1 === count( $nodes[0]['about'] ) && 1 === count( $nodes[0]['mentions'] ) );
check( 'org location', 'https://example.com/#entity-example-village' === $nodes[1]['location'][0]['@id'] );

// Page mentioning only Sample Town: no org link, parent chain not needed.
$GLOBALS['meta'][10] = array( '_pim_schema_about' => array( 'sample-town' ) );
$nodes = PIM_Schema_Entities::add_nodes( array(), '' );
check( 'about sample-town: WebPage + 1 entity', 2 === count( $nodes ) );

// Page with nothing: no nodes; front page: org location + place.
$GLOBALS['meta'][10] = array();
check( 'no refs → no nodes', array() === PIM_Schema_Entities::add_nodes( array(), '' ) );
$GLOBALS['ctx']['front'] = true;
$nodes = PIM_Schema_Entities::add_nodes( array(), '' );
check( 'front page: Organization location + place (+ parent)', 'Organization' === $nodes[0]['@type'] && 3 === count( $nodes ) );

exit( $fail ? 1 : 0 );
