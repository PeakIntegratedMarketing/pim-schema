<?php
// Run: php tests/extractor.test.php  (no WordPress needed: minimal wp_kses stub below)
define( 'ABSPATH', __DIR__ );

// Stub: keep allowed tags with allowed attributes only (like wp_kses for this use).
function wp_kses( $html, $allowed ) {
	$html = strip_tags( $html, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
	return preg_replace_callback( '#<(/?)([a-z0-9]+)\b([^>]*)>#i', function ( $m ) use ( $allowed ) {
		$tag = strtolower( $m[2] );
		$attrs = '';
		if ( '' === $m[1] && preg_match_all( '#([a-z-]+)="([^"]*)"#i', $m[3], $a, PREG_SET_ORDER ) ) {
			foreach ( $a as $attr ) {
				if ( isset( $allowed[ $tag ][ strtolower( $attr[1] ) ] ) ) {
					$attrs .= ' ' . $attr[1] . '="' . $attr[2] . '"';
				}
			}
		}
		return '<' . $m[1] . $tag . $attrs . '>';
	}, $html );
}
require __DIR__ . '/../includes/faq/class-faq-extractor.php';

$fail = 0;
function check( $label, $ok ) { global $fail; echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fail++; } }

// 1. Etch markup (synthetic page with the structure of a real Etch FAQ page).
$items = PIM_Schema_FAQ_Extractor::extract( file_get_contents( __DIR__ . '/fixtures/etch-faqs.html' ) );
check( 'Etch faqs page: items found (' . count( $items ) . ')', count( $items ) > 10 );
check( 'first question text', 'What Is Example Village?' === $items[0]['question'] );
check( 'answer keeps <p>, no div wrapper', 0 === strpos( $items[0]['answer'], '<p>Example Village is a private' ) );
check( 'no icon text / attributes in answers', ! preg_grep( '/class=|aria-hidden/', array_column( $items, 'answer' ) ) );

// 2. Block markup, opt-out, dedupe, sanitising.
$html = '<html><head></head><body>
<div class="faq" data-faq-icon="chevron"><div class="faq__list">
<details class="wp-block-pim-faq-item faq-item" data-faq><summary class="faq-item__question"><span class="faq-item__icon" aria-hidden="true"></span><span class="faq-item__title">Can I <em>rent</em> it?</span></summary><div class="faq-item__answer"><p>Yes, <a href="/stay/" class="x">see Stay</a>.</p><ul><li>One</li></ul><script>bad()</script><img src="x.jpg"></div></details>
<details data-faq><summary>Can I rent it?</summary><p>Duplicate</p></details>
</div></div>
<div data-faq-schema="off"><details class="faq-item"><summary>Hidden?</summary><p>No</p></details></div>
<details class="wp-block-details"><summary>Not an FAQ</summary><p>x</p></details>
<details class="faq-item"><summary>Empty answer</summary></details>
<details class="my-faq"><summary>Custom class?</summary><p>Yes</p></details>
</body></html>';
$items = PIM_Schema_FAQ_Extractor::extract( $html );
check( 'block items + dedupe + opt-out + core details ignored (' . count( $items ) . ')', 1 === count( $items ) );
check( 'question text without icon, entities ok', 'Can I rent it?' === $items[0]['question'] );
check( 'answer sanitised: href kept, class/script/img dropped', '<p>Yes, <a href="/stay/">see Stay</a>.</p><ul><li>One</li></ul>' === $items[0]['answer'] );
$items = PIM_Schema_FAQ_Extractor::extract( $html, array( 'faq-item', 'my-faq' ) );
check( 'custom class setting', 2 === count( $items ) && 'Custom class?' === $items[1]['question'] );
check( 'no details → empty', array() === PIM_Schema_FAQ_Extractor::extract( '<p>hi</p>' ) );

exit( $fail ? 1 : 0 );
