// based on WP's post.js file

function new_tag_remove_tag() {
	var id = jQuery( this ).attr( 'id' );
	var num = id.substr( 10 );
	var current_tags = jQuery( '#tags-input' ).val().split(',');
	delete current_tags[num];
	var new_tags = [];
	jQuery.each( current_tags, function( key, val ) {
		if ( val && !val.match(/^\s+$/) && '' != val ) {
			new_tags = new_tags.concat( val );
		}
	});
	jQuery( '#tags-input' ).val( new_tags.join( ',' ).replace( /\s*,+\s*/, ',' ).replace( /,+/, ',' ).replace( /,+\s+,+/, ',' ).replace( /,+\s*$/, '' ).replace( /^\s*,+/, '' ) );
	tag_update_quickclicks();
	jQuery('#newtag').focus();
	return false;
}

function tag_update_quickclicks() {
	var current_tags = jQuery( '#tags-input' ).val().split(',');
	jQuery( '#tagchecklist' ).empty();
	shown = false;
//	jQuery.merge( current_tags, current_tags ); // this doesn't work anymore, need something to array_unique
	jQuery.each( current_tags, function( key, val ) {
		val = val.replace( /^\s+/, '' ).replace( /\s+$/, '' ); // trim
		if ( !val.match(/^\s+$/) && '' != val ) {
			txt = '<span><a id="tag-check-' + key + '" class="ntdelbutton">X</a>&nbsp;' + val + '</span> ';
			jQuery( '#tagchecklist' ).append( txt );
			jQuery( '#tag-check-' + key ).click( new_tag_remove_tag );
			shown = true;
		}
	});
	if ( shown )
		jQuery( '#tagchecklist' ).prepend( '<strong>'+page_tagsL10n.tagsUsed+'</strong><br />' );
}

function tag_flush_to_text() {
	var newtags = jQuery('#tags-input').val() + ',' + jQuery('#newtag').val();
	// massage
	newtags = newtags.replace( /\s+,+\s*/g, ',' ).replace( /,+/g, ',' ).replace( /,+\s+,+/g, ',' ).replace( /,+\s*$/g, '' ).replace( /^\s*,+/g, '' );
	jQuery('#tags-input').val( newtags );
	tag_update_quickclicks();
	jQuery('#newtag').val('');
	jQuery('#newtag').focus();
	return false;
}

function tag_save_on_publish() {
	if ( jQuery('#newtag').val() != page_tagsL10n.addTag )
		tag_flush_to_text();
}

function tag_press_key( e ) {
	if ( 13 == e.keyCode ) {
		tag_flush_to_text();
		return false;
	}
}

addLoadEvent( function() {
	// If no tags on the page, skip the tag and category stuff.
	if ( !jQuery('#tags-input').size() ) {
		return;
	}

	jQuery('#tags-input').hide();
	tag_update_quickclicks();
	// add the quickadd form
	jQuery('#jaxtag').prepend('<span id="ajaxtag"><input type="text" name="newtag" id="newtag" class="form-input-tip" size="16" autocomplete="off" value="'+page_tagsL10n.addTag+'" /><input type="button" class="button" id="tagadd" value="' + page_tagsL10n.add + '"/><input type="hidden"/><input type="hidden"/><span class="howto">'+page_tagsL10n.separate+'</span></span>');
	jQuery('#tagadd').click( tag_flush_to_text );
	jQuery('#newtag').focus(function() {
		if ( this.value == page_tagsL10n.addTag )
			jQuery(this).val( '' ).removeClass( 'form-input-tip' );
	});
	jQuery('#newtag').blur(function() {
		if ( this.value == '' )
			jQuery(this).val( page_tagsL10n.addTag ).addClass( 'form-input-tip' );
	});

	// auto-save tags on page save/publish
	jQuery('#publish').click( tag_save_on_publish );
	jQuery('#save-post').click( tag_save_on_publish );

	// auto-suggest stuff
	jQuery('#newtag').suggest( 'admin-ajax.php?action=ajax-tag-search', { delay: 500, minchars: 2 } );
	jQuery('#newtag').keypress( tag_press_key );
});
