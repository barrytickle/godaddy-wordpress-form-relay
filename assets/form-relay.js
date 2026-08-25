( function () {
	'use strict';
	var turnstilePromise;
	function responseFor( form ) {
		var existing = form.querySelector( '[data-form-relay-response]' );
		if ( existing ) return existing;
		var response = document.createElement( 'div' );
		response.className = 'form-relay-message'; response.setAttribute( 'aria-live', 'polite' ); response.setAttribute( 'data-form-relay-response', '' );
		form.insertAdjacentElement( 'beforeend', response ); return response;
	}
	function loaderFor( form, response ) {
		var existing = form.querySelector( '[data-form-relay-loader]' );
		if ( existing ) return existing;
		var loader = document.createElement( 'div' );
		loader.className = 'form-relay-loader'; loader.setAttribute( 'role', 'status' ); loader.setAttribute( 'aria-live', 'polite' ); loader.setAttribute( 'data-form-relay-loader', '' ); loader.hidden = true;
		loader.innerHTML = '<span class="form-relay-loader__spinner" aria-hidden="true"></span><span>Sending&hellip;</span>';
		response.insertAdjacentElement( 'beforebegin', loader ); return loader;
	}
	function show( form, response, type, message, behaviour ) {
		response.textContent = message || ''; response.classList.remove( 'form-relay-message--success', 'form-relay-message--error' ); response.classList.add( 'form-relay-message--' + type );
		response.setAttribute( 'role', 'success' === type ? 'status' : 'alert' ); response.setAttribute( 'aria-live', 'success' === type ? 'polite' : 'assertive' );
		form.classList.toggle( 'form-relay-submitted', 'success' === type ); form.classList.toggle( 'form-relay-has-error', 'error' === type );
		var customClasses = 'success' === type ? behaviour.successClasses : behaviour.errorClasses; if ( Array.isArray( customClasses ) ) customClasses.forEach( function ( className ) { response.classList.add( className ); } );
		if ( behaviour.scroll && response.scrollIntoView ) response.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}
	function loadTurnstile() {
		if ( window.turnstile ) return Promise.resolve( window.turnstile );
		if ( turnstilePromise ) return turnstilePromise;
		turnstilePromise = new Promise( function ( resolve, reject ) {
			var existing = document.querySelector( 'script[data-form-relay-turnstile],script[src*="challenges.cloudflare.com/turnstile"]' );
			var loaded = function () { if ( window.turnstile ) resolve( window.turnstile ); else reject( new Error( 'Turnstile did not load.' ) ); };
			if ( existing ) { existing.addEventListener( 'load', loaded, { once: true } ); existing.addEventListener( 'error', reject, { once: true } ); return; }
			var script = document.createElement( 'script' ); script.src = FormRelayConfig.turnstile.scriptUrl + '?render=explicit'; script.async = true; script.defer = true; script.setAttribute( 'data-form-relay-turnstile', '' ); script.addEventListener( 'load', loaded, { once: true } ); script.addEventListener( 'error', reject, { once: true } ); document.head.appendChild( script );
		} );
		return turnstilePromise;
	}
	function prepareTurnstile( form, behaviour ) {
		var container = 'manual' === behaviour.turnstileLocation ? form.querySelector( '[data-form-relay-captcha]' ) : null;
		if ( 'manual' === behaviour.turnstileLocation && ! container ) return Promise.reject( new Error( 'Manual Turnstile placement was not found.' ) );
		if ( ! container ) { container = document.createElement( 'div' ); container.setAttribute( 'data-form-relay-captcha', '' ); container.setAttribute( 'data-form-relay-captcha-generated', '' ); var submit = form.querySelector( 'button:not([type]),button[type="submit"],input[type="submit"]' ); if ( submit ) submit.insertAdjacentElement( 'beforebegin', container ); else form.insertAdjacentElement( 'beforeend', container ); }
		container.classList.add( 'form-relay-captcha' );
		if ( ! FormRelayConfig.turnstile.siteKey ) return Promise.reject( new Error( 'Turnstile is not configured.' ) );
		return loadTurnstile().then( function ( turnstile ) { return turnstile.render( container, { sitekey: FormRelayConfig.turnstile.siteKey, action: form.dataset.formRelay, theme: 'auto', size: 'flexible', retry: 'auto', 'refresh-expired': 'auto' } ); } );
	}
	function resetTurnstile( widgetId ) { if ( null !== widgetId && window.turnstile ) window.turnstile.reset( widgetId ); }
	function init( form ) {
		if ( form.dataset.formRelayReady ) return; form.dataset.formRelayReady = 'true';
		var response = responseFor( form ); var loader = loaderFor( form, response ); var behaviour = FormRelayConfig.forms[ form.dataset.formRelay ] || { disable: true, scroll: false }; var widgetId = null;
		if ( behaviour.turnstile ) prepareTurnstile( form, behaviour ).then( function ( id ) { widgetId = id; } ).catch( function () { widgetId = null; } );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault(); if ( form.classList.contains( 'form-relay-loading' ) ) return;
			var captchaToken = ''; if ( behaviour.turnstile ) { if ( null === widgetId || ! window.turnstile ) { show( form, response, 'error', 'Spam protection is temporarily unavailable. Please try again shortly.', behaviour ); return; } captchaToken = window.turnstile.getResponse( widgetId ); if ( ! captchaToken ) { show( form, response, 'error', 'Please complete the spam check and try again.', behaviour ); return; } }
			var submitters = form.querySelectorAll( 'button:not([type]),button[type="submit"],input[type="submit"]' ); response.textContent = ''; response.className = 'form-relay-message';
			form.classList.remove( 'form-relay-submitted', 'form-relay-has-error' ); form.classList.add( 'form-relay-loading' ); loader.hidden = false;
			if ( behaviour.disable ) submitters.forEach( function ( button ) { button.disabled = true; } );
			var fields = {}; new FormData( form ).forEach( function ( value, key ) { if ( value instanceof File || 'cf-turnstile-response' === key ) return; if ( Object.prototype.hasOwnProperty.call( fields, key ) ) { if ( ! Array.isArray( fields[ key ] ) ) fields[ key ] = [ fields[ key ] ]; fields[ key ].push( value ); } else fields[ key ] = value; } );
			fetch( FormRelayConfig.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': FormRelayConfig.nonce }, body: JSON.stringify( { form_id: form.dataset.formRelay, fields: fields, captcha_token: captchaToken, meta: { site_name: FormRelayConfig.siteName, page_title: document.title, page_url: location.href } } ) } )
				.then( function ( reply ) { return reply.json().catch( function () { return { success: false, error: { message: 'Something went wrong. Please try again.' } }; } ); } )
				.then( function ( result ) { resetTurnstile( widgetId ); if ( result.success ) { if ( 'page' === behaviour.responseType && behaviour.thankYouUrl ) { window.location.assign( behaviour.thankYouUrl ); return; } show( form, response, 'success', result.message, behaviour ); if ( result.reset ) form.reset(); } else show( form, response, 'error', result.error && result.error.message ? result.error.message : 'Something went wrong. Please try again.', behaviour ); } )
				.catch( function () { resetTurnstile( widgetId ); show( form, response, 'error', 'Something went wrong. Please try again.', behaviour ); } )
				.finally( function () { form.classList.remove( 'form-relay-loading' ); loader.hidden = true; if ( behaviour.disable ) submitters.forEach( function ( button ) { button.disabled = false; } ); } );
		} );
		if ( ! form.querySelector( '[name="_form_relay_hp"]' ) ) { var hp = document.createElement( 'input' ); hp.type = 'text'; hp.name = '_form_relay_hp'; hp.tabIndex = -1; hp.autocomplete = 'off'; hp.setAttribute( 'aria-hidden', 'true' ); hp.className = 'form-relay-honeypot'; form.appendChild( hp ); }
	}
	function boot() { document.querySelectorAll( 'form[data-form-relay]' ).forEach( init ); }
	if ( 'loading' === document.readyState ) document.addEventListener( 'DOMContentLoaded', boot ); else boot();
}() );
