function site_poll_get_cookie(name) {
	var cookies = document.cookie;

	if (cookies && (cookies.length > 0)) {
		var items = cookies.split(';');

		for (var index = 0; index < items.length; index++) {
			if (items[index].length > 0) {
				var pair = items[index].trim();

				if (pair && (pair.length > 0)) {
					var data = pair.split('=');

					if (data && (data.length >= 2)) {
						if (data[0] == name) {
							return data[1];
						}
					}
				}
			}
		}
	}

	return '';
}

function site_poll_process_result(poll_id, answers) {
	var index = 0;
	var total_votes = 0;

	for (index = 0; index < answers.length; ++index) {
		total_votes += parseInt(answers[index]['votes']);
	}

	for (index = 0; index < answers.length; ++index) {
		var answer_id = answers[index]['answer_id'];

		if (answer_id > 0) {
			var percentage = 0;

			if (total_votes > 0) {
				percentage = Math.round((parseFloat(answers[index]['votes']) / (total_votes / 100.0)) * 100) / 100;
			}

			var poll_bar = jQuery("#site_poll_" + poll_id + " #site_poll_bar_" + answer_id);

			if (poll_bar && (poll_bar.length > 0)) {
				var new_html = '';

				var votes = parseInt(answers[index]['votes']);

				if (votes == 1) {
					new_html += '<div class="site-poll-info">' + percentage + '%, ' + votes + ' vote</div>';
				}
				else {
					new_html += '<div class="site-poll-info">' + percentage + '%, ' + votes + ' votes</div>';
				}

				if (percentage <= 0) {
					new_html += '<div class="site-poll-bar-bg" style="width:3px;"></div>';
				}
				else {
					new_html += '<div class="site-poll-bar-bg" style="width:' + percentage + '%;"></div>';
				}

				poll_bar.html(new_html);
			}
		}
	}

	jQuery("#site_poll_" + poll_id + " .site-poll-total").text('Total votes: ' + total_votes);
}

function site_polls_send_vote(sender) {
	var has_answer = false;
	var answer_ids = [];
	var poll_id = 0;
	var lifetime = 0;
	var max_answers = 0;
	var admin_ajax = '';

	var poll_container = jQuery(sender).closest('.site-poll-type-standard');

	if (poll_container.length > 0) {
		var poll_id_holder = poll_container.find('input[id^="site_poll_id"]');
		var lifetime_holder = poll_container.find('input[id^="site_poll_lifetime"]');
		var max_answers_holder = poll_container.find('input[id^="site_poll_maxanswers"]');
		var admin_ajax_holder = poll_container.find('input[id^="site_poll_adminajax"]');

		if (poll_id_holder.length > 0) {
			poll_id = poll_id_holder.val();
		}

		if (lifetime_holder.length > 0) {
			lifetime = lifetime_holder.val();
		}

		if (max_answers_holder.length > 0) {
			max_answers = max_answers_holder.val();
		}

		if (admin_ajax_holder.length > 0) {
			admin_ajax = admin_ajax_holder.val();
		}
	}

	if (!poll_id) {
		return;
	}

	jQuery("#site_poll_" + poll_id + " .site-poll-answers input").each(function (index) {
		if (jQuery(this).prop('checked')) {
			has_answer = true;

			var elem_id = jQuery(this).attr('id');

			if (elem_id && (elem_id.length > 0)) {
				answer_ids.push(elem_id.replace('site_poll_answer_', ''));
			}
		}
	});

	if (!has_answer) {
		alert('Please select at least one answer!');

		return;
	}

	var data = {
		'action': 'site_polls_vote',
		'site_poll_id': poll_id,
		'site_poll_answer_ids': answer_ids
	};

	jQuery.post(admin_ajax, data, function (response) {
		if (response) {
			var result = jQuery.parseJSON(response);

			if ((typeof result['status'] !== 'undefined') && (result['status'].indexOf('success') >= 0)) {
				alert('Your vote has been successfully accepted!');

				var answers = result['answers'];

				if (answers instanceof Array) {
					site_poll_process_result(poll_id, answers);
				}

				if (lifetime > 0) {
					var currentDate = new Date();
					currentDate.setTime(currentDate.getTime() + lifetime * 1000);
					var expires = "expires=" + currentDate.toGMTString();
					document.cookie = "SITE_POLL_" + poll_id + "_VOTED=YES; " + expires + "; path=/";

					jQuery("#site_poll_" + poll_id + "_view_question").hide();

					jQuery("#site_poll_" + poll_id + " .site-poll-content").hide();
					jQuery("#site_poll_" + poll_id + " .site-poll-result").show();
				}
				else {
					jQuery("#site_poll_" + poll_id + " .site-poll-content").hide();
					jQuery("#site_poll_" + poll_id + " .site-poll-result").show();

					if (max_answers == 1) {
						poll_container.find('.site-poll-content input[type="radio"]').prop('checked', false);
					}
					else {
						poll_container.find('.site-poll-content input[type="checkbox"]').prop('checked', false);

						if (max_answers > 1) {
							poll_container.find('.site-poll-content input[type="checkbox"]').prop('disabled', false);
						}
					}
				}
			}
			else {
				alert('Failed to save results. Server is not responding. Please try again later.');
			}
		}
	});
}

