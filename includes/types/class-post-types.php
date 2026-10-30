<?php
/**
 * Module 3: post-type mappings. Turns posts of a custom post type into schema nodes from a
 * declarative template that the site's own plugin registers:
 *
 *   add_filter( 'pim_schema_post_types', function ( $types ) {
 *       $types['property_listing'] = array(
 *           'nodes'     => array(                       // First node = the page's main entity.
 *               array(
 *                   '@type'  => 'RealEstateListing',
 *                   '@id'    => '#listing',               // '#…' = relative to the post's permalink.
 *                   'name'   => '{title}',
 *                   'offers' => array( '@type' => 'Offer', 'price' => '{meta:price}', 'priceCurrency' => 'USD' ),
 *               ),
 *           ),
 *           'item_list' => array( 'villas-for-sale' ),  // Pages (IDs or slugs) that get an ItemList of the
 *       );                                              // posts they link to; true = any page.
 *       return $types;
 *   } );
 *
 * Template values:
 *   - Plain strings are literal. `{token}` is replaced; a value that is exactly one token keeps
 *     the token's type (number, list, reference), mixed strings are interpolated.
 *   - Tokens: title, url, excerpt, content, date, modified, image, org, website, webpage,
 *     meta:<key>, meta_text:<key> (HTML stripped), meta_image:<key>, meta_gallery:<key> (comma-separated
 *     attachment IDs → URLs), meta_list:<key> (comma-separated → list), terms:<taxonomy>,
 *     (list tokens take a limit: {meta_gallery:gallery|10}),
 *     post:<key> (meta holds a post ID → reference to that post's main node, which is added to the
 *     page when its type is mapped), entity:<key> (PIM Schema entity), ref:#<fragment>.
 *   - A Closure is called with ( WP_Post $post, PIM_Schema_Post_Types $engine ).
 *   - `'@if' => '{token}'` on an object drops the object when the token is empty (0 counts as empty).
 *   - Empty values are removed; an object left with only @type is removed.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Post_Types {

	/** Nodes of linked posts (e.g. a listing's agent), keyed by @id. */
	private $extra = array();

	/** @var WP_Post */
	private $post;

	private $depth = 0;

	public static function init() {
		// Before the entities module (5), so entity references made here are included.
		add_filter( 'pim_schema_page_nodes', array( __CLASS__, 'add_nodes' ), 4, 2 );
	}

	public static function types() {
		$types = apply_filters( 'pim_schema_post_types', array() );
		return is_array( $types ) ? array_filter( $types, 'is_array' ) : array();
	}

	public static function add_nodes( $nodes, $html ) {
		$types = self::types();
		if ( ! $types ) {
			return $nodes;
		}
		$engine = new self();
		$url    = PIM_Schema_Output::current_url();

		$object = is_singular() ? get_queried_object() : null;
		if ( $object instanceof WP_Post && isset( $types[ $object->post_type ] ) ) {
			$built = $engine->build( $object, $types[ $object->post_type ] );
			if ( $built ) {
				$nodes[] = array(
					'@type'      => 'WebPage',
					'@id'        => $url . '#webpage',
					'mainEntity' => array( '@id' => $built[0]['@id'] ),
				);
				$nodes = array_merge( $nodes, $built );
			}
		}

		foreach ( $types as $post_type => $config ) {
			if ( $object instanceof WP_Post && $object->post_type === $post_type ) {
				continue;
			}
			if ( ! self::item_list_applies( $config, $object ) ) {
				continue;
			}
			$list = self::item_list( $post_type, $html, $url );
			if ( $list ) {
				$nodes[] = $list;
			}
		}

		return array_merge( $nodes, array_values( $engine->extra ) );
	}

	/**
	 * Builds a post's nodes. Returns a list; the first node is the main entity.
	 */
	public function build( WP_Post $post, array $config ) {
		$templates = isset( $config['nodes'] ) ? (array) $config['nodes'] : array();
		$prev      = $this->post;
		$this->post = $post;
		$out       = array();
		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$node = $this->resolve( $template );
			if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
				continue;
			}
			if ( empty( $node['@id'] ) ) {
				$node['@id'] = get_permalink( $post ) . '#' . strtolower( is_array( $node['@type'] ) ? $node['@type'][0] : $node['@type'] );
			}
			$out[] = $node;
		}
		$this->post = $prev;
		$out        = apply_filters( 'pim_schema_post_type_nodes', $out, $post, $config );
		return array_values( array_filter( (array) $out ) );
	}

	/** Resolves a template value for the current post. */
	public function resolve( $value ) {
		if ( $value instanceof Closure ) {
			return self::clean( $value( $this->post, $this ) );
		}
		if ( is_string( $value ) ) {
			return self::interpolate( $value, array( $this, 'token' ) );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( self::is_list( $value ) ) {
			$list = array();
			foreach ( $value as $item ) {
				$item = $this->resolve( $item );
				if ( is_array( $item ) && self::is_list( $item ) ) {
					$list = array_merge( $list, $item );
				} elseif ( ! self::is_empty( $item ) ) {
					$list[] = $item;
				}
			}
			return self::clean( self::unique( $list ) );
		}
		if ( array_key_exists( '@if', $value ) ) {
			$cond = $this->resolve( $value['@if'] );
			unset( $value['@if'] );
			if ( self::is_empty( $cond, true ) ) {
				return null;
			}
		}
		$obj = array();
		foreach ( $value as $key => $item ) {
			$item = '@id' === $key && is_string( $item ) && 0 === strpos( $item, '#' ) ? get_permalink( $this->post ) . $item : $this->resolve( $item );
			if ( ! self::is_empty( $item ) ) {
				$obj[ $key ] = $item;
			}
		}
		return self::clean( $obj );
	}

	/**
	 * Replaces {tokens} in a string. A string that is exactly one token returns the token's raw value.
	 *
	 * @param string   $value    Template string.
	 * @param callable $resolver fn( string $token ) → mixed.
	 */
	public static function interpolate( $value, $resolver ) {
		if ( preg_match( '/^\{([a-z_]+(?::[^{}]+)?)\}$/', $value, $m ) ) {
			return call_user_func( $resolver, $m[1] );
		}
		if ( false === strpos( $value, '{' ) ) {
			return $value;
		}
		$empty = false;
		$out   = preg_replace_callback(
			'/\{([a-z_]+(?::[^{}]+)?)\}/',
			function ( $m ) use ( $resolver, &$empty ) {
				$v = call_user_func( $resolver, $m[1] );
				if ( is_array( $v ) ) {
					$v = isset( $v['@id'] ) ? $v['@id'] : implode( ', ', array_filter( $v, 'is_scalar' ) );
				}
				return (string) $v;
			},
			$value
		);
		return trim( preg_replace( '/\s+/', ' ', $out ) );
	}

	/** Value of one token for the current post. */
	public function token( $token ) {
		$post = $this->post;
		list( $name, $arg ) = array_pad( explode( ':', $token, 2 ), 2, '' );
		$limit = 0;
		if ( preg_match( '/^(.*)\|(\d+)$/', $arg, $m ) ) {
			list( , $arg, $limit ) = $m;
			$limit = (int) $limit;
		}
		$value = $this->token_value( $post, $name, $arg );
		return $limit && is_array( $value ) && self::is_list( $value ) ? array_slice( $value, 0, $limit ) : $value;
	}

	private function token_value( WP_Post $post, $name, $arg ) {
		switch ( $name ) {
			case 'title':
				return html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
			case 'url':
				return get_permalink( $post );
			case 'excerpt':
				return self::text( has_excerpt( $post ) ? $post->post_excerpt : '' );
			case 'content':
				return self::text( $post->post_content );
			case 'date':
				return get_post_time( 'c', true, $post );
			case 'modified':
				return get_post_modified_time( 'c', true, $post );
			case 'image':
				$url = get_the_post_thumbnail_url( $post, 'full' );
				return $url ? $url : null;
			case 'org':
				return array( '@id' => home_url( '/' ) . '#organization' );
			case 'website':
				return array( '@id' => home_url( '/' ) . '#website' );
			case 'webpage':
				return array( '@id' => get_permalink( $post ) . '#webpage' );
			case 'ref':
				return array( '@id' => get_permalink( $post ) . $arg );
			case 'entity':
				return class_exists( 'PIM_Schema_Entities' ) ? PIM_Schema_Entities::ref( $arg ) : null;
			case 'meta':
				return self::typed( get_post_meta( $post->ID, $arg, true ) );
			case 'meta_text':
				return self::text( get_post_meta( $post->ID, $arg, true ) );
			case 'meta_list':
				return self::split( get_post_meta( $post->ID, $arg, true ) );
			case 'meta_image':
				$id = (int) get_post_meta( $post->ID, $arg, true );
				$url = $id ? wp_get_attachment_url( $id ) : '';
				return $url ? $url : null;
			case 'meta_gallery':
				$urls = array();
				foreach ( self::split( get_post_meta( $post->ID, $arg, true ) ) as $id ) {
					$url = wp_get_attachment_url( (int) $id );
					if ( $url ) {
						$urls[] = $url;
					}
				}
				return $urls;
			case 'terms':
				$terms = get_the_terms( $post, $arg );
				return $terms && ! is_wp_error( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
			case 'post':
				return $this->post_ref( (int) get_post_meta( $post->ID, $arg, true ) );
		}
		return null;
	}

	/** Reference to another post's main node; adds that post's nodes to the page (one level deep). */
	public function post_ref( $post_id ) {
		$linked = $post_id ? get_post( $post_id ) : null;
		if ( ! $linked || 'publish' !== $linked->post_status ) {
			return null;
		}
		$types = self::types();
		if ( ! isset( $types[ $linked->post_type ] ) || $this->depth > 0 ) {
			return isset( $types[ $linked->post_type ] ) ? array( '@id' => $this->main_id( $linked, $types[ $linked->post_type ] ) ) : null;
		}
		++$this->depth;
		$built = $this->build( $linked, $types[ $linked->post_type ] );
		--$this->depth;
		if ( ! $built ) {
			return null;
		}
		foreach ( $built as $node ) {
			$this->extra[ $node['@id'] ] = $node;
		}
		return array( '@id' => $built[0]['@id'] );
	}

	private function main_id( WP_Post $post, array $config ) {
		$first = isset( $config['nodes'][0] ) ? $config['nodes'][0] : array();
		$id    = isset( $first['@id'] ) && is_string( $first['@id'] ) ? $first['@id'] : '';
		if ( 0 === strpos( $id, '#' ) ) {
			return get_permalink( $post ) . $id;
		}
		$type = isset( $first['@type'] ) ? ( is_array( $first['@type'] ) ? $first['@type'][0] : $first['@type'] ) : 'thing';
		return $id ? $id : get_permalink( $post ) . '#' . strtolower( $type );
	}

	private static function item_list_applies( array $config, $object ) {
		$pages = isset( $config['item_list'] ) ? $config['item_list'] : false;
		if ( true === $pages ) {
			return true;
		}
		if ( ! $pages || ! ( $object instanceof WP_Post ) ) {
			return false;
		}
		foreach ( (array) $pages as $page ) {
			if ( ( is_numeric( $page ) && (int) $page === $object->ID ) || ( is_string( $page ) && $page === $object->post_name ) ) {
				return true;
			}
		}
		return false;
	}

	/** ItemList of the posts of a type that the page's main content links to, in order. */
	public static function item_list( $post_type, $html, $url ) {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => 500,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);
		if ( count( $ids ) < 2 ) {
			return null;
		}
		$by_url = array();
		foreach ( $ids as $id ) {
			$by_url[ untrailingslashit( get_permalink( $id ) ) ] = $id;
		}
		$found = self::linked_posts( $html, $by_url );
		if ( count( $found ) < 2 ) {
			return null;
		}
		$items = array();
		foreach ( array_values( $found ) as $i => $id ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'url'      => get_permalink( $id ),
				'name'     => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		$obj  = get_post_type_object( $post_type );
		$list = array(
			'@type'           => 'ItemList',
			'@id'             => $url . '#list-' . $post_type,
			'name'            => $obj ? $obj->labels->name : $post_type,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
		return apply_filters( 'pim_schema_item_list', $list, $post_type, $found );
	}

	/**
	 * Post IDs linked from the page's main content (header, footer and nav removed), first-seen order.
	 *
	 * @param string $html   Rendered page.
	 * @param array  $by_url Permalink without trailing slash → post ID.
	 */
	public static function linked_posts( $html, array $by_url ) {
		$body = $html;
		if ( preg_match( '/<main\b[^>]*>(.*)<\/main>/is', $html, $m ) ) {
			$body = $m[1];
		} else {
			$body = preg_replace( '/<(header|footer|nav)\b.*?<\/\1>/is', '', $html );
		}
		$found = array();
		if ( preg_match_all( '/<a\b[^>]*\bhref=(["\'])(.*?)\1/is', $body, $m ) ) {
			foreach ( $m[2] as $href ) {
				$href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
				$href = untrailingslashit( preg_replace( '/[?#].*$/', '', $href ) );
				if ( '' !== $href && '/' === $href[0] && ( ! isset( $href[1] ) || '/' !== $href[1] ) ) {
					$href = untrailingslashit( home_url( $href ) );
				}
				if ( isset( $by_url[ $href ] ) && ! in_array( $by_url[ $href ], $found, true ) ) {
					$found[] = $by_url[ $href ];
				}
			}
		}
		return $found;
	}

	/* Helpers (public static so tests can reach them). */

	public static function text( $html ) {
		$text = wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br/>', '<br />', '</li>' ), array( "</p>\n", "\n", "\n", "\n", "</li>\n" ), (string) $html ) );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		return '' === $text ? null : $text;
	}

	public static function split( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'trim', $value ), 'strlen' ) );
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
	}

	public static function typed( $value ) {
		if ( is_string( $value ) && is_numeric( $value ) && preg_match( '/^-?\d+(\.\d+)?$/', trim( $value ) ) ) {
			return false === strpos( $value, '.' ) ? (int) $value : (float) $value;
		}
		return '' === $value ? null : $value;
	}

	public static function is_empty( $value, $zero = false ) {
		if ( null === $value || false === $value || '' === $value || array() === $value ) {
			return true;
		}
		return $zero && ( 0 === $value || 0.0 === $value || '0' === $value );
	}

	public static function is_list( array $value ) {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function unique( array $list ) {
		$seen = array();
		$out  = array();
		foreach ( $list as $item ) {
			$key = is_scalar( $item ) ? 's:' . $item : 'j:' . wp_json_encode( $item );
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$out[]        = $item;
			}
		}
		return $out;
	}

	/** Drops empty values; an object with nothing but @type is dropped too. */
	public static function clean( $value ) {
		if ( ! is_array( $value ) ) {
			return self::is_empty( $value ) ? null : $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$v = self::clean( $v );
			if ( ! self::is_empty( $v ) ) {
				$out[ $k ] = $v;
			}
		}
		if ( self::is_list( $value ) ) {
			$out = array_values( $out );
		} elseif ( array( '@type' ) === array_keys( $out ) ) {
			return null;
		}
		return $out ? $out : null;
	}
}
