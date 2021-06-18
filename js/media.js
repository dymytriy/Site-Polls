(function ($) {

	$(document).ready(function () {
		$('.site-polls-media-insert').click(function () {
			$('.site-polls-popup').removeClass('site-polls-inactive');

			var index = 0;
			var options = '';

			for (index = 0; index < site_polls_media_ids.length; index++) {
				if (typeof site_polls_media_ids[index] !== 'undefined') {
					options += '<option value="' + site_polls_media_ids[index]['id'] + '">' + site_polls_media_ids[index]['title'] + '</option>';
				}
			}

			if (options && (options.length > 0)) {
				$('.site-polls-select').html(options);
			} else {
				$('.site-polls-select').html('<option value="0">---</option>');
			}
		});

		$('.site-polls-button-insert').click(function (event) {
			if (!$('.site-polls-popup').hasClass('site-polls-inactive')) {
				if ($(this).closest('.site-polls-popup-content').find('.site-polls-select').length) {
					var object_id = $(this).closest('.site-polls-popup-content').find('.site-polls-select').val();

					var win = window.dialogArguments || opener || parent || top;

					if (typeof win !== 'undefined') {
						win.send_to_editor('[site_poll id="' + object_id + '"]');
					}

					$('.site-polls-popup').addClass('site-polls-inactive');
				}
			}
		});

		$('.site-polls-popup').click(function (event) {
			if (event.target === this) {
				if (!$('.site-polls-popup').hasClass('site-polls-inactive')) {
					$('.site-polls-popup').addClass('site-polls-inactive');
				}
			}
		});

		$('.site-polls-button-cancel').click(function (event) {
			if (!$('.site-polls-popup').hasClass('site-polls-inactive')) {
				$('.site-polls-popup').addClass('site-polls-inactive');
			}
		});

		$('.site-polls-popup-close a').click(function (event) {
			event.preventDefault();

			if (!$('.site-polls-popup').hasClass('site-polls-inactive')) {
				$('.site-polls-popup').addClass('site-polls-inactive');
			}
		});

		$(document).on('keyup.site-polls', function (event) {
			if (event.keyCode === 27) {
				if (!$('.site-polls-popup').hasClass('site-polls-inactive')) {
					$('.site-polls-popup').addClass('site-polls-inactive');
				}
			}
		});
	});
})(jQuery);
