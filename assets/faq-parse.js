/**
 * PIM Schema: turns a list of blocks (e.g. a Google Docs paste run through
 * wp.blocks.pasteHandler) into FAQ items. Pure function, no WordPress needed,
 * so it runs in Node tests too (tests/faq-parse.test.js).
 *
 * Question detection, first rule that finds questions wins:
 *   1. Heading 3 blocks (the documented Google Docs format)
 *   2. Paragraphs that are entirely bold
 *   3. The most-used heading level that appears at least twice (deeper wins ties)
 *   4. Short paragraphs ending in "?"
 * core/details blocks always become items (summary = question).
 *
 * Returns { title, titleLevel, items: [{ question, answer: [blocks] }], before: [blocks], after: [blocks] }.
 * `before` = content ahead of the first question (kept in the post, above the FAQ);
 * `after`  = a higher-level heading and everything after it (kept below the FAQ).
 */
( function ( root, factory ) {
	var api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	} else {
		root.pimFaqParse = api;
	}
} )( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function html( value ) {
		if ( value === null || value === undefined ) {
			return '';
		}
		if ( typeof value === 'string' ) {
			return value;
		}
		if ( typeof value.toHTMLString === 'function' ) {
			return value.toHTMLString();
		}
		return String( value );
	}

	function text( value ) {
		return html( value )
			.replace( /<[^>]*>/g, '' )
			.replace( /&nbsp;| /g, ' ' )
			.replace( /&amp;/g, '&' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function isBold( value ) {
		var h = html( value ).trim();
		if ( ! /^<(strong|b)\b[^>]*>[\s\S]*<\/(strong|b)>$/i.test( h ) ) {
			return false;
		}
		// Reject "<strong>a</strong> plain <strong>b</strong>": bold must cover everything.
		var outside = h.replace( /<(strong|b)\b[^>]*>[\s\S]*?<\/\1>/gi, '' );
		return text( outside ) === '';
	}

	function cleanQuestion( value ) {
		var h = html( value ).trim();
		var m;
		while ( ( m = h.match( /^<(strong|b)\b[^>]*>([\s\S]*)<\/\1>$/i ) ) ) {
			h = m[ 2 ].trim();
		}
		h = h.replace( /^(?:<br\s*\/?>)+|(?:<br\s*\/?>)+$/gi, '' );
		// Drop "Q:", "Q1.", "1." style prefixes.
		h = h.replace( /^\s*(?:Q\s*\d*\s*[:.)\-–]|\d+\s*[.)])\s*/i, '' );
		return h.trim();
	}

	function stripAnswerPrefix( blocks ) {
		var first = blocks[ 0 ];
		if ( first && first.name === 'core/paragraph' ) {
			var c = html( first.attributes.content );
			var stripped = c.replace( /^(\s*(?:<(?:strong|b)>)?\s*)A\s*[:.)\-–]\s*(?:<\/(?:strong|b)>)?\s*/i, '$1' );
			if ( stripped !== c ) {
				first.attributes = Object.assign( {}, first.attributes, { content: stripped.replace( /^<(strong|b)>\s*<\/\1>/i, '' ).trim() } );
			}
		}
		return blocks;
	}

	function level( block ) {
		return ( block.attributes && block.attributes.level ) || 2;
	}

	function isHeading( block ) {
		return block.name === 'core/heading';
	}

	function isParagraph( block ) {
		return block.name === 'core/paragraph';
	}

	function chooseRule( blocks ) {
		var counts = {};
		blocks.forEach( function ( b ) {
			if ( isHeading( b ) ) {
				counts[ level( b ) ] = ( counts[ level( b ) ] || 0 ) + 1;
			}
		} );
		if ( counts[ 3 ] ) {
			return { type: 'heading', level: 3 };
		}
		var bold = blocks.filter( function ( b ) {
			return isParagraph( b ) && isBold( b.attributes.content );
		} );
		if ( bold.length ) {
			return { type: 'bold' };
		}
		var best = null;
		Object.keys( counts ).forEach( function ( l ) {
			l = Number( l );
			if ( counts[ l ] >= 2 && ( best === null || counts[ l ] > counts[ best ] || ( counts[ l ] === counts[ best ] && l > best ) ) ) {
				best = l;
			}
		} );
		if ( best !== null ) {
			return { type: 'heading', level: best };
		}
		return { type: 'mark' };
	}

	function isQuestion( block, rule ) {
		if ( rule.type === 'heading' ) {
			return isHeading( block ) && level( block ) === rule.level;
		}
		if ( ! isParagraph( block ) ) {
			return false;
		}
		if ( rule.type === 'bold' ) {
			return isBold( block.attributes.content );
		}
		var t = text( block.attributes.content );
		return t.length > 0 && t.length <= 200 && /\?\s*$/.test( t );
	}

	/** Ends the FAQ: a heading above the question level (heading rule) or any heading (other rules). */
	function endsFaq( block, rule ) {
		if ( ! isHeading( block ) ) {
			return false;
		}
		return rule.type === 'heading' ? level( block ) < rule.level : true;
	}

	function parse( blocks ) {
		blocks = ( blocks || [] ).filter( function ( b ) {
			// Empty paragraphs from blank lines in the doc.
			return b && b.name && ! ( isParagraph( b ) && text( b.attributes.content ) === '' && ! /<img/i.test( html( b.attributes.content ) ) );
		} );
		var rule = chooseRule( blocks );
		var out = { title: '', titleLevel: 2, items: [], before: [], after: [], rule: rule.type };
		var current = null;
		var titleBlock = null;

		for ( var i = 0; i < blocks.length; i++ ) {
			var b = blocks[ i ];

			if ( b.name === 'core/details' ) {
				current = {
					question: cleanQuestion( b.attributes.summary ),
					answer: ( b.innerBlocks || [] ).slice(),
				};
				out.items.push( current );
				current = null; // Following blocks don't belong to a details item.
				continue;
			}

			if ( isQuestion( b, rule ) ) {
				current = { question: cleanQuestion( b.attributes.content ), answer: [] };
				out.items.push( current );
				continue;
			}

			if ( out.items.length && endsFaq( b, rule ) ) {
				out.after = blocks.slice( i );
				break;
			}

			if ( ! out.items.length ) {
				// Before the first question: the last heading becomes the title, the rest stays above the FAQ.
				if ( titleBlock ) {
					// Another block follows the candidate title, so it wasn't the FAQ title.
					out.before.push( titleBlock );
					titleBlock = null;
				}
				if ( isHeading( b ) ) {
					titleBlock = b;
				} else {
					out.before.push( b );
				}
				continue;
			}

			if ( current ) {
				current.answer.push( b );
			} else {
				// After a details item, loose content: keep it with the previous item's answer.
				out.items[ out.items.length - 1 ].answer.push( b );
			}
		}

		if ( titleBlock ) {
			if ( out.items.length ) {
				out.title = html( titleBlock.attributes.content ).trim();
				out.titleLevel = level( titleBlock );
			} else {
				out.before.push( titleBlock );
			}
		}
		out.items = out.items.filter( function ( it ) {
			return text( it.question ) !== '';
		} );
		out.items.forEach( function ( it ) {
			stripAnswerPrefix( it.answer );
		} );
		return out;
	}

	return { parse: parse, text: text, html: html, isBold: isBold, cleanQuestion: cleanQuestion };
} );
