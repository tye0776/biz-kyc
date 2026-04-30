/**
 * KYC Linked Contacts — Family & Reminders section
 * Moved to modules/family-graph/js/
 * Handles add, edit, delete of linked contacts via AJAX.
 */
(function ($) {
	'use strict';

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
			? '<span class="kyc-mutual-badge" title="Mutual link confirmed">🔗 Confirmed</span>'
			: '<span class="kyc-pending-badge" title="Linked, but they haven\'t registered yet">⏳ Pending</span>';
	}

	function renderContacts(contacts) {
		const $table = $('#kyc-contacts-table');
		const $empty = $('#kyc-contacts-empty');
		if (!contacts || contacts.length === 0) { $table.hide(); $empty.show(); return; }
		$empty.hide();
		const $tbody = $table.find('tbody').empty();
		$table.show();
		contacts.forEach(function (c) {
			$tbody.append(`
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
				</tr>`);
		});
	}

	function openModal(contact) {
		const c = contact || {};
		const relValue  = c.relationship || 'Wife';
		const isOther   = !RELATIONSHIPS.includes(relValue);
		const selectVal = isOther ? 'Other' : relValue;

		$('#kyc-contact-modal-title').text(contact ? 'Edit Family Member' : 'Add Family Member');
		$('#kyc-contact-id').val(c.id || '');
		$('#kyc-contact-linked-name').val(c.linked_name || '');
		$('#kyc-contact-linked-phone').val(c.linked_phone || '');
		$('#kyc-contact-dob').val(c.dob && c.dob !== '0000-00-00' ? c.dob : '');
		$('#kyc-contact-anniversary').val(c.anniversary && c.anniversary !== '0000-00-00' ? c.anniversary : '');
		$('#kyc-contact-relationship').replaceWith(buildRelationshipSelect(selectVal));

		if (isOther) { $('#kyc-custom-relationship-wrap').show(); $('#kyc-contact-custom-relationship').val(relValue); }
		else         { $('#kyc-custom-relationship-wrap').hide();  $('#kyc-contact-custom-relationship').val(''); }

		$('#kyc-contact-msg').text('').hide();
		$('#kyc-contact-modal-overlay').fadeIn(200);
	}

	function closeModal() { $('#kyc-contact-modal-overlay').fadeOut(150); }

	let contactsData = [];

	$(document).ready(function () {
		const raw = $('#kyc-contacts-root').data('contacts');
		contactsData = raw ? JSON.parse(raw) : [];
		renderContacts(contactsData);

		$(document).on('click', '#kyc-add-contact-btn', function () { openModal(null); });

		$(document).on('click', '.kyc-edit-contact', function () {
			const id = parseInt($(this).data('id'), 10);
			const c  = contactsData.find(function (x) { return parseInt(x.id, 10) === id; });
			if (c) openModal(c);
		});

		$(document).on('click', '.kyc-delete-contact', function () {
			if (!confirm('Remove this family member from your profile?')) return;
			const id = parseInt($(this).data('id'), 10);
			$.post(kycContactsObj.ajaxurl, { action: 'kyc_delete_linked_contact', security: kycContactsObj.nonce, contact_id: id },
				function (res) {
					if (res.success) { contactsData = contactsData.filter(function (c) { return parseInt(c.id, 10) !== id; }); renderContacts(contactsData); }
					else alert(res.data.message || 'Could not remove contact.');
				});
		});

		$(document).on('click', '#kyc-contact-modal-close, #kyc-contact-modal-overlay', function (e) { if (e.target === this) closeModal(); });

		$(document).on('change', '#kyc-contact-relationship', function () {
			if ($(this).val() === 'Other') $('#kyc-custom-relationship-wrap').show().find('input').focus();
			else { $('#kyc-custom-relationship-wrap').hide(); $('#kyc-contact-custom-relationship').val(''); }
		});

		$(document).on('submit', '#kyc-contact-form', function (e) {
			e.preventDefault();
			const $btn = $(this).find('.kyc-contact-submit-btn');
			const $msg = $('#kyc-contact-msg');
			$btn.prop('disabled', true).text('Saving…');
			$.post(kycContactsObj.ajaxurl, {
				action: 'kyc_save_linked_contact', security: kycContactsObj.nonce,
				contact_id: $('#kyc-contact-id').val(), linked_name: $('#kyc-contact-linked-name').val(),
				linked_phone: $('#kyc-contact-linked-phone').val(), relationship: $('#kyc-contact-relationship').val(),
				custom_relationship: $('#kyc-contact-custom-relationship').val(),
				dob: $('#kyc-contact-dob').val(), anniversary: $('#kyc-contact-anniversary').val(),
			}, function (res) {
				$btn.prop('disabled', false).text('Save');
				if (res.success) {
					const saved = res.data.contact;
					const idx = contactsData.findIndex(function (c) { return parseInt(c.id, 10) === parseInt(saved.id, 10); });
					if (idx >= 0) contactsData[idx] = saved; else contactsData.push(saved);
					renderContacts(contactsData); closeModal();
				} else {
					$msg.text(res.data.message || 'Error saving contact.').show().css('color', '#d63638');
				}
			}).fail(function () { $btn.prop('disabled', false).text('Save'); $msg.text('Network error.').show().css('color', '#d63638'); });
		});
	});

}(jQuery));
