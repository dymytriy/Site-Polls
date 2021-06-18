function site_polls_add_answer() {
	var next_index = jQuery('#site_poll_text_answers .site-polls-elem').length + 1;
	var next_id = next_index;

	var site_poll_next_answer_id = jQuery('#site_poll_next_answer_id');

	if (site_poll_next_answer_id.length > 0) {
		next_id = parseInt(site_poll_next_answer_id.val());

		jQuery('#site_poll_next_answer_id').val(next_id + 1);
	}

	var answers_list = jQuery('#site_poll_text_answers');

	if (answers_list && (answers_list.length > 0)) {
		answers_list.append('<p><span class="site-polls-elem">' + next_index + '. </span><input type="hidden" name="site_poll_answer_ids[' + next_id + ']" value="0"/><span><input type="text" size="50" name="site_poll_answers[' + next_id + ']"/></span><span class="site-polls-votes">Votes:</span><input type="text" size="5" name="site_poll_answer_votes[' + next_id + ']" value="0"/><a class="site-polls-delete" href="#" title="Delete" onclick="return site_polls_delete_answer(this);">Delete</a></p>');
	}

	return false;
}

function site_polls_delete_answer(source) {
	var elem_count = jQuery('#site_poll_text_answers .site-polls-elem').length;

	var parent = jQuery(source).parent();

	if (parent && (parent.length > 0) && (elem_count > 1)) {
		parent.remove();
	}
	else {
		var input_element = parent.find('input');

		if (input_element && (input_element.length > 0)) {
			parent.find('input[type="hidden"]').val('');
			parent.find('input[type="text"]').val('');
		}
	}

	var ids = jQuery('#site_poll_text_answers .site-polls-elem').each(function (index) {
		jQuery(this).text((index + 1) + '. ');
	});

	return false;
}

function site_polls_cancel_button(polls_admin_url) {
	location.href = polls_admin_url;

	return false;
}
