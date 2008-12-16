<?php
/*
Plugin Name: Related Widgets
Plugin URI: http://www.semiologic.com/software/widgets/related-widgets/
Description: WordPress widgets that let you list related posts or pages. Requires that you tag your posts and pages.
Author: Denis de Bernardy
Version: 2.2.1 alpha
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/plugins
Update Tag: related_widgets
Update Package: http://www.semiologic.com/media/software/widgets/related-widgets/related-widgets.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('related-widgets','wp-content/plugins/related-widgets');

class related_widgets
{
	#
	# init()
	#

	function init()
	{
		add_action('widgets_init', array('related_widgets', 'widgetize'));

		foreach ( array(
				'save_post',
				'delete_post',
				'switch_theme',
				'update_option_active_plugins',
				'update_option_show_on_front',
				'update_option_page_on_front',
				'update_option_page_for_posts',
				'generate_rewrite_rules',
				) as $hook)
		{
			add_action($hook, array('related_widgets', 'clear_cache'));
		}
		
		register_taxonomy('yahoo_terms', 'post', array('update_count_callback' => array('extract_terms', 'update_taxonomy_count')));
		
		register_activation_hook(__FILE__, array('related_widgets', 'clear_cache'));
		register_deactivation_hook(__FILE__, array('related_widgets', 'clear_cache'));
	} # init()


	#
	# widgetize()
	#

	function widgetize()
	{
		$options = related_widgets::get_options();
		
		$widget_options = array('classname' => 'related_widget', 'description' => __( "Related posts or pages") );
		$control_options = array('width' => 500, 'id_base' => 'related-widget');
		
		$id = false;

		# registered widgets
		foreach ( array_keys($options) as $o )
		{
			if ( !is_numeric($o) ) continue;
			$id = "related-widget-$o";

			wp_register_sidebar_widget($id, __('Related Widget'), array('related_widgets', 'display_widget'), $widget_options, array( 'number' => $o ));
			wp_register_widget_control($id, __('Related Widget'), array('related_widgets_admin', 'widget_control'), $control_options, array( 'number' => $o ) );
		}
		
		# default widget if none were registered
		if ( !$id )
		{
			$id = "related-widget-1";
			wp_register_sidebar_widget($id, __('Related Widget'), array('related_widgets', 'display_widget'), $widget_options, array( 'number' => -1 ));
			wp_register_widget_control($id, __('Related Widget'), array('related_widgets_admin', 'widget_control'), $control_options, array( 'number' => -1 ) );
		}
	} # widgetize()


	#
	# display_widget()
	#

	function display_widget($args, $widget_args = 1)
	{
		# fetch object_id
		if ( !is_admin() )
		{
			if ( in_the_loop() )
			{
				$object_id = get_the_ID();
			}
			elseif ( is_singular() )
			{
				$object_id = $GLOBALS['wp_query']->get_queried_object_id();
			}
			else
			{
				return ;
			}
		}
		
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );
		
		$number = intval($number);
		
		# front end: serve cache if available
		if ( !is_admin() )
		{
			if ( in_array(
					'_related_widgets_cache_' . $number,
					(array) get_post_custom_keys($object_id)
					)
				)
			{
				$cache = get_post_meta($object_id, '_related_widgets_cache_' . $number, true);
				echo $cache;
				return;
			}
		}
		
		# get options
		$options = related_widgets::get_options();
		$options = $options[$number];
		$options['object_id'] = $object_id;
		
		# admin area: serve a formatted title
		if ( is_admin() )
		{
			echo $args['before_widget']
				. $args['before_title'] . $options['title'] . $args['after_title']
				. $args['after_widget'];

			return;
		}
		
		$do_cache = true;

		# fetch yahoo terms if not available
		if ( !count(wp_get_object_terms($object_id, 'yahoo_terms'))
			&& !get_post_meta($object_id, '_related_widgets_got_yahoo_terms', true)
			)
		{
			# fetch terms after output buffer gets flushed
			add_action('shutdown', create_function('', "related_widgets::extract_terms($object_id);"));
			
			# kill caching
			$do_cache = false;
		}
		
		# initialize
		$o = '';
		
		# fetch items
		switch ( $options['type'] )
		{
		case 'posts':
			$items = related_widgets::get_posts($options);
			break;

		case 'pages':
			$items = related_widgets::get_pages($options);
			break;

		default:
			$items = array();
		}

		# fetch output
		if ( $items )
		{
			$o .= $args['before_widget'] . "\n"
				. ( $options['title']
					? ( $args['before_title'] . $options['title'] . $args['after_title'] . "\n" )
					: ''
					);

			$o .= '<ul>' . "\n";

			foreach ( $items as $item )
			{
				$o .= '<li>'
					. $item->item_label
					. '</li>' . "\n";
			}

			$o .= '</ul>' . "\n";

			$o .= $args['after_widget'] . "\n";
		}

		# cache
		# delete_post_meta($object_id, '_related_widgets_cache_' . $number);
		if ( $do_cache )
		{
			add_post_meta($object_id, '_related_widgets_cache_' . $number, $o, true);
		}
		
		# display
		echo $o;
	} # display_widget()


	#
	# get_posts()
	#

	function get_posts($options)
	{
		global $wpdb;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";

		$items_sql = "
			SELECT	posts.*,
					posts.ID as item_id,
					lower( post_title ) as item_name,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc
			FROM	$wpdb->posts as posts
			"
			. ( $options['filter']
				? ( "
			INNER JOIN $wpdb->term_relationships as term_relationships
			ON		term_relationships.object_id = posts.ID
			INNER JOIN $wpdb->term_taxonomy as term_taxonomy
			ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
			AND		term_taxonomy.taxonomy = 'category'
			AND		term_taxonomy.term_id = " . intval($options['filter'])
			)
				: ''
				)
			. "
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'post'
			AND		posts.post_password = ''
			AND		posts.ID NOT IN ( $exclude_sql )
			AND		posts.ID <> " . intval($options['object_id']) . "
			"
			;

		$items = related_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['score']
					? ( ' (' . $items[$key]->item_score . '%)' )
					: ''
					)
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_posts()


	#
	# get_pages()
	#

	function get_pages($options)
	{
		global $wpdb;
		global $page_filters;

		$exclude_sql = "
			SELECT	post_id
			FROM	$wpdb->postmeta
			WHERE	meta_key = '_widgets_exclude'
			";

		if ( $options['filter'] )
		{
			if ( isset($page_filters[$options['filter']]) )
			{
				$parents_sql = $page_filters[$options['filter']];
			}
			else
			{
				$parents = array($options['filter']);

				do
				{
					$old_parents = $parents;

					$parents_sql = implode(', ', $parents);

					$parents = (array) $wpdb->get_col("
						SELECT	posts.ID
						FROM	$wpdb->posts as posts
						WHERE	posts.post_status = 'publish'
						AND		posts.post_type = 'page'
						AND		( posts.ID IN ( $parents_sql ) OR posts.post_parent IN ( $parents_sql ) )
						");
					
					sort($parents);
				} while ( $parents != $old_parents );

				$page_filters[$options['filter']] = $parents_sql;
			}
		}

		$items_sql = "
			SELECT	posts.*,
					posts.ID as item_id,
					lower( post_title ) as item_name,
					COALESCE(post_label.meta_value, post_title) as post_label,
					COALESCE(post_desc.meta_value, '') as post_desc
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->postmeta as post_label
			ON		post_label.post_id = posts.ID
			AND		post_label.meta_key = '_widgets_label'
			LEFT JOIN $wpdb->postmeta as post_desc
			ON		post_desc.post_id = posts.ID
			AND		post_desc.meta_key = '_widgets_desc'
			WHERE	posts.post_status = 'publish'
			AND		posts.post_type = 'page'
			AND		posts.post_password = ''
			"
			. ( $options['filter']
				? ( "
			AND		posts.post_parent IN ( $parents_sql )
			" )
				: ''
				)
			. "
			AND		posts.ID NOT IN ( $exclude_sql )
			AND		posts.ID <> " . intval($options['object_id']) . "
			"
			;

		$items = related_widgets::get_items($items_sql, $options);

		update_post_cache($items);

		foreach ( array_keys($items) as $key )
		{
			$items[$key]->item_label = '<a href="'
				. htmlspecialchars(apply_filters('the_permalink', get_permalink($items[$key]->ID)))
				. '">'
				. $items[$key]->post_label
				. '</a>'
				. ( $options['score']
					? ( ' (' . $items[$key]->item_score . '%)' )
					: ''
					)
				. ( $options['desc'] && $items[$key]->post_desc
					? wpautop($items[$key]->post_desc)
					: ''
					);
		}

		return $items;
	} # get_pages()


	#
	# get_items()
	#

	function get_items($items_sql, $options)
	{
		global $wpdb;

		$term_scores_sql = "
			SELECT	term_relationships.object_id,
					term_relationships.term_taxonomy_id,
					CASE
					WHEN
						term_taxonomy.taxonomy = 'post_tag'
					THEN
						100
					ELSE
						75
					END as taxonomy_score,
					CASE
					WHEN
						term_relationships.object_id = " . intval($options['object_id']) . "
					THEN
						100
					WHEN
						term_relationships.object_id = object_relationships.object_id
					THEN
						90
					ELSE
						80
					END as relationship_score
			FROM	$wpdb->term_relationships as object_relationships
			INNER JOIN $wpdb->term_taxonomy as object_taxonomy
			ON		object_taxonomy.taxonomy IN ( 'post_tag', 'yahoo_terms' )
			AND		object_taxonomy.term_taxonomy_id = object_relationships.term_taxonomy_id
			INNER JOIN $wpdb->terms as object_terms
			ON		object_terms.term_id = object_taxonomy.term_id
			INNER JOIN $wpdb->term_relationships as related_relationships
			ON		related_relationships.term_taxonomy_id = object_relationships.term_taxonomy_id
			INNER JOIN $wpdb->term_relationships as term_relationships
			ON		term_relationships.object_id = related_relationships.object_id
			INNER JOIN $wpdb->term_taxonomy as term_taxonomy
			ON		term_taxonomy.taxonomy IN ( 'post_tag', 'yahoo_terms' )
			AND		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
			WHERE	object_relationships.object_id = " . intval($options['object_id']) . "
			";

		#dump($term_scores_sql);
		#dump($wpdb->get_results($term_scores_sql));

		$term_weights_sql = "
			SELECT	object_id,
					term_taxonomy_id,
					MAX( ( taxonomy_score * relationship_score ) ) as term_weight
			FROM	( $term_scores_sql ) as term_scores
			GROUP BY object_id, term_taxonomy_id
			";

		#dump($term_weights_sql);
		#dump($wpdb->get_results($term_weights_sql));


		$object_weights_sql = "
			SELECT	object_id,
					SUM( term_weight ) as object_weight
			FROM	( $term_weights_sql ) as term_weights
			GROUP BY object_id
			";

		#dump($wpdb->get_results($object_weights_sql));


		$object_scores = "
			SELECT	object_id,
					object_weight,
					MAX( max_weight ) as max_weight
			FROM	( $object_weights_sql ) as object_weights,
					(
					SELECT	object_weight as max_weight
					FROM	( $object_weights_sql ) as max_weights
					) as max_weights
			GROUP BY object_id
			ORDER BY object_weight DESC
			LIMIT " . intval($options['amount'])
			;

		#dump($wpdb->get_results($object_scores));


		$items = (array) $wpdb->get_results("
			SELECT	items.*,
					floor( 100 * exp( ( object_weight + max_weight ) / ( 2 * max_weight ) ) / exp( 1 ) ) as item_score
			FROM	( $items_sql ) as items
			INNER JOIN ( $object_scores ) as related_objects
			ON		related_objects.object_id = items.item_id
			ORDER BY item_score DESC, lower(items.item_name)
			");

		#dump($items);

		return $items;
	} # get_items()


	#
	# clear_cache()
	#

	function clear_cache($in = null)
	{
		global $wpdb;
		$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_related_widgets_cache%'");

		return $in;
	} # clear_cache()


	#
	# get_options()
	#

	function get_options()
	{
		if ( ( $o = get_option('related_widgets') ) === false )
		{
			$o = array();

			update_option('related_widgets', $o);
		}

		return $o;
	} # get_options()
	
	
	#
	# new_widget()
	#
	
	function new_widget()
	{
		$o = related_widgets::get_options();
		$k = time();
		do $k++; while ( isset($o[$k]) );
		$o[$k] = related_widgets::default_options();
		
		update_option('related_widgets', $o);
		
		return 'related-widget-' . $k;
	} # new_widget()


	#
	# default_options()
	#

	function default_options()
	{
		return array(
			'title' => __('Related Posts'),
			'type' => 'posts',
			'amount' => 5,
			'score' => false,
			'desc' => false,
			);
	} # default_options()
	
	
	#
	# extract_terms()
	#
	
	function extract_terms($post_id)
	{
		$post = get_post($post_id);
		
		if ( $post->post_status = 'publish')
		{
			if ( !class_exists('extract_terms') )
			{
				include dirname(__FILE__) . '/extract-terms.php';
			}
			
			$terms = extract_terms::get_post_terms($post);

			if ( count($terms) > 2 )
			{
				$terms = array_slice($terms, 0, 2 + round(log(count($terms))));
		 	}
		
			if ( $terms )
			{
				wp_set_object_terms($post_id, $terms, 'yahoo_terms');
			}

			delete_post_meta($post_id, '_related_widgets_got_yahoo_terms');
			add_post_meta($post_id, '_related_widgets_got_yahoo_terms', '1', true);
		}
	} # extract_terms()
} # related_widgets

related_widgets::init();


if ( is_admin() )
{
	include dirname(__FILE__) . '/related-widgets-admin.php';
}
?>