<?php
/**
 * The FAQ blocks (pim/faq + pim/faq-item). Dynamic: the saved content is only the
 * answer blocks, the wrapper markup comes from PHP, so markup changes never break
 * saved posts. Markup matches the Etch FAQ convention (details.faq-item etc.).
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_FAQ_Blocks {

	const QUESTION_TAGS = array( 'none', 'h2', 'h3', 'h4', 'h5', 'h6' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		wp_register_script(
			'pim-schema-faq-parse',
			PIM_SCHEMA_URL . 'assets/faq-parse.js',
			array(),
			PIM_SCHEMA_VERSION,
			true
		);
		wp_register_script(
			'pim-schema-faq-editor',
			PIM_SCHEMA_URL . 'assets/faq-editor.js',
			array( 'pim-schema-faq-parse', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
			PIM_SCHEMA_VERSION,
			true
		);
		wp_add_inline_script(
			'pim-schema-faq-editor',
			'window.pimSchemaFaq = ' . wp_json_encode(
				array(
					'icon'  => PIM_Schema_Settings::get( 'faq_icon' ),
					'icons' => PIM_Schema_Settings::icons(),
				)
			) . ';',
			'before'
		);
		// Registered either way so block.json's handle resolves; empty when styles are off.
		wp_register_style(
			'pim-schema-faq',
			PIM_Schema_Settings::get( 'faq_styles' ) ? PIM_SCHEMA_URL . 'assets/faq.css' : false,
			array(),
			PIM_SCHEMA_VERSION
		);
		wp_register_style(
			'pim-schema-faq-editor',
			PIM_SCHEMA_URL . 'assets/faq-editor.css',
			array(),
			PIM_SCHEMA_VERSION
		);

		register_block_type( PIM_SCHEMA_DIR . 'blocks/faq', array( 'render_callback' => array( __CLASS__, 'render_faq' ) ) );
		register_block_type( PIM_SCHEMA_DIR . 'blocks/faq-item', array( 'render_callback' => array( __CLASS__, 'render_item' ) ) );
	}

	/** Inline formatting allowed in questions and titles. */
	private static function inline_kses( $html ) {
		return trim(
			wp_kses(
				(string) $html,
				array(
					'strong' => array(),
					'b'      => array(),
					'em'     => array(),
					'i'      => array(),
					'br'     => array(),
				)
			)
		);
	}

	public static function render_faq( $attributes, $content ) {
		if ( '' === trim( $content ) ) {
			return '';
		}
		$icon = $attributes['icon'] ?? '';
		if ( ! array_key_exists( $icon, PIM_Schema_Settings::icons() ) ) {
			$icon = PIM_Schema_Settings::get( 'faq_icon' );
		}
		$extra = array(
			'class'        => 'faq',
			'data-faq-icon' => $icon,
		);
		if ( isset( $attributes['schema'] ) && ! $attributes['schema'] ) {
			$extra['data-faq-schema'] = 'off';
		}

		// One open at a time: native exclusive accordion via a shared name.
		if ( ! empty( $attributes['exclusive'] ) && class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$name = wp_unique_id( 'faq-' );
			$tags = new WP_HTML_Tag_Processor( $content );
			while ( $tags->next_tag( 'details' ) ) {
				if ( null !== $tags->get_attribute( 'data-faq' ) ) {
					$tags->set_attribute( 'name', $name );
				}
			}
			$content = $tags->get_updated_html();
		}

		$title = self::inline_kses( $attributes['title'] ?? '' );
		$level = min( 6, max( 2, (int) ( $attributes['titleLevel'] ?? 2 ) ) );
		$head  = '' === $title ? '' : sprintf( '<h%1$d class="faq__title">%2$s</h%1$d>', $level, $title );

		return sprintf(
			'<div %1$s>%2$s<div class="faq__list">%3$s</div></div>',
			get_block_wrapper_attributes( $extra ),
			$head,
			$content
		);
	}

	public static function render_item( $attributes, $content, $block ) {
		$question = self::inline_kses( $attributes['question'] ?? '' );
		if ( '' === $question ) {
			return '';
		}
		$tag = $block->context['pim/faqQuestionTag'] ?? 'none';
		$tag = in_array( $tag, self::QUESTION_TAGS, true ) ? $tag : 'none';
		$tag = 'none' === $tag ? 'span' : $tag;

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'faq-item' ) );
		return sprintf(
			'<details %1$s data-faq%2$s><summary class="faq-item__question"><span class="faq-item__icon" aria-hidden="true"></span><%3$s class="faq-item__title">%4$s</%3$s></summary><div class="faq-item__answer">%5$s</div></details>',
			$wrapper,
			empty( $attributes['open'] ) ? '' : ' open',
			$tag,
			$question,
			$content
		);
	}
}
