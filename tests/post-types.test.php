<?php
// Run: php tests/post-types.test.php  (minimal WordPress stubs below)
define( 'ABSPATH', __DIR__ );
class WP_Post {
	public $ID; public $post_type; public $post_status = 'publish'; public $post_name = ''; public $post_excerpt = ''; public $post_content = ''; public $title = '';
	function __construct( $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
$GLOBALS['posts'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['types'] = array();
$GLOBALS['ctx']   = array( 'object' => null );
function home_url( $p = '' ) { return 'https://example.com' . $p; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function apply_filters( $tag, $v ) { return 'pim_schema_post_types' === $tag ? $GLOBALS['types'] : $v; }
function add_action() {}
function add_filter() {}
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_posts( $args ) {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $p ) { if ( $p->post_type === $args['post_type'] ) { $ids[] = $p->ID; } }
	return $ids;
}
function get_permalink( $p = null ) { $p = null === $p ? $GLOBALS['ctx']['object'] : ( is_object( $p ) ? $p : get_post( $p ) ); return 'https://example.com/' . $p->post_type . '/' . $p->post_name . '/'; }
function get_the_title( $p ) { $p = is_object( $p ) ? $p : get_post( $p ); return $p->title; }
function has_excerpt( $p ) { return '' !== $p->post_excerpt; }
function get_post_time( $f, $gmt, $p ) { return '2026-10-01T12:00:00+00:00'; }
function get_post_modified_time( $f, $gmt, $p ) { return '2026-10-02T12:00:00+00:00'; }
function get_the_post_thumbnail_url( $p ) { return $GLOBALS['meta'][ $p->ID ]['_thumb'] ?? false; }
function get_post_meta( $id, $k, $single ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function wp_get_attachment_url( $id ) { return $id ? 'https://example.com/img-' . $id . '.jpg' : false; }
function get_the_terms( $p, $tax ) { return 'model' === $tax ? array( (object) array( 'name' => 'Model A' ) ) : false; }
function is_wp_error( $v ) { return false; }
function wp_list_pluck( $list, $f ) { return array_map( function ( $o ) use ( $f ) { return $o->$f; }, $list ); }
function get_post_type_object( $t ) { return (object) array( 'labels' => (object) array( 'name' => ucfirst( $t ) . 's' ) ); }
function is_singular() { return null !== $GLOBALS['ctx']['object']; }
function get_queried_object() { return $GLOBALS['ctx']['object']; }
class PIM_Schema_Entities { static function ref( $k ) { return 'example-village' === $k ? array( '@id' => 'https://example.com/#entity-' . $k ) : null; } }
function is_author() { return false; }
class PIM_Schema_Settings { static function get( $k ) { return 1; } }
require __DIR__ . '/../includes/class-output.php';
require __DIR__ . '/../includes/class-rank-math.php';
require __DIR__ . '/../includes/types/class-post-types.php';

$fail = 0;
function check( $label, $ok ) { global $fail; echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fail++; } }

// Helpers.
check( 'typed numbers', 785000 === PIM_Schema_Post_Types::typed( '785000' ) && 2.5 === PIM_Schema_Post_Types::typed( '2.5' ) && '27193a' === PIM_Schema_Post_Types::typed( '27193a' ) );
check( 'text strips HTML + entities', 'One & two Three' === PIM_Schema_Post_Types::text( '<p>One &amp; two</p><p>Three</p>' ) );
check( 'split comma list', array( 'Pool', 'Hot tub' ) === PIM_Schema_Post_Types::split( 'Pool, Hot tub, ' ) );
check( 'clean drops empties and @type-only objects', array( 'a' => 1, 'c' => array( 2 ) ) === PIM_Schema_Post_Types::clean( array( 'a' => 1, 'b' => '', 'c' => array( 2, null ), 'd' => array( '@type' => 'X', 'v' => null ) ) ) );
check( 'interpolate mixed', 'Home 47 for sale' === PIM_Schema_Post_Types::interpolate( '{title} for sale', function () { return 'Home 47'; } ) );
check( 'interpolate single token keeps type', 3 === PIM_Schema_Post_Types::interpolate( '{meta:beds}', function () { return 3; } ) );

// Posts.
$GLOBALS['posts'][752] = new WP_Post( array( 'ID' => 752, 'post_type' => 'team', 'post_name' => 'jane', 'title' => 'Jane Doe' ) );
$GLOBALS['posts'][830] = new WP_Post( array( 'ID' => 830, 'post_type' => 'listing', 'post_name' => 'home-47', 'title' => 'Home 47' ) );
$GLOBALS['posts'][831] = new WP_Post( array( 'ID' => 831, 'post_type' => 'listing', 'post_name' => 'home-48', 'title' => 'Home 48' ) );
$GLOBALS['posts'][900] = new WP_Post( array( 'ID' => 900, 'post_type' => 'page', 'post_name' => 'villas-for-sale', 'title' => 'Villas' ) );
$GLOBALS['meta'][830]  = array( '_thumb' => 'https://example.com/thumb.jpg', 'price' => '785000', 'status' => 'for_sale', 'beds' => '3', 'baths' => '2.5', 'sqft' => '2852', 'lat' => '', 'gallery' => '942,944', 'agent' => '752', 'brochure' => '0', 'features' => '<ul><li>Pool</li><li>View</li></ul>' );
$GLOBALS['meta'][752]  = array( 'job_title' => 'Sales', 'email' => '' );
$GLOBALS['types'] = array(
	'listing' => array(
		'item_list' => array( 'villas-for-sale' ),
		'nodes'     => array(
			array(
				'@type'  => 'RealEstateListing',
				'@id'    => '#listing',
				'name'   => '{title}',
				'image'  => array( '{image}', '{meta_gallery:gallery}' ),
				'about'  => '{ref:#residence}',
				'offers' => array(
					'@type'         => 'Offer',
					'price'         => '{meta:price}',
					'priceCurrency' => 'USD',
					'availability'  => function ( $post ) { return 'for_sale' === get_post_meta( $post->ID, 'status', true ) ? 'https://schema.org/InStock' : null; },
					'offeredBy'     => '{post:agent}',
				),
				'subjectOf' => array( '@if' => '{meta:brochure}', '@type' => 'DigitalDocument', 'url' => '{meta_image:brochure}' ),
			),
			array(
				'@type'              => 'SingleFamilyResidence',
				'@id'                => '#residence',
				'numberOfBedrooms'   => '{meta:beds}',
				'numberOfBathroomsTotal' => '{meta:baths}',
				'floorSize'          => array( '@if' => '{meta:sqft}', '@type' => 'QuantitativeValue', 'value' => '{meta:sqft}', 'unitCode' => 'FTK' ),
				'geo'                => array( '@if' => '{meta:lat}', '@type' => 'GeoCoordinates', 'latitude' => '{meta:lat}' ),
				'containedInPlace'   => '{entity:example-village}',
				'description'        => '{meta_text:features}',
				'keywords'           => '{terms:model}',
			),
		),
	),
	'team'    => array(
		'nodes' => array( array( '@type' => 'Person', '@id' => '#person', 'name' => '{title}', 'jobTitle' => '{meta:job_title}', 'email' => '{meta:email}', 'worksFor' => '{org}', 'colleague' => '{post:agent}' ) ),
	),
);

$engine = new PIM_Schema_Post_Types();
$nodes  = $engine->build( $GLOBALS['posts'][830], $GLOBALS['types']['listing'] );
$l      = $nodes[0];
$r      = $nodes[1];
check( 'two nodes, relative @ids', 2 === count( $nodes ) && 'https://example.com/listing/home-47/#listing' === $l['@id'] && 'https://example.com/listing/home-47/#residence' === $r['@id'] );
check( 'list limit', array( 'https://example.com/img-942.jpg' ) === ( function () use ( $engine ) { $e = new PIM_Schema_Post_Types(); $r = new ReflectionProperty( $e, 'post' ); $r->setValue( $e, $GLOBALS['posts'][830] ); return $e->token( 'meta_gallery:gallery|1' ); } )() );
check( 'images flattened + deduped', array( 'https://example.com/thumb.jpg', 'https://example.com/img-942.jpg', 'https://example.com/img-944.jpg' ) === $l['image'] );
check( 'offer typed price + closure', 785000 === $l['offers']['price'] && 'https://schema.org/InStock' === $l['offers']['availability'] );
check( 'post ref', array( '@id' => 'https://example.com/team/jane/#person' ) === $l['offers']['offeredBy'] );
check( '@if 0 drops object', ! isset( $l['subjectOf'] ) );
check( 'residence numbers', 3 === $r['numberOfBedrooms'] && 2.5 === $r['numberOfBathroomsTotal'] && 2852 === $r['floorSize']['value'] );
check( 'empty geo dropped', ! isset( $r['geo'] ) );
check( 'entity ref', 'https://example.com/#entity-example-village' === $r['containedInPlace']['@id'] );
check( 'meta_text + terms', 'Pool View' === $r['description'] && array( 'Model A' ) === $r['keywords'] );

// Single listing page: WebPage mainEntity + agent node included; agent's own post refs not followed.
$GLOBALS['ctx']['object'] = $GLOBALS['posts'][830];
$page = PIM_Schema_Post_Types::add_nodes( array(), '<html></html>' );
$ids  = array_map( function ( $n ) { return $n['@id']; }, $page );
check( 'single: WebPage + 2 nodes + agent', 4 === count( $page ) && 'WebPage' === $page[0]['@type'] && 'https://example.com/listing/home-47/#listing' === $page[0]['mainEntity']['@id'] && in_array( 'https://example.com/team/jane/#person', $ids, true ) );
$person = end( $page );
check( 'agent: worksFor org, empty email dropped', 'https://example.com/#organization' === $person['worksFor']['@id'] && ! isset( $person['email'] ) && 'Sales' === $person['jobTitle'] );

// Loop page: ItemList from links in <main>, header links ignored, order kept.
$GLOBALS['ctx']['object'] = $GLOBALS['posts'][900];
$html = '<header><a href="/listing/home-47/">Nav</a></header><main><a href="https://example.com/listing/home-48/">48</a><a href="/listing/home-47/?x=1">47</a><a href="/listing/home-48/">again</a></main>';
$page = PIM_Schema_Post_Types::add_nodes( array(), $html );
check( 'item list on loop page', 1 === count( $page ) && 'ItemList' === $page[0]['@type'] && 2 === $page[0]['numberOfItems'] );
check( 'item order', 'https://example.com/listing/home-48/' === $page[0]['itemListElement'][0]['url'] && 2 === $page[0]['itemListElement'][1]['position'] && 'Home 47' === $page[0]['itemListElement'][1]['name'] );
$GLOBALS['posts'][900]->post_name = 'other-page';
check( 'no item list on other pages', array() === PIM_Schema_Post_Types::add_nodes( array(), $html ) );

// Output merge: same @id combined.
$merged = PIM_Schema_Output::merge( array(
	array( '@type' => 'WebPage', '@id' => 'u#webpage', 'about' => array( array( '@id' => 'a' ) ) ),
	array( '@type' => 'Thing', '@id' => 'x' ),
	array( '@type' => 'WebPage', '@id' => 'u#webpage', 'mainEntity' => array( '@id' => 'm' ), 'about' => array( array( '@id' => 'a' ), array( '@id' => 'b' ) ) ),
) );
check( 'merge: 2 nodes, props combined', 2 === count( $merged ) && 'm' === $merged[0]['mainEntity']['@id'] && 2 === count( $merged[0]['about'] ) && 'WebPage' === $merged[0]['@type'] );

// Rank Math: unused author Person removed, referenced one kept.
$rm = array( 'WebPage' => array( '@id' => 'u#webpage' ), 'ProfilePage' => array( '@type' => 'Person', '@id' => 'https://example.com/author/admin/' ) );
check( 'rank math: unused author dropped', ! isset( PIM_Schema_Rank_Math::drop_unused_author( $rm )['ProfilePage'] ) );
$rm['richSnippet'] = array( '@type' => 'BlogPosting', 'author' => array( '@id' => 'https://example.com/author/admin/' ) );
check( 'rank math: referenced author kept', isset( PIM_Schema_Rank_Math::drop_unused_author( $rm )['ProfilePage'] ) );
check( 'rank math: global entities on singulars', true === PIM_Schema_Rank_Math::global_entities( false ) );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
