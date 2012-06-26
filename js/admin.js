jQuery(document).ready(function($) {

	if (window.dwtwitter.cpts !== undefined) {

		$('.taxonomies-add').hide();

		var select = $('#twitter-post-type'),
		curr_cpt = $('#twitter-post-type').val(),
		cpts = dwtwitter.cpts;

		show_tax_blocks(curr_cpt,cpts);

		$(select).change(function() {
			$('.taxonomies-add').hide();
			show_tax_blocks($(select).val(),cpts);
		});

	}

	function show_tax_blocks(curr_cpt,cpts) {

		if (typeof cpts[curr_cpt] !== 'undefined') {
			curr_taxes = cpts[curr_cpt];

			curr_taxes = curr_taxes.toString();
			curr_taxes = curr_taxes.split(',');
			$.each(curr_taxes, function(i, tax) {
				$('.taxonomy-'+tax).show();
			});
		}
	}

	$('.delete-twitter-user').click(function(event) {
		if ( !confirm('Are you sure you want to delete user, '+ $(this).attr('id').replace('delete-','') +'?') ) {
			event.preventDefault();
		}
	});

	$('.contextual-help-tabs a').click(function(event) {
		$('.dw-pw-form').hide();
		$('.import-button').show();
	});

	$('.dw-pw-form').hide();
	$('.import-button').click(function(event) {
		$(this).hide();
		var id = $(this).attr('id').replace('import-',''),
		action = $('.dw-pw-form').attr('action'),
		replace = changeQueryVar(action,'tweetimport',id);
		$('.dw-pw-form').show();
		$('.dw-pw-form').attr('action', replace);
		$('.dw-pw-form input[type="password"]').focus();
		event.preventDefault();
	});

	function changeQueryVar(url, keyString, replaceString) {
		var vars = url.split('&');
		for (var i = 0; i < vars.length; i++) {
			var pair = vars[i].split('=');
			if (pair[0] == keyString) {
				vars[i] = pair[0] + '=' + replaceString;
			}
		}
		return vars.join('&');
	}
});