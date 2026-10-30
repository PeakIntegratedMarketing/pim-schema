// Run: node tests/faq-parse.test.js
const { parse } = require( '../assets/faq-parse.js' );
let fail = 0;
const check = ( label, ok ) => { console.log( ( ok ? 'ok   ' : 'FAIL ' ) + label ); if ( ! ok ) fail++; };
const h = ( level, content ) => ( { name: 'core/heading', attributes: { level, content }, innerBlocks: [] } );
const p = ( content ) => ( { name: 'core/paragraph', attributes: { content }, innerBlocks: [] } );
const list = ( items ) => ( { name: 'core/list', attributes: {}, innerBlocks: items.map( ( c ) => ( { name: 'core/list-item', attributes: { content: c }, innerBlocks: [] } ) ) } );
const details = ( summary, inner ) => ( { name: 'core/details', attributes: { summary }, innerBlocks: inner } );

// 1. Documented format: H2 title, H3 questions, mixed answers, intro before, next section after.
let r = parse( [ p( 'Intro paragraph.' ), h( 2, 'Frequently Asked Questions' ), h( 3, 'What is it?' ), p( 'A villa.' ), list( [ 'a', 'b' ] ), h( 3, '<strong>Where?</strong>' ), p( 'A: Near Sample Town.' ), p( '' ), h( 2, 'Next section' ), p( 'More.' ) ] );
check( 'h3: 2 items', r.items.length === 2 && r.rule === 'heading' );
check( 'title from H2', r.title === 'Frequently Asked Questions' && r.titleLevel === 2 );
check( 'answer keeps list', r.items[ 0 ].answer.length === 2 && r.items[ 0 ].answer[ 1 ].name === 'core/list' );
check( 'bold unwrapped in question', r.items[ 1 ].question === 'Where?' );
check( '"A:" prefix stripped', r.items[ 1 ].answer[ 0 ].attributes.content === 'Near Sample Town.' );
check( 'intro kept before', r.before.length === 1 && r.before[ 0 ].attributes.content === 'Intro paragraph.' );
check( 'next H2 section kept after', r.after.length === 2 && r.after[ 0 ].attributes.content === 'Next section' );

// 2. Bold-line questions, no headings.
r = parse( [ p( '<strong>Q1. Is it gated?</strong>' ), p( 'Yes.' ), p( '<b>Pets allowed?</b>' ), p( 'Yes, with <strong>rules</strong>.' ), p( 'Ask us.' ) ] );
check( 'bold: 2 items', r.items.length === 2 && r.rule === 'bold' );
check( 'Q1. prefix stripped', r.items[ 0 ].question === 'Is it gated?' );
check( 'partly bold answer not a question', r.items[ 1 ].answer.length === 2 );

// 3. Other heading level (H4 x3) with an H2 title.
r = parse( [ h( 2, 'FAQ' ), h( 4, 'One?' ), p( '1' ), h( 4, 'Two?' ), p( '2' ), h( 4, 'Three?' ), p( '3' ) ] );
check( 'h4 fallback: 3 items + title', r.items.length === 3 && r.title === 'FAQ' );

// 4. Plain "?" lines (e.g. plain-text paste).
r = parse( [ p( 'How far is the airport?' ), p( 'About 90 minutes.' ), p( 'Is there Wi-Fi?' ), p( 'Yes.' ) ] );
check( 'question-mark fallback', r.items.length === 2 && r.rule === 'mark' );

// 5. Existing core/details blocks (imported blog posts).
r = parse( [ h( 2, 'Frequently Asked Questions About Example Village' ), details( 'What is Example Village?', [ p( 'A community.' ) ] ), details( 'Where?', [ p( 'Sample Country.' ), p( 'Near Sample Town.' ) ] ) ] );
check( 'details: 2 items + title', r.items.length === 2 && r.title.startsWith( 'Frequently' ) && r.items[ 1 ].answer.length === 2 );

// 6. Heading followed by content before questions isn't the title.
r = parse( [ h( 2, 'About' ), p( 'Text' ), h( 3, 'Q?' ), p( 'A' ) ] );
check( 'heading with content after is not the title', r.title === '' && r.before.length === 2 );

// 7. Nothing recognisable.
r = parse( [ p( 'Just text.' ), p( 'More text.' ) ] );
check( 'no questions', r.items.length === 0 );

process.exit( fail ? 1 : 0 );
