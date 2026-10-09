/**
 * Mailwright for Contact Form 7 — template editor.
 *
 * Uses the CodeMirror instance WordPress core already ships. When the user has
 * syntax highlighting turned off, everything falls back to the plain textarea.
 */
( function () {
	'use strict';

	const root = document.querySelector( '.mwright-editor' );

	if ( ! root || ! window.mwrightUI ) {
		return;
	}

	const ui = window.mwrightUI;
	const i18n = window.mwright.i18n;

	const nameInput = root.querySelector( '[data-field="name"]' );
	const bodyArea = root.querySelector( '[data-field="body"]' );
	const typeSelect = root.querySelector( '[data-field="type"]' );
	const statusSelect = root.querySelector( '[data-field="status"]' );
	const statusBadge = root.querySelector( '[data-status-badge]' );
	const dirtyFlag = root.querySelector( '[data-dirty-flag]' );
	const formSelect = root.querySelector( '[data-field="form_context"]' );
	const tagSearch = root.querySelector( '#mwright-tag-search' );
	const formTagList = root.querySelector( '[data-form-tags]' );
	const fileTagList = root.querySelector( '[data-file-tags]' );
	const fileGroup = root.querySelector( '[data-file-group]' );
	const unknownBox = root.querySelector( '[data-unknown-tags]' );
	const unknownList = root.querySelector( '[data-unknown-list]' );
	const newBox = root.querySelector( '[data-new-tags]' );
	const newList = root.querySelector( '[data-new-tags-list]' );
	const newMessage = root.querySelector( '[data-new-tags-message]' );
	const attachmentBox = root.querySelector( '[data-attachment-warning]' );

	let templateId = parseInt( root.dataset.templateId, 10 ) || 0;
	let editor = null;
	let dirty = false;
	let lastField = bodyArea;
	let availableTags = [];
	let dismissed = [];

	/* ------------------------------------------------------------------ */
	/* Editor set-up                                                       */
	/* ------------------------------------------------------------------ */

	if ( window.mwrightEditor && window.mwrightEditor.codeEditor && window.wp && window.wp.codeEditor ) {
		editor = window.wp.codeEditor.initialize( bodyArea, window.mwrightEditor.codeEditor ).codemirror;

		editor.on( 'change', () => {
			markDirty();
			scheduleReport();
		} );

		editor.on( 'focus', () => {
			lastField = bodyArea;
		} );
	}

	/**
	 * Current body text, wherever it lives.
	 *
	 * @return {string} Body content.
	 */
	function bodyValue() {
		return editor ? editor.getValue() : bodyArea.value;
	}

	/**
	 * Replaces the body, wherever it lives.
	 *
	 * @param {string} html New body content.
	 */
	function setBody( html ) {
		if ( editor ) {
			editor.setValue( html );
		} else {
			bodyArea.value = html;
		}

		markDirty();
		scheduleReport();
	}

	/**
	 * Collects the whole template from the form.
	 *
	 * @return {Object} Template payload.
	 */
	function collect() {
		const template = { id: templateId, body: bodyValue() };

		root.querySelectorAll( '[data-field]' ).forEach( ( field ) => {
			const key = field.dataset.field;

			if ( key === 'body' ) {
				return;
			}

			template[ key ] = field.type === 'checkbox' ? ( field.checked ? 1 : 0 ) : field.value;
		} );

		return template;
	}

	/* ------------------------------------------------------------------ */
	/* Dirty state                                                         */
	/* ------------------------------------------------------------------ */

	/** Flags unsaved changes. */
	function markDirty() {
		dirty = true;

		if ( dirtyFlag ) {
			dirtyFlag.hidden = false;
		}
	}

	/** Clears the unsaved-changes flag. */
	function markClean() {
		dirty = false;

		if ( dirtyFlag ) {
			dirtyFlag.hidden = true;
		}
	}

	root.querySelectorAll( '[data-field]' ).forEach( ( field ) => {
		field.addEventListener( 'input', markDirty );
		field.addEventListener( 'change', markDirty );
	} );

	window.addEventListener( 'beforeunload', ( event ) => {
		if ( dirty ) {
			event.preventDefault();
			event.returnValue = i18n.unsaved;
			return i18n.unsaved;
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Tag insertion                                                       */
	/* ------------------------------------------------------------------ */

	root.querySelectorAll( '[data-insertable]' ).forEach( ( field ) => {
		field.addEventListener( 'focus', () => {
			lastField = field;
		} );
	} );

	/**
	 * Inserts a tag at the caret of whichever field was last focused.
	 *
	 * @param {string} tag Tag name without brackets.
	 */
	function insertTag( tag ) {
		const text = '[' + tag + ']';

		if ( lastField === bodyArea && editor ) {
			editor.replaceSelection( text );
			editor.focus();
		} else {
			const field = lastField || bodyArea;
			const start = field.selectionStart || 0;
			const end = field.selectionEnd || 0;

			field.setRangeText( text, start, end, 'end' );
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			field.focus();
		}

		markDirty();
		rememberTag( tag );
		scheduleReport();
	}

	/** Stores the tag in the recently-used list. */
	function rememberTag( tag ) {
		let recent = [];

		try {
			recent = JSON.parse( window.localStorage.getItem( 'mwrightRecentTags' ) || '[]' );
		} catch ( error ) {
			recent = [];
		}

		recent = [ tag ].concat( recent.filter( ( item ) => item !== tag ) ).slice( 0, 6 );

		try {
			window.localStorage.setItem( 'mwrightRecentTags', JSON.stringify( recent ) );
		} catch ( error ) {
			// Storage unavailable (private mode); recents are a nicety, not a feature.
		}

		renderRecent( recent );
	}

	/**
	 * Renders the recently-used tag chips.
	 *
	 * @param {Array} recent Tag names.
	 */
	function renderRecent( recent ) {
		const box = root.querySelector( '[data-recent-tags]' );
		const list = root.querySelector( '[data-recent-list]' );

		if ( ! box || ! list || ! recent.length ) {
			return;
		}

		list.innerHTML = '';

		recent.forEach( ( tag ) => list.appendChild( tagChip( tag, tag ) ) );

		box.hidden = false;
	}

	/**
	 * Builds a tag chip element.
	 *
	 * @param {string}  tag    Tag name.
	 * @param {string}  label  Friendly label.
	 * @param {boolean} isFile Whether the tag is a file-upload field.
	 * @return {HTMLElement} The chip.
	 */
	function tagChip( tag, label, isFile ) {
		const wrapper = document.createElement( 'span' );

		wrapper.className = 'mwright-tag' + ( isFile ? ' mwright-tag--file' : '' );
		wrapper.dataset.search = ( label + ' ' + tag ).toLowerCase();

		const insert = document.createElement( 'button' );
		insert.type = 'button';
		insert.className = 'mwright-tag__insert';
		insert.title = '[' + tag + ']';
		insert.dataset.insert = tag;

		const labelNode = document.createElement( 'span' );
		labelNode.className = 'mwright-tag__label';
		labelNode.textContent = label;

		const code = document.createElement( 'code' );
		code.className = 'mwright-tag__code';
		code.textContent = '[' + tag + ']';

		insert.appendChild( labelNode );
		insert.appendChild( code );

		const copy = document.createElement( 'button' );
		copy.type = 'button';
		copy.className = 'mwright-tag__copy';
		copy.dataset.copy = tag;
		copy.setAttribute( 'aria-label', '[' + tag + ']' );
		copy.innerHTML = '<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>';

		wrapper.appendChild( insert );
		wrapper.appendChild( copy );

		return wrapper;
	}

	root.addEventListener( 'click', ( event ) => {
		const insert = event.target.closest( '[data-insert]' );

		if ( insert ) {
			insertTag( insert.dataset.insert );
			return;
		}

		const copy = event.target.closest( '[data-copy]' );

		if ( copy && navigator.clipboard ) {
			navigator.clipboard
				.writeText( '[' + copy.dataset.copy + ']' )
				.then( () => ui.toast( i18n.copied, 'success' ) )
				.catch( () => {} );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Tag search                                                          */
	/* ------------------------------------------------------------------ */

	if ( tagSearch ) {
		tagSearch.addEventListener( 'input', () => {
			const term = tagSearch.value.trim().toLowerCase();

			root.querySelectorAll( '.mwright-tags .mwright-tag' ).forEach( ( chip ) => {
				chip.hidden = term !== '' && ! ( chip.dataset.search || '' ).includes( term );
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Form tags and validation                                            */
	/* ------------------------------------------------------------------ */

	let reportTimer = null;

	/** Debounces the tag report so typing stays smooth. */
	function scheduleReport() {
		window.clearTimeout( reportTimer );
		reportTimer = window.setTimeout( refreshTags, 400 );
	}

	/** Fetches available tags for the selected form and reports on them. */
	function refreshTags() {
		const formId = formSelect ? formSelect.value : '0';

		if ( ! formId || formId === '0' ) {
			formTagList.innerHTML = '';
			fileTagList.innerHTML = '';
			fileGroup.hidden = true;
			unknownBox.hidden = true;
			newBox.hidden = true;
			return;
		}

		const template = collect();

		ui.post( 'form_tags', {
			form_id: formId,
			content: template.subject + ' ' + template.body,
			attachments: template.attachments,
		} )
			.then( ( data ) => {
				availableTags = data.tags || [];
				renderFormTags( availableTags );
				renderReport( data.report || {} );
			} )
			.catch( () => {} );
	}

	/**
	 * Renders the Form Fields group.
	 *
	 * @param {Array} tags Tag details from the server.
	 */
	function renderFormTags( tags ) {
		formTagList.innerHTML = '';
		fileTagList.innerHTML = '';

		const files = tags.filter( ( tag ) => tag.is_file );

		// File uploads get their own group; the section hides when there are none.
		fileGroup.hidden = files.length === 0;

		if ( ! tags.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'mwright-muted';
			empty.textContent = i18n.noTags;
			formTagList.appendChild( empty );
			return;
		}

		tags.forEach( ( tag ) => {
			const chip = tagChip( tag.name, tag.label + ( tag.required ? ' *' : '' ), tag.is_file );
			( tag.is_file ? fileTagList : formTagList ).appendChild( chip );
		} );
	}

	/**
	 * Renders the unknown-tag warning and the new-tag notice.
	 *
	 * @param {Object} report unknown and unused tag lists.
	 */
	function renderReport( report ) {
		const unknown = ( report.unknown || [] ).filter( ( tag ) => ! dismissed.includes( tag ) );

		unknownList.innerHTML = '';
		unknownBox.hidden = unknown.length === 0;

		unknown.forEach( ( tag ) => unknownList.appendChild( unknownRow( tag ) ) );

		const unused = report.unused || [];

		newList.innerHTML = '';
		newBox.hidden = unused.length === 0;

		if ( unused.length ) {
			newMessage.textContent = unused.length === 1
				? i18n.newTagsOne
				: i18n.newTagsMany.replace( '%d', unused.length );

			unused.forEach( ( tag ) => {
				const detail = availableTags.find( ( item ) => item.name === tag );
				newList.appendChild( tagChip( tag, detail ? detail.label : tag, detail && detail.is_file ) );
			} );
		}

		// Attachment lines that name something CF7 will never attach.
		const badAttachments = report.attachments || [];

		attachmentBox.hidden = badAttachments.length === 0;

		if ( badAttachments.length ) {
			attachmentBox.textContent = i18n.badAttach.replace(
				'%s',
				badAttachments.map( ( tag ) => '[' + tag + ']' ).join( ', ' )
			);
		}
	}

	/**
	 * One unknown tag, with Remove / Keep / Replace.
	 *
	 * Nothing is ever changed without the administrator choosing it.
	 *
	 * @param {string} tag Unknown tag name.
	 * @return {HTMLElement} Row element.
	 */
	function unknownRow( tag ) {
		const row = document.createElement( 'span' );
		row.className = 'mwright-tag mwright-tag--unknown';

		const label = document.createElement( 'span' );
		label.className = 'mwright-tag__insert';
		label.innerHTML = '<code class="mwright-tag__code"></code>';
		label.querySelector( 'code' ).textContent = '[' + tag + ']';
		row.appendChild( label );

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'mwright-btn mwright-btn--small';
		remove.textContent = i18n.remove;
		remove.addEventListener( 'click', () => {
			replaceEverywhere( '[' + tag + ']', '' );
			scheduleReport();
		} );

		const keep = document.createElement( 'button' );
		keep.type = 'button';
		keep.className = 'mwright-btn mwright-btn--small';
		keep.textContent = i18n.keep;
		keep.addEventListener( 'click', () => {
			dismissed.push( tag );
			row.remove();

			if ( ! unknownList.children.length ) {
				unknownBox.hidden = true;
			}
		} );

		const replace = document.createElement( 'select' );

		const placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = i18n.replaceWith;
		replace.appendChild( placeholder );

		availableTags.forEach( ( item ) => {
			const option = document.createElement( 'option' );
			option.value = item.name;
			option.textContent = item.label;
			replace.appendChild( option );
		} );

		replace.addEventListener( 'change', () => {
			if ( ! replace.value ) {
				return;
			}

			replaceEverywhere( '[' + tag + ']', '[' + replace.value + ']' );
			scheduleReport();
		} );

		row.appendChild( remove );
		row.appendChild( keep );
		row.appendChild( replace );

		return row;
	}

	/**
	 * Swaps a string in the subject and the body.
	 *
	 * @param {string} search      Text to find.
	 * @param {string} replacement Text to insert.
	 */
	function replaceEverywhere( search, replacement ) {
		const subject = root.querySelector( '[data-field="subject"]' );

		if ( subject ) {
			subject.value = subject.value.split( search ).join( replacement );
		}

		setBody( bodyValue().split( search ).join( replacement ) );
	}

	if ( formSelect ) {
		formSelect.addEventListener( 'change', () => {
			dismissed = [];
			refreshTags();
		} );
	}

	root.querySelectorAll( '[data-field="subject"], [data-field="attachments"]' ).forEach( ( field ) => {
		field.addEventListener( 'input', scheduleReport );
	} );

	if ( ! editor ) {
		bodyArea.addEventListener( 'input', scheduleReport );
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Saves the template.
	 *
	 * @return {Promise} Resolves once saved.
	 */
	function save() {
		const button = root.querySelector( '[data-action="save"]' );

		button.disabled = true;
		button.textContent = i18n.saving;

		return ui
			.post( 'save_template', { template: collect() } )
			.then( ( data ) => {
				templateId = data.id;
				root.dataset.templateId = data.id;

				if ( statusBadge ) {
					statusBadge.textContent = data.label;
					statusBadge.className = 'mwright-badge mwright-badge--' +
						( statusSelect.value === 'publish' ? 'success' : statusSelect.value === 'private' ? 'neutral' : 'warning' );
				}

				markClean();
				ui.toast( data.message, 'success' );

				// Put the new template's ID in the URL so a refresh keeps editing it.
				if ( window.history.replaceState ) {
					window.history.replaceState( {}, '', data.editUrl );
				}

				return data;
			} )
			.catch( ( error ) => {
				ui.toast( error.message, 'error' );
				throw error;
			} )
			.finally( () => {
				button.disabled = false;
				button.textContent = i18n.save;
			} );
	}

	/** Opens the preview modal with sample data. */
	function preview() {
		ui.post( 'preview', { template: collect() } )
			.then( ( data ) => {
				const wrapper = document.createElement( 'div' );
				wrapper.style.display = 'contents';

				const subject = document.createElement( 'div' );
				subject.className = 'mwright-modal__subject';
				subject.innerHTML = '<strong></strong> <span></span>';
				subject.querySelector( 'strong' ).textContent = i18n.subject;
				subject.querySelector( 'span' ).textContent = data.subject;

				const frame = document.createElement( 'iframe' );
				frame.className = 'mwright-modal__frame';
				frame.title = i18n.previewTitle;
				// Sandboxed with no allow-scripts: a hostile template cannot
				// touch the admin page.
				frame.setAttribute( 'sandbox', '' );
				frame.srcdoc = data.type === 'html'
					? data.body
					: '<pre style="font:13px/1.6 Consolas,monospace;white-space:pre-wrap;padding:24px;margin:0;">' +
						data.body.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ) + '</pre>';

				wrapper.appendChild( subject );
				wrapper.appendChild( frame );

				ui.modal( {
					title: i18n.previewTitle,
					body: wrapper,
					wide: true,
					flush: true,
				} );
			} )
			.catch( ( error ) => ui.toast( error.message, 'error' ) );
	}

	/** Asks for a recipient, then sends a test email. */
	function sendTest() {
		const wrapper = document.createElement( 'div' );

		const field = document.createElement( 'p' );
		field.className = 'mwright-field';
		field.innerHTML = '<label for="mwright-test-to"></label><input type="email" id="mwright-test-to" />';
		field.querySelector( 'label' ).textContent = i18n.testPrompt;

		wrapper.appendChild( field );

		const handle = ui.modal( {
			title: i18n.sendTest,
			body: wrapper,
			buttons: [
				{ label: i18n.cancel, onClick: ( dialog ) => dialog.close() },
				{
					label: i18n.sendTest,
					className: 'mwright-btn--primary',
					onClick: ( dialog ) => {
						const recipient = wrapper.querySelector( '#mwright-test-to' ).value;

						dialog.close();

						ui.post( 'send_test', { template: collect(), recipient: recipient } )
							.then( ( data ) => ui.toast( data.message, 'success' ) )
							.catch( ( error ) => ui.toast( error.message, 'error' ) );
					},
				},
			],
		} );

		handle.body.querySelector( '#mwright-test-to' ).focus();
	}

	root.querySelector( '[data-action="save"]' ).addEventListener( 'click', save );
	root.querySelector( '[data-action="preview"]' ).addEventListener( 'click', preview );
	root.querySelector( '[data-action="send-test"]' ).addEventListener( 'click', sendTest );

	// Ctrl/Cmd+S saves, as the rest of the admin does not.
	document.addEventListener( 'keydown', ( event ) => {
		if ( ( event.ctrlKey || event.metaKey ) && event.key === 's' ) {
			event.preventDefault();
			save();
		}
	} );

	if ( typeSelect ) {
		typeSelect.addEventListener( 'change', scheduleReport );
	}

	if ( nameInput && ! nameInput.value ) {
		nameInput.focus();
	}

	try {
		renderRecent( JSON.parse( window.localStorage.getItem( 'mwrightRecentTags' ) || '[]' ) );
	} catch ( error ) {
		// No recents yet.
	}

	/*
	 * What the visual builder needs from this file. The builder owns the
	 * blocks; this file stays the only thing that touches CodeMirror.
	 */
	window.mwrightBody = {
		get: bodyValue,
		set: setBody,
		tags: () => availableTags,
		focusField: ( field ) => {
			lastField = field;
		},
		refresh: () => {
			if ( editor ) {
				editor.refresh();
			}
		},
	};

	refreshTags();

	// The templates list links here with #preview to open the preview straight away.
	if ( '#preview' === window.location.hash ) {
		preview();
	}
} )();
