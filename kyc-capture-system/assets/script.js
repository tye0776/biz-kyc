jQuery(document).ready(function($) {
	const $overlay = $('#kyc-modal-overlay');
	const $triggerBtn = $('#kyc-trigger-btn');

	// Open modal on floating button click
	$triggerBtn.on('click', function(e) {
        e.preventDefault();
		$overlay.fadeIn();
	});

	// Close modal
	$('.kyc-modal-close').on('click', function() {
		$overlay.fadeOut();
	});

	// Close on click outside
	$overlay.on('click', function(e) {
		if ($(e.target).is('#kyc-modal-overlay')) {
			$overlay.fadeOut();
		}
	});

	// Form Submission (handles both popup and full form)
	$('.kyc-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $msg = $form.find('.kyc-msg');
		const $btn = $form.find('button[type="submit"]');

		$btn.prop('disabled', true).text('Submitting...');
		$msg.removeClass('success error').hide().text('');

		// Serialize form data and append nonce/action
		let data = $form.serialize();
		data += '&security=' + kyc_ajax_obj.nonce;

		$.post({
			url: kyc_ajax_obj.ajaxurl,
			data: data,
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					$msg.addClass('success').text(response.data.message).show();
					// Optional: hide modal after success if it's the popup
					if ($form.attr('id') === 'kyc-popup-form') {
                        setTimeout(function() {
                            $overlay.fadeOut();
                            $form[0].reset();
                        }, 3000);
                    }
				} else {
					$msg.addClass('error').text(response.data.message).show();
				}
			},
			error: function() {
				$msg.addClass('error').text('An error occurred. Please try again.').show();
			},
			complete: function() {
				$btn.prop('disabled', false).text($form.attr('id') === 'kyc-popup-form' ? 'Submit' : 'Save Profile');
			}
		});
	});
});
