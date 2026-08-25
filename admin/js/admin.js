document.addEventListener( 'click', function ( event ) {
	var placeholderButton = event.target.closest( '[data-placeholder]' );
	if ( placeholderButton ) {
		var group = placeholderButton.closest( '[data-target]' );
		var editor = document.getElementById( group.dataset.target );
		editor.setRangeText( placeholderButton.dataset.placeholder, editor.selectionStart, editor.selectionEnd, 'end' ); editor.focus();
	}
	var copy = event.target.closest( '[data-copy]' );
	if ( copy && navigator.clipboard ) navigator.clipboard.writeText( copy.dataset.copy ).then( function () { var old = copy.textContent; copy.textContent = 'Copied'; setTimeout( function () { copy.textContent = old; }, 1500 ); } );
	if ( event.target.closest( '.confirm-delete' ) && ! window.confirm( 'Delete this item? This cannot be undone.' ) ) event.preventDefault();
	if ( event.target.closest( '.form-relay-modal-preview' ) ) openEmailPreview();
	if ( event.target.closest( '[data-preview-close]' ) ) closeEmailPreview();
} );

document.addEventListener( 'keydown', function ( event ) { if ( 'Escape' === event.key ) closeEmailPreview(); } );
document.addEventListener( 'change', function ( event ) { if ( event.target.matches( '[name="form[response_type]"]' ) ) updateResponsePanels(); if ( event.target.matches( '[data-mail-method], [data-smtp-auth]' ) ) updateMailPanels(); if ( event.target.matches( '[data-turnstile-global-toggle], [data-turnstile-form-toggle]' ) ) updateTurnstilePanels(); if ( event.target.matches( '.form-relay-select-all' ) ) { document.querySelectorAll( '[name="submission_ids[]"], .form-relay-select-all' ).forEach( function ( checkbox ) { checkbox.checked = event.target.checked; } ); } } );
document.addEventListener( 'input', function ( event ) { if ( event.target.matches( '[name="form[sender_domain]"]' ) ) updateSenderDomain( event.target.value ); } );
document.addEventListener( 'DOMContentLoaded', function () { updateResponsePanels(); updateMailPanels(); updateTurnstilePanels(); } );

function updateSenderDomain( domain ) {
	var suffix = document.querySelector( '.sender-email__domain' ); if ( suffix ) suffix.textContent = '@' + domain.replace( /^https?:\/\//i, '' ).replace( /^www\./i, '' ).split( '/' )[0];
}

function updateResponsePanels() {
	var selected = document.querySelector( '[name="form[response_type]"]:checked' ); if ( ! selected ) return;
	document.querySelectorAll( '[data-response-panel]' ).forEach( function ( panel ) { panel.hidden = panel.dataset.responsePanel !== selected.value; } );
}

function updateMailPanels() {
	var method = document.querySelector( '[data-mail-method]' ); if ( ! method ) return;
	document.querySelectorAll( '[data-mail-panel]' ).forEach( function ( panel ) { panel.hidden = panel.dataset.mailPanel !== method.value; } );
	var auth = document.querySelector( '[data-smtp-auth]' ); var authFields = document.querySelector( '.smtp-auth-fields' ); if ( auth && authFields ) authFields.hidden = ! auth.checked;
}

function updateTurnstilePanels() {
	var globalToggle = document.querySelector( '[data-turnstile-global-toggle]' ); var globalPanel = document.querySelector( '[data-turnstile-global-panel]' ); if ( globalToggle && globalPanel ) globalPanel.hidden = ! globalToggle.checked;
	var formToggle = document.querySelector( '[data-turnstile-form-toggle]' ); var formPanel = document.querySelector( '[data-turnstile-form-panel]' ); if ( formToggle && formPanel ) formPanel.hidden = ! formToggle.checked;
}

function openEmailPreview() {
	var modal = document.getElementById( 'form-relay-preview-modal' ); var form = document.getElementById( 'form-relay-editor' );
	if ( ! modal || ! form ) return;
	var status = modal.querySelector( '.form-relay-modal__status' ); var notice = modal.querySelector( '.form-relay-modal__error' ); var frame = modal.querySelector( '.form-relay-modal__frame' ); var closeButton = modal.querySelector( '.form-relay-modal__close' );
	if ( ! status || ! notice || ! frame || ! closeButton ) return;
	modal.hidden = false; document.body.classList.add( 'form-relay-modal-open' );
	status.hidden = true; notice.hidden = true; frame.hidden = true; frame.removeAttribute( 'srcdoc' );
	closeButton.focus();
	try { frame.srcdoc = renderLocalEmailPreview( form, modal.dataset.siteName ); frame.hidden = false; } catch ( error ) { notice.querySelector( 'p' ).textContent = error.message || 'The preview could not be rendered.'; notice.hidden = false; }
}

function renderLocalEmailPreview( form, siteName ) {
	var mainTemplate = form.querySelector( '[name="form[email_template]"]' ).value; var rowTemplate = form.querySelector( '[name="form[row_template]"]' ).value; var formName = form.querySelector( '[name="form[name]"]' ).value || 'Sample Form';
	var fields = { name: 'Sample User', email: 'sample@example.com', phone: '07123 456789', company: 'Example Company', service: 'Web Development', message: "I'd like some information about a new website." };
	var ignoredInput = form.querySelector( '[name="form[ignored_fields]"]' ); var ignored = ignoredInput ? ignoredInput.value.split( /\r?\n/ ).map( function ( item ) { return item.trim(); } ) : [];
	var rows = Object.keys( fields ).filter( function ( key ) { return -1 === ignored.indexOf( key ); } ).map( function ( key ) { return replacePreviewPlaceholders( rowTemplate, { field_key: escapePreviewHtml( key ), field_label: escapePreviewHtml( previewLabel( key ) ), field_value: previewValue( fields[ key ] ) } ); } ).join( '' );
	return replacePreviewPlaceholders( mainTemplate, { form_name: escapePreviewHtml( formName ), site_name: escapePreviewHtml( siteName || 'Example Website' ), page_title: 'Sample Page', page_url: 'https://example.com/sample/', submitted_at: new Date().toLocaleString(), fields: rows } );
}

function replacePreviewPlaceholders( template, values ) { Object.keys( values ).forEach( function ( key ) { template = template.split( '{{' + key + '}}' ).join( values[ key ] ); } ); return template.replace( /{{[a-z_]+}}/g, '' ); }
function previewLabel( key ) { return key.replace( /([a-z])([A-Z])/g, '$1 $2' ).replace( /[_-]+/g, ' ' ).replace( /\b\w/g, function ( letter ) { return letter.toUpperCase(); } ); }
function previewValue( value ) { var escaped = escapePreviewHtml( value ); if ( /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) return '<a href="mailto:' + escaped + '">' + escaped + '</a>'; if ( /^\+?[0-9][0-9 ()\-.]{6,20}$/.test( value ) ) return '<a href="tel:' + value.replace( /[^0-9+]/g, '' ) + '">' + escaped + '</a>'; return escaped.replace( /\n/g, '<br>' ); }
function escapePreviewHtml( value ) { var element = document.createElement( 'div' ); element.textContent = String( value ); return element.innerHTML; }

function closeEmailPreview() {
	var modal = document.getElementById( 'form-relay-preview-modal' ); if ( ! modal || modal.hidden ) return;
	modal.hidden = true; document.body.classList.remove( 'form-relay-modal-open' ); var trigger = document.querySelector( '.form-relay-modal-preview' ); if ( trigger ) trigger.focus();
}
