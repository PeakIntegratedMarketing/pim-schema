<?php
/**
 * Example: post-type mappings for a real-estate community site (listings, vacation rentals, team).
 * Copy into the site's own plugin and adjust the post types, meta keys and entity key.
 * This is the mapping used on the first PIM site, with the site-specific names generalised.
 * Template syntax: see includes/types/class-post-types.php. Does nothing without PIM Schema.
 *
 * - Listing  → RealEstateListing (+ Offer) about a SingleFamilyResidence in the Example Village entity.
 * - Rental   → VacationRental containing a House. No aggregateRating: the ratings are Airbnb's
 *              (third-party reviews aren't allowed in review markup).
 * - Team     → Person working for the Organization; the placeholder headshot is left out.
 * - Loop pages /villas-for-sale/, /stay/, /team/ → ItemList of the posts they show.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'pim_schema_post_types', 'example_schema_post_types' );

function example_schema_post_types( $types ) {
	$types['property_listing'] = array(
		'item_list' => array( 'villas-for-sale' ),
		'nodes'     => array(
			array(
				'@type'        => 'RealEstateListing',
				'@id'          => '#listing',
				'name'         => '{title}',
				'headline'     => '{meta:headline}',
				'description'  => '{meta_text:property_details}',
				'url'          => '{url}',
				'datePosted'   => '{date}',
				'dateModified' => '{modified}',
				'image'        => array( '{image}', '{meta_image:hero_image}', '{meta_gallery:gallery|10}' ),
				'identifier'   => '{meta:property_id}',
				'mainEntity'   => '{ref:#home}',
				'offers'       => array(
					'@if'           => '{meta:price}',
					'@type'         => 'Offer',
					'price'         => '{meta:price}',
					'priceCurrency' => 'USD',
					'availability'  => 'example_schema_availability',
					'url'           => '{url}',
					'itemOffered'   => '{ref:#home}',
					'seller'        => '{org}',
					'offeredBy'     => '{post:agent}',
				),
			),
			array(
				'@type'                  => 'SingleFamilyResidence',
				'@id'                    => '#home',
				'name'                   => '{title}',
				'description'            => '{meta:subtitle}',
				'image'                  => '{image}',
				'numberOfBedrooms'       => '{meta:beds}',
				'numberOfBathroomsTotal' => '{meta:baths}',
				'floorSize'              => array( '@if' => '{meta:interior_sqft}', '@type' => 'QuantitativeValue', 'value' => '{meta:interior_sqft}', 'unitCode' => 'FTK' ),
				'yearBuilt'              => '{meta:year_built}',
				'accommodationFloorPlan' => array( '@if' => '{meta:floorplan}', '@type' => 'FloorPlan', 'layoutImage' => '{meta_image:floorplan}' ),
				'additionalProperty'     => array(
					array( '@if' => '{meta:lot_sqft}', '@type' => 'PropertyValue', 'name' => 'Lot size', 'value' => '{meta:lot_sqft}', 'unitCode' => 'FTK' ),
					array( '@if' => '{meta:model_name}', '@type' => 'PropertyValue', 'name' => 'Model design', 'value' => '{terms:model_design}' ),
					array( '@if' => '{meta:condition}', '@type' => 'PropertyValue', 'name' => 'Condition', 'value' => '{meta:condition_label}' ),
				),
				'geo'                    => array( '@if' => '{meta:latitude}', '@type' => 'GeoCoordinates', 'latitude' => '{meta:latitude}', 'longitude' => '{meta:longitude}' ),
				'containedInPlace'       => '{entity:example-village}',
			),
		),
	);

	$types['rental'] = array(
		'item_list' => array( 'stay' ),
		'nodes'     => array(
			array(
				'@type'            => 'VacationRental',
				'@id'              => '#rental',
				'name'             => '{title}',
				'description'      => '{meta_text:summary}',
				'url'              => '{url}',
				'image'            => array( '{image}', '{meta_image:hero_image}', '{meta_gallery:gallery|10}' ),
				'sameAs'           => '{meta:airbnb_url}',
				'checkinTime'      => 'example_schema_checkin',
				'checkoutTime'     => 'example_schema_checkout',
				'amenityFeature'   => 'example_schema_rental_amenities',
				'priceRange'       => 'example_schema_price_range',
				'containedInPlace' => '{entity:example-village}',
				'containsPlace'    => '{ref:#unit}',
			),
			array(
				'@type'                  => 'House',
				'@id'                    => '#unit',
				'name'                   => '{title}',
				'occupancy'              => array( '@if' => '{meta:guests}', '@type' => 'QuantitativeValue', 'maxValue' => '{meta:guests}' ),
				'numberOfBedrooms'       => '{meta:bedrooms}',
				'numberOfBathroomsTotal' => '{meta:baths}',
				'bed'                    => array( '@if' => '{meta:beds}', '@type' => 'BedDetails', 'numberOfBeds' => '{meta:beds}' ),
			),
		),
	);

	$types['team_member'] = array(
		'item_list' => array( 'team' ),
		'nodes'     => array(
			array(
				'@type'       => 'Person',
				'@id'         => '#person',
				'name'        => '{title}',
				'jobTitle'    => '{meta:job_title}',
				'description' => '{meta_text:bio}',
				'url'         => '{url}',
				'image'       => 'example_schema_headshot',
				'email'       => '{meta:email}',
				'telephone'   => array( '{meta:phone}', '{meta:phone_us}' ),
				'worksFor'    => '{org}',
			),
		),
	);

	// Closures are what the engine calls; the named functions above keep the array readable.
	array_walk_recursive(
		$types,
		function ( &$value ) {
			if ( is_string( $value ) && 0 === strpos( $value, 'example_schema_' ) && function_exists( $value ) ) {
				$value = Closure::fromCallable( $value );
			}
		}
	);
	return $types;
}

function example_schema_availability( $post ) {
	$map = array(
		'for_sale' => 'https://schema.org/InStock',
		'reserved' => 'https://schema.org/Reserved',
		'sold'     => 'https://schema.org/SoldOut',
	);
	$status = get_post_meta( $post->ID, 'status', true );
	return $map[ $status ] ?? null;
}

/** "After 3:00 PM" → "15:00:00". */
function example_schema_time( $text ) {
	if ( ! preg_match( '/(\d{1,2})(?::(\d{2}))?\s*([AaPp])\.?\s*[Mm]/', (string) $text, $m ) ) {
		return null;
	}
	$h = (int) $m[1] % 12 + ( 'p' === strtolower( $m[3] ) ? 12 : 0 );
	return sprintf( '%02d:%02d:00', $h, isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0 );
}

