<?php

/*
	Plugin Name: Site Polls
	Plugin URI: https://github.com/dymytriy/site-polls
	Description: This plugin allows to add interactive polls on your WordPress web site. You can use shortcuts to place desired questions in a post or in any widget.
	Author: Dymytriy
	Author URI: https://github.com/dymytriy
	Text Domain: site-polls
	Version: 1.0
	License: GNU General Public License v2 or later
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

define("SITE_POLLS_VERSION", "1.0");

define("SITE_POLLS_ADMIN_PAGE", "site_polls");
define("SITE_POLLS_SETTINGS_PAGE", "site_polls_settings");

define("SITE_POLLS_TYPE_STANDARD", 0);
define("SITE_POLLS_TYPE_IMAGE_LIST", 1);
define("SITE_POLLS_TYPE_IMAGE_GRID", 2);

// Add actions that are required by our plugin

add_action('init', 'site_polls_init');
add_action('widgets_init', 'site_polls_widgets_init');
add_action('admin_menu', 'site_polls_register_pages');
add_action('admin_enqueue_scripts', 'site_polls_admin_scripts');
add_action('wp_enqueue_media', 'site_polls_enqueue_media');
add_action('wp_enqueue_scripts', 'site_polls_enqueue_scripts');
add_filter('widget_text', 'site_polls_do_shortcode');
add_action('media_buttons', 'site_polls_media_buttons');

add_action('wp_ajax_site_polls_vote', 'site_polls_vote');
add_action('wp_ajax_nopriv_site_polls_vote', 'site_polls_vote');
add_action('wp_ajax_site_polls_results', 'site_polls_results');
add_action('wp_ajax_nopriv_site_polls_results', 'site_polls_results');

register_activation_hook(__FILE__, 'site_polls_activate');
register_uninstall_hook(__FILE__, 'site_polls_uninstall');

if (is_multisite()) {
	add_action('wpmu_new_blog', 'site_polls_wpmu_new_blog', 10, 6);
	add_filter('wpmu_drop_tables', 'site_polls_wpmu_drop_tables', 10, 1);
}

// Classes

class SitePollsWidget extends WP_Widget {
	function __construct() {
		parent::__construct('site-polls-widget', 'Site Polls', array('description' => __('Widget that displays AJAX polls.', 'site-polls')));
	}

	function widget($args, $instance) {
		// Get poll
		if (empty($instance['poll_id'])) {
			return;
		}

		$poll_id = intval($instance['poll_id']);

		$instance['title'] = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);

		echo $args['before_widget'];

		if (!empty($instance['title'])) {
			echo $args['before_title'] . $instance['title'] . $args['after_title'];
		}

		$poll = site_polls_get($poll_id);

		if (!empty($poll) && !empty($poll['poll'])) {
			echo site_polls_render($poll);
		} else {
			echo '<p><b>' . __('Error: no poll found with the specified ID', 'site-polls') . '</b></p>';
		}

		echo $args['after_widget'];
	}

	function update($new_instance, $old_instance) {
		$instance = array();

		if (!empty($new_instance['title'])) {
			$instance['title'] = sanitize_text_field($new_instance['title']);
		}

		if (!empty($new_instance['poll_id'])) {
			$instance['poll_id'] = (int)$new_instance['poll_id'];
		}

		return $instance;
	}

	function form($instance) {
		$title = isset( $instance['title'] ) ? esc_attr($instance['title']) : '';
		$poll_id = isset( $instance['poll_id'] ) ? $instance['poll_id'] : '';

		$polls = site_polls_get_all();

		if ($polls && count($polls) > 0) {
	?>
	<p>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'site-polls') ?></label>
		<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" />
	</p>
	<p>
		<?php _e('Please choose desired poll:', 'site-polls') ?>
	</p>
	<p>
	<select style="width:100%;" id="<?php echo $this->get_field_id('poll_id'); ?>" name="<?php echo $this->get_field_name('poll_id'); ?>">
		<option value="0"><?php _e('&mdash; Select &mdash;', 'site-polls') ?></option>
	<?php
			foreach ($polls as $item) {
				$item_title = '';

				if (!empty($item['title'])) {
					$item_title = __('ID #', 'site-polls') . $item['id'] . ': ' . esc_html(stripslashes($item['title']));
				} else {
					$item_title = __('ID #', 'site-polls') . $item['id'] . ': ' . __('(No title)', 'site-polls');
				}

				echo '<option value="' . $item['id'] . '"'
					. selected($poll_id, $item['id'], false)
					. '>' . $item_title . '</option>';
			}
	?>
	</select>
	</p>
<?php
		} else {
			echo '<p>'. sprintf(__('No polls have been created yet. <a href="%s">Create some</a>.', 'site-polls'), admin_url('admin.php?page=' . SITE_POLLS_ADMIN_PAGE)) . '</p>';
		}
	}
}

// Functions

function site_polls_checked($value_name) {
	if (!empty($_POST[$value_name])) {
		$post_value = trim(strtolower($_POST[$value_name]));

		if (($post_value == 'on') ||
			($post_value == 'true') ||
			($post_value == 'yes')) {
			return true;
		}
	}

	return false;
}

function site_polls_admin_scripts($name) {
	// Register plugin stylesheet file
	if ((stripos($name, SITE_POLLS_ADMIN_PAGE) !== FALSE) ||
		(stripos($name, SITE_POLLS_SETTINGS_PAGE) !== FALSE)) {
		wp_register_style('site_polls_admin_style', plugins_url('css/admin-style.css', __FILE__), array(), SITE_POLLS_VERSION);
		wp_register_script('site_polls_admin_script', plugins_url('js/management.js', __FILE__), array('jquery'), SITE_POLLS_VERSION);

    	wp_enqueue_style('site_polls_admin_style');
    	wp_enqueue_script('site_polls_admin_script');
    }
}

function site_polls_media_buttons($editor_id = 'content') {
	printf( '<button type="button" class="button site-polls-media-insert" data-editor="%s">%s</button>',
		esc_attr( $editor_id ),
		__( 'Add Poll' )
	);
}

function site_polls_media_inline() {
	$polls_js_data = array();

	$polls = site_polls_get_all();

	foreach ($polls as $item) {
		$new_item = array();

		$new_item['id'] = $item['id'];

		if (!empty($item['title'])) {
			$new_item['title'] = __('ID #', 'site-polls') . $item['id'] . ': ' . esc_html(stripslashes($item['title']));
		} else {
			$new_item['title'] = __('ID #', 'site-polls') . $item['id'] . ': ' . __('(No title)', 'site-polls');
		}

		$polls_js_data[] = $new_item;
	}

?>
<div class="site-polls-popup site-polls-inactive">
	<div class="site-polls-popup-content">
		<div class="site-polls-popup-close"><a href="#" class="dashicons dashicons-no"></a></div>
		<div class="site-polls-popup-title">Insert poll</div>
		<?php if ($polls && count($polls) > 0) { ?>
		<div class="site-polls-popup-description">Please select your poll from the list below</div>
		<select class="site-polls-select"><option value="0">---</option></select>
		<div class="site-polls-popup-button">
			<button class="site-polls-button-insert button button-primary">Insert shortcode</button>
			<button class="site-polls-button-cancel button">Cancel</button>
		</div>
		<?php } else { ?>
		<div class="site-polls-popup-description">Sorry, no polls have been created yet.</div>
		<div class="site-polls-popup-button">
			<button class="site-polls-button-cancel button">Cancel</button>
		</div>
		<?php } ?>
	</div>
</div>
<script type="text/javascript">
var site_polls_media_ids = <?php echo json_encode($polls_js_data) ?>;
</script>
<?php
}

function site_polls_enqueue_media() {
	wp_register_script('site_polls_media_script', plugins_url('js/media.js', __FILE__), array('jquery'), SITE_POLLS_VERSION);
	wp_enqueue_script('site_polls_media_script');

	wp_register_style('site_polls_media_style', plugins_url('css/media.css', __FILE__), array('dashicons'), SITE_POLLS_VERSION);
	wp_enqueue_style('site_polls_media_style');

	add_action('admin_footer', 'site_polls_media_inline');
}

function site_polls_enqueue_scripts() {
	wp_register_style('site_polls_style', plugins_url('/css/polls.css' , __FILE__), array(), SITE_POLLS_VERSION);
	wp_register_script('site_polls_script', plugins_url('/js/polls.js' , __FILE__), array('jquery'), SITE_POLLS_VERSION);

	wp_enqueue_style('site_polls_style');
	wp_enqueue_script('site_polls_script');
}

function site_polls_create_db() {
	// Create required database structure

	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	$wpdb->query('
		CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'polls` (
		  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `type` bigint(20) UNSIGNED NOT NULL,
		  `title` mediumtext NOT NULL,
		  `max_answers` int(11) NOT NULL,
		  `lifetime` bigint(20) UNSIGNED NOT NULL,
		  `config` mediumtext NOT NULL,
		  `style` mediumtext NOT NULL,
		  PRIMARY KEY (`id`)
		) DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
	');

	$wpdb->query('
		CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'polls_answers` (
		  `answer_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		  `poll_id` bigint(20) UNSIGNED NOT NULL,
		  `type` bigint(20) UNSIGNED NOT NULL,
		  `title` mediumtext NOT NULL,
		  `image` mediumtext NOT NULL,
		  `votes` bigint(20) UNSIGNED NOT NULL,
		  PRIMARY KEY (`answer_id`),
		  KEY `poll_id` (`poll_id`)
		) DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
	');
}

function site_polls_activate($networkwide) {
	global $wpdb;

	if (is_multisite()) {
		if ($networkwide) {
			$old_blog = $wpdb->blogid;

			$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");

			if ($blogids && (!empty($blogids))) {
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);

					site_polls_create_db();
				}
			}

			switch_to_blog($old_blog);

			return;
		}
	}

	site_polls_create_db();
}

function site_polls_remove_data() {
	// Drop all tables in case if user wants to remove all information
	if (get_option('site_polls_remove_db', false)) {
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'site_';

		$wpdb->query('DROP TABLE IF EXISTS `' . $table_prefix . 'polls`;');
		$wpdb->query('DROP TABLE IF EXISTS `' . $table_prefix . 'polls_answers`;');

		delete_option('site_polls_version');
		delete_option('site_polls_remove_db');
	}
}

function site_polls_uninstall() {
	global $wpdb;

	if (is_multisite()) {
		$old_blog = $wpdb->blogid;

		$blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");

		if ($blogids && (!empty($blogids))) {
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);

				site_polls_remove_data();
			}
		}

		switch_to_blog($old_blog);

		return;
	}

	site_polls_remove_data();
}

function site_polls_wpmu_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {
	global $wpdb;

	if (is_plugin_active_for_network(basename(dirname(__FILE__)) . '/' . basename(__FILE__))) {
		$old_blog = $wpdb->blogid;

		switch_to_blog($blog_id);

		site_polls_create_db();

		switch_to_blog($old_blog);
	}
}

function site_polls_wpmu_drop_tables($tables) {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	$tables[] = $table_prefix . 'polls';
	$tables[] = $table_prefix . 'polls_answers';

	return $tables;
}

function site_polls_init() {
	add_shortcode('site_poll', 'site_polls_show');
}

function site_polls_widgets_init() {
	register_widget('SitePollsWidget');
}

function site_polls_vote() {
	$result = array();

	if (	!empty($_POST['site_poll_id']) &&
			!empty($_POST['site_poll_answer_ids']) &&
			is_array($_POST['site_poll_answer_ids'])) {
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'site_';

		foreach ($_POST['site_poll_answer_ids'] as $answer_id) {
			$wpdb->query('
				UPDATE `' . $table_prefix . 'polls_answers`
				SET `votes` = (votes+1)
				WHERE `poll_id` = ' . intval($_POST['site_poll_id']) . ' AND `answer_id` = ' . intval($answer_id) . ';
			');
		}

		// Get data from the database
		$result['status'] = 'success';

		$result['answers'] = $wpdb->get_results('
			SELECT votes, answer_id FROM `' . $table_prefix . 'polls_answers`
			WHERE `poll_id` = ' . intval($_POST['site_poll_id']) . '
			ORDER BY answer_id ASC;
		', ARRAY_A);

		echo json_encode($result);

		exit;
	}

	$result['status'] = 'error';

	echo json_encode($result);

	exit;
}

function site_polls_results() {
	$result = array();

	if (	!empty($_POST['site_poll_check_ids']) &&
			is_array($_POST['site_poll_check_ids'])) {
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'site_';

		// Get data from the database
		$result['status'] = 'success';

		$polls = array();

		foreach ($_POST['site_poll_check_ids'] as $poll_id) {
			$new_item = array();

			$new_item['poll_id'] = $poll_id;

			$new_item['answers'] = $wpdb->get_results('
				SELECT votes, answer_id FROM `' . $table_prefix . 'polls_answers`
				WHERE `poll_id` = ' . intval($poll_id) . '
				ORDER BY answer_id ASC;
			', ARRAY_A);

			$polls[] = $new_item;
		}

		$result['polls'] = $polls;

		echo json_encode($result);

		exit;
	}

	$result['status'] = 'error';

	echo json_encode($result);

	exit;
}

function site_polls_do_shortcode($text) {
	if (stripos($text, 'site_poll') !== FALSE) {
		return do_shortcode($text);
	}

	return $text;
}

function site_polls_show($atts, $content = null) {
	extract(shortcode_atts(array('id' => '0'), $atts));

	if (!empty($id)) {
		$poll = site_polls_get($id);

		if (!empty($poll) && !empty($poll['poll'])) {
			return site_polls_render($poll);
		}
	}

	return '<p><b>' . __('Error: no poll found with the specified ID', 'site-polls') . '</b></p>';
}

function site_polls_unserialize_poll(&$poll) {
	if (!empty($poll['config'])) {
		$config = unserialize($poll['config']);

		if ((!empty($config)) && is_array($config)) {
			$poll['config'] = $config;
		} else {
			$poll['config'] = null;
		}
	} else {
		$poll['config'] = null;
	}

	if (!empty($poll['style'])) {
		$style = unserialize($poll['style']);

		if ((!empty($style)) && is_array($style)) {
			$poll['style'] = $style;
		} else {
			$poll['style'] = null;
		}
	} else {
		$poll['style'] = null;
	}
}

function site_polls_get($id) {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	$poll_base = $wpdb->get_row('SELECT * FROM `' . $table_prefix . 'polls` WHERE id = ' . intval($id) . ';', ARRAY_A);

	if (!empty($poll_base)) {
		$poll_answers = $wpdb->get_results('
			SELECT * FROM `' . $table_prefix . 'polls_answers`
			WHERE poll_id = ' . intval($id) . '
			ORDER BY answer_id ASC;', ARRAY_A);

		$poll = array();

		site_polls_unserialize_poll($poll_base);

		$poll['poll'] = $poll_base;
		$poll['answers'] = $poll_answers;

		return $poll;
	}

	return null;
}

function site_polls_get_range($current_page, $items_per_page) {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	$polls = $wpdb->get_results('
		SELECT * FROM `' . $table_prefix . 'polls`
		ORDER BY id ASC
		LIMIT ' . $current_page * $items_per_page.', ' . $items_per_page . ';', ARRAY_A);

	if (!empty($polls) && is_array($polls)) {
		foreach ($polls as $poll_key => $poll) {
			site_polls_unserialize_poll($polls[$poll_key]);
		}
	}

	return $polls;
}

function site_polls_get_all() {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	$polls = $wpdb->get_results('
		SELECT * FROM `' . $table_prefix . 'polls` ORDER BY id ASC;', ARRAY_A);

	if (!empty($polls) && is_array($polls)) {
		foreach ($polls as $poll_key => $poll) {
			site_polls_unserialize_poll($polls[$poll_key]);
		}
	}

	return $polls;
}

function site_polls_get_all_count() {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	return $wpdb->get_var('SELECT COUNT(id) FROM `' . $table_prefix . 'polls`;');
}

function site_polls_render($poll) {
	$result = '';

	$theme = "blue";

	if (!empty($poll['poll']['style']['theme'])) {
		$theme = $poll['poll']['style']['theme'];
	}

	$poll_type = intval($poll['poll']['type']);
	$max_answers = intval($poll['poll']['max_answers']);
	$lifetime = intval($poll['poll']['lifetime']);

	if ($poll_type == SITE_POLLS_TYPE_STANDARD) {
		$already_voted = false;

		if (!empty($poll['poll']['lifetime']) && (intval($poll['poll']['lifetime']) > 0)) {
			if (!empty($_COOKIE['SITE_POLL_' . $poll['poll']['id'] . '_VOTED'])) {
				$already_voted = trim(strtolower($_COOKIE['SITE_POLL_' . $poll['poll']['id'] . '_VOTED'])) == 'yes';
			}
		}

		$result .= '
<div id="site_poll_' . $poll['poll']['id'] . '" class="site-poll site-poll-' . $poll['poll']['id'] . '">
	<div class="site-poll-container site-poll-type-standard site-poll-theme-' . $theme . '">
		<input type="hidden" id="site_poll_id_' . $poll['poll']['id'] . '" value="' . $poll['poll']['id'] . '"/>
		<input type="hidden" id="site_poll_lifetime_' . $poll['poll']['id'] . '" value="' . $lifetime . '"/>
		<input type="hidden" id="site_poll_maxanswers_' . $poll['poll']['id'] . '" value="' . $max_answers . '"/>
		<input type="hidden" id="site_poll_adminajax_' . $poll['poll']['id'] . '" value="' . admin_url('admin-ajax.php') . '"/>';

		if (!empty($poll['poll']['title'])) {
			$result .= '
		<div class="site-poll-title">
			<p>' . esc_html(stripslashes($poll['poll']['title'])) . '</p>
		</div>';
		}

		$result .= '
		<div class="site-poll-content"' . ($already_voted ? ' style="display:none;"' : '') . '>
			<div class="site-poll-answers">
';

		if (!empty($poll['answers']) && is_array($poll['answers'])) {
			foreach ($poll['answers'] as $answer) {
				if ($max_answers == 1) {
					$result .= '
				<p><label><input type="radio" id="site_poll_answer_' . $answer['answer_id'] . '" name="site_poll_answer"/>&nbsp;&nbsp;' . esc_html(stripslashes($answer['title'])) . '</label></p>
';
				} else {
					$result .= '
				<p><label><input type="checkbox" id="site_poll_answer_' . $answer['answer_id'] . '"/>&nbsp;&nbsp;' . stripslashes(($answer['title'])) . '</label></p>
';
				}
			}
		}

		$result .= '
			</div>';

		if (count($poll['answers']) > 0) {
			$result .= '
			<div class="site-poll-actions">
				<p><button class="site-polls-button" onclick="site_polls_send_vote(this);">' . __('Vote!', 'site-polls') . '</button></p>
			</div>';
		}

		$result .= '
			<div class="site-poll-other">
				<p><a href="#" title="' . __('View results', 'site-polls') . '" onclick="return site_polls_view_result(this);">' . __('View results', 'site-polls') . '</a></p>
			</div>
		</div>';


	$result .= '
		<div class="site-poll-result"' . ($already_voted ? '': ' style="display:none;"') . '>
			<div class="site-poll-data">
';

	// Get total number of votes
	$total_votes = 0;

	if (!empty($poll['answers']) && is_array($poll['answers'])) {
		foreach ($poll['answers'] as $answer) {
			$total_votes += $answer['votes'];
		}

		// Show resutls
		foreach ($poll['answers'] as $answer) {
			$percentage = 0;

			if ($total_votes > 0) {
				$percentage = round($answer['votes'] / ($total_votes / 100.0), 2);
			}

			$result .= '
				<div class="site-poll-line">
					<label class="site-poll-label"><b>' . esc_html(stripslashes($answer['title'])) . '</b></label>
					<div id="site_poll_bar_' . $answer['answer_id'] . '" class="site-poll-bar">
';

			if ($answer['votes'] == 1) {
				$result .= '
						<div class="site-poll-info">' . $percentage . '%, ' . $answer['votes'] . ' ' . __('vote', 'site-polls') . '</div>
';
			} else {
				$result .= '
						<div class="site-poll-info">' . $percentage . '%, ' . $answer['votes'] . ' ' . __('votes', 'site-polls') . '</div>
';
			}

			if ($percentage <= 0) {
				$result .= '
						<div class="site-poll-bar-bg" style="width:3px;"></div>
';
			} else {
				$result .= '
						<div class="site-poll-bar-bg" style="width:' . $percentage . '%;"></div>
';
			}

			$result .= '
					</div>
				</div>
';
		}
	}

	$result .= '
			</div>
			<div class="site-poll-total">' . sprintf(__('Total votes: %d', 'site-polls'), $total_votes) . '</div>
			<div class="site-poll-other">
				<p><a href="#" id="site_poll_' . $poll['poll']['id'] . '_view_question"' . ($already_voted ? ' style="display:none;"' : '') . ' title="' . __('View questions', 'site-polls') . '" onclick="return site_polls_view_question(this);">' . __('View questions', 'site-polls') . '</a></p>
			</div>
		</div>
	</div>
</div>';
	} else {
		$result .= '<p><b>' . __('Error: unsupported poll type', 'site-polls') . '</b></p>';
	}

	return $result;
}

function site_polls_delete_poll($id) {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	$wpdb->query('DELETE FROM `' . $table_prefix . 'polls` WHERE id = ' . intval($id). ';');
	$wpdb->query('DELETE FROM `' . $table_prefix . 'polls_answers` WHERE poll_id = ' . intval($id). ';');
}

function site_polls_edit_poll() {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	if (	!empty($_POST['site_poll_id']) &&
			!empty($_POST['site_poll_votes_type']) &&
			!empty($_POST['site_poll_answers']) &&
			is_array($_POST['site_poll_answers']) &&
			(count($_POST['site_poll_answers']) > 0)
		) {
		$max_answers = 0;
		$lifetime = 0;

		if ($_POST['site_poll_votes_type'] === 'one') {
			$max_answers = 1;
		} else if ($_POST['site_poll_votes_type'] === 'any') {
			$max_answers = 0;
		} else if ($_POST['site_poll_votes_type'] === 'number') {
			$max_answers = intval($_POST['site_polls_max_votes']);
		}

		$lifetime = intval($_POST['site_poll_revote_time']);

		// Now we need to check if we has to delete some answers
		$answers = $wpdb->get_results('
			SELECT * FROM `' . $table_prefix . 'polls_answers`
			WHERE `poll_id` = ' . intval($_POST['site_poll_id']) . ';
		', ARRAY_A);

		if (!empty($answers) && is_array($answers)) {
			foreach ($answers as $answer) {
				$answer_id = $answer['answer_id'];

				if (!in_array($answer_id, $_POST['site_poll_answer_ids'])) {
					$wpdb->query('DELETE FROM `' . $table_prefix . 'polls_answers` WHERE answer_id = ' . intval($answer_id));
				}
			}
		}

		// Update data in the database
		$style = array();
		$style['theme'] = esc_sql($_POST['site_poll_theme']);

		$wpdb->query('
			UPDATE `' . $table_prefix . 'polls`
			SET `title` = \'' . esc_sql($_POST['site_poll_title']) . '\', `max_answers` = ' . $max_answers . ', `lifetime` = ' . $lifetime . ', `style` = \'' . serialize($style) . '\'
			WHERE `id` = ' . intval($_POST['site_poll_id']) . ';
		');

		$new_poll_id = intval($_POST['site_poll_id']);

		foreach ($_POST['site_poll_answers'] as $key => $answer) {
			if (!empty($answer)) {
				$votes = 0;

				if (!empty($_POST['site_poll_answer_votes'][$key])) {
					$votes = intval($_POST['site_poll_answer_votes'][$key]);
				}

				if (!empty($_POST['site_poll_answer_ids'][$key]) && (intval($_POST['site_poll_answer_ids'][$key]) > 0)) {
					$wpdb->query('
						UPDATE `' . $table_prefix . 'polls_answers`
						SET `title` = \'' . esc_sql($answer) . '\', votes = ' . $votes. '
						WHERE `poll_id` = ' . intval($_POST['site_poll_id']) . ' AND `answer_id` = ' . intval($_POST['site_poll_answer_ids'][$key]) . ';
					');
				} else {
					$wpdb->query('
						INSERT INTO `' . $table_prefix . 'polls_answers`
						(`poll_id`, `type`, `title`, `image`, `votes`)
						VALUES
						(' . $new_poll_id . ', 0, \'' . esc_sql($answer) . '\', \'\', ' . $votes. ');
					');
				}
			}
		}
	}
}

function site_polls_insert_poll() {
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'site_';

	if (	!empty($_POST['site_poll_votes_type']) &&
			!empty($_POST['site_poll_answers']) &&
			is_array($_POST['site_poll_answers']) &&
			(count($_POST['site_poll_answers']) > 0)
		) {
		$max_answers = 0;
		$lifetime = 0;

		if ($_POST['site_poll_votes_type'] === 'one') {
			$max_answers = 1;
		} else if ($_POST['site_poll_votes_type'] === 'any') {
			$max_answers = 0;
		} else if ($_POST['site_poll_votes_type'] === 'number') {
			$max_answers = intval($_POST['site_polls_max_votes']);
		}

		$lifetime = intval($_POST['site_poll_revote_time']);

		$style = array();
		$style['theme'] = 'blue';

		if (!empty($_POST['site_poll_theme'])) {
			$style['theme'] = esc_sql($_POST['site_poll_theme']);
		}

		$wpdb->query('
			INSERT INTO `' . $table_prefix . 'polls`
			(`created`, `type`, `title`, `max_answers`, `lifetime`, `config`, `style`)
			VALUES
			(NOW(), ' . SITE_POLLS_TYPE_STANDARD . ', \'' . esc_sql($_POST['site_poll_title']) . '\', ' . $max_answers . ', ' . $lifetime . ', \'\', \'' . serialize($style) . '\');
		');

		$new_poll_id = $wpdb->insert_id;

		foreach ($_POST['site_poll_answers'] as $key => $answer) {
			if (!empty($answer)) {
				$votes = 0;

				if (!empty($_POST['site_poll_answer_votes'][$key])) {
					$votes = intval($_POST['site_poll_answer_votes'][$key]);
				}

				$wpdb->query('
					INSERT INTO `' . $table_prefix . 'polls_answers`
					(`poll_id`, `type`, `title`, `image`, `votes`)
					VALUES
					(' . $new_poll_id . ', 0, \'' . esc_sql($answer) . '\', \'\', ' . $votes. ');
				');
			}
		}
	}
}

function site_polls_register_pages() {
    add_menu_page(
    	__('Site Polls', 'site-polls'),
    	__('Polls', 'site-polls'),
    	'manage_options',
    	SITE_POLLS_ADMIN_PAGE,
    	'site_polls_page',
		'dashicons-megaphone',
		'50.9342');

    add_submenu_page(
    	SITE_POLLS_ADMIN_PAGE,
    	__('All Polls', 'site-polls'),
    	__('All Polls', 'site-polls'),
    	'manage_options',
    	SITE_POLLS_ADMIN_PAGE,
    	'site_polls_page');

    add_submenu_page(
    	SITE_POLLS_ADMIN_PAGE,
    	__('Settings', 'site-polls'),
    	__('Settings', 'site-polls'),
    	'manage_options',
    	SITE_POLLS_SETTINGS_PAGE,
    	'site_polls_settings_page');
}

function site_polls_info_bar() {
?>
<div class="site-polls-bar">
	<div class="site-polls-widget">
		<div class="site-polls-header"><?php _e('About', 'site-polls'); ?></div>
		<div class="site-polls-info">
			<p><?php _e('This plugin was developed and maintained by <a href="https://github.com/dymytriy" target="_blank">Dymytriy</a>.', 'site-polls'); ?></p>
			<p><?php _e('Version:', 'site-polls'); ?> <b><?php echo SITE_POLLS_VERSION; ?></b></p>
			<p><?php _e('Project home: <a href="https://github.com/dymytriy/site-polls" target="_blank">view repository</a>', 'site-polls'); ?></p>
		</div>
	</div>
</div>
<?php
}

function site_polls_page() {
	$request_uri = $_SERVER['REQUEST_URI'];
	$request_url = strtok($request_uri, '?');
	$request_main = add_query_arg(array('page' => SITE_POLLS_ADMIN_PAGE), $request_url);

?>
<h1><?php _e('Site Polls', 'site-polls'); ?></h1>
<?php
	// Save poll if that is required
	if (!empty($_POST['site_polls_save_poll']) && ($_POST['site_polls_save_poll'] == 'yes')) {
		if (!empty($_POST['site_poll_id'])) {
			site_polls_edit_poll();
		} else {
			site_polls_insert_poll();
		}
	} else if (!empty($_GET['delete'])) {
		site_polls_delete_poll($_GET['delete']);
	}

	$seconds_per_day = 86400;

	$revote_immediately = 0;
	$revote_1_day = $seconds_per_day;
	$revote_3_days = 3 * $seconds_per_day;
	$revote_1_week = 7 * $seconds_per_day;
	$revote_2_weeks = 2 * 7 * $seconds_per_day;
	$revote_1_month = 30 * $seconds_per_day;
	$revote_3_months = 91 * $seconds_per_day;
	$revote_6_months = 182 * $seconds_per_day;
	$revote_year = 365 * $seconds_per_day;

	// Render user interface
	if (!empty($_GET['add_poll'])) {
?>
<h3><?php _e('Add new poll to your site.', 'site-polls'); ?></h3>

<div class="site-polls-main">
	<form method="post" action="<?php echo $request_main; ?>">
		<input type="hidden" name="site_polls_save_poll" value="yes" />
		<div class="site-polls-text">
			<p><label><strong><?php _e('Title', 'site-polls'); ?></strong></label></p>
			<p><input type="text" class="site-polls-title" name="site_poll_title" id="site_poll_title" size="50"/></p>
			<p><label><strong><?php _e('Max. number of votes', 'site-polls'); ?></strong></label></p>
			<p><label><input type="radio" name="site_poll_votes_type" id="site_poll_votes_single" value="one" checked="checked"/><?php _e('Only one vote is allowed', 'site-polls'); ?></label></p>
			<p><label><input type="radio" name="site_poll_votes_type" id="site_poll_votes_unlimited" value="any"/><?php _e('Unlimited votes are allowed', 'site-polls'); ?></label></p>
			<p><label><input type="radio" name="site_poll_votes_type" id="site_poll_votes_number" value="number"/><?php _e('Specify max votes: ', 'site-polls'); ?></label> <input type="text" class="site-polls-max-votes" name="site_polls_max_votes" size="5" value=""></p>
			<p><label><strong><?php _e('Theme', 'site-polls'); ?></strong></label></p>
			<p>
				<select class="site-polls-select" id="site_poll_theme" name="site_poll_theme">
					<option value="black"><?php _e('Black', 'site-polls'); ?></option>
					<option value="blue" selected="selected"><?php _e('Blue', 'site-polls'); ?></option>
					<option value="brown"><?php _e('Brown', 'site-polls'); ?></option>
					<option value="gray"><?php _e('Gray', 'site-polls'); ?></option>
					<option value="green"><?php _e('Green', 'site-polls'); ?></option>
					<option value="pink"><?php _e('Pink', 'site-polls'); ?></option>
					<option value="red"><?php _e('Red', 'site-polls'); ?></option>
					<option value="yellow"><?php _e('Yellow', 'site-polls'); ?></option>
				</select>
			</p>
			<p><label><strong><?php _e('Revote is allowed every', 'site-polls'); ?></strong></label></p>
			<p>
				<select class="site-polls-select" id="site_poll_revote_time" name="site_poll_revote_time">
					<option<?php echo ' value="' . $revote_immediately .'"'; ?>><?php _e('Immediately', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_day .'"'; ?>><?php _e('1 day', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_days .'"'; ?>><?php _e('3 days', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_week .'"'; ?> selected="selected"><?php _e('1 week', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_2_weeks .'"'; ?>><?php _e('2 weeks', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_month .'"'; ?>><?php _e('1 month', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_months .'"'; ?>><?php _e('3 months', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_6_months .'"'; ?>><?php _e('6 months', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_year .'"'; ?>><?php _e('Year', 'site-polls'); ?></option>
				</select>
			</p>
			<p><label><strong><?php _e('Answers', 'site-polls'); ?></strong></label></p>
			<div id="site_poll_text_answers">
				<p><span class="site-polls-elem">1. </span><input type="hidden" name="site_poll_answer_ids[1]" value="0"/><span><input type="text" size="50" name="site_poll_answers[1]"/></span><span class="site-polls-votes"><?php _e('Votes:', 'site-polls'); ?></span><input type="text" size="5" name="site_poll_answer_votes[1]" value="0"/><a class="site-polls-delete" href="#" title="<?php _e('Delete', 'site-polls'); ?>" onclick="return site_polls_delete_answer(this);"><?php _e('Delete', 'site-polls'); ?></a></p>
			</div>
			<input type="hidden" id="site_poll_next_answer_id" value="2" />
			<p><button class="site-polls-button" onclick="return site_polls_add_answer();"><?php _e('Add Answer', 'site-polls'); ?></button></p>
		</div>
		<p><button class="site-polls-button site-polls-save"><?php _e('Save Poll', 'site-polls'); ?></button><button class="site-polls-button" onclick="return site_polls_cancel_button('<?php echo $request_main; ?>');"><?php _e('Cancel', 'site-polls'); ?></button></p>
	</form>
</div>
<?php
	} else if (!empty($_GET['edit'])) {
		// Loading existing poll if we are editing something
		$poll = site_polls_get($_GET['edit']);

		$theme = "blue";

		if (!empty($poll['poll']['style']['theme'])) {
			$theme = $poll['poll']['style']['theme'];
		}

		if (!empty($poll) && is_array($poll)) {
			$poll_type = intval($poll['poll']['type']);
			$max_answers = intval($poll['poll']['max_answers']);
			$lifetime = intval($poll['poll']['lifetime']);

			if ($poll_type == SITE_POLLS_TYPE_STANDARD) {
?>
<h3><?php _e('Edit your existing poll.', 'site-polls'); ?></h3>

<div class="site-polls-main">
	<form method="post" action="<?php echo $request_main; ?>">
		<input type="hidden" name="site_polls_save_poll" value="yes" />
		<div class="site-polls-text">
			<input type="hidden" name="site_poll_id" id="site_poll_id" value="<?php echo $poll['poll']['id']; ?>"/>
			<p><label><strong><?php _e('Title', 'site-polls'); ?></strong></label></p>
			<p><input type="text" class="site-polls-title" name="site_poll_title" id="site_poll_title" size="50" value="<?php echo esc_html(stripslashes($poll['poll']['title'])); ?>"/></p>
			<p><label><strong><?php _e('Max. number of votes', 'site-polls'); ?></strong></label></p>
			<p><label><input type="radio" name="site_poll_votes_type" id="site_poll_votes_single" value="one"<?php echo (($max_answers == 1) ? ' checked="checked"' : ''); ?>/><?php _e('Only one vote is allowed', 'site-polls'); ?></label></p>
			<p><label><input type="radio" name="site_poll_votes_type" id="site_poll_votes_unlimited" value="any"<?php echo (($max_answers == 0) ? ' checked="checked"' : ''); ?>/><?php _e('Unlimited votes are allowed', 'site-polls'); ?></label></p>
			<p><label><input type="radio" name="site_poll_votes_type" id="site_poll_votes_number" value="number"<?php
				echo (($max_answers > 1) ? ' checked="checked"' : '');
				?>/><?php _e('Specify max votes: ', 'site-polls'); ?></label> <input type="text" class="site-polls-max-votes" name="site_polls_max_votes" size="5"<?php
					echo (($max_answers > 1) ? ' value="' . $max_answers . '"' : '');
				?>/></p>
			<p><label><strong><?php _e('Theme', 'site-polls'); ?></strong></label></p>
			<p>
				<select class="site-polls-select" id="site_poll_theme" name="site_poll_theme">
					<option value="black"<?php echo ($theme == 'black' ? ' selected="selected"' : '');?>><?php _e('Black', 'site-polls'); ?></option>
					<option value="blue"<?php echo ($theme == 'blue' ? ' selected="selected"' : '');?>><?php _e('Blue', 'site-polls'); ?></option>
					<option value="brown"<?php echo ($theme == 'brown' ? ' selected="selected"' : '');?>><?php _e('Brown', 'site-polls'); ?></option>
					<option value="gray"<?php echo ($theme == 'gray' ? ' selected="selected"' : '');?>><?php _e('Gray', 'site-polls'); ?></option>
					<option value="green"<?php echo ($theme == 'green' ? ' selected="selected"' : '');?>><?php _e('Green', 'site-polls'); ?></option>
					<option value="pink"<?php echo ($theme == 'pink' ? ' selected="selected"' : '');?>><?php _e('Pink', 'site-polls'); ?></option>
					<option value="red"<?php echo ($theme == 'red' ? ' selected="selected"' : '');?>><?php _e('Red', 'site-polls'); ?></option>
					<option value="yellow"<?php echo ($theme == 'yellow' ? ' selected="selected"' : '');?>><?php _e('Yellow', 'site-polls'); ?></option>
				</select>
			</p>
			<p><label><strong><?php _e('Revote is allowed every', 'site-polls'); ?></strong></label></p>
			<p>
				<select class="site-polls-select" id="site_poll_revote_time" name="site_poll_revote_time">
					<option<?php echo ' value="' . $revote_immediately .'"'; echo ($lifetime == $revote_immediately ? ' selected="selected"' : '');?>><?php _e('Immediately', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_day .'"'; echo ($lifetime == $revote_1_day ? ' selected="selected"' : '');?>><?php _e('1 day', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_days .'"'; echo ($lifetime == $revote_3_days ? ' selected="selected"' : '');?>><?php _e('3 days', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_week .'"'; echo ($lifetime == $revote_1_week ? ' selected="selected"' : '');?>><?php _e('1 week', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_2_weeks .'"'; echo ($lifetime == $revote_2_weeks ? ' selected="selected"' : '');?>><?php _e('2 weeks', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_month .'"'; echo ($lifetime == $revote_1_month ? ' selected="selected"' : '');?>><?php _e('1 month', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_months .'"'; echo ($lifetime == $revote_3_months ? ' selected="selected"' : '');?>><?php _e('3 months', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_6_months .'"'; echo ($lifetime == $revote_6_months ? ' selected="selected"' : '');?>><?php _e('6 months', 'site-polls'); ?></option>
					<option<?php echo ' value="' . $revote_year .'"'; echo ($lifetime == $revote_year ? ' selected="selected"' : '');?>><?php _e('Year', 'site-polls'); ?></option>
				</select>
			</p>
			<p><label><strong><?php _e('Answers', 'site-polls'); ?></strong></label></p>
			<div id="site_poll_text_answers">
<?php
				$index = 1;

				if (!empty($poll['answers']) && is_array($poll['answers'])) {
					foreach ($poll['answers'] as $answer) {
?>
				<p><span class="site-polls-elem"><?php echo $index; ?>. </span><input type="hidden" name="site_poll_answer_ids[<?php echo $index; ?>]" value="<?php echo $answer['answer_id']; ?>"/><span><input type="text" size="50" name="site_poll_answers[<?php echo $index; ?>]" value="<?php echo esc_html(stripslashes($answer['title'])); ?>"/></span><span class="site-polls-votes"><?php _e('Votes:', 'site-polls'); ?></span><input type="text" size="5" name="site_poll_answer_votes[<?php echo $index; ?>]" value="<?php echo $answer['votes']; ?>"/><a class="site-polls-delete" href="#" title="<?php _e('Delete', 'site-polls'); ?>" onclick="return site_polls_delete_answer(this);"><?php _e('Delete', 'site-polls'); ?></a></p>
<?php
						$index++;
					}
				}
?>
			</div>
			<input type="hidden" id="site_poll_next_answer_id" value="<?php echo $index; ?>" />
			<p><button class="site-polls-button" onclick="return site_polls_add_answer();"><?php _e('Add Answer', 'site-polls'); ?></button></p>
		</div>
		<p><button class="site-polls-button site-polls-save"><?php _e('Save Poll', 'site-polls'); ?></button><button class="site-polls-button" onclick="return site_polls_cancel_button('<?php echo $request_main; ?>');"><?php _e('Cancel', 'site-polls'); ?></button></p>
	</form>
</div>
<?php
			} else {
?>
<h3><?php _e('Edit your existing poll.', 'site-polls'); ?></h3>

<div class="site-polls-main">
	<div class="site-polls-text">
		<p><?php _e('This type of poll is not supported in free version of plugin.', 'site-polls'); ?></p>
		<p><?php _e('Please upgrade to advanced version of Site Polls.', 'site-polls'); ?></p>
	</div>
</div>
<?php
			}
		} else {
?>
<h3><?php _e('Add new poll to your site.', 'site-polls'); ?></h3>

<div class="site-polls-main">
	<p><b><?php _e('Error: failed to get information from the database.', 'site-polls'); ?></b></p>
</div>
<?php
		}
	} else {
		$total_items = site_polls_get_all_count();

		$items_per_page = 10;
		$current_page = 1;
		$total_pages = floor($total_items / $items_per_page);

		if (($total_items % $items_per_page) > 0) {
			$total_pages++;
		}

		if (!empty($_GET['subpage'])) {
			$current_page = intval($_GET['subpage']);
		}

		$polls = site_polls_get_range($current_page - 1, $items_per_page);

		$pagelink_args = array(
			'base'         => $request_main . '%_%',
			'format'       => '&subpage=%#%',
			'total'        => $total_pages,
			'current'      => $current_page,
			'show_all'     => false,
			'end_size'     => 4,
			'mid_size'     => 4,
			'prev_next'    => true,
			'prev_text'    => __('« Previous', 'site-polls'),
			'next_text'    => __('Next »', 'site-polls'),
			'type'         => 'plain',
			'add_args'     => true,
			'add_fragment' => '',
			'before_page_number' => '',
			'after_page_number' => ''
		);

?>
<h3><?php _e('List of your interactive polls.', 'site-polls'); ?></h3>

<div class="site-polls-main">

<div class="site-polls-content">
	<div class="site-polls-text">
		<p><a class="site-polls-button" href="<?php echo add_query_arg(array('add_poll' => 'yes'), $request_main); ?>"><?php _e('Add New Poll', 'site-polls'); ?></a></p>
		<?php

			$page_links = paginate_links($pagelink_args);

			if (!empty($page_links)) {
?>
		<p><?php echo $page_links ?></p>
<?php
			}

		?>
		<table class="site-polls-table">
			<tr>
				<th><?php _e('ID', 'site-polls'); ?></th>
				<th><?php _e('Title', 'site-polls'); ?></th>
				<th><?php _e('Created', 'site-polls'); ?></th>
				<th><?php _e('Shortcode', 'site-polls'); ?></th>
				<th><?php _e('Theme', 'site-polls'); ?></th>
				<th><?php _e('Actions', 'site-polls'); ?></th>
			</tr>
<?php

	if (empty($polls) || !is_array($polls) || (count($polls) <= 0)) {
?>
			<tr>
				<td colspan="5"><p><?php _e('Currently, you do not have any active polls.', 'site-polls'); ?></p></td>
			</tr>
<?php
	} else {
		foreach ($polls as $poll) {
			$theme = "blue";

			if (!empty($poll['style']['theme'])) {
				$theme = $poll['style']['theme'];
			}

?>
			<tr>
				<td class="site-polls-td-id"><?php echo $poll['id']; ?></td>
				<td class="site-polls-td-title"><?php

					if (!empty($poll['title'])) {
						echo esc_html(stripslashes($poll['title']));
					} else {
						echo __('(No title)', 'site-polls');
					}

				?></td>
				<td class="site-polls-td-created"><?php echo $poll['created']; ?></td>
				<td class="site-polls-td-shortcode"><input type="text" value="<?php echo esc_html('[site_poll id="' . $poll['id'] . '"]') ?>" /></td>
				<td class="site-polls-td-theme"><?php echo ucwords($theme); ?></td>
				<td class="site-polls-td-actions">
					<a href="<?php echo add_query_arg(array('edit' => $poll['id']), $request_main); ?>" title="<?php _e('Edit', 'site-polls'); ?>"><?php _e('Edit', 'site-polls'); ?></a>
					<a href="<?php echo add_query_arg(array('delete' => $poll['id']), $request_main); ?>" title="<?php _e('Delete', 'site-polls'); ?>" onclick="return confirm('<?php _e('Are you sure?', 'site-polls'); ?>');"><?php _e('Delete', 'site-polls'); ?></a>
				</td>
			</tr>
<?php
		}
	}
?>
		</table>
		<?php

			$page_links = paginate_links($pagelink_args);

			if (!empty($page_links)) {
?>
		<p><?php echo $page_links ?></p>
<?php
			}

		?>
		<p><?php _e('<b>Hint:</b> you can use <b>shortcodes</b> to place your polls in posts or pages.', 'site-polls'); ?></p>
	</div>
</div>

<?php site_polls_info_bar(); ?>

<div class="site-polls-clear"></div>
</div>
<?php
	}
}

function site_polls_settings_page() {
	$site_polls_remove_db = false;

	if (!empty($_POST['site_polls_save_settings']) && ($_POST['site_polls_save_settings'] == 'yes')) {
		if (site_polls_checked('site_polls_remove_db')) {
			$site_polls_remove_db = true;
		} else {
			$site_polls_remove_db = false;
		}

		update_option('site_polls_remove_db', $site_polls_remove_db);
	} else {
		$site_polls_remove_db = get_option('site_polls_remove_db', false);
	}

?>
<h1><?php _e('Settings', 'site-polls'); ?></h1>
<h3><?php _e('Configure your plugin.', 'site-polls'); ?></h3>

<div class="site-polls-main">

<div class="site-polls-content">
	<div class="site-polls-text">
		<form method="post">
			<input type="hidden" name="site_polls_save_settings" value="yes" />
			<p><label><input type="checkbox" name="site_polls_remove_db" <?php if ($site_polls_remove_db) echo 'checked="checked"'; ?> /><?php _e('Remove MySQL tables with all data when plugin is uninstalled.', 'site-polls') ?></label></p>
			<p><button class="site-polls-button site-polls-save"><?php _e('Save', 'site-polls'); ?></button></p>
		</form>
	</div>
</div>

<?php site_polls_info_bar(); ?>

<div class="site-polls-clear"></div>
</div>
<?php
}

?>
