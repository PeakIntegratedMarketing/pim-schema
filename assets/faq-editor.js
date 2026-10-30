/**
 * PIM Schema: FAQ blocks for the block editor. Plain JS (no build step).
 */
( function ( wp, parser, config ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var blocks = wp.blocks;
	var be = wp.blockEditor;
	var c = wp.components;
	var data = wp.data;

	config = config || { icon: 'chevron', icons: {} };

	/* ---------- Parsed result → blocks ---------- */

	function copy( block ) {
		return block.clientId ? blocks.cloneBlock( block ) : blocks.createBlock( block.name, block.attributes, ( block.innerBlocks || [] ).map( copy ) );
	}

	function itemBlocks( result ) {
		return result.items.map( function ( item ) {
			var answer = item.answer.length ? item.answer.map( copy ) : [ blocks.createBlock( 'core/paragraph' ) ];
			return blocks.createBlock( 'pim/faq-item', { question: item.question }, answer );
		} );
	}

	function parseBlocks( list ) {
		return parser.parse( list );
	}

	/* ---------- Paste box ---------- */

	function PasteBox( props ) {
		var state = useState( '' );
		var notice = state[ 0 ];
		var setNotice = state[ 1 ];

		function onPaste( event ) {
			event.preventDefault();
			var clip = event.clipboardData || window.clipboardData;
			var HTML = clip.getData( 'text/html' ) || '';
			var plainText = clip.getData( 'text/plain' ) || '';
			var list = blocks.pasteHandler( { HTML: HTML, plainText: plainText, mode: 'BLOCKS' } );
			var result = parseBlocks( Array.isArray( list ) ? list : [] );
			if ( ! result.items.length ) {
				setNotice( __( 'No questions found. Format each question as Heading 3 in the doc (or make it bold), with its answer as normal text below it.', 'pim-schema' ) );
				return;
			}
			setNotice( '' );
			props.onParsed( result );
		}

		return el(
			'div',
			{ className: 'pim-faq-paste' },
			el( 'div', {
				className: 'pim-faq-paste__box',
				contentEditable: true,
				suppressContentEditableWarning: true,
				role: 'textbox',
				'aria-label': __( 'Paste your FAQ here', 'pim-schema' ),
				'data-placeholder': __( 'Click here and paste the FAQ (⌘V / Ctrl+V)', 'pim-schema' ),
				onPaste: onPaste,
				onKeyDown: function ( e ) {
					// Only paste is meant to happen here.
					if ( ! ( e.metaKey || e.ctrlKey ) && e.key.length === 1 ) {
						e.preventDefault();
					}
				},
			} ),
			notice ? el( c.Notice, { status: 'warning', isDismissible: false }, notice ) : null
		);
	}

	/* ---------- pim/faq ---------- */

	function FaqEdit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var clientId = props.clientId;

		var hasItems = data.useSelect(
			function ( select ) {
				return select( 'core/block-editor' ).getBlockCount( clientId ) > 0;
			},
			[ clientId ]
		);
		var dispatch = data.useDispatch( 'core/block-editor' );
		var registry = data.useRegistry();

		function applyParsed( result ) {
			var store = registry.select( 'core/block-editor' );
			var rootId = store.getBlockRootClientId( clientId ) || undefined;
			var index = store.getBlockIndex( clientId );
			if ( result.title && ! attributes.title ) {
				setAttributes( { title: result.title, titleLevel: Math.min( 6, Math.max( 2, result.titleLevel ) ) } );
			}
			dispatch.replaceInnerBlocks( clientId, itemBlocks( result ), false );
			if ( result.after.length ) {
				dispatch.insertBlocks( result.after.map( copy ), index + 1, rootId, false );
			}
			if ( result.before.length ) {
				dispatch.insertBlocks( result.before.map( copy ), index, rootId, false );
			}
		}

		var iconLabel = config.icons[ config.icon ] || config.icon;
		var iconOptions = [ { label: sprintf( __( 'Site default (%s)', 'pim-schema' ), iconLabel ), value: '' } ].concat(
			Object.keys( config.icons ).map( function ( key ) {
				return { label: config.icons[ key ], value: key };
			} )
		);
		var icon = attributes.icon || config.icon;

		var blockProps = be.useBlockProps( { className: 'faq', 'data-faq-icon': icon } );
		var innerProps = be.useInnerBlocksProps(
			{ className: 'faq__list' },
			{
				allowedBlocks: [ 'pim/faq-item' ],
				renderAppender: hasItems ? be.InnerBlocks.ButtonBlockAppender : false,
			}
		);

		var inspector = el(
			be.InspectorControls,
			null,
			el(
				c.PanelBody,
				{ title: __( 'FAQ settings', 'pim-schema' ) },
				el( c.SelectControl, {
					label: __( 'Title heading level', 'pim-schema' ),
					value: String( attributes.titleLevel ),
					options: [ 2, 3, 4, 5, 6 ].map( function ( l ) {
						return { label: 'H' + l, value: String( l ) };
					} ),
					onChange: function ( v ) {
						setAttributes( { titleLevel: Number( v ) } );
					},
				} ),
				el( c.SelectControl, {
					label: __( 'Questions as', 'pim-schema' ),
					help: __( 'Plain text is the most accessible choice. Headings put the questions in the page outline, but screen readers may not announce them as headings inside an accordion.', 'pim-schema' ),
					value: attributes.questionTag,
					options: [
						{ label: __( 'Plain text', 'pim-schema' ), value: 'none' },
						{ label: 'H2', value: 'h2' },
						{ label: 'H3', value: 'h3' },
						{ label: 'H4', value: 'h4' },
					],
					onChange: function ( v ) {
						setAttributes( { questionTag: v } );
					},
				} ),
				el( c.SelectControl, {
					label: __( 'Icon', 'pim-schema' ),
					value: attributes.icon,
					options: iconOptions,
					onChange: function ( v ) {
						setAttributes( { icon: v } );
					},
				} ),
				el( c.ToggleControl, {
					label: __( 'One open at a time', 'pim-schema' ),
					help: __( 'Opening a question closes the others.', 'pim-schema' ),
					checked: !! attributes.exclusive,
					onChange: function ( v ) {
						setAttributes( { exclusive: v } );
					},
				} ),
				el( c.ToggleControl, {
					label: __( 'Include in FAQ schema', 'pim-schema' ),
					help: __( 'Turn off for an FAQ repeated from another page.', 'pim-schema' ),
					checked: attributes.schema !== false,
					onChange: function ( v ) {
						setAttributes( { schema: v } );
					},
				} )
			)
		);

		var title = el( be.RichText, {
			tagName: 'h' + attributes.titleLevel,
			className: 'faq__title',
			value: attributes.title,
			allowedFormats: [ 'core/bold', 'core/italic' ],
			placeholder: __( 'FAQ title (optional)', 'pim-schema' ),
			onChange: function ( v ) {
				setAttributes( { title: v } );
			},
		} );

		if ( ! hasItems ) {
			return el(
				Fragment,
				null,
				inspector,
				el(
					'div',
					blockProps,
					el(
						c.Placeholder,
						{
							icon: 'editor-help',
							label: __( 'FAQ', 'pim-schema' ),
							instructions: __( 'Paste the FAQ from Google Docs: questions as Heading 3, answers as normal text below them. A heading above the questions becomes the title.', 'pim-schema' ),
						},
						el( PasteBox, { onParsed: applyParsed } ),
						el(
							c.Button,
							{
								variant: 'secondary',
								onClick: function () {
									dispatch.replaceInnerBlocks( clientId, [ blocks.createBlock( 'pim/faq-item', {}, [ blocks.createBlock( 'core/paragraph' ) ] ) ], true );
								},
							},
							__( 'Add questions one by one', 'pim-schema' )
						)
					)
				)
			);
		}

		return el( Fragment, null, inspector, el( 'div', blockProps, title, el( 'div', innerProps ) ) );
	}

	/* ---------- pim/faq-item ---------- */

	var ANSWER_BLOCKS = [
		'core/paragraph',
		'core/list',
		'core/heading',
		'core/image',
		'core/quote',
		'core/table',
		'core/buttons',
		'core/embed',
		'core/separator',
		'core/html',
	];

	function ItemEdit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		var blockProps = be.useBlockProps( { className: 'faq-item' + ( attributes.open ? ' is-open' : '' ) } );
		var innerProps = be.useInnerBlocksProps(
			{ className: 'faq-item__answer' },
			{
				allowedBlocks: ANSWER_BLOCKS,
				template: [ [ 'core/paragraph', { placeholder: __( 'Answer', 'pim-schema' ) } ] ],
				templateLock: false,
			}
		);

		return el(
			Fragment,
			null,
			el(
				be.InspectorControls,
				null,
				el(
					c.PanelBody,
					{ title: __( 'Question', 'pim-schema' ) },
					el( c.ToggleControl, {
						label: __( 'Open by default', 'pim-schema' ),
						checked: !! attributes.open,
						onChange: function ( v ) {
							setAttributes( { open: v } );
						},
					} )
				)
			),
			el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'faq-item__question' },
					el( 'span', { className: 'faq-item__icon', 'aria-hidden': 'true' } ),
					el( be.RichText, {
						tagName: 'div',
						className: 'faq-item__title',
						value: attributes.question,
						allowedFormats: [ 'core/bold', 'core/italic' ],
						placeholder: __( 'Question', 'pim-schema' ),
						onChange: function ( v ) {
							setAttributes( { question: v } );
						},
					} )
				),
				el( 'div', innerProps )
			)
		);
	}

	/* ---------- Transforms ---------- */

	function buildFaq( list ) {
		var result = parseBlocks( list );
		var faq = blocks.createBlock(
			'pim/faq',
			result.title ? { title: result.title, titleLevel: Math.min( 6, Math.max( 2, result.titleLevel ) ) } : {},
			itemBlocks( result )
		);
		return result.before.map( copy ).concat( [ faq ], result.after.map( copy ) );
	}

	function canBuild( list ) {
		return Array.isArray( list ) && parseBlocks( list ).items.length > 0;
	}

	var faqTransforms = {
		from: [
			{
				type: 'block',
				isMultiBlock: true,
				blocks: [ '*' ],
				isMatch: function ( attributes, list ) {
					return canBuild( list );
				},
				__experimentalConvert: function ( list ) {
					return buildFaq( list );
				},
			},
		],
		to: [
			{
				// Back to plain blocks: H3 questions + answers (the Google Docs format).
				type: 'block',
				blocks: [ 'core/group' ],
				transform: function ( attributes, innerBlocks ) {
					var out = [];
					if ( attributes.title ) {
						out.push( blocks.createBlock( 'core/heading', { level: attributes.titleLevel || 2, content: attributes.title } ) );
					}
					innerBlocks.forEach( function ( item ) {
						out.push( blocks.createBlock( 'core/heading', { level: 3, content: item.attributes.question } ) );
						item.innerBlocks.forEach( function ( b ) {
							out.push( blocks.cloneBlock( b ) );
						} );
					} );
					return blocks.createBlock( 'core/group', {}, out );
				},
			},
		],
	};

	/* ---------- Register ---------- */

	blocks.registerBlockType( 'pim/faq', {
		edit: FaqEdit,
		save: function () {
			return el( be.InnerBlocks.Content );
		},
		transforms: faqTransforms,
	} );

	blocks.registerBlockType( 'pim/faq-item', {
		edit: ItemEdit,
		save: function () {
			return el( be.InnerBlocks.Content );
		},
	} );
} )( window.wp, window.pimFaqParse, window.pimSchemaFaq );