function example_schema_checkin( $post ) {
	return example_schema_time( get_post_meta( $post->ID, 'check_in', true ) );
}

function example_schema_checkout( $post ) {
	return example_schema_time( get_post_meta( $post->ID, 'check_out', true ) );
}

function example_schema_rental_amenities( $post ) {
	$out = array();
	foreach ( array( 'pool', 'parking', 'services', 'entertainment', 'kitchen', 'location', 'climate', 'bath', 'internet' ) as $group ) {
		foreach ( explode( ',', (string) get_post_meta( $post->ID, 'amen_' . $group, true ) ) as $name ) {
			$name = trim( $name );
			if ( '' !== $name ) {
				$out[ strtolower( $name ) ] = array(
					'@type' => 'LocationFeatureSpecification',
					'name'  => $name,
					'value' => true,
				);
			}
		}
	}
	return array_values( $out );
}

function example_schema_price_range( $post ) {
	$price = (float) get_post_meta( $post->ID, 'price_from', true );
	if ( $price <= 0 ) {
		return null;
	}
	return trim( 'From $' . number_format( $price ) . ' ' . get_post_meta( $post->ID, 'price_unit', true ) );
}

function example_schema_headshot( $post ) {
	$id = (int) get_post_thumbnail_id( $post );
	if ( ! $id || false !== strpos( (string) get_post_field( 'post_name', $id ), 'placeholder' ) ) {
		return null;
	}
	$url = wp_get_attachment_url( $id );
	return $url ? $url : null;
}
