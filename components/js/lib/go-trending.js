if ( 'undefined' === go_trending ) {
	var go_trending = {};
}//end if

( function( $ ) {
	'use strict';

	go_trending.event = {};

	go_trending.init = function() {
		var jqxhr = $.ajax( {
			url: this.endpoint,
			dataType: 'json'
		} );

		jqxhr.done( function( data ) {
			if ( 'undefined' === typeof data.data ) {
				return;
			}//end if

			var trending = [];

			for ( var i in data.data ) {
				if ( 'gigaom.com/' === data.data[ i ].path ) {
					continue;
				}//end if

				trending.push( data.data[ i ] );
			}//end for

			var $template_container = $( document.getElementById( 'trending-posts-template' ) );
			var source = $template_container.html();
			var template = Handlebars.compile( source );

			$template_container.after( template( {
				posts: trending
			} ) );

			$template_container.closest( '.widget' ).removeClass( 'hide' );
		} );
	};

	$( function() {
		go_trending.init();
	} );
} )( jQuery );
