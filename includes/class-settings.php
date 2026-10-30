<?php
/**
 * Settings → PIM Schema. One option array, `pim_schema`.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Settings {

	const OPTION = 'pim_schema';

	public static function defaults() {
		return array(
			'faq_schema'  => 1,          // Output FAQPage JSON-LD.
			'faq_styles'  => 1,          // Load the FAQ block's default stylesheet.
			'faq_icon'    => 'chevron',  // Default icon for FAQ blocks: chevron | plus | triangle | native | none.
			'faq_classes' => 'faq-item', // Classes that mark a <details> as an FAQ item in theme/Etch markup (space-separated).
			'rm_global_entities' => 1,   // Keep Rank Math's base graph on posts whose Schema Type is "None".
		);
	}

	public static function icons() {
		return array(
			'chevron'  => __( 'Chevron', 'pim-schema' ),
			'plus'     => __( 'Plus / minus', 'pim-schema' ),
			'triangle' => __( 'Triangle', 'pim-schema' ),
			'native'   => __( 'Browser default (native marker)', 'pim-schema' ),
			'none'     => __( 'None', 'pim-schema' ),
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return null === $key ? $opts : ( $opts[ $key ] ?? null );
	}

	/** Classes from the settings field, as a clean list. */
	public static function faq_classes() {
		$classes = preg_split( '/[\s,]+/', (string) self::get( 'faq_classes' ), -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_filter( array_map( 'sanitize_html_class', $classes ) ) );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		// On init (not admin_init) so the option is also registered for REST requests (/wp/v2/settings).
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_pim-schema/pim-schema.php', array( __CLASS__, 'action_link' ) );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=pim-schema' ) ) . '">' . esc_html__( 'Settings', 'pim-schema' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_options_page( 'PIM Schema', 'PIM Schema', 'manage_options', 'pim-schema', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting(
			'pim_schema',
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'faq_schema'  => array( 'type' => 'integer' ),
							'faq_styles'  => array( 'type' => 'integer' ),
							'faq_icon'    => array( 'type' => 'string' ),
							'faq_classes' => array( 'type' => 'string' ),
							'rm_global_entities' => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
	}

	public static function sanitize( $input ) {
		$input = (array) $input;
		$icon  = $input['faq_icon'] ?? 'chevron';
		return array(
			'faq_schema'  => empty( $input['faq_schema'] ) ? 0 : 1,
			'faq_styles'  => empty( $input['faq_styles'] ) ? 0 : 1,
			'faq_icon'    => array_key_exists( $icon, self::icons() ) ? $icon : 'chevron',
			'faq_classes' => implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/[\s,]+/', (string) ( $input['faq_classes'] ?? '' ) ) ) ) ),
			'rm_global_entities' => empty( $input['rm_global_entities'] ) ? 0 : 1,
		);
	}

	public static function page() {
		$o    = self::get();
		$name = self::OPTION;
		$tab  = isset( $_GET['tab'] ) && 'entities' === $_GET['tab'] ? 'entities' : 'faq'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$base = admin_url( 'options-general.php?page=pim-schema' );
		?>
		<div class="wrap">
			<h1>PIM Schema</h1>
			<nav class="nav-tab-wrapper" style="margin-block-end: 16px;">
				<a href="<?php echo esc_url( $base ); ?>" class="nav-tab<?php echo 'faq' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'FAQs', 'pim-schema' ); ?></a>
				<a href="<?php echo esc_url( $base . '&tab=entities' ); ?>" class="nav-tab<?php echo 'entities' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Entities', 'pim-schema' ); ?></a>
			</nav>
			<?php
			if ( 'entities' === $tab ) {
				PIM_Schema_Entities_Admin::render();
				echo '</div>';
				return;
			}
			?>
			<form method="post" action="options.php">
				<?php settings_fields( 'pim_schema' ); ?>
				<h2><?php esc_html_e( 'FAQs', 'pim-schema' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'FAQ schema', 'pim-schema' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[faq_schema]" value="1" <?php checked( $o['faq_schema'] ); ?>> <?php esc_html_e( 'Output FAQPage JSON-LD for the FAQs on each page', 'pim-schema' ); ?></label>
						<p class="description"><?php esc_html_e( 'Collects every FAQ on the rendered page (FAQ blocks and theme/Etch FAQ markup) into one FAQPage. Skipped when the page already has FAQPage schema from another plugin.', 'pim-schema' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="pim-faq-classes"><?php esc_html_e( 'FAQ item classes', 'pim-schema' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="pim-faq-classes" name="<?php echo esc_attr( $name ); ?>[faq_classes]" value="<?php echo esc_attr( $o['faq_classes'] ); ?>">
						<p class="description"><?php esc_html_e( 'A <details> element with one of these classes counts as an FAQ item (question = <summary>, answer = the rest). Elements with a data-faq attribute always count. Add data-faq-schema="off" to an item or a wrapper to leave it out.', 'pim-schema' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default styles', 'pim-schema' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[faq_styles]" value="1" <?php checked( $o['faq_styles'] ); ?>> <?php esc_html_e( 'Load the FAQ block stylesheet', 'pim-schema' ); ?></label>
						<p class="description"><?php esc_html_e( 'Zero-specificity CSS using ACSS variables when present, so any site CSS wins. Turn off to style the block entirely from the site.', 'pim-schema' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="pim-faq-icon"><?php esc_html_e( 'Default icon', 'pim-schema' ); ?></label></th>
						<td><select id="pim-faq-icon" name="<?php echo esc_attr( $name ); ?>[faq_icon]">
							<?php foreach ( self::icons() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $o['faq_icon'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used by FAQ blocks set to "Site default".', 'pim-schema' ); ?></p></td>
					</tr>
				</table>
				<h2><?php esc_html_e( 'Rank Math', 'pim-schema' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Base schema everywhere', 'pim-schema' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[rm_global_entities]" value="1" <?php checked( $o['rm_global_entities'] ); ?>> <?php esc_html_e( 'Keep Rank Math\'s Organization, WebSite and WebPage on posts whose Schema Type is "None"', 'pim-schema' ); ?></label>
						<p class="description"><?php esc_html_e( 'Rank Math drops its whole graph on those posts (pages, custom post types), which leaves PIM Schema nothing to attach to. The unused author Person it would add is removed.', 'pim-schema' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Pasting an FAQ from Google Docs', 'pim-schema' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In the doc, format each question as Heading 3 and write its answer as normal text under it (paragraphs, lists and links are kept). A heading above the questions, such as "Frequently Asked Questions", becomes the FAQ title.', 'pim-schema' ); ?></li>
				<li><?php esc_html_e( 'Copy the whole FAQ section.', 'pim-schema' ); ?></li>
				<li><?php esc_html_e( 'In the post, add an FAQ block, click its paste box and paste. Or paste the text anywhere, select the pasted blocks and choose Transform → FAQ.', 'pim-schema' ); ?></li>
			</ol>
			<p><?php esc_html_e( 'Without Heading 3 questions, bold lines are used as questions, then other headings, then lines ending in "?".', 'pim-schema' ); ?></p>
		</div>
		<?php
	}
}
