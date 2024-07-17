jQuery( ($) => {
	let rb_a_join_g_a = false;
	$('.group-button.join-anonymously .join-group').click(function(){
		rb_a_join_g_a = true;
	});
	$.ajaxPrefilter(function( options, originalOptions, jqXHR ) {
		if ( ! rb_a_join_g_a ) {
			return false;
		}
		
		let action = '';
		if ( Object.prototype.hasOwnProperty.call( originalOptions, 'data' ) && Object.prototype.hasOwnProperty.call( originalOptions.data, 'action' ) ) {
			action = originalOptions.data.action;
		}

        if ( typeof action == 'undefined' || action != 'groups_join_group' ) {
			return false;
		}

		rb_a_join_g_a = false;//Reset.
        var new_data = $.extend( { }, originalOptions.data, {
            nuqneH: '1'
        } );
        
        options.data = $.param( new_data );
	});
});