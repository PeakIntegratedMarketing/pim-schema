<?php
/**
 * FAQPage node: every FAQ item on the rendered page, merged into one FAQPage.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_FAQ_Schema {

	public static function init() {
		add_filter( 'pim_schema_page_nodes', array( __CLASS__, 'add_node' ), 10, 2 );
	}

	public static function add_node( $nodes, $html ) {
		if ( ! PIM_Schema_Settings::get( 'faq_schema' ) || ! apply_filters( 'pim_schema_faq_enabled', true ) ) {
			return $nodes;
		}
		// Another plugin (e.g. a Rank Math or Yoast FAQ block) already outputs FAQPage: don't duplicate it.
		if ( preg_match( '/"@type"\s*:\s*(?:\[[^\]]*)?"FAQPage"/', $html ) ) {
			return $nodes;
		}

		$items = PIM_Schema_FAQ_Extractor::extract( $html, PIM_Schema_Settings::faq_classes() );
		$items = apply_filters( 'pim_schema_faq_items', $items );
		if ( ! $items ) {
			return $nodes;
		}

		$url  = PIM_Schema_Output::current_url();
		$node = array(
			'@type'      => 'FAQPage',
			'@id'        => $url . '#faq',
			'url'        => $url,
			'inLanguage' => get_bloginfo( 'language' ),
			'mainEntity' => array(),
		);
		foreach ( $items as $item ) {
			$node['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}
		$nodes[] = apply_filters( 'pim_schema_faq_node', $node, $items );
		return $nodes;
	}
}
