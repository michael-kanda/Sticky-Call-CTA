/**
 * Sticky Call CTA – Frontend
 *
 * Meldet Klicks an den eigenen Zaehler und an GA4. Fuer GA4 bevorzugt gtag()
 * (von Site Kit oder gtag.js bereits global vorhanden) und faellt auf
 * dataLayer.push() fuer den Google Tag Manager zurueck.
 *
 * Wichtig: Der Button wird spaet im Footer ausgegeben, dieses Skript laeuft
 * dort frueher. Die Initialisierung wartet deshalb auf DOMContentLoaded,
 * sonst ist das Element beim Start noch nicht vorhanden.
 */
( function () {
	'use strict';

	var config = window.dsgnSccData || {};

	function init() {
		var root = document.getElementById( 'dsgn-scc' );

		if ( ! root ) {
			return;
		}

		var link = root.querySelector( '.dsgn-scc__link' );
		var numberElement = root.querySelector( '[data-dsgn-scc-swap="number"]' );
		var startedAt = Date.now();
		var hadInteraction = false;
		var alreadySent = false;

		function markInteraction() {
			hadInteraction = true;
		}

		[ 'pointerdown', 'touchstart', 'mousedown', 'keydown', 'scroll', 'wheel' ].forEach( function ( type ) {
			window.addEventListener( type, markInteraction, { passive: true, capture: true } );
		} );

		function updateHeight() {
			var height = root.offsetHeight || 0;
			document.documentElement.style.setProperty( '--dsgn-scc-height', height + 'px' );
		}

		if ( config.offsetBody ) {
			document.body.classList.add( 'dsgn-scc-offset' );
			updateHeight();

			window.addEventListener( 'resize', updateHeight );
			window.addEventListener( 'orientationchange', updateHeight );

			if ( 'ResizeObserver' in window ) {
				new window.ResizeObserver( updateHeight ).observe( root );
			}
		}

		/**
		 * Aktuell verlinkte Nummer.
		 *
		 * Bewusst aus dem href gelesen statt aus der Konfiguration: Skripte
		 * fuer dynamische Rufnummernzuweisung tauschen den Link nach dem
		 * Laden aus, gemeldet werden muss die tatsaechlich gewaehlte Nummer.
		 *
		 * @return {string} Telefonnummer ohne tel:-Praefix.
		 */
		function currentNumber() {
			var href = link ? link.getAttribute( 'href' ) || '' : '';

			return href.replace( /^tel:/i, '' ) || ( config.phoneNumber || '' );
		}

		/**
		 * aria-label an eine getauschte Nummer angleichen.
		 */
		function syncAriaLabel() {
			if ( ! link ) {
				return;
			}

			var prefix = link.getAttribute( 'data-dsgn-scc-aria-prefix' ) || '';
			var shown = numberElement ? numberElement.textContent.trim() : currentNumber();

			if ( ! shown ) {
				return;
			}

			link.setAttribute( 'aria-label', prefix ? prefix + ' – ' + shown : shown );
		}

		if ( link && 'MutationObserver' in window ) {
			var observer = new window.MutationObserver( syncAriaLabel );

			observer.observe( link, { attributes: true, attributeFilter: [ 'href' ] } );

			if ( numberElement ) {
				observer.observe( numberElement, { childList: true, characterData: true, subtree: true } );
			}
		}

		function consentGranted() {
			if ( ! config.requireConsent ) {
				return true;
			}

			return true === window.dsgnSccConsentGranted;
		}

		/**
		 * Plausibilitaetspruefung des Klicks.
		 *
		 * Absichtlich ohne User-Agent-Listen: die sind faelschbar und muessten
		 * gepflegt werden. Tastaturbedienung wird nicht ausgeschlossen, damit
		 * Screenreader-Nutzer weiter gezaehlt werden.
		 *
		 * @param {Event} event Klick-Event.
		 * @return {boolean} true, wenn der Klick gemeldet werden darf.
		 */
		function looksHuman( event ) {
			if ( ! config.humanOnly ) {
				return true;
			}

			if ( event && false === event.isTrusted ) {
				return false;
			}

			if ( true === window.navigator.webdriver ) {
				return false;
			}

			if ( ! hadInteraction ) {
				return false;
			}

			return true;
		}

		function dwellReached() {
			var minimum = parseInt( config.minDwell, 10 );

			if ( ! minimum || minimum < 1 ) {
				return true;
			}

			return ( Date.now() - startedAt ) >= minimum;
		}

		function send( name, params ) {
			if ( 'function' === typeof window.gtag ) {
				window.gtag( 'event', name, params );
				return;
			}

			window.dataLayer = window.dataLayer || [];

			var payload = { event: name };

			Object.keys( params ).forEach( function ( key ) {
				payload[ key ] = params[ key ];
			} );

			window.dataLayer.push( payload );
		}

		/**
		 * Aggregierten Zaehler im Plugin erhoehen.
		 */
		function countClick() {
			if ( ! config.stats || ! config.statsUrl ) {
				return;
			}

			var body = JSON.stringify( {
				location_id: config.locationId || '',
				post_id: config.postId || 0
			} );

			if ( window.navigator.sendBeacon ) {
				try {
					window.navigator.sendBeacon( config.statsUrl, new Blob( [ body ], { type: 'application/json' } ) );
					return;
				} catch ( error ) {
					// Faellt unten auf fetch zurueck.
				}
			}

			if ( window.fetch ) {
				window.fetch( config.statsUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: body,
					keepalive: true,
					credentials: 'omit'
				} ).catch( function () {} );
			}
		}

		function sendGa4() {
			if ( ! config.track || ! consentGranted() ) {
				return;
			}

			send( config.event, {
				location_label: config.locationLabel || '',
				location_id: config.locationId || '',
				phone_number: currentNumber(),
				page_path: window.location.pathname,
				link_url: link ? link.getAttribute( 'href' ) : ''
			} );

			if ( config.generateLead ) {
				send( 'generate_lead', {
					location_label: config.locationLabel || '',
					location_id: config.locationId || '',
					phone_number: currentNumber(),
					page_path: window.location.pathname,
					link_url: link ? link.getAttribute( 'href' ) : ''
				} );
			}
		}

		/**
		 * Ein Klick, zwei Ziele: die eigene Zaehlung laeuft unabhaengig vom
		 * Consent, weil sie rein aggregiert ist. Die Plausibilitaetspruefungen
		 * gelten fuer beide, damit die Zahlen vergleichbar bleiben.
		 *
		 * @param {Event} event Klick-Event.
		 */
		function handleClick( event ) {
			if ( config.oncePerView && alreadySent ) {
				return;
			}

			if ( ! looksHuman( event ) || ! dwellReached() ) {
				return;
			}

			alreadySent = true;

			countClick();
			sendGa4();
		}

		if ( link ) {
			link.addEventListener( 'click', handleClick );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
