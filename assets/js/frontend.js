/**
 * Sticky Call CTA – Frontend
 *
 * Meldet Klicks an GA4. Bevorzugt gtag() (von Site Kit oder gtag.js bereits
 * global vorhanden) und faellt auf dataLayer.push() fuer den Google Tag
 * Manager zurueck.
 *
 * Vor dem Senden wird geprueft, ob der Klick plausibel von einem Menschen
 * stammt. Der tel:-Link funktioniert davon unabhaengig immer – eine
 * fehlgeschlagene Pruefung unterdrueckt nur das Event, nie den Anruf.
 */
( function () {
	'use strict';

	var config = window.dsgnSccData || {};
	var root = document.getElementById( 'dsgn-scc' );

	if ( ! root ) {
		return;
	}

	var link = root.querySelector( '.dsgn-scc__link' );
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

		// Synthetische Klicks aus Skripten melden isTrusted === false.
		if ( event && false === event.isTrusted ) {
			return false;
		}

		// Browser, die sich selbst als automatisiert ausweisen.
		if ( true === window.navigator.webdriver ) {
			return false;
		}

		// Vor einem echten Klick gibt es immer ein pointerdown, touchstart
		// oder keydown. Ein Skript, das direkt click() aufruft, hat das nicht.
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
	 *
	 * Uebertragen werden nur Standort-ID und Seiten-ID. Die Anfrage laeuft
	 * bewusst ohne Cookies, damit keine Sitzung mitgeschickt wird.
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

		var params = {
			location_label: config.locationLabel || '',
			location_id: config.locationId || '',
			phone_number: config.phoneNumber || '',
			page_path: window.location.pathname,
			link_url: link ? link.getAttribute( 'href' ) : ''
		};

		send( config.event, params );

		if ( config.generateLead ) {
			send( 'generate_lead', params );
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
}() );
