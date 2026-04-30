jQuery(document).ready(function($) {
	const $overlay = $('#kyc-modal-overlay');
	const $triggerBtn = $('#kyc-trigger-btn');
	const storageKey = 'kyc_popup_submitted';

	// Handle Popup Display with Delay
	if ($overlay.length > 0) {
		const isSubmitted = localStorage.getItem(storageKey);
		
		if (!isSubmitted) {
			setTimeout(function() {
				$overlay.fadeIn();
			}, kycObj.delay);
		}
	}

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

	// Form Submission
	$('.kyc-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $msg = $form.find('.kyc-msg');
		const $btn = $form.find('button[type="submit"]');
		const isPopup = $form.attr('id') === 'kyc-popup-form';

		$btn.prop('disabled', true).text('Submitting...');
		$msg.removeClass('success error').hide().text('');

		let data = $form.serialize();
		data += '&security=' + kycObj.nonce;

		$.post({
			url: kycObj.ajaxurl,
			data: data,
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					$msg.addClass('success').text(response.data.message).show();
					
					if (isPopup) {
						localStorage.setItem(storageKey, 'true');
						setTimeout(function() {
							$overlay.fadeOut();
							$form[0].reset();
						}, 2000);
					}
				} else {
					$msg.addClass('error').text(response.data.message).show();
				}
			},
			error: function() {
				$msg.addClass('error').text('An error occurred. Please try again.').show();
			},
			complete: function() {
				$btn.prop('disabled', false).text(isPopup ? 'Submit' : 'Save Profile');
			}
		});
	});
});
