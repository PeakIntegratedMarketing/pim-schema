<?php
/**
 * Settings → PIM Schema → Entities tab. One card per entity plus an empty card to add one.
 * The About / Mentions page lists read and write the post meta, so they stay in sync with
 * the block-editor panel and REST.
 */

defined( 'ABSPATH' ) || exit;

class PIM_Schema_Entities_Admin {

	public static function init() {
		add_action( 'admin_post_pim_schema_save_entities', array( __CLASS__, 'save' ) );
	}

	/** Public posts of every public post type (except attachments), for the page pickers. */
	private static function posts() {
		$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
		return get_posts(
			array(
				'post_type'      => array_values( $types ),
				'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
				'posts_per_page' => 1000,
				'orderby'        => array(
					'type'  => 'ASC',
					'title' => 'ASC',
				),
				'no_found_rows'  => true,
			)
		);
	}

	/** [ key => [ 'about' => [post ids], 'mentions' => [post ids] ] ] from post meta. */
	private static function assignments() {
		$out = array();
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => PIM_Schema_Entities::META_ABOUT, 'compare' => 'EXISTS' ),
					array( 'key' => PIM_Schema_Entities::META_MENTIONS, 'compare' => 'EXISTS' ),
				),
			)
		);
		foreach ( $ids as $id ) {
			foreach ( PIM_Schema_Entities::post_refs( $id ) as $rel => $keys ) {
				foreach ( $keys as $key ) {
					$out[ $key ][ $rel ][] = $id;
				}
			}
		}
		return $out;
	}

	public static function render() {
		$entities = array_values( PIM_Schema_Entities::all() );
		$entities[] = PIM_Schema_Entities::sanitize_entity( array() ); // Empty card for a new entity.
		$assigned = self::assignments();
		$posts    = self::posts();
		$keys     = wp_list_pluck( array_slice( $entities, 0, -1 ), 'name', 'key' );

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Entities saved.', 'pim-schema' ) . '</p></div>';
		}
		?>
		<p><?php esc_html_e( 'Places and other things the site talks about, defined once. Pages marked "About" or "Mentions" link to them in their structured data (WebPage about / mentions). Leave a field empty to omit it.', 'pim-schema' ); ?></p>
		<datalist id="pim-entity-types">
			<?php foreach ( PIM_Schema_Entities::TYPES as $type ) : ?>
				<option value="<?php echo esc_attr( $type ); ?>">
			<?php endforeach; ?>
		</datalist>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pim_schema_save_entities">
			<?php wp_nonce_field( 'pim_schema_save_entities' ); ?>
			<?php foreach ( $entities as $i => $e ) : ?>
				<?php
				$is_new = '' === $e['name'];
				$f      = function ( $field ) use ( $i ) {
					return 'entities[' . $i . '][' . $field . ']';
				};
				?>
				<div class="card" style="max-width: 980px; margin-block-end: 16px;">
					<h2 style="margin-block-start: 8px;"><?php echo $is_new ? esc_html__( 'Add an entity', 'pim-schema' ) : esc_html( $e['name'] ) . ' <code style="font-size: 12px;">' . esc_html( $e['key'] ) . '</code>'; ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Name / key / type', 'pim-schema' ); ?></th>
							<td>
								<input type="text" class="regular-text" name="<?php echo esc_attr( $f( 'name' ) ); ?>" value="<?php echo esc_attr( $e['name'] ); ?>" placeholder="<?php esc_attr_e( 'Name', 'pim-schema' ); ?>">
								<input type="text" name="<?php echo esc_attr( $f( 'key' ) ); ?>" value="<?php echo esc_attr( $e['key'] ); ?>" placeholder="<?php esc_attr_e( 'key (from name)', 'pim-schema' ); ?>" size="18">
								<input type="text" list="pim-entity-types" name="<?php echo esc_attr( $f( 'type' ) ); ?>" value="<?php echo esc_attr( $is_new ? '' : $e['type'] ); ?>" placeholder="Place" size="22">
								<p class="description"><?php esc_html_e( 'Key = stable ID used in @id and by pages (changing it unlinks pages). Type = any schema.org type.', 'pim-schema' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Description', 'pim-schema' ); ?></th>
							<td><textarea class="large-text" rows="2" name="<?php echo esc_attr( $f( 'description' ) ); ?>"><?php echo esc_textarea( $e['description'] ); ?></textarea></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Other names', 'pim-schema' ); ?></th>
							<td><textarea class="large-text" rows="2" name="<?php echo esc_attr( $f( 'alternateName' ) ); ?>" placeholder="<?php esc_attr_e( 'One per line', 'pim-schema' ); ?>"><?php echo esc_textarea( implode( "\n", $e['alternateName'] ) ); ?></textarea></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Main page / image', 'pim-schema' ); ?></th>
							<td>
								<input type="url" class="regular-text" name="<?php echo esc_attr( $f( 'url' ) ); ?>" value="<?php echo esc_attr( $e['url'] ); ?>" placeholder="https://">
								<input type="text" class="regular-text" name="<?php echo esc_attr( $f( 'image' ) ); ?>" value="<?php echo esc_attr( $e['image'] ); ?>" placeholder="<?php esc_attr_e( 'Image URL or media ID', 'pim-schema' ); ?>">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Address', 'pim-schema' ); ?></th>
							<td>
								<input type="text" class="large-text" name="<?php echo esc_attr( $f( 'streetAddress' ) ); ?>" value="<?php echo esc_attr( $e['streetAddress'] ); ?>" placeholder="<?php esc_attr_e( 'Street address (as the client writes it)', 'pim-schema' ); ?>">
								<p>
									<input type="text" name="<?php echo esc_attr( $f( 'addressLocality' ) ); ?>" value="<?php echo esc_attr( $e['addressLocality'] ); ?>" placeholder="<?php esc_attr_e( 'Town / city', 'pim-schema' ); ?>">
									<input type="text" name="<?php echo esc_attr( $f( 'addressRegion' ) ); ?>" value="<?php echo esc_attr( $e['addressRegion'] ); ?>" placeholder="<?php esc_attr_e( 'Region / province', 'pim-schema' ); ?>">
									<input type="text" name="<?php echo esc_attr( $f( 'postalCode' ) ); ?>" value="<?php echo esc_attr( $e['postalCode'] ); ?>" placeholder="<?php esc_attr_e( 'Postal code', 'pim-schema' ); ?>" size="10">
									<input type="text" name="<?php echo esc_attr( $f( 'addressCountry' ) ); ?>" value="<?php echo esc_attr( $e['addressCountry'] ); ?>" placeholder="<?php esc_attr_e( 'Country (e.g. CR)', 'pim-schema' ); ?>" size="10">
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Location', 'pim-schema' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( $f( 'latitude' ) ); ?>" value="<?php echo esc_attr( $e['latitude'] ); ?>" placeholder="<?php esc_attr_e( 'Latitude', 'pim-schema' ); ?>" size="14">
								<input type="text" name="<?php echo esc_attr( $f( 'longitude' ) ); ?>" value="<?php echo esc_attr( $e['longitude'] ); ?>" placeholder="<?php esc_attr_e( 'Longitude', 'pim-schema' ); ?>" size="14">
								<input type="url" class="regular-text" name="<?php echo esc_attr( $f( 'hasMap' ) ); ?>" value="<?php echo esc_attr( $e['hasMap'] ); ?>" placeholder="<?php esc_attr_e( 'Map link (Google Business Profile ?cid=)', 'pim-schema' ); ?>">
								<p>
									<label><?php esc_html_e( 'Inside:', 'pim-schema' ); ?>
									<select name="<?php echo esc_attr( $f( 'containedInPlace' ) ); ?>">
										<option value=""><?php esc_html_e( '—', 'pim-schema' ); ?></option>
										<?php foreach ( $keys as $key => $name ) : ?>
											<?php if ( $key !== $e['key'] ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $e['containedInPlace'], $key ); ?>><?php echo esc_html( $name ); ?></option>
											<?php endif; ?>
										<?php endforeach; ?>
									</select></label>
									&nbsp; <label><input type="checkbox" name="<?php echo esc_attr( $f( 'orgLocation' ) ); ?>" value="1" <?php checked( $e['orgLocation'] ); ?>> <?php esc_html_e( 'This is the organization\'s location', 'pim-schema' ); ?></label>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Same as', 'pim-schema' ); ?></th>
							<td><textarea class="large-text code" rows="2" name="<?php echo esc_attr( $f( 'sameAs' ) ); ?>" placeholder="<?php esc_attr_e( 'Wikipedia, Wikidata, Google Business Profile, social profiles: one URL per line', 'pim-schema' ); ?>"><?php echo esc_textarea( implode( "\n", $e['sameAs'] ) ); ?></textarea></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Amenities', 'pim-schema' ); ?></th>
							<td><textarea class="large-text" rows="2" name="<?php echo esc_attr( $f( 'amenities' ) ); ?>" placeholder="<?php esc_attr_e( 'One per line (places only)', 'pim-schema' ); ?>"><?php echo esc_textarea( implode( "\n", $e['amenities'] ) ); ?></textarea></td>
						</tr>
						<?php foreach ( array( 'about' => __( 'Pages about it', 'pim-schema' ), 'mentions' => __( 'Pages that mention it', 'pim-schema' ) ) as $rel => $label ) : ?>
							<tr>
								<th><?php echo esc_html( $label ); ?></th>
								<td>
									<select multiple size="6" style="min-width: 420px;" name="<?php echo esc_attr( $f( $rel . '_posts' ) ); ?>[]">
										<?php
										$type = '';
										foreach ( $posts as $post ) {
											if ( $post->post_type !== $type ) {
												if ( '' !== $type ) {
													echo '</optgroup>';
												}
												$type = $post->post_type;
												$obj  = get_post_type_object( $type );
												echo '<optgroup label="' . esc_attr( $obj ? $obj->labels->name : $type ) . '">';
											}
											$on = in_array( $post->ID, $assigned[ $e['key'] ][ $rel ] ?? array(), true );
											echo '<option value="' . (int) $post->ID . '"' . selected( $on, true, false ) . '>' . esc_html( $post->post_title ? $post->post_title : '#' . $post->ID ) . '</option>';
										}
										if ( '' !== $type ) {
											echo '</optgroup>';
										}
										?>
									</select>
									<p class="description"><?php esc_html_e( 'Ctrl/⌘-click to select several. Same data as the "Schema: About / Mentions" panel in the block editor.', 'pim-schema' ); ?></p>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! $is_new ) : ?>
							<tr>
								<th><?php esc_html_e( 'Remove', 'pim-schema' ); ?></th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( $f( 'remove' ) ); ?>" value="1"> <?php esc_html_e( 'Delete this entity (and unlink its pages)', 'pim-schema' ); ?></label></td>
							</tr>
						<?php endif; ?>
					</table>
				</div>
			<?php endforeach; ?>
			<?php submit_button( __( 'Save entities', 'pim-schema' ) ); ?>
		</form>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'pim-schema' ) );
		}
		check_admin_referer( 'pim_schema_save_entities' );

		$rows     = isset( $_POST['entities'] ) && is_array( $_POST['entities'] ) ? wp_unslash( $_POST['entities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$entities = array();
		$wanted   = array(); // post id => [ 'about' => keys, 'mentions' => keys ]
		foreach ( $rows as $row ) {
			if ( ! empty( $row['remove'] ) || '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
				continue;
			}
			$e          = PIM_Schema_Entities::sanitize_entity( $row );
			$entities[] = $e;
			foreach ( array( 'about', 'mentions' ) as $rel ) {
				foreach ( array_map( 'absint', (array) ( $row[ $rel . '_posts' ] ?? array() ) ) as $post_id ) {
					if ( $post_id ) {
						$wanted[ $post_id ][ $rel ][] = $e['key'];
					}
				}
			}
		}
		update_option( PIM_Schema_Entities::OPTION, PIM_Schema_Entities::sanitize_entities( $entities ) );

		// Rewrite the meta of every post that has it or is now selected.
		$current = array();
		foreach ( self::assignments() as $assignment ) {
			foreach ( $assignment as $ids ) {
				$current = array_merge( $current, $ids );
			}
		}
		foreach ( array_unique( array_merge( $current, array_keys( $wanted ) ) ) as $post_id ) {
			foreach ( array( 'about' => PIM_Schema_Entities::META_ABOUT, 'mentions' => PIM_Schema_Entities::META_MENTIONS ) as $rel => $meta ) {
				$keys = array_values( array_unique( $wanted[ $post_id ][ $rel ] ?? array() ) );
				if ( $keys ) {
					update_post_meta( $post_id, $meta, $keys );
				} else {
					delete_post_meta( $post_id, $meta );
				}
			}
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=pim-schema&tab=entities&saved=1' ) );
		exit;
	}
}
