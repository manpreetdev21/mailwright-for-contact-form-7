/**
 * Mailwright for Contact Form 7 — visual block builder.
 *
 * The body HTML stays the single source of truth. Blocks are parsed out of it
 * when the visual tab opens and written straight back on every edit, so save,
 * preview, test emails and the tag report keep working untouched.
 *
 * Every block is one <td data-mwright-block="type"> row in the email table.
 */
( function ( $ ) {
	'use strict';

	const root = document.querySelector( '.mwright-editor' );
	const bridge = window.mwrightBody;
	const config = window.mwrightEditor || {};

	if ( ! root || ! bridge || ! $ || ! $.fn.sortable ) {
		return;
	}

	const t = config.i18n || {};
	const canvas = root.querySelector( '[data-blocks-canvas]' );
	const palette = root.querySelector( '[data-block-palette]' );
	const builder = root.querySelector( '[data-builder]' );
	const htmlPane = root.querySelector( '[data-html-editor]' );
	const emptyNote = root.querySelector( '[data-blocks-empty]' );
	const importNote = root.querySelector( '[data-blocks-import]' );
	const typeSelect = root.querySelector( '[data-field="type"]' );

	/* ------------------------------------------------------------------ */
	/* Small helpers                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Escapes text for use in HTML.
	 *
	 * @param {string} text Raw text.
	 * @return {string} Escaped text.
	 */
	function esc( text ) {
		const node = document.createElement( 'div' );
		node.textContent = text === null || text === undefined ? '' : String( text );
		return node.innerHTML;
	}

	/**
	 * Escapes text for use inside a double-quoted attribute.
	 *
	 * @param {string} text Raw text.
	 * @return {string} Escaped text.
	 */
	function escAttr( text ) {
		return esc( text ).replace( /"/g, '&quot;' );
	}

	/**
	 * Keeps only link protocols an email client should follow. Mail tags and
	 * branding tags are allowed through untouched.
	 *
	 * @param {string} url Raw URL.
	 * @return {string} Safe URL, or an empty string.
	 */
	function safeUrl( url ) {
		const value = String( url === null || url === undefined ? '' : url ).trim();
		return /^(https?:\/\/|mailto:|tel:|#|\/|\[)/i.test( value ) ? value : '';
	}

	/**
	 * Keeps only hex colours, plain colour names and branding tags.
	 *
	 * @param {string} value Raw colour.
	 * @return {string} Safe colour, or an empty string.
	 */
	function safeColor( value ) {
		const colour = String( value === null || value === undefined ? '' : value ).trim();
		return /^(#[0-9a-f]{3,8}|[a-z]+|\[[a-z0-9_-]+\])$/i.test( colour ) ? colour : '';
	}

	/**
	 * Restricts alignment to the three values the markup understands.
	 *
	 * @param {string} value Raw alignment.
	 * @return {string} left, center or right.
	 */
	function align( value ) {
		return [ 'center', 'right' ].indexOf( value ) > -1 ? value : 'left';
	}

	/**
	 * A whole number within bounds, for widths and heights.
	 *
	 * @param {string} value    Raw value.
	 * @param {number} fallback Value to use when the input is unusable.
	 * @param {number} max      Upper bound.
	 * @return {number} Clamped integer.
	 */
	function num( value, fallback, max ) {
		const parsed = parseInt( value, 10 );
		return isNaN( parsed ) || parsed < 0 ? fallback : Math.min( parsed, max );
	}

	/**
	 * Background declaration for a cell, empty when no colour is set.
	 *
	 * @param {Object} block Block data.
	 * @return {string} CSS.
	 */
	function bgStyle( block ) {
		const colour = safeColor( block.bg );
		return colour ? 'background-color:' + colour + ';' : '';
	}

	/**
	 * Builds one block row.
	 *
	 * @param {string} type  Block type.
	 * @param {Object} data  Values to store as data attributes.
	 * @param {string} style Inline style for the cell.
	 * @param {string} inner Inner HTML, already escaped.
	 * @return {string} Table row.
	 */
	function row( type, data, style, inner ) {
		let attrs = ' data-mwright-block="' + type + '"';

		Object.keys( data ).forEach( ( key ) => {
			if ( data[ key ] !== '' && data[ key ] !== null && data[ key ] !== undefined ) {
				attrs += ' data-' + key + '="' + escAttr( data[ key ] ) + '"';
			}
		} );

		return '<tr>\n<td' + attrs + ' style="' + style + '">' + inner + '</td>\n</tr>';
	}

	/**
	 * Text of a parsed element, with <br> turned back into newlines.
	 *
	 * The element comes from an inert DOMParser document, so mutating it here
	 * touches nothing on screen.
	 *
	 * @param {Element} el Parsed element.
	 * @return {string} Plain text.
	 */
	function textOf( el ) {
		el.querySelectorAll( 'br' ).forEach( ( br ) => br.replaceWith( '\n' ) );
		return el.textContent.replace( /[ \t]+\n/g, '\n' ).trim();
	}

	/* ------------------------------------------------------------------ */
	/* Field descriptors shared by several blocks                          */
	/* ------------------------------------------------------------------ */

	const ALIGN_FIELD = {
		prop: 'align',
		type: 'select',
		label: t.align,
		options: [ [ 'left', t.left ], [ 'center', t.center ], [ 'right', t.right ] ],
	};

	const BG_FIELD = { prop: 'bg', type: 'text', label: t.background, placeholder: '#ffffff' };

	const HEADING_SIZES = { h1: '20px', h2: '17px', h3: '15px' };

	/* ------------------------------------------------------------------ */
	/* Block types                                                         */
	/* ------------------------------------------------------------------ */

	const BLOCKS = {
		heading: {
			make: () => ( { text: t.headingSample || 'Heading', level: 'h1', align: 'left', color: '', bg: '' } ),
			fields: [
				{ prop: 'text', type: 'text', label: t.text },
				{
					prop: 'level',
					type: 'select',
					label: t.level,
					options: [ [ 'h1', 'H1' ], [ 'h2', 'H2' ], [ 'h3', 'H3' ] ],
				},
				ALIGN_FIELD,
				{ prop: 'color', type: 'text', label: t.textColour, placeholder: '#1d2327' },
				BG_FIELD,
			],
			html: ( b ) => {
				const level = HEADING_SIZES[ b.level ] ? b.level : 'h1';

				return row(
					'heading',
					{ level: level, align: align( b.align ), color: b.color, bg: b.bg },
					'padding:24px 32px 8px;' + bgStyle( b ) + 'text-align:' + align( b.align ) + ';',
					'<' + level + ' style="margin:0;font-size:' + HEADING_SIZES[ level ] +
						';line-height:1.3;font-weight:600;color:' + ( safeColor( b.color ) || '#1d2327' ) + ';">' +
						esc( b.text ) + '</' + level + '>'
				);
			},
			parse: ( el ) => {
				const node = el.querySelector( 'h1, h2, h3, h4, h5, h6' );

				return {
					text: node ? textOf( node ) : textOf( el ),
					level: HEADING_SIZES[ el.dataset.level ] ? el.dataset.level : 'h1',
					align: align( el.dataset.align ),
					color: el.dataset.color || '',
					bg: el.dataset.bg || '',
				};
			},
		},

		text: {
			make: () => ( { text: '', align: 'left', bg: '' } ),
			fields: [
				{ prop: 'text', type: 'textarea', label: t.text, help: t.textHelp },
				ALIGN_FIELD,
				BG_FIELD,
			],
			html: ( b ) => {
				const paragraphs = String( b.text || '' )
					.split( /\n{2,}/ )
					.map( ( part ) => '<p style="margin:0 0 12px;">' + esc( part ).replace( /\n/g, '<br />' ) + '</p>' )
					.join( '\n' );

				return row(
					'text',
					{ align: align( b.align ), bg: b.bg },
					'padding:8px 32px;' + bgStyle( b ) + 'font-size:14px;line-height:1.6;color:#1d2327;text-align:' +
						align( b.align ) + ';',
					'\n' + paragraphs + '\n'
				);
			},
			parse: ( el ) => {
				const paragraphs = Array.from( el.querySelectorAll( 'p' ) );

				return {
					text: paragraphs.length ? paragraphs.map( textOf ).join( '\n\n' ) : textOf( el ),
					align: align( el.dataset.align ),
					bg: el.dataset.bg || '',
				};
			},
		},

		fields: {
			make: () => ( { rows: [ { label: '', tag: '' } ], bg: '' } ),
			fields: [ { prop: 'rows', type: 'rows', label: t.fieldRows }, BG_FIELD ],
			html: ( b ) => {
				const cells = ( b.rows || [] )
					.filter( ( item ) => ( item.label || '' ).trim() !== '' || ( item.tag || '' ).trim() !== '' )
					.map( ( item ) =>
						'<tr>\n<td width="140" valign="top" style="padding:10px 12px 10px 0;border-bottom:1px solid #f0f0f1;color:#646970;font-weight:600;">' +
						esc( item.label ) +
						'</td>\n<td valign="top" style="padding:10px 0;border-bottom:1px solid #f0f0f1;color:#1d2327;">' +
						esc( item.tag ) + '</td>\n</tr>'
					)
					.join( '\n' );

				return row(
					'fields',
					{ bg: b.bg },
					'padding:12px 32px;' + bgStyle( b ),
					'\n<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">\n' +
						cells + '\n</table>\n'
				);
			},
			parse: ( el ) => ( {
				rows: Array.from( el.querySelectorAll( 'tr' ) ).map( ( tr ) => {
					const cells = Array.from( tr.children );

					return {
						label: cells[ 0 ] ? textOf( cells[ 0 ] ) : '',
						tag: cells[ 1 ] ? textOf( cells[ 1 ] ) : '',
					};
				} ),
				bg: el.dataset.bg || '',
			} ),
		},

		button: {
			make: () => ( {
				text: t.buttonSample || 'Click here',
				url: '',
				align: 'center',
				btnbg: '#2271b1',
				btncolor: '#ffffff',
				bg: '',
			} ),
			fields: [
				{ prop: 'text', type: 'text', label: t.text },
				{ prop: 'url', type: 'text', label: t.url, placeholder: 'https://' },
				ALIGN_FIELD,
				{ prop: 'btnbg', type: 'text', label: t.buttonColour, placeholder: '#2271b1' },
				{ prop: 'btncolor', type: 'text', label: t.textColour, placeholder: '#ffffff' },
				BG_FIELD,
			],
			html: ( b ) => {
				const link = '<a href="' + escAttr( safeUrl( b.url ) ) +
					'" style="display:inline-block;padding:12px 24px;background-color:' +
					( safeColor( b.btnbg ) || '#2271b1' ) + ';color:' + ( safeColor( b.btncolor ) || '#ffffff' ) +
					';text-decoration:none;border-radius:4px;font-size:14px;font-weight:600;">' + esc( b.text ) + '</a>';

				return row(
					'button',
					{ align: align( b.align ), btnbg: b.btnbg, btncolor: b.btncolor, bg: b.bg },
					'padding:16px 32px;' + bgStyle( b ) + 'text-align:' + align( b.align ) + ';',
					link
				);
			},
			parse: ( el ) => {
				const link = el.querySelector( 'a' );

				return {
					text: link ? textOf( link ) : '',
					url: link ? link.getAttribute( 'href' ) || '' : '',
					align: align( el.dataset.align ),
					btnbg: el.dataset.btnbg || '#2271b1',
					btncolor: el.dataset.btncolor || '#ffffff',
					bg: el.dataset.bg || '',
				};
			},
		},

		image: {
			make: () => ( { src: '', alt: '', width: '', href: '', align: 'center', bg: '' } ),
			fields: [
				{ prop: 'src', type: 'media', label: t.imageUrl },
				{ prop: 'alt', type: 'text', label: t.altText },
				{ prop: 'width', type: 'number', label: t.width, placeholder: '600' },
				{ prop: 'href', type: 'text', label: t.url, placeholder: 'https://' },
				ALIGN_FIELD,
				BG_FIELD,
			],
			html: ( b ) => {
				const width = b.width ? num( b.width, 0, 1200 ) : 0;
				const href = safeUrl( b.href );

				let img = '<img src="' + escAttr( safeUrl( b.src ) ) + '" alt="' + escAttr( b.alt ) + '"' +
					( width ? ' width="' + width + '"' : '' ) +
					' style="display:block;border:0;max-width:100%;height:auto;' +
					( align( b.align ) === 'center' ? 'margin:0 auto;' : '' ) + '" />';

				if ( href ) {
					img = '<a href="' + escAttr( href ) + '">' + img + '</a>';
				}

				return row(
					'image',
					{ align: align( b.align ), width: b.width, href: b.href, bg: b.bg },
					'padding:16px 32px;' + bgStyle( b ) + 'text-align:' + align( b.align ) + ';',
					img
				);
			},
			parse: ( el ) => {
				const img = el.querySelector( 'img' );

				return {
					src: img ? img.getAttribute( 'src' ) || '' : '',
					alt: img ? img.getAttribute( 'alt' ) || '' : '',
					width: el.dataset.width || ( img ? img.getAttribute( 'width' ) || '' : '' ),
					href: el.dataset.href || '',
					align: align( el.dataset.align ),
					bg: el.dataset.bg || '',
				};
			},
		},

		divider: {
			make: () => ( { color: '#e0e0e0', bg: '' } ),
			fields: [
				{ prop: 'color', type: 'text', label: t.lineColour, placeholder: '#e0e0e0' },
				BG_FIELD,
			],
			html: ( b ) =>
				row(
					'divider',
					{ color: b.color, bg: b.bg },
					'padding:8px 32px;' + bgStyle( b ),
					'<hr style="border:0;border-top:1px solid ' + ( safeColor( b.color ) || '#e0e0e0' ) + ';margin:0;" />'
				),
			parse: ( el ) => ( { color: el.dataset.color || '#e0e0e0', bg: el.dataset.bg || '' } ),
		},

		spacer: {
			make: () => ( { height: '24', bg: '' } ),
			fields: [
				{ prop: 'height', type: 'number', label: t.height, placeholder: '24' },
				BG_FIELD,
			],
			html: ( b ) => {
				const height = num( b.height, 24, 400 );

				return row(
					'spacer',
					{ height: height, bg: b.bg },
					'height:' + height + 'px;line-height:' + height + 'px;font-size:0;' + bgStyle( b ),
					'&nbsp;'
				);
			},
			parse: ( el ) => ( { height: el.dataset.height || '24', bg: el.dataset.bg || '' } ),
		},
	};

	/* ------------------------------------------------------------------ */
	/* The email shell around the blocks                                   */
	/* ------------------------------------------------------------------ */

	const SHELL_OPEN =
		'<!doctype html>\n<html>\n<head>\n<meta charset="utf-8" />\n' +
		'<meta name="viewport" content="width=device-width, initial-scale=1" />\n</head>\n' +
		'<body style="margin:0;padding:0;background-color:#f1f1f1;">\n' +
		'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f1f1;padding:24px 12px;">\n' +
		'<tr>\n<td align="center">\n' +
		'<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background-color:#ffffff;border:1px solid #e0e0e0;border-radius:6px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;color:#1d2327;">\n';

	const SHELL_CLOSE = '\n</table>\n</td>\n</tr>\n</table>\n</body>\n</html>';

	/* ------------------------------------------------------------------ */
	/* Cards                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Builds one editor card. The block data lives on the element, so
	 * reordering the DOM reorders the email with nothing to keep in sync.
	 *
	 * @param {string} type Block type.
	 * @param {Object} data Block values.
	 * @return {HTMLElement} Card element.
	 */
	function card( type, data ) {
		const spec = BLOCKS[ type ];
		const el = document.createElement( 'div' );

		el.className = 'mwright-block';
		el.dataset.type = type;
		el._block = data;

		const bar = document.createElement( 'div' );
		bar.className = 'mwright-block__bar';

		const handle = document.createElement( 'span' );
		handle.className = 'mwright-block__handle dashicons dashicons-menu';
		handle.setAttribute( 'aria-hidden', 'true' );

		const title = document.createElement( 'span' );
		title.className = 'mwright-block__title';
		title.textContent = config.blocks && config.blocks[ type ] ? config.blocks[ type ] : type;

		bar.appendChild( handle );
		bar.appendChild( title );

		const tools = document.createElement( 'span' );
		tools.className = 'mwright-block__tools';

		[
			[ 'up', 'arrow-up-alt2', t.moveUp ],
			[ 'down', 'arrow-down-alt2', t.moveDown ],
			[ 'duplicate', 'admin-page', t.duplicate ],
			[ 'delete', 'trash', t.remove ],
		].forEach( ( pair ) => {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'mwright-block__tool';
			button.dataset.blockAction = pair[ 0 ];
			button.setAttribute( 'aria-label', pair[ 2 ] || pair[ 0 ] );
			button.title = pair[ 2 ] || pair[ 0 ];

			const icon = document.createElement( 'span' );
			icon.className = 'dashicons dashicons-' + pair[ 1 ];
			icon.setAttribute( 'aria-hidden', 'true' );
			button.appendChild( icon );

			tools.appendChild( button );
		} );

		bar.appendChild( tools );
		el.appendChild( bar );

		const body = document.createElement( 'div' );
		body.className = 'mwright-block__body';

		spec.fields.forEach( ( field ) => body.appendChild( fieldNode( field, data ) ) );

		el.appendChild( body );

		return el;
	}

	/**
	 * Builds the control for one block property.
	 *
	 * @param {Object} spec  Field descriptor.
	 * @param {Object} block Block data.
	 * @return {HTMLElement} Field element.
	 */
	function fieldNode( spec, block ) {
		const wrap = document.createElement( 'label' );
		wrap.className = 'mwright-block__field mwright-block__field--' + spec.type;

		const name = document.createElement( 'span' );
		name.className = 'mwright-block__label';
		name.textContent = spec.label || spec.prop;
		wrap.appendChild( name );

		if ( 'rows' === spec.type ) {
			wrap.appendChild( rowsEditor( block ) );
			return wrap;
		}

		let input;

		if ( 'select' === spec.type ) {
			input = document.createElement( 'select' );

			spec.options.forEach( ( option ) => {
				const node = document.createElement( 'option' );
				node.value = option[ 0 ];
				node.textContent = option[ 1 ] || option[ 0 ];
				input.appendChild( node );
			} );
		} else if ( 'textarea' === spec.type ) {
			input = document.createElement( 'textarea' );
			input.rows = 5;
		} else {
			input = document.createElement( 'input' );
			input.type = 'number' === spec.type ? 'number' : 'text';
		}

		input.dataset.prop = spec.prop;
		input.value = block[ spec.prop ] === null || block[ spec.prop ] === undefined ? '' : block[ spec.prop ];

		if ( spec.placeholder ) {
			input.placeholder = spec.placeholder;
		}

		wrap.appendChild( input );

		if ( 'media' === spec.type && window.wp && window.wp.media ) {
			const choose = document.createElement( 'button' );
			choose.type = 'button';
			choose.className = 'mwright-btn mwright-btn--small';
			choose.dataset.blockAction = 'media';
			choose.textContent = t.chooseImage || 'Choose image';
			wrap.appendChild( choose );
		}

		if ( spec.help ) {
			const help = document.createElement( 'span' );
			help.className = 'mwright-help';
			help.textContent = spec.help;
			wrap.appendChild( help );
		}

		return wrap;
	}

	/**
	 * The label / tag table editor used by the Form Fields block.
	 *
	 * @param {Object} block Block data.
	 * @return {HTMLElement} Rows editor.
	 */
	function rowsEditor( block ) {
		const wrap = document.createElement( 'span' );
		wrap.className = 'mwright-block__rows';
		wrap.dataset.rows = '1';

		( block.rows || [] ).forEach( ( item ) => wrap.appendChild( fieldRow( item ) ) );

		const actions = document.createElement( 'span' );
		actions.className = 'mwright-block__rowactions';

		[ [ 'add-row', t.addRow ], [ 'fill-rows', t.addFormFields ] ].forEach( ( pair ) => {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'mwright-btn mwright-btn--small';
			button.dataset.blockAction = pair[ 0 ];
			button.textContent = pair[ 1 ] || pair[ 0 ];
			actions.appendChild( button );
		} );

		wrap.appendChild( actions );

		return wrap;
	}

	/**
	 * One label / tag pair.
	 *
	 * @param {Object} item Row values.
	 * @return {HTMLElement} Row element.
	 */
	function fieldRow( item ) {
		const line = document.createElement( 'span' );
		line.className = 'mwright-block__row';

		[ [ 'label', t.rowLabel ], [ 'tag', t.rowTag ] ].forEach( ( pair ) => {
			const input = document.createElement( 'input' );
			input.type = 'text';
			input.dataset.rowProp = pair[ 0 ];
			input.value = item[ pair[ 0 ] ] || '';
			input.placeholder = pair[ 1 ] || pair[ 0 ];
			line.appendChild( input );
		} );

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'mwright-block__tool';
		remove.dataset.blockAction = 'remove-row';
		remove.setAttribute( 'aria-label', t.removeRow || 'Remove row' );
		remove.title = t.removeRow || 'Remove row';

		const icon = document.createElement( 'span' );
		icon.className = 'dashicons dashicons-no-alt';
		icon.setAttribute( 'aria-hidden', 'true' );
		remove.appendChild( icon );

		line.appendChild( remove );

		return line;
	}

	/* ------------------------------------------------------------------ */
	/* Reading and writing the body                                        */
	/* ------------------------------------------------------------------ */

	/** @return {Array} Cards currently on the canvas. */
	function cards() {
		return Array.from( canvas.children ).filter( ( el ) => el.classList.contains( 'mwright-block' ) );
	}

	let syncTimer = null;

	/** Regenerates the body HTML from the canvas. */
	function sync() {
		window.clearTimeout( syncTimer );

		syncTimer = window.setTimeout( () => {
			const rows = cards()
				.map( ( el ) => BLOCKS[ el.dataset.type ].html( el._block ) )
				.join( '\n' );

			bridge.set( SHELL_OPEN + rows + SHELL_CLOSE );
			refreshEmpty();
		}, 250 );
	}

	/** Shows the "drag a block here" hint only on an empty canvas. */
	function refreshEmpty() {
		if ( emptyNote ) {
			emptyNote.hidden = cards().length > 0;
		}
	}

	/**
	 * Reads blocks out of body HTML.
	 *
	 * @param {string} html Body HTML.
	 * @return {Array|null} Blocks, or null when this is not a block template.
	 */
	function parse( html ) {
		const doc = new DOMParser().parseFromString( html, 'text/html' );
		const cells = doc.querySelectorAll( '[data-mwright-block]' );

		if ( ! cells.length ) {
			return null;
		}

		return Array.from( cells )
			.filter( ( el ) => BLOCKS[ el.dataset.mwrightBlock ] )
			.map( ( el ) => ( {
				type: el.dataset.mwrightBlock,
				data: BLOCKS[ el.dataset.mwrightBlock ].parse( el ),
			} ) );
	}

	/**
	 * Best-effort conversion of hand-written HTML into blocks. Lossy by
	 * nature, which is why it only ever runs when the user asks for it.
	 *
	 * @param {string} html Body HTML.
	 * @return {Array} Blocks.
	 */
	function convert( html ) {
		const doc = new DOMParser().parseFromString( html, 'text/html' );
		const out = [];

		walk( doc.body, out );

		return out;
	}

	/**
	 * Reads one colour declaration out of an inline style.
	 *
	 * @param {Element} el   Parsed element.
	 * @param {string}  prop CSS property name.
	 * @return {string} Safe colour, or an empty string.
	 */
	function styleValue( el, prop ) {
		const match = new RegExp( '(?:^|;)\s*' + prop + '\s*:\s*([^;]+)', 'i' )
			.exec( el.getAttribute( 'style' ) || '' );

		return match ? safeColor( match[ 1 ] ) : '';
	}

	/**
	 * Walks an element, turning what it recognises into blocks. Cell colours
	 * are carried down so a coloured header band survives the conversion.
	 *
	 * @param {Element} node Parent element.
	 * @param {Array}   out  Collected blocks.
	 * @param {string}  bg   Background colour inherited from an ancestor cell.
	 */
	function walk( node, out, bg ) {
		Array.from( node.children ).forEach( ( el ) => {
			const tag = el.tagName.toLowerCase();

			if ( 'script' === tag || 'style' === tag ) {
				return;
			}

			const cellBg = styleValue( el, 'background-color' ) || bg || '';

			if ( /^h[1-6]$/.test( tag ) ) {
				out.push( {
					type: 'heading',
					data: Object.assign( BLOCKS.heading.make(), {
						text: textOf( el ),
						level: 'h1' === tag || 'h2' === tag ? tag : 'h3',
						color: styleValue( el, 'color' ),
						bg: cellBg,
					} ),
				} );
				return;
			}

			if ( 'hr' === tag ) {
				out.push( { type: 'divider', data: Object.assign( BLOCKS.divider.make(), { bg: cellBg } ) } );
				return;
			}

			if ( 'img' === tag ) {
				out.push( {
					type: 'image',
					data: Object.assign( BLOCKS.image.make(), {
						src: el.getAttribute( 'src' ) || '',
						alt: el.getAttribute( 'alt' ) || '',
						width: el.getAttribute( 'width' ) || '',
						bg: cellBg,
					} ),
				} );
				return;
			}

			if ( 'a' === tag && /background|inline-block/i.test( el.getAttribute( 'style' ) || '' ) ) {
				out.push( {
					type: 'button',
					data: Object.assign( BLOCKS.button.make(), {
						text: textOf( el ),
						url: el.getAttribute( 'href' ) || '',
						btnbg: styleValue( el, 'background-color' ) || '#2271b1',
						btncolor: styleValue( el, 'color' ) || '#ffffff',
						bg: bg || '',
					} ),
				} );
				return;
			}

			if ( 'table' === tag ) {
				const rows = fieldPairs( el );

				if ( rows ) {
					out.push( {
						type: 'fields',
						data: Object.assign( BLOCKS.fields.make(), { rows: rows, bg: cellBg } ),
					} );
					return;
				}
			}

			if ( 'p' === tag || ! el.children.length ) {
				const text = textOf( el );

				if ( text ) {
					out.push( {
						type: 'text',
						data: Object.assign( BLOCKS.text.make(), { text: text, bg: cellBg } ),
					} );
				}

				return;
			}

			walk( el, out, cellBg );
		} );
	}

	/**
	 * A table whose every row has exactly two cells reads as a fields block.
	 *
	 * @param {Element} table Table element.
	 * @return {Array|null} Rows, or null when the shape does not match.
	 */
	function fieldPairs( table ) {
		const rows = Array.from( table.querySelectorAll( 'tr' ) );

		if ( ! rows.length || rows.some( ( tr ) => tr.children.length !== 2 ) ) {
			return null;
		}

		return rows.map( ( tr ) => ( {
			label: textOf( tr.children[ 0 ] ),
			tag: textOf( tr.children[ 1 ] ),
		} ) );
	}

	/**
	 * Renders a list of blocks onto the canvas.
	 *
	 * @param {Array} blocks Blocks.
	 */
	function render( blocks ) {
		canvas.innerHTML = '';
		blocks.forEach( ( block ) => canvas.appendChild( card( block.type, block.data ) ) );
		refreshEmpty();
	}

	/** Loads the current body into the canvas. */
	function load() {
		const html = bridge.get();
		const blocks = parse( html );

		if ( blocks ) {
			render( blocks );
			importNote.hidden = true;
			return;
		}

		render( [] );
		// Hand-written HTML: offer conversion rather than silently rewriting it.
		importNote.hidden = '' === html.trim();
	}

	/* ------------------------------------------------------------------ */
	/* Editing                                                             */
	/* ------------------------------------------------------------------ */

	canvas.addEventListener( 'input', onEdit );
	canvas.addEventListener( 'change', onEdit );

	/**
	 * Copies an edited control back into its block.
	 *
	 * @param {Event} event Input or change event.
	 */
	function onEdit( event ) {
		const input = event.target.closest( '[data-prop], [data-row-prop]' );
		const host = input && input.closest( '.mwright-block' );

		if ( ! host ) {
			return;
		}

		if ( input.dataset.rowProp ) {
			host._block.rows = readRows( host );
		} else {
			host._block[ input.dataset.prop ] = input.value;
		}

		sync();
	}

	/**
	 * Reads the label / tag rows out of a card.
	 *
	 * @param {HTMLElement} host Card element.
	 * @return {Array} Rows.
	 */
	function readRows( host ) {
		return Array.from( host.querySelectorAll( '.mwright-block__row' ) ).map( ( line ) => ( {
			label: line.querySelector( '[data-row-prop="label"]' ).value,
			tag: line.querySelector( '[data-row-prop="tag"]' ).value,
		} ) );
	}

	canvas.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-block-action]' );

		if ( ! button ) {
			return;
		}

		const host = button.closest( '.mwright-block' );
		const action = button.dataset.blockAction;

		if ( 'up' === action && host.previousElementSibling ) {
			host.previousElementSibling.before( host );
		} else if ( 'down' === action && host.nextElementSibling ) {
			host.nextElementSibling.after( host );
		} else if ( 'duplicate' === action ) {
			host.after( card( host.dataset.type, JSON.parse( JSON.stringify( host._block ) ) ) );
		} else if ( 'delete' === action ) {
			host.remove();
		} else if ( 'add-row' === action ) {
			button.closest( '.mwright-block__rowactions' ).before( fieldRow( {} ) );
			host._block.rows = readRows( host );
		} else if ( 'remove-row' === action ) {
			button.closest( '.mwright-block__row' ).remove();
			host._block.rows = readRows( host );
		} else if ( 'fill-rows' === action ) {
			fillRows( host, button );
		} else if ( 'media' === action ) {
			chooseImage( button );
			return;
		} else {
			return;
		}

		sync();
	} );

	/**
	 * Fills a fields block with every tag on the selected form.
	 *
	 * @param {HTMLElement} host   Card element.
	 * @param {HTMLElement} button The button that was clicked.
	 */
	function fillRows( host, button ) {
		const actions = button.closest( '.mwright-block__rowactions' );
		const existing = readRows( host ).map( ( item ) => item.tag );

		bridge.tags().forEach( ( tag ) => {
			const markup = '[' + tag.name + ']';

			if ( -1 === existing.indexOf( markup ) ) {
				actions.before( fieldRow( { label: tag.label, tag: markup } ) );
			}
		} );

		// Blank starter rows are noise once real fields arrive.
		host.querySelectorAll( '.mwright-block__row' ).forEach( ( line ) => {
			const values = Array.from( line.querySelectorAll( 'input' ) ).map( ( input ) => input.value.trim() );

			if ( '' === values.join( '' ) ) {
				line.remove();
			}
		} );

		host._block.rows = readRows( host );
	}

	let mediaFrame = null;

	/**
	 * Opens the media library for an image block.
	 *
	 * @param {HTMLElement} button The Choose image button.
	 */
	function chooseImage( button ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		const input = button.parentNode.querySelector( '[data-prop="src"]' );

		if ( ! mediaFrame ) {
			mediaFrame = window.wp.media( { multiple: false, library: { type: 'image' } } );
		}

		mediaFrame.off( 'select' );

		mediaFrame.on( 'select', () => {
			const attachment = mediaFrame.state().get( 'selection' ).first().toJSON();

			input.value = attachment.url;
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );

		mediaFrame.open();
	}

	// Tag chips insert into whichever block field was last focused.
	canvas.addEventListener( 'focusin', ( event ) => {
		if ( event.target.matches( 'input[type="text"], textarea' ) ) {
			bridge.focusField( event.target );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Palette and drag-and-drop                                           */
	/* ------------------------------------------------------------------ */

	$( canvas ).sortable( {
		handle: '.mwright-block__handle',
		placeholder: 'mwright-block__placeholder',
		forcePlaceholderSize: true,
		tolerance: 'pointer',
		update: sync,
	} );

	/**
	 * Turns any palette item dropped on the canvas into a real block.
	 *
	 * jQuery UI puts the dragged clone into the list itself and keeps hold of
	 * it until the drag ends, so the swap has to happen after that — doing it
	 * in the sortable's receive event gets undone.
	 */
	function adoptClones() {
		canvas.querySelectorAll( '[data-add-block]' ).forEach( ( clone ) => {
			const type = clone.dataset.addBlock;

			if ( BLOCKS[ type ] ) {
				clone.replaceWith( card( type, BLOCKS[ type ].make() ) );
			} else {
				clone.remove();
			}
		} );

		sync();
	}

	let dragging = false;

	$( palette ).find( '[data-add-block]' ).draggable( {
		connectToSortable: canvas,
		helper: 'clone',
		revert: 'invalid',
		// jQuery UI refuses to drag a <button> unless its cancel list is cleared.
		cancel: '',
		start: () => {
			dragging = true;
		},
		stop: () => {
			window.setTimeout( () => {
				adoptClones();
				// The click that follows a drop must not add a second block.
				dragging = false;
			}, 0 );
		},
	} );

	// Clicking a palette item appends it, which keeps the palette usable from
	// the keyboard.
	palette.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-add-block]' );
		const type = button && button.dataset.addBlock;

		if ( dragging || ! BLOCKS[ type ] ) {
			return;
		}

		canvas.appendChild( card( type, BLOCKS[ type ].make() ) );
		sync();
	} );

	/* ------------------------------------------------------------------ */
	/* Modes                                                               */
	/* ------------------------------------------------------------------ */

	const modeButtons = Array.from( root.querySelectorAll( '[data-mode]' ) );

	/**
	 * Switches between the visual builder and the HTML source.
	 *
	 * @param {string} mode 'visual' or 'html'.
	 */
	function setMode( mode ) {
		const visual = 'visual' === mode;

		if ( visual ) {
			load();
		}

		builder.hidden = ! visual;
		htmlPane.hidden = visual;

		if ( ! visual ) {
			// CodeMirror measures itself wrong while its pane is hidden.
			bridge.refresh();
		}

		modeButtons.forEach( ( button ) => {
			button.classList.toggle( 'is-active', button.dataset.mode === mode );
			button.setAttribute( 'aria-pressed', button.dataset.mode === mode ? 'true' : 'false' );
		} );

		try {
			window.localStorage.setItem( 'mwrightEditorMode', mode );
		} catch ( error ) {
			// Storage unavailable; the mode simply resets next time.
		}
	}

	modeButtons.forEach( ( button ) => {
		button.addEventListener( 'click', () => setMode( button.dataset.mode ) );
	} );

	root.querySelector( '[data-action="convert-blocks"]' ).addEventListener( 'click', () => {
		render( convert( bridge.get() ) );
		importNote.hidden = true;
		sync();
	} );

	// A plain-text template has no layout to build.
	const modeBar = root.querySelector( '[data-modes]' );

	/** Hides the builder for plain-text templates. */
	function applyType() {
		const isText = typeSelect && 'text' === typeSelect.value;

		modeBar.hidden = isText;

		if ( isText ) {
			builder.hidden = true;
			htmlPane.hidden = false;
		}
	}

	if ( typeSelect ) {
		typeSelect.addEventListener( 'change', applyType );
	}

	let startMode = 'visual';

	try {
		startMode = window.localStorage.getItem( 'mwrightEditorMode' ) || 'visual';
	} catch ( error ) {
		startMode = 'visual';
	}

	// Never open the builder on a template it cannot represent yet.
	if ( 'visual' === startMode && '' !== bridge.get().trim() && ! parse( bridge.get() ) ) {
		startMode = 'html';
	}

	setMode( startMode );
	applyType();
}( window.jQuery ) );
