<?php
/**
 * Entities module: places (and other things) defined once with stable @ids, which
 * pages declare they are "about" or "mention". Builds on Rank Math's graph when
 * present: WebPage `{url}#webpage` gets about/mentions, Organization `{home}#organization`
 * can get a `location`.
 *
 * Storage
 * - Option `pim_schema_entities`: list of entities (see sanitize_entity()). REST: /wp/v2/settings.
 * - Post meta `_pim_schema_about` / `_pim_schema_mentions`: entity keys. REST: the post's `meta`.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Entities {

	const OPTION        = 'pim_schema_entities';
	const META_ABOUT    = '_pim_schema_about';
	const META_MENTIONS = '_pim_schema_mentions';

	/** Suggested types (any schema.org type name is accepted). */
	const TYPES = array( 'GatedResidenceCommunity', 'Residence', 'ApartmentComplex', 'Place', 'TouristDestination', 'TouristAttraction', 'City', 'AdministrativeArea', 'Country', 'Park', 'LakeBodyOfWater', 'RiverBodyOfWater', 'Mountain', 'Volcano', 'Beach', 'LandmarksOrHistoricalBuildings', 'Organization', 'LocalBusiness', 'Event', 'Thing' );

	const FIELDS = array( 'key', 'type', 'name', 'alternateName', 'description', 'url', 'image', 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry', 'latitude', 'longitude', 'hasMap', 'containedInPlace', 'sameAs', 'amenities', 'orgLocation' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'pim_schema_page_nodes', array( __CLASS__, 'add_nodes' ), 5, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	/* ---------- Registration ---------- */

	public static function register() {
		$string_list = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		$properties  = array();
		foreach ( self::FIELDS as $field ) {
			$properties[ $field ] = in_array( $field, array( 'alternateName', 'sameAs', 'amenities' ), true ) ? $string_list : array( 'type' => 'orgLocation' === $field ? 'boolean' : 'string' );
		}
		register_setting(
			'pim_schema_entities',
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_entities' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => $properties,
						),
					),
				),
			)
		);

		foreach ( array( self::META_ABOUT, self::META_MENTIONS ) as $key ) {
			register_post_meta(
				'',
				$key,
				array(
					'type'              => 'array',
					'single'            => true,
					'default'           => array(),
					'sanitize_callback' => array( __CLASS__, 'sanitize_keys' ),
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
					'show_in_rest'      => array( 'schema' => $string_list ),
				)
			);
		}
	}

	/* ---------- Data ---------- */

	public static function all() {
		$list = get_option( self::OPTION, array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $entity ) {
			if ( ! empty( $entity['key'] ) && ! empty( $entity['name'] ) ) {
				$out[ $entity['key'] ] = $entity;
			}
		}
		return $out;
	}

	public static function entity_id( $key ) {
		return home_url( '/' ) . '#entity-' . $key;
	}

	/** Keys referenced by other modules on this request (their nodes get included). */
	private static $included = array();

	/**
	 * Reference to an entity for use in another module's node; the entity (and its parent places)
	 * is added to the page. Null when the key isn't defined.
	 */
	public static function ref( $key ) {
		$key = sanitize_title( (string) $key );
		$all = self::all();
		if ( ! isset( $all[ $key ] ) ) {
			return null;
		}
		self::$included[ $key ] = true;
		return array( '@id' => self::entity_id( $key ) );
	}

	public static function sanitize_keys( $keys ) {
		$keys = is_array( $keys ) ? $keys : preg_split( '/[\s,]+/', (string) $keys, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_unique( array_filter( array_map( 'sanitize_title', $keys ) ) ) );
	}

	private static function lines( $value ) {
		$list = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		return array_values( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $list ) ), 'strlen' ) );
	}

	public static function sanitize_entity( $e ) {
		$e    = is_array( $e ) ? $e : array();
		$name = sanitize_text_field( $e['name'] ?? '' );
		$key  = sanitize_title( ! empty( $e['key'] ) ? $e['key'] : $name );
		$num  = function ( $v ) {
			$v = trim( (string) $v );
			return preg_match( '/^-?\d{1,3}(\.\d+)?$/', $v ) ? $v : '';
		};
		$image = trim( (string) ( $e['image'] ?? '' ) );
		return array(
			'key'              => $key,
			'type'             => preg_replace( '/[^A-Za-z]/', '', (string) ( $e['type'] ?? '' ) ) ?: 'Place',
			'name'             => $name,
			'alternateName'    => self::lines( $e['alternateName'] ?? array() ),
			'description'      => sanitize_textarea_field( $e['description'] ?? '' ),
			'url'              => esc_url_raw( $e['url'] ?? '' ),
			'image'            => ctype_digit( $image ) ? $image : esc_url_raw( $image ),
			'streetAddress'    => sanitize_text_field( $e['streetAddress'] ?? '' ),
			'addressLocality'  => sanitize_text_field( $e['addressLocality'] ?? '' ),
			'addressRegion'    => sanitize_text_field( $e['addressRegion'] ?? '' ),
			'postalCode'       => sanitize_text_field( $e['postalCode'] ?? '' ),
			'addressCountry'   => sanitize_text_field( $e['addressCountry'] ?? '' ),
			'latitude'         => $num( $e['latitude'] ?? '' ),
			'longitude'        => $num( $e['longitude'] ?? '' ),
			'hasMap'           => esc_url_raw( $e['hasMap'] ?? '' ),
			'containedInPlace' => sanitize_title( $e['containedInPlace'] ?? '' ),
			'sameAs'           => array_values( array_filter( array_map( 'esc_url_raw', self::lines( $e['sameAs'] ?? array() ) ) ) ),
			'amenities'        => self::lines( $e['amenities'] ?? array() ),
			'orgLocation'      => ! empty( $e['orgLocation'] ),
		);
	}

	public static function sanitize_entities( $list ) {
		$out  = array();
		$seen = array();
		foreach ( is_array( $list ) ? $list : array() as $e ) {
			$e = self::sanitize_entity( $e );
			if ( '' === $e['name'] || '' === $e['key'] || isset( $seen[ $e['key'] ] ) ) {
				continue;
			}
			$seen[ $e['key'] ] = true;
			$out[]             = $e;
		}
		return $out;
	}

	/* ---------- Schema ---------- */

	public static function node( array $e, array $all ) {
		$node = array(
			'@type' => $e['type'],
			'@id'   => self::entity_id( $e['key'] ),
			'name'  => $e['name'],
		);
		if ( $e['alternateName'] ) {
			$node['alternateName'] = 1 === count( $e['alternateName'] ) ? $e['alternateName'][0] : $e['alternateName'];
		}
		foreach ( array( 'description', 'url', 'hasMap' ) as $field ) {
			if ( '' !== $e[ $field ] ) {
				$node[ $field ] = $e[ $field ];
			}
		}
		if ( '' !== $e['image'] ) {
			$image = ctype_digit( (string) $e['image'] ) ? wp_get_attachment_image_url( (int) $e['image'], 'full' ) : $e['image'];
			if ( $image ) {
				$node['image'] = $image;
			}
		}
		$address = array();
		foreach ( array( 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry' ) as $field ) {
			if ( '' !== $e[ $field ] ) {
				$address[ $field ] = $e[ $field ];
			}
		}
		if ( $address ) {
			$node['address'] = array( '@type' => 'PostalAddress' ) + $address;
		}
		if ( '' !== $e['latitude'] && '' !== $e['longitude'] ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $e['latitude'],
				'longitude' => (float) $e['longitude'],
			);
		}
		if ( $e['containedInPlace'] && isset( $all[ $e['containedInPlace'] ] ) ) {
			$node['containedInPlace'] = array( '@id' => self::entity_id( $e['containedInPlace'] ) );
		}
		if ( $e['amenities'] ) {
			$node['amenityFeature'] = array_map(
				function ( $name ) {
					return array(
						'@type' => 'LocationFeatureSpecification',
						'name'  => $name,
						'value' => true,
					);
				},
				$e['amenities']
			);
		}
		if ( $e['sameAs'] ) {
			$node['sameAs'] = $e['sameAs'];
		}
		return apply_filters( 'pim_schema_entity_node', $node, $e );
	}

	/** Entity keys for a post: [ 'about' => [], 'mentions' => [] ]. */
	public static function post_refs( $post_id ) {
		return array(
			'about'    => self::sanitize_keys( get_post_meta( $post_id, self::META_ABOUT, true ) ),
			'mentions' => self::sanitize_keys( get_post_meta( $post_id, self::META_MENTIONS, true ) ),
		);
	}

	public static function add_nodes( $nodes, $html ) {
		$all = self::all();
		if ( ! $all ) {
			return $nodes;
		}
		$refs = array(
			'about'    => array(),
			'mentions' => array(),
		);
		$post_id = is_singular() ? get_queried_object_id() : ( is_front_page() ? (int) get_option( 'page_on_front' ) : 0 );
		if ( $post_id ) {
			$refs = self::post_refs( $post_id );
		}
		$refs = apply_filters( 'pim_schema_entity_refs', $refs, $post_id );

		$output   = array();
		$org_locs = array();
		foreach ( $all as $key => $e ) {
			if ( $e['orgLocation'] ) {
				$org_locs[] = $key;
			}
		}
		// Only link the Organization when something defines it (Rank Math by default).
		$has_org  = (bool) apply_filters( 'pim_schema_has_organization', defined( 'RANK_MATH_VERSION' ) );
		$show_org = $has_org && $org_locs && ( is_front_page() || array_intersect( $org_locs, array_merge( $refs['about'], $refs['mentions'] ) ) );

		$queue = array_merge( $refs['about'], $refs['mentions'], array_keys( self::$included ), $show_org ? $org_locs : array() );
		// Include parent places (containedInPlace chain).
		for ( $i = 0; $i < count( $queue ) && $i < 50; $i++ ) {
			$key = $queue[ $i ];
			if ( ! isset( $all[ $key ] ) || isset( $output[ $key ] ) ) {
				continue;
			}
			$output[ $key ] = self::node( $all[ $key ], $all );
			if ( $all[ $key ]['containedInPlace'] ) {
				$queue[] = $all[ $key ]['containedInPlace'];
			}
		}
		if ( ! $output ) {
			return $nodes;
		}

		$ids = function ( $keys ) use ( $all ) {
			$list = array();
			foreach ( $keys as $key ) {
				if ( isset( $all[ $key ] ) ) {
					$list[] = array( '@id' => self::entity_id( $key ) );
				}
			}
			return $list;
		};
		$about    = $ids( $refs['about'] );
		$mentions = $ids( $refs['mentions'] );
		if ( $about || $mentions ) {
			$page = array(
				'@type' => 'WebPage',
				'@id'   => PIM_Schema_Output::current_url() . '#webpage',
			);
			if ( $about ) {
				$page['about'] = $about;
			}
			if ( $mentions ) {
				$page['mentions'] = $mentions;
			}
			$nodes[] = $page;
		}
		if ( $show_org ) {
			$nodes[] = array(
				'@type'    => 'Organization',
				'@id'      => home_url( '/' ) . '#organization',
				'location' => $ids( $org_locs ),
			);
		}
		return array_merge( $nodes, array_values( $output ) );
	}

	/* ---------- Block editor panel ---------- */

	public static function editor_assets() {
		$all = self::all();
		wp_enqueue_script(
			'pim-schema-entities-panel',
			PIM_SCHEMA_URL . 'assets/entities-panel.js',
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-edit-post', 'wp-editor' ),
			PIM_SCHEMA_VERSION,
			true
		);
		wp_add_inline_script(
			'pim-schema-entities-panel',
			'window.pimSchemaEntities = ' . wp_json_encode(
				array_values(
					array_map(
						function ( $e ) {
							return array(
								'key'  => $e['key'],
								'name' => $e['name'],
								'type' => $e['type'],
							);
						},
						$all
					)
				)
			) . ';',
			'before'
		);
	}
}
