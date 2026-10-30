/**
 * PIM Schema: "Schema: About / Mentions" panel in the block editor's document sidebar.
 * Writes the post meta _pim_schema_about / _pim_schema_mentions (entity keys).
 */
( function ( wp, entities ) {
	'use strict';

	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! Panel || ! wp.plugins || ! entities || ! entities.length ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var data = wp.data;

	function Group( props ) {
		return el(
			'fieldset',
			{ className: 'pim-schema-entities__group', style: { marginBlockEnd: '16px' } },
			el( 'legend', { style: { fontWeight: 600, marginBlockEnd: '8px' } }, props.label ),
			entities.map( function ( e ) {
				return el( c.CheckboxControl, {
					key: e.key,
					label: e.name,
					help: e.type,
					checked: props.value.indexOf( e.key ) !== -1,
					onChange: function ( on ) {
						var next = props.value.filter( function ( k ) {
							return k !== e.key;
						} );
						if ( on ) {
							next.push( e.key );
						}
						props.onChange( next );
					},
				} );
			} )
		);
	}

	function EntitiesPanel() {
		var meta = data.useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		}, [] );
		var editPost = data.useDispatch( 'core/editor' ).editPost;
		if ( ! meta || ! ( '_pim_schema_about' in meta ) ) {
			return null; // Post type without custom-fields support.
		}
		function set( key ) {
			return function ( value ) {
				var patch = {};
				patch[ key ] = value;
				editPost( { meta: patch } );
			};
		}
		return el(
			Panel,
			{ name: 'pim-schema-entities', title: __( 'Schema: About / Mentions', 'pim-schema' ) },
			el( 'p', { style: { marginBlockStart: 0 } }, __( 'What this page is about (main topic) and what it mentions. Adds structured data that links the page to these places.', 'pim-schema' ) ),
			el( Group, { label: __( 'About', 'pim-schema' ), value: meta._pim_schema_about || [], onChange: set( '_pim_schema_about' ) } ),
			el( Group, { label: __( 'Mentions', 'pim-schema' ), value: meta._pim_schema_mentions || [], onChange: set( '_pim_schema_mentions' ) } )
		);
	}

	wp.plugins.registerPlugin( 'pim-schema-entities', { render: EntitiesPanel } );
} )( window.wp, window.pimSchemaEntities );