function site_polls_view_result(sender) {
	var poll_id = 0;

	var poll_container = jQuery(sender).closest('.site-poll-type-standard');

	if (poll_container.length > 0) {
		var poll_id_holder = poll_container.find('input[id^="site_poll_id"]');

		if (poll_id_holder.length > 0) {
			poll_id = poll_id_holder.val();

			jQuery("#site_poll_" + poll_id + " .site-poll-content").hide();
			jQuery("#site_poll_" + poll_id + " .site-poll-result").show();
		}
	}

	return false;
}

function site_polls_view_question(sender) {
	var poll_id = 0;

	var poll_container = jQuery(sender).closest('.site-poll-type-standard');

	if (poll_container.length > 0) {
		var poll_id_holder = poll_container.find('input[id^="site_poll_id"]');

		if (poll_id_holder.length > 0) {
			poll_id = poll_id_holder.val();

			jQuery("#site_poll_" + poll_id + " .site-poll-content").show();
			jQuery("#site_poll_" + poll_id + " .site-poll-result").hide();
		}
	}

	return false;
}

jQuery(document).ready(function ($) {
	if ($('.site-poll-type-standard').length) {
		var request_ids = [];
		var admin_ajax = '';

		$('.site-poll-type-standard').each(function (index) {
			var poll_id = 0;

			var poll_container = $(this);

			if (poll_container.length > 0) {
				var poll_id_holder = poll_container.find('input[id^="site_poll_id"]');

				if (poll_id_holder.length > 0) {
					poll_id = poll_id_holder.val();
				}

				if (admin_ajax.length <= 0) {
					var admin_ajax_holder = poll_container.find('input[id^="site_poll_adminajax"]');

					if (admin_ajax_holder.length > 0) {
						admin_ajax = admin_ajax_holder.val();
					}
				}

				if (poll_id) {
					request_ids.push(poll_id);
				}
			}
		});

		if (request_ids && request_ids.length > 0) {
			var data = {
				'action': 'site_polls_results',
				'site_poll_check_ids': request_ids,
			};

			$.post(admin_ajax, data, function (response) {
				if (response) {
					var result = $.parseJSON(response);

					if ((typeof result['status'] !== 'undefined') &&
						(result['status'].indexOf('success') >= 0) &&
						(typeof result['polls'] !== 'undefined') &&
						(result['polls'] instanceof Array)) {
						for (var index = 0; index < result['polls'].length; ++index) {
							if (typeof result['polls'][index] !== 'undefined') {
								var target_poll_id = result['polls'][index]['poll_id'];
								var target_answers = result['polls'][index]['answers'];

								var lifetime = 0;
								var max_answers = 0;

								site_poll_process_result(target_poll_id, target_answers);

								var poll_container = $("#site_poll_" + target_poll_id + " .site-poll-container");

								if (poll_container.length > 0) {
									var lifetime = 0;
									var max_answers = 0;
									var already_voted = false;

									var lifetime_holder = poll_container.find('input[id^="site_poll_lifetime"]');
									var max_answers_holder = poll_container.find('input[id^="site_poll_maxanswers"]');

									if (lifetime_holder.length > 0) {
										lifetime = lifetime_holder.val();
									}

									if (max_answers_holder.length > 0) {
										max_answers = max_answers_holder.val();
									}

									if (lifetime > 0) {
										var cookie_data = site_poll_get_cookie("SITE_POLL_" + target_poll_id + "_VOTED");

										if (cookie_data && (cookie_data.length > 0) && (cookie_data.toLowerCase() == 'yes')) {
											already_voted = true;
										}
									}

									if (already_voted) {
										$("#site_poll_" + target_poll_id + "_view_question").hide();

										$("#site_poll_" + target_poll_id + " .site-poll-content").hide();
										$("#site_poll_" + target_poll_id + " .site-poll-result").show();
									}
									else {
										$("#site_poll_" + target_poll_id + "_view_question").show();

										$("#site_poll_" + target_poll_id + " .site-poll-content").show();
										$("#site_poll_" + target_poll_id + " .site-poll-result").hide();
									}

									if (max_answers == 1) {
										poll_container.find('.site-poll-content input[type="radio"]').prop('checked', false);
									}
									else {
										poll_container.find('.site-poll-content input[type="checkbox"]').prop('checked', false);

										if (max_answers > 1) {
											poll_container.find('.site-poll-content input[type="checkbox"]').prop('disabled', false);
										}
									}
								}
							}
						}
					}
				}
			});
		}

		$('.site-poll-type-standard .site-poll-content input[type="checkbox"]').change(function (event) {
			var max_answers = 0;

			var poll_container = $(this).closest('.site-poll-type-standard');

			if (poll_container.length > 0) {
				var max_answers_holder = poll_container.find('input[id^="site_poll_maxanswers"]');

				if (max_answers_holder.length > 0) {
					max_answers = max_answers_holder.val();

					if (max_answers > 1) {
						var voted_count = poll_container.find('.site-poll-content input[type="checkbox"]:checked').length;

						poll_container.find('.site-poll-content input[type="checkbox"]').removeAttr('disabled');

						if (voted_count >= max_answers) {
							poll_container.find('.site-poll-content input[type="checkbox"]:not(:checked)').prop('disabled', true);
						}
					}
				}
			}
		});
	}
});
