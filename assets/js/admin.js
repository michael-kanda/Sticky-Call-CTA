/**
 * Sticky Call CTA – Einstellungsseite
 *
 * Fuegt Zeilen fuer Standorte und URL-Regeln hinzu bzw. entfernt sie.
 * Die Indizes werden nur zur Formularuebertragung gebraucht, gespeichert
 * wird serverseitig neu durchnummeriert.
 */
( function () {
	'use strict';

	var strings = window.dsgnSccAdmin || {};
	var counter = Date.now();

	function addRow( tableId ) {
		var table = document.getElementById( tableId );
		var template = document.getElementById( 'dsgn-scc-tmpl-' + tableId );

		if ( ! table || ! template ) {
			return;
		}

		var body = table.querySelector( 'tbody' );

		if ( ! body ) {
			return;
		}

		counter++;

		var markup = template.innerHTML.split( '__INDEX__' ).join( String( counter ) );
		var holder = document.createElement( 'tbody' );

		holder.innerHTML = markup;

		var row = holder.querySelector( 'tr' );

		if ( ! row ) {
			return;
		}

		body.appendChild( row );

		var firstField = row.querySelector( 'input[type="text"]' );

		if ( firstField ) {
			firstField.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var addButton = event.target.closest( '.dsgn-scc-add' );

		if ( addButton ) {
			event.preventDefault();
			addRow( addButton.getAttribute( 'data-target' ) );
			return;
		}

		var removeButton = event.target.closest( '.dsgn-scc-remove' );

		if ( removeButton ) {
			event.preventDefault();

			var row = removeButton.closest( 'tr' );

			if ( ! row ) {
				return;
			}

			if ( window.confirm( strings.confirmRemove || 'Remove?' ) ) {
				row.parentNode.removeChild( row );
			}
		}
	} );
}() );
