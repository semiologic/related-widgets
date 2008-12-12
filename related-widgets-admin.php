<?php
if ( !class_exists('widget_utils') )
{
	include dirname(__FILE__) . '/widget-utils.php';
}

class related_widgets_admin
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('related_widgets_admin', 'meta_boxes'));

		add_filter('sem_api_key_protected', array('related_widgets_admin', 'sem_api_key_protected'));
		
		if ( version_compare(mysql_get_server_info(), '4.1', '<') )
		{
			add_action('admin_notices', array('related_widgets_admin', 'mysql_warning'));
			remove_action('widgets_init', array('related_widgets', 'widgetize'));
		}
	} # init()
	
	
	#
	# mysql_warning()
	#
	
	function mysql_warning()
	{
		echo '<div class="error">'
			. '<p><b style="color: firebrick;">Related Widgets Error</b><br /><b>Your MySQL version is lower than 4.1.</b> It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.</p>'
			. '</div>';
	} # mysql_warning()


	#
	# sem_api_key_protected()
	#
	
	function sem_api_key_protected($array)
	{
		$array[] = 'http://www.semiologic.com/media/software/widgets/related-widgets/related-widgets.zip';
		
		return $array;
	} # sem_api_key_protected()

	#
	# meta_boxes()
	#

	function meta_boxes()
	{
		if ( !class_exists('widget_utils') ) return;
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();

		add_action('post_widget_config_affected', array('related_widgets_admin', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('related_widgets_admin', 'widget_config_affected'));
	} # meta_boxes()

	#
	# widget_config_affected()
	#

	function widget_config_affected()
	{
		echo '<li>'
			. 'Related Widgets'
			. '</li>';
	} # widget_config_affected()


	#
	# widget_control()
	#

	function widget_control($widget_args)
	{
		global $wpdb;
		global $post_stubs;
		global $page_stubs;

		if ( !isset($post_stubs) )
		{
			$post_stubs = (array) $wpdb->get_results("
				SELECT	terms.term_id as value,
						terms.name as label
				FROM	$wpdb->terms as terms
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_id = terms.term_id
				AND		term_taxonomy.taxonomy = 'category'
				WHERE	parent = 0
				ORDER BY terms.name
				");
		}

		if ( !isset($page_stubs) )
		{
			$page_stubs = (array) $wpdb->get_results("
				SELECT	posts.ID as value,
						posts.post_title as label
				FROM	$wpdb->posts as posts
				WHERE	post_parent = 0
				AND		post_type = 'page'
				AND		post_status = 'publish'
				ORDER BY posts.post_title
				");
		}


		global $wp_registered_widgets;
		static $updated = false;

		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP ); // extract number

		$options = related_widgets::get_options();

		if ( !$updated && !empty($_POST['sidebar']) )
		{
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();

			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();

			foreach ( $this_sidebar as $_widget_id )
			{
				if ( array('related_widgets', 'display_widget') == $wp_registered_widgets[$_widget_id]['callback']
					&& isset($wp_registered_widgets[$_widget_id]['params'][0]['number'])
					)
				{
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "related-widget-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
						unset($options[$widget_number]);
					
					related_widgets::clear_cache();
				}
			}

			foreach ( (array) $_POST['related-widget'] as $num => $opt ) {
				$title = strip_tags(stripslashes($opt['title']));
				$type = $opt['type'];
				$amount = intval($opt['amount']);
				$score = isset($opt['score']);
				$desc = isset($opt['desc']);

				if ( !preg_match("/^([a-z_]+)(?:-(\d+))?$/", $type, $match) )
				{
					$type = 'posts';
					$filter = false;
				}
				else
				{
					$type = $match[1];
					$filter = isset($match[2]) ? $match[2] : false;
				}

				if ( $amount <= 0 )
				{
					$amount = 5;
				}

				$options[$num] = compact( 'title', 'type', 'filter', 'amount', 'score', 'desc' );
			}

			update_option('related_widgets', $options);

			$updated = true;
		}

		if ( -1 == $number )
		{
			$ops = related_widgets::default_options();
			$number = '%i%';
		}
		else
		{
			$ops = $options[$number];
		}

		extract($ops);


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="related-widget-title-' . $number . '">'
			. __('Title', 'related-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 320px;"'
			. ' id="related-widget-title-' . $number . '" name="related-widget[' . $number . '][title]"'
			. ' type="text" value="' . attribute_escape($title) . '"'
			. ' />'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="related-widget-type-' . $number . '">'
			. __('Recent', 'related-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">';

		$type = $type
			. ( $filter
				? ( '-' . $filter )
				: ''
				);

		echo '<select'
				. ' style="width: 320px;"'
				. ' id="related-widget-type-' . $number . '" name="related-widget[' . $number . '][type]"'
				. '>';

		echo '<optgroup label="' . __('Posts', 'related-widgets') . '">'
			. '<option'
			. ' value="posts"'
			. ( $type == 'posts'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Posts', 'related-widgets') . ' / ' . __('All categories', 'related-widgets')
			. '</option>';

		foreach ( $post_stubs as $option )
		{
			echo '<option'
				. ' value="posts-' . $option->value . '"'
				. ( $type == ( 'posts-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Posts', 'related-widgets') . ' / ' . attribute_escape($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '<optgroup label="' . __('Pages', 'related-widgets') . '">'
			. '<option'
			. ' value="pages"'
			. ( $type == 'pages'
				? ' selected="selected"'
				: ''
				)
			. '>'
			. __('Pages', 'related-widgets') . ' / ' . __('All Parents', 'related-widgets')
			. '</option>';

		foreach ( $page_stubs as $option )
		{
			echo '<option'
				. ' value="pages-' . $option->value . '"'
				. ( $type == ( 'pages-' . $option->value )
					? ' selected="selected"'
					: ''
					)
				. '>'
				. __('Pages', 'related-widgets') . ' / ' . attribute_escape($option->label)
				. '</option>';
		}

		echo '</optgroup>';

		echo '</select>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';


		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 120px; float: left; padding-top: 2px;">'
			. '<label for="related-widget-amount-' . $number . '">'
			. __('Quantity', 'related-widgets')
			. '</label>'
			. '</div>'
			. '<div style="width: 330px; float: right;">'
			. '<input style="width: 30px;"'
			. ' id="related-widget-amount-' . $number . '" name="related-widget[' . $number . '][amount]"'
			. ' type="text" value="' . $amount . '"'
			. ' />'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 330px; float: right;">'
			. '<label for="related-widget-score-' . $number . '">'
			. '<input'
			. ' id="related-widget-score-' . $number . '" name="related-widget[' . $number . '][score]"'
			. ' type="checkbox"'
			. ( $score
				? ' checked="checked"'
				: ''
				)
			. ' />'
			. '&nbsp;' . __('Show Score', 'related-widgets')
			. '</label>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';

		echo '<div style="margin: 0px 0px 6px 0px;">'
			. '<div style="width: 330px; float: right;">'
			. '<label for="related-widget-desc-' . $number . '">'
			. '<input'
			. ' id="related-widget-desc-' . $number . '" name="related-widget[' . $number . '][desc]"'
			. ' type="checkbox"'
			. ( $desc
				? ' checked="checked"'
				: ''
				)
			. ' />'
			. '&nbsp;' . __('Show Descriptions', 'related-widgets')
			. '</label>'
			. '</div>'
			. '<div style="clear: both;"></div>'
			. '</div>';
	} # widget_control()
} # related_widgets_admin

related_widgets_admin::init();
?>