<?php
/**
 * Finds FAQ items in rendered HTML.
 *
 * An item is a <details> with `data-faq` (the FAQ block) or one of the configured
 * classes (theme/Etch markup, default `faq-item`). Question = <summary> text without
 * aria-hidden parts (icons); answer = everything else in the <details>.
 * Items inside an element with data-faq-schema="off" are skipped.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_FAQ_Extractor {

	/** Tags Google allows in Answer.text. */
	const ANSWER_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'ol', 'ul', 'li', 'a', 'p', 'div', 'b', 'strong', 'i', 'em' );

	/**
	 * @param string   $html    Full page or fragment.
	 * @param string[] $classes Classes that mark an FAQ <details>.
	 * @return array[] List of [ 'question' => string, 'answer' => string ] (answer is HTML).
	 */
	public static function extract( $html, array $classes = array( 'faq-item' ) ) {
		if ( false === stripos( $html, '<details' ) || ! class_exists( 'DOMDocument' ) ) {
			return array();
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return array();
		}

		$conditions = array( '@data-faq' );
		foreach ( $classes as $class ) {
			$conditions[] = sprintf( "contains(concat(' ', normalize-space(@class), ' '), ' %s ')", $class );
		}
		$xpath = new DOMXPath( $dom );
		$query = '//details[(' . implode( ' or ', $conditions ) . ') and not(ancestor-or-self::*[@data-faq-schema="off"])]';
		$nodes = $xpath->query( $query );
		if ( ! $nodes ) {
			return array();
		}

		$items = array();
		$seen  = array();
		foreach ( $nodes as $details ) {
			$summary = null;
			$answer  = '';
			foreach ( $details->childNodes as $child ) {
				if ( null === $summary && XML_ELEMENT_NODE === $child->nodeType && 'summary' === strtolower( $child->nodeName ) ) {
					$summary = $child;
					continue;
				}
				$answer .= $dom->saveHTML( $child );
			}
			if ( ! $summary ) {
				continue;
			}
			$question = self::question_text( $summary );
			$answer   = self::clean_answer( $answer );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$key = strtolower( $question );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$items[]      = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}
		return $items;
	}

	private static function question_text( DOMElement $summary ) {
		$clone = $summary->cloneNode( true );
		foreach ( self::find_hidden( $clone ) as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
		return self::squash( html_entity_decode( $clone->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private static function find_hidden( DOMNode $node ) {
		$found = array();
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$class = ' ' . $child->getAttribute( 'class' ) . ' ';
			if ( 'true' === $child->getAttribute( 'aria-hidden' ) || false !== strpos( $class, ' faq-item__icon ' ) ) {
				$found[] = $child;
			} else {
				$found = array_merge( $found, self::find_hidden( $child ) );
			}
		}
		return $found;
	}

	/** Keeps Google's allowed tags (links keep href only), drops the rest, tidies whitespace. */
	public static function clean_answer( $html ) {
		$allowed = array();
		foreach ( self::ANSWER_TAGS as $tag ) {
			$allowed[ $tag ] = 'a' === $tag ? array( 'href' => true ) : array();
		}
		$html = preg_replace( '#<(script|style|template|noscript)\b[^>]*>.*?</\1>#is', '', $html );
		$html = function_exists( 'wp_kses' ) ? wp_kses( $html, $allowed ) : strip_tags( $html, '<' . implode( '><', self::ANSWER_TAGS ) . '>' );
		$html = preg_replace( '/\s+/u', ' ', $html );
		$html = preg_replace( '#\s*(</?(?:p|div|ul|ol|li|h[1-6]|br)\b[^>]*>)\s*#i', '$1', $html );
		$html = preg_replace( '#<(p|div|li|strong|b|em|i)>\s*</\1>#i', '', $html );
		$html = trim( $html );
		// Root-relative links → absolute (schema is read outside the page).
		if ( function_exists( 'home_url' ) ) {
			$html = preg_replace_callback( '#href="(/(?!/)[^"]*)"#', function ( $m ) {
				return 'href="' . esc_url( home_url( $m[1] ) ) . '"';
			}, $html );
		}
		// Unwrap a single wrapper div (the Etch/block answer container).
		while ( preg_match( '#^<div>(.*)</div>$#s', $html, $m ) && false === stripos( $m[1], '<div' ) ) {
			$html = trim( $m[1] );
		}
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $html ) : strip_tags( $html );
		return '' === trim( $text ) ? '' : $html;
	}

	private static function squash( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );
	}
}

