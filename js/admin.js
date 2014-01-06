jQuery(document).ready(function($) {

	if (window.dwtwitter.cpts !== undefined) {

		show_tax_blocks_init();

		$('.tab-twitter-user a').click(function() {
			show_tax_blocks_init($(this).text());
		});

	}
	function show_tax_blocks_init(user) {

		$('.taxonomies-add').hide();

		var select = $('.help-tab-content.active .twitter-post-type'),
		curr_cpt = $('.help-tab-content.active .twitter-post-type').val(),
		cpts = dwtwitter.cpts;

		if (user !== undefined) {
			select = $('#twitter-post-type-'+user),
			curr_cpt = $('#twitter-post-type-'+user).val();
		}

		show_tax_blocks(curr_cpt,cpts);

		$(select).change(function() {
			$('.taxonomies-add').hide();
			show_tax_blocks($(select).val(),cpts);
		});
	}


	function show_tax_blocks(curr_cpt,cpts) {

		// hashtags saver (disable)
		var selector = 'select[id$="hashtags_as_tax"]:visible';
		$(selector).prop('disabled',true);

		if (typeof cpts[curr_cpt] !== 'undefined') {
			curr_taxes = cpts[curr_cpt];

			curr_taxes = curr_taxes.toString();
			curr_taxes = curr_taxes.split(',');

			// hashtags saver (disable options)
			$(selector + ' option:not(.empty)').prop('disabled',true);
			var selected = $(selector + ' option:selected').prop('selected',false).text();
			var option;

			for ( var i = 0; i < curr_taxes.length; i++ ) {

				var tax = curr_taxes[i];
				$('.taxonomy-'+tax).show();

				// skip post formats
				if ( tax === 'post_format' )
					continue;

				// hashtags saver (re-enable options)
				$(selector).prop('disabled',false);
				option = $(selector + ' option.taxonomy-'+tax);
				option.prop('disabled',false);
				if ( option.text() === selected )
					option.prop('selected', true);
			}
		}
	}

	$('.delete-twitter-user').click(function(event) {
		if ( !confirm('Are you sure you want to delete user, '+ $(this).attr('id').replace('delete-','') +'?') ) {
			event.preventDefault();
		}
	});

	var width = $('.help-tab-content').width();
	$('.dw-pw-form').width(width-85);

	$('.button-primary').click(function(event) {
		var id = $(this).attr('id').replace('save-','');
		$('input[name="dsgnwrks_tweet_options[username]"]').val(id);
		// event.preventDefault();
	});

	$('.import-button').click(function(event) {
		var newaction = $(this).attr('name'),
		id = $(this).attr('id').replace('import-','');
		$('.twitter-importer').attr('action', newaction);
		$('input[name="dsgnwrks_tweet_options[username]"]').val(id);
		// event.preventDefault();
	});

});