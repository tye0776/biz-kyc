jQuery(document).ready(function($) {
	let modalShown = false;
	const $overlay = $('#kyc-modal-overlay');

	function showModal() {
		if (!modalShown && $overlay.length) {
			$overlay.fadeIn();
			modalShown = true;
		}
	}

	// 1. Show after 5 seconds
	if ($overlay.length) {
		setTimeout(showModal, 5000);

		// 2. Or on exit intent (mouse leaves window)
		$(document).on('mouseleave', function(e) {
			if (e.clientY < 0) {
				showModal();
			}
		});
	}

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

	// Form Submission
	$('#kyc-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $msg = $form.find('.kyc-msg');
		const $btn = $form.find('.kyc-submit-btn');

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
					$form[0].reset();
					// Optional: hide modal after success
					setTimeout(function() {
						$overlay.fadeOut();
					}, 3000);
				} else {
					$msg.addClass('error').text(response.data.message).show();
				}
			},
			error: function() {
				$msg.addClass('error').text('An error occurred. Please try again.').show();
			},
			complete: function() {
				$btn.prop('disabled', false).text('Submit');
			}
		});
	});
});
