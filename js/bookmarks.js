jQuery( document ).ready( function( $ ) {

function clearPostProperties() {
	var fieldIds = [
		'link_url',
		'link_name',
		'cite_summary',
		'link_tags',
		'link_author',
		'link_author_url',
		'link_author_photo',
		'link_image',
	];
		if ( ! confirm( PKAPI.clear_message ) ) {
			return;
		}
		$.each( fieldIds, function( count, val ) {
			document.getElementById( val ).value = '';
		});
}

function addhttp( url ) {
	if ( ! /^(?:f|ht)tps?\:\/\//.test( url ) ) {
		url = 'http://' + url;
	}
	return url;
}

//function used to validate website URL
function checkUrl( url ) {

    //regular expression for URL
    var pattern = /^(http|https)?:\/\/[a-zA-Z0-9-\.]+\.[a-z]{2,4}/;

    if ( pattern.test( url ) ) {
        return true;
    } else {
        return false;
    }
}

function getLinkPreview() {
	if ( '' === $( '#link_url' ).val() ) {
		return;
	}
	$.ajax({
		type: 'GET',

		// Here we supply the endpoint url, as opposed to the action in the data object with the admin-ajax method
		url: PKAPI.api_url + 'parse/',
		beforeSend: function( xhr ) {

		// Here we set a header 'X-WP-Nonce' with the nonce as opposed to the nonce in the data object with admin-ajax
		xhr.setRequestHeader( 'X-WP-Nonce', PKAPI.api_nonce );
		},
		data: {
			url: $( '#link_url' ).val(),
			follow: true
		},
		success: function( response ) {
			var published;
			var updated;
			if ( 'undefined' === typeof response ) {
				alert( 'Error: Unable to Retrieve' );
				return;
			}
			if ( 'message' in response ) {
				alert( response.message );
				return;
			}
			if ( 'name' in response ) {
				$( '#link_name' ).val( response.name );
			}
			if ( 'published' in response ) {
				$( '#link_published' ).val( response.published ) ;
			}

			if ( 'content' in response ) {
				$( '#link_notes' ).val( response.content.html ) ;
			}
			if ( 'featured' in response ) {
				$( '#link_image' ).val( response.featured ) ;
			}
			if ( ( 'author' in response ) && ( 'string' != typeof response.author ) ) {
				if ( 'name' in response.author ) {
					if ( 'string' === typeof response.author.name ) {
						$( '#link_author' ).val( response.author.name );
					} else {
						$( '#link_author' ).val( response.author.name.join( ';' ) ) ;
					}
				}
				if ( 'photo' in response.author ) {
					if ( 'string' === typeof response.author.name ) {
						$( '#link_author_photo' ).val( response.author.photo );
					} else {
						$( '#link_author_photo' ).val( response.author.photo.join( ';' ) ) ;
					}
				}
				if ( 'url' in response.author ) {
					if ( 'string' === typeof response.author.url ) {
						$( '#link_author_url' ).val( response.author.url );
					} else {
						$( '#link_author_url' ).val( response.author.url.join( ';' ) ) ;
					}
				}
			}
			if ( 'publication' in response && ( 'string' != typeof response.publication ) ) {
				if ( 'name' in response.publication ) {
					$( '#link_site' ).val( response.publication.name );
				}
				if ( 'url' in response.publication ) {
					$( '#link_site_url' ).val( response.publication.url );
				}

			} else {
				$( '#link_site' ).val( response.publication ) ;
			}

			if ( 'category' in response ) {
				if ( 'object' === typeof response.category ) {
					$( '#tax-input-link_tag' ).val( response.category.join( ',' ) );
				} else {
					$( '#tax-input-link_tag' ).val( response.category );
				}
			}
			if ( 'type' in response && 'feed' === response.type ) {
				$( '#rss_uri' ).val( response.url );
			}
		alert( PKAPI.success_message );
		console.log( response );
		},
		fail: function( response ) {
			console.log( response );
			alert( response.message );
		},
		error: function( jqXHR, textStatus, errorThrown ) {
			alert( jqXHR.responseJSON.message );
			console.log( jqXHR );
		},
	});
}

jQuery( document )
	.on( 'blur', '#link_url', function( event ) {
		if ( '' !== $( '#link_url' ).val() ) {
			if ( false == checkUrl( $( '#link_url' ).val() ) ) {
				alert( 'Invalid URL' );
			} else if ( '' === $( '#link_name' ).val() ) {
				getLinkPreview();
			}
			event.preventDefault();
		}
	})
	.on( 'click', '.clear-link-button', function( event ) {
		clearPostProperties();
		event.preventDefault();
	})
});
