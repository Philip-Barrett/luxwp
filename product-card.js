(function () {
	'use strict';

	if ( typeof wplendProductCard === 'undefined' ) {
		return;
	}

	var cfg = wplendProductCard;

	function post( action, data ) {
		var form = new FormData();
		form.append( 'action', action );
		Object.keys( data ).forEach( function ( key ) {
			form.append( key, data[ key ] );
		} );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: form,
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function promptSignIn( message ) {
		if ( window.confirm( message + '\n\nOK = ' + cfg.loginUrl ) ) {
			window.location.href = cfg.loginUrl;
		}
	}

	/* ---------- Модалки (открытие/закрытие) ---------- */
	function openModal( id ) {
		var modal = document.getElementById( id );
		if ( modal ) {
			modal.hidden = false;
		}
	}

	document.querySelectorAll( '[data-wl-modal]' ).forEach( function ( trigger ) {
		trigger.addEventListener( 'click', function () {
			openModal( trigger.getAttribute( 'data-wl-modal' ) );
		} );
	} );

	document.querySelectorAll( '[data-wl-modal-close]' ).forEach( function ( closer ) {
		closer.addEventListener( 'click', function () {
			var modal = closer.closest( '.wl-modal' );
			if ( modal ) {
				modal.hidden = true;
			}
		} );
	} );

	/* ---------- Запрос обновления версии ---------- */
	document.querySelectorAll( '[data-wl-update-trigger]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! cfg.isLoggedIn ) {
				promptSignIn( cfg.i18n.signInUpdate );
				return;
			}
			openModal( 'wl-update-modal' );
		} );
	} );

	var updateForm = document.getElementById( 'wl-update-form' );
	if ( updateForm ) {
		updateForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var msgEl = updateForm.querySelector( '[data-wl-form-message]' );
			var submitBtn = updateForm.querySelector( 'button[type="submit"]' );
			var data = new FormData( updateForm );

			submitBtn.disabled = true;

			post( 'wplend_request_update', {
				nonce: cfg.updateNonce,
				product_id: updateForm.getAttribute( 'data-product-id' ),
				username: data.get( 'username' ),
				email: data.get( 'email' ),
				version: data.get( 'version' ),
				captcha_a: data.get( 'captcha_a' ),
				captcha_b: data.get( 'captcha_b' ),
				captcha_token: data.get( 'captcha_token' ),
				captcha_answer: data.get( 'captcha_answer' ),
			} ).then( function ( res ) {
				submitBtn.disabled = false;

				if ( res.success ) {
					msgEl.hidden = false;
					msgEl.className = 'wl-form-message wl-form-message--ok';
					msgEl.textContent = cfg.i18n.requestSent;
					updateForm.reset();
				} else {
					msgEl.hidden = false;
					msgEl.className = 'wl-form-message wl-form-message--error';

					if ( res.data && 'captcha' === res.data.code ) {
						msgEl.textContent = cfg.i18n.captchaWrong;
					} else if ( res.data && 'not_logged_in' === res.data.code ) {
						msgEl.textContent = cfg.i18n.signInUpdate;
					} else {
						msgEl.textContent = cfg.i18n.genericError;
					}
				}
			} ).catch( function () {
				submitBtn.disabled = false;
				msgEl.hidden = false;
				msgEl.className = 'wl-form-message wl-form-message--error';
				msgEl.textContent = cfg.i18n.genericError;
			} );
		} );
	}
})();
