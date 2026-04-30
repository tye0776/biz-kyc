/**
 * KYC Linked Contacts — Family & Reminders section
 * Handles add, edit, delete of linked contacts via AJAX.
 */
(function ($) {
	'use strict';

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	const RELATIONSHIPS = [
		'Wife', 'Husband', 'Son', 'Daughter', 'Mother', 'Father',
		'Brother', 'Sister', 'Friend', 'In-Law', 'Other'
	];

	function buildRelationshipSelect(selectedValue) {
		let html = '<select id="kyc-contact-relationship" name="relationship" required>';
		RELATIONSHIPS.forEach(function (rel) {
			const selected = rel === selectedValue ? ' selected' : '';
			html += `<option value="${rel}"${selected}>${rel}</option>`;
		});
		html += '</select>';
		return html;
	}

	function formatDate(dateStr) {
		if (!dateStr || dateStr === '0000-00-00') return '—';
		const d = new Date(dateStr + 'T00:00:00');
		return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
	}

	function mutualBadge(isMutual) {
		return isMutual
			? '<span class="kyc-mutual-badge" title="Mutual link confirmed — both of you have linked each other">🔗 Confirmed</span>'
			: '<span class="kyc-pending-badge" title="Linked, but they haven\'t registered yet">⏳ Pending</span>';
	}

	// -------------------------------------------------------------------------
	// Render the contacts table
	// -------------------------------------------------------------------------

	function renderContacts(contacts) {
		const $table = $('#kyc-contacts-table');
		const $empty = $('#kyc-contacts-empty');

		if (!contacts || contacts.length === 0) {
			$table.hide();
			$empty.show();
			return;
		}

		$empty.hide();
		const $tbody = $table.find('tbody').empty();
		$table.show();

		contacts.forEach(function (c) {
			const row = `
				<tr data-contact-id="${c.id}">
					<td>${$('<div>').text(c.linked_name).html()}</td>
					<td>${$('<div>').text(c.linked_phone).html()}</td>
					<td>${$('<div>').text(c.relationship).html()}</td>
					<td>${formatDate(c.dob)}</td>
					<td>${formatDate(c.anniversary)}</td>
					<td>${mutualBadge(c.linked_customer_id)}</td>
					<td>
						<button class="button button-small kyc-edit-contact" data-id="${c.id}">Edit</button>
						<button class="button button-small kyc-delete-contact" data-id="${c.id}" style="color:#d63638;">Remove</button>
					</td>
				</tr>`;
			$tbody.append(row);
		});
	}

	// -------------------------------------------------------------------------
	// Modal management
	// -------------------------------------------------------------------------

	function openModal(contact) {
		const isEdit = !!contact;
		const c = contact || {};

		const relValue = c.relationship || 'Wife';
		const isOther  = !RELATIONSHIPS.includes(relValue);
		const selectVal = isOther ? 'Other' : relValue;

		$('#kyc-contact-modal-title').text(isEdit ? 'Edit Family Member' : 'Add Family Member');
		$('#kyc-contact-id').val(c.id || '');
		$('#kyc-contact-linked-name').val(c.linked_name || '');
		$('#kyc-contact-linked-phone').val(c.linked_phone || '');
		$('#kyc-contact-dob').val(c.dob && c.dob !== '0000-00-00' ? c.dob : '');
		$('#kyc-contact-anniversary').val(c.anniversary && c.anniversary !== '0000-00-00' ? c.anniversary : '');

		// Rebuild select to reflect current value
		$('#kyc-contact-relationship').replaceWith(buildRelationshipSelect(selectVal));

		// Show/hide custom field
		if (isOther) {
			$('#kyc-custom-relationship-wrap').show();
			$('#kyc-contact-custom-relationship').val(relValue);
		} else {
			$('#kyc-custom-relationship-wrap').hide();
			$('#kyc-contact-custom-relationship').val('');
		}

		$('#kyc-contact-msg').text('').hide();
		$('#kyc-contact-modal-overlay').fadeIn(200);
	}

	function closeModal() {
		$('#kyc-contact-modal-overlay').fadeOut(150);
	}

	// -------------------------------------------------------------------------
	// Load contacts on page ready
	// -------------------------------------------------------------------------

	let contactsData = [];

	function loadContactsFromDOM() {
		// Initial contacts are rendered server-side; grab from data attribute
		const raw = $('#kyc-contacts-root').data('contacts');
		contactsData = raw ? JSON.parse(raw) : [];
		renderContacts(contactsData);
	}

	// -------------------------------------------------------------------------
	// Event Bindings
	// -------------------------------------------------------------------------

	$(document).ready(function () {
		loadContactsFromDOM();

		// "Add Family Member" button
		$(document).on('click', '#kyc-add-contact-btn', function () {
			openModal(null);
		});

		// Edit button (delegated — table rows are dynamic)
		$(document).on('click', '.kyc-edit-contact', function () {
			const id = parseInt($(this).data('id'), 10);
			const contact = contactsData.find(function (c) { return parseInt(c.id, 10) === id; });
			if (contact) openModal(contact);
		});

		// Delete button
		$(document).on('click', '.kyc-delete-contact', function () {
			if (!confirm('Remove this family member from your profile?')) return;
			const $btn = $(this);
			const id   = parseInt($btn.data('id'), 10);

			$.post(kycContactsObj.ajaxurl, {
				action:     'kyc_delete_linked_contact',
				security:   kycContactsObj.nonce,
				contact_id: id,
			}, function (res) {
				if (res.success) {
					contactsData = contactsData.filter(function (c) { return parseInt(c.id, 10) !== id; });
					renderContacts(contactsData);
				} else {
					alert(res.data.message || 'Could not remove contact.');
				}
			});
		});

		// Close modal
		$(document).on('click', '#kyc-contact-modal-close, #kyc-contact-modal-overlay', function (e) {
			if (e.target === this) closeModal();
		});

		// Toggle custom relationship field
		$(document).on('change', '#kyc-contact-relationship', function () {
			if ($(this).val() === 'Other') {
				$('#kyc-custom-relationship-wrap').show().find('input').focus();
			} else {
				$('#kyc-custom-relationship-wrap').hide();
				$('#kyc-contact-custom-relationship').val('');
			}
		});

		// Form submit — save contact
		$(document).on('submit', '#kyc-contact-form', function (e) {
			e.preventDefault();
			const $form = $(this);
			const $btn  = $form.find('.kyc-contact-submit-btn');
			const $msg  = $('#kyc-contact-msg');

			$btn.prop('disabled', true).text('Saving…');

			$.post(kycContactsObj.ajaxurl, {
				action:              'kyc_save_linked_contact',
				security:            kycContactsObj.nonce,
				contact_id:          $('#kyc-contact-id').val(),
				linked_name:         $('#kyc-contact-linked-name').val(),
				linked_phone:        $('#kyc-contact-linked-phone').val(),
				relationship:        $('#kyc-contact-relationship').val(),
				custom_relationship: $('#kyc-contact-custom-relationship').val(),
				dob:                 $('#kyc-contact-dob').val(),
				anniversary:         $('#kyc-contact-anniversary').val(),
			}, function (res) {
				$btn.prop('disabled', false).text('Save');

				if (res.success) {
					const saved = res.data.contact;
					const existingIdx = contactsData.findIndex(function (c) { return parseInt(c.id, 10) === parseInt(saved.id, 10); });
					if (existingIdx >= 0) {
						contactsData[existingIdx] = saved;
					} else {
						contactsData.push(saved);
					}
					renderContacts(contactsData);
					closeModal();
				} else {
					$msg.text(res.data.message || 'Error saving contact.').show().css('color', '#d63638');
				}
			}).fail(function () {
				$btn.prop('disabled', false).text('Save');
				$msg.text('Network error. Please try again.').show().css('color', '#d63638');
			});
		});
	});

}(jQuery));
