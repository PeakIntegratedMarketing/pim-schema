<?php
/**
 * Front-end output: buffers the rendered page, asks the modules for schema nodes
 * (they can read the final HTML, which covers Etch templates, components and loops),
 * and prints them as one JSON-LD script before </head>.
 *
 * Modules add nodes with the `pim_schema_page_nodes` filter: ( array $nodes, string $html ).
 * Nodes with the same @id are merged (e.g. the entities and post-type modules both add to the WebPage).
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Output {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), 0 );
	}

	public static function should_run() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( is_feed() || is_embed() || is_robots() || is_trackback() || is_favicon() ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}
		return (bool) apply_filters( 'pim_schema_should_run', true );
	}

	public static function start() {
		if ( self::should_run() ) {
			ob_start( array( __CLASS__, 'finish' ) );
		}
	}

	/**
	 * Output-buffer callback. Never breaks the page: any problem returns the HTML unchanged.
	 */
	public static function finish( $html, $phase = PHP_OUTPUT_HANDLER_FINAL ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		// Only act on the complete document (a mid-page flush would leave us a fragment).
		if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) && false === stripos( $html, '</html>' ) ) {
			return $html;
		}
		if ( false === stripos( $html, '</head>' ) || ! self::is_html_response() ) {
			return $html;
		}
		try {
			$nodes = apply_filters( 'pim_schema_page_nodes', array(), $html );
			$nodes = self::merge( array_values( array_filter( (array) $nodes ) ) );
			if ( ! $nodes ) {
				return $html;
			}
			$doc  = array(
				'@context' => 'https://schema.org',
				'@graph'   => $nodes,
			);
			$json = wp_json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
			if ( ! $json ) {
				return $html;
			}
			$script = '<script type="application/ld+json" class="pim-schema">' . $json . '</script>' . "\n";
			$pos    = stripos( $html, '</head>' );
			return substr( $html, 0, $pos ) . $script . substr( $html, $pos );
		} catch ( \Throwable $e ) {
			return $html;
		}
	}

	/**
	 * Merges nodes that share an @id. The first node's scalar values win; references and lists are combined.
	 */
	public static function merge( array $nodes ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$id = isset( $node['@id'] ) && is_string( $node['@id'] ) ? $node['@id'] : null;
			if ( null === $id || ! isset( $out[ $id ] ) ) {
				if ( null === $id ) {
					$out[] = $node;
				} else {
					$out[ $id ] = $node;
				}
				continue;
			}
			foreach ( $node as $key => $value ) {
				if ( ! array_key_exists( $key, $out[ $id ] ) ) {
					$out[ $id ][ $key ] = $value;
					continue;
				}
				$have = $out[ $id ][ $key ];
				if ( '@type' === $key || '@id' === $key || ! is_array( $have ) || ! is_array( $value ) ) {
					continue;
				}
				$list = array_merge( self::as_list( $have ), self::as_list( $value ) );
				$seen = array();
				$uniq = array();
				foreach ( $list as $item ) {
					$k = wp_json_encode( $item );
					if ( ! isset( $seen[ $k ] ) ) {
						$seen[ $k ] = true;
						$uniq[]     = $item;
					}
				}
				$out[ $id ][ $key ] = 1 === count( $uniq ) ? $uniq[0] : $uniq;
			}
		}
		return array_values( $out );
	}

	private static function as_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 ) ? $value : array( $value );
	}

	private static function is_html_response() {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) ) {
				return false !== stripos( $header, 'text/html' );
			}
		}
		return true;
	}

	/** Canonical URL of the current request, for @id values. */
	public static function current_url() {
		if ( is_singular() ) {
			$url = get_permalink();
		} elseif ( is_front_page() || is_home() ) {
			$url = is_front_page() ? home_url( '/' ) : get_permalink( (int) get_option( 'page_for_posts' ) );
		} else {
			global $wp;
			$url = home_url( isset( $wp->request ) ? trailingslashit( $wp->request ) : '/' );
		}
		return (string) apply_filters( 'pim_schema_current_url', $url ? $url : home_url( '/' ) );
	}
}
