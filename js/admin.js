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

	$('.import-button').click(function(event) {
		var newaction = $(this).attr('name'),
		id = $(this).attr('id').replace('import-','');
		$('.twitter-importer').attr('action', newaction);
		$('input[name="username"]').val(id);
		// event.preventDefault();
	});

});