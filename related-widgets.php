<?php
/*
Plugin Name: Related Widgets
Plugin URI: http://www.semiologic.com/software/related-widgets/
Description: WordPress widgets that let you list related posts or pages, based on their tags.
Version: 3.0.4 RC
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: related-widgets
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/


load_plugin_textdomain('related-widgets', false, dirname(plugin_basename(__FILE__)) . '/lang');

if ( !defined('widget_utils_textdomain') )
	define('widget_utils_textdomain', 'related-widgets');

if ( !defined('page_tags_textdomain') )
	define('page_tags_textdomain', 'related-widgets');

if ( !defined('sem_widget_cache_debug') )
	define('sem_widget_cache_debug', false);


/**
 * related_widget
 *
 * @package Related Widgets
 **/

class related_widget extends WP_Widget {
	/**
	 * activate()
	 *
	 * @return void
	 **/

	function activate() {
		if ( get_option('related_widgets_activated') )
			return;
		
		update_option('related_widgets_activated', 1);
		
		$ignore_user_abort = ignore_user_abort(true);
		
		if ( !function_exists('dbDelta') ) {
			include ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		
		global $wpdb;
		$charset_collate = '';
		
		if ( $wpdb->has_cap( 'collation' ) ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE $wpdb->collate";
		}
		
		dbDelta("
CREATE TABLE $wpdb->term_relationships (
 object_id bigint(20) unsigned NOT NULL default 0,
 term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
 term_order int(11) NOT NULL default 0,
 PRIMARY KEY  (object_id,term_taxonomy_id),
 UNIQUE KEY reverse_pkey (term_taxonomy_id,object_id),
 KEY term_taxonomy_id (term_taxonomy_id)
) $charset_collate;");
		
		ignore_user_abort($ignore_user_abort);
	} # activate()
	
	
	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		if ( get_option('widget_related_widget') === false ) {
			foreach ( array(
				'related_widgets' => 'upgrade',
				) as $ops => $method ) {
				if ( get_option($ops) !== false ) {
					$this->alt_option_name = $ops;
					add_filter('option_' . $ops, array(get_class($this), $method));
					break;
				}
			}
		}
	} # init()
	
	
	/**
	 * editor_init()
	 *
	 * @return void
	 **/

	function editor_init() {
		if ( !class_exists('widget_utils') )
			include dirname(__FILE__) . '/widget-utils/widget-utils.php';
		
		widget_utils::post_meta_boxes();
		widget_utils::page_meta_boxes();
		add_action('post_widget_config_affected', array('related_widget', 'widget_config_affected'));
		add_action('page_widget_config_affected', array('related_widget', 'widget_config_affected'));
		
		if ( !class_exists('page_tags') )
			include dirname(__FILE__) . '/page-tags/page-tags.php';
		
		page_tags::meta_boxes();
	} # editor_init()
	
	
	/**
	 * widget_config_affected()
	 *
	 * @return void
	 **/

	function widget_config_affected() {
		echo '<li>'
			. __('Related Widgets', 'related-widgets')
			. '</li>' . "\n";
	} # widget_config_affected()
	
	
	/**
	 * widgets_init()
	 *
	 * @return void
	 **/

	function widgets_init() {
		register_widget('related_widget');
	} # widgets_init()
	
	
	/**
	 * related_widget()
	 *
	 * @return void
	 **/

	function related_widget() {
		$widget_ops = array(
			'classname' => 'related_widget',
			'description' => __('Related Posts or Pages, based on your tags.', 'related-widgets'),
			);
		$control_ops = array(
			'width' => 330,
			);
		
		$this->init();
		$this->WP_Widget('related_widget', __('Related Widget', 'related-widgets'), $widget_ops, $control_ops);
	} # related_widget()
	
	
	/**
	 * widget()
	 *
	 * @param array $args widget args
	 * @param array $instance widget options
	 * @return void
	 **/

	function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		$instance = wp_parse_args($instance, related_widget::defaults());
		extract($instance, EXTR_SKIP);
		
		if ( is_admin() ) {
			echo $before_widget
				. ( $title
					? ( $before_title . $title . $after_title )
					: ''
					)
				. $after_widget;
			return;
		} elseif ( !in_array($type, array('pages', 'posts')) )
			return;
		
		if ( is_singular() ) {
			global $wp_the_query;
			$post_id = (int) $wp_the_query->get_queried_object_id();
		} elseif ( in_the_loop() ) {
			$post_id = (int) get_the_ID();
		} else {
			return;
		}
		
		global $_wp_using_ext_object_cache;
		$cache_id = "_$widget_id";
		if ( $_wp_using_ext_object_cache )
			$o = wp_cache_get($post_id, $widget_id);
		else
			$o = get_post_meta($post_id, $cache_id, true);
		
		if ( !sem_widget_cache_debug && !is_preview() ) {
			if ( $o ) {
				echo $o;
				return;
			} elseif ( $_wp_using_ext_object_cache && $o !== false ) {
				return;
			} elseif ( !$_wp_using_ext_object_cache && in_array($cache_id, (array) get_post_custom_keys($post_id)) ) {
				return;
			}
		}
		
		switch ( $type ) {
		case 'pages':
			$posts = related_widget::get_pages($post_id, $instance);
			break;
		case 'posts':
			$posts = related_widget::get_posts($post_id, $instance);
			break;
		}
		
		if ( !$posts ) {
			if ( !is_preview() ) {
				if ( $_wp_using_ext_object_cache )
					wp_cache_set($post_id, $o, $widget_id);
				else
					update_post_meta($post_id, $cache_id, $o);
			}
			return;
		}
		
		$title = apply_filters('widget_title', $title);
		
		ob_start();
		
		echo $before_widget;
		
		if ( $title )
			echo $before_title . $title . $after_title;
		
		echo '<ul>' . "\n";
		
		foreach ( $posts as $post ) {
			$label = get_post_meta($post->ID, '_widgets_label', true);
			if ( (string) $label === '' )
				$label = $post->post_title;
			if ( (string) $label === '' )
				$label = __('Untitled', 'related-widgets');
			
			echo '<li>'
				. '<a href="' . esc_url(apply_filters('the_permalink', get_permalink($post->ID))) . '"'
					. ' title="' . esc_attr($label) . '"'
					. '>'
				. $label
				. '</a>';
				
				if ( $desc ) {
					$descr = trim(get_post_meta($post->ID, '_widgets_desc', true));
					if ( $descr )
						echo "\n\n" . wpautop(apply_filters('widget_text', $descr));
				}
				
				echo '</li>' . "\n";
		}
		
		echo '</ul>' . "\n";
		
		echo $after_widget;
		
		$o = ob_get_clean();
		
		if ( !is_preview() ) {
			if ( $_wp_using_ext_object_cache )
				wp_cache_set($post_id, $o, $widget_id);
			else
				update_post_meta($post_id, $cache_id, $o);
		}
		
		echo $o;
	} # widget()
	
	
	/**
	 * get_pages()
	 *
	 * @param int $post_id
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_pages($post_id, $instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		
		$join_sql = "
				JOIN	$wpdb->posts as related_post
				ON		related_post.ID = related_tr.object_id
				AND		related_post.post_status = 'publish'
				AND		related_post.post_type = 'page'
				";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			if ( !get_transient('cached_section_ids') )
				related_widget::cache_section_ids();
			
			$join_sql .= "
				JOIN	$wpdb->postmeta as meta_filter
				ON		meta_filter.post_id = related_tr.object_id
				AND		meta_filter.meta_key = '_section_id'
				AND		meta_filter.meta_value = '$filter'
				";
		}
		
		$score_sql = related_widget::get_score_sql($post_id, $join_sql, $amount);
		
		$cache_id = md5($score_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($score_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');
			
			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_pages()
	
	
	/**
	 * get_posts()
	 *
	 * @param int $post_id
	 * @param array $instance
	 * @return array $posts
	 **/

	function get_posts($post_id, $instance) {
		global $wpdb;
		extract($instance, EXTR_SKIP);
		
		$join_sql = "
				JOIN	$wpdb->posts as related_post
				ON		related_post.ID = related_tr.object_id
				AND		related_post.post_status = 'publish'
				AND		related_post.post_type = 'post'
				";
		
		if ( $filter ) {
			$filter = intval($filter);
			
			$join_sql .= "
				JOIN	$wpdb->term_relationships as filter_tr
				ON		filter_tr.object_id = related_tr.object_id
				JOIN	$wpdb->term_taxonomy as filter_tt
				ON		filter_tt.term_taxonomy_id = filter_tr.term_taxonomy_id
				AND		filter_tt.term_id = $filter
				AND		filter_tt.taxonomy = 'category'
				";
		}
		
		$score_sql = related_widget::get_score_sql($post_id, $join_sql, $amount);
		
		$cache_id = md5($score_sql);
		$posts = wp_cache_get($cache_id, 'widget_queries');
		
		if ( $posts === false ) {
			$posts = $wpdb->get_results($score_sql);
			update_post_cache($posts);
			wp_cache_add($cache_id, $posts, 'widget_queries');
			
			$post_ids = array();
			foreach ( $posts as $post )
				$post_ids[] = $post->ID;
			update_postmeta_cache($post_ids);
		}
		
		return $posts;
	} # get_posts()
	
	
	/**
	 * get_score_sql()
	 *
	 * @return string $str
	 **/

	function get_score_sql($post_id, $join_sql = '', $limit = '') {
		global $wpdb;
		
		$term_weight = 105;
		$seed_weight = 120;
		$path_weight = 110;
		
		$noise_terms = 12;
		
		if ( $join_sql ) {
			$select_sql = "related_post.*";
			
			$limit_sql = "LIMIT " . min(max((int) $limit, 1), 10);
		} else {
			# debug
			$select_sql = "related_post.post_title,
					
					# num_terms
					COUNT( DISTINCT seed_tt.term_taxonomy_id )
					as num_terms,
				
					# num_seeds
					COUNT( DISTINCT seed_tr.object_id )
					as num_seeds,
					
					# num_paths
					COUNT( DISTINCT seed_tr.object_id, seed_tt.term_taxonomy_id )
					as num_path,
					
					# direct_terms
					COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					as direct_terms,
					
					# indirect_terms
					CASE COUNT( DISTINCT
						CASE seed_tr.object_id <> object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					WHEN	0
					THEN	0
					ELSE
						COUNT( DISTINCT seed_tt.term_taxonomy_id )
						- COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					END
					as indirect_terms,
					
					# direct seeds
					COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tr.object_id
						ELSE	NULL
						END )
					as direct_seeds,
					
					# indirect seeds
					COUNT( DISTINCT
						CASE seed_tr.object_id <> object_tr.object_id
						WHEN	TRUE
						THEN	seed_tr.object_id
						ELSE	NULL
						END )
					as indirect_seeds,
					
					# direct paths
					COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					as direct_paths,
					
					# indirect paths
					COUNT( DISTINCT seed_tr.object_id, seed_tt.term_taxonomy_id )
					- COUNT(
						DISTINCT CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					as indirect_paths";
			
			$join_sql = "
			JOIN	$wpdb->posts as related_post
			ON 		related_post.ID = related_tr.object_id
			AND		related_post.post_status = 'publish'
			";
			
			$limit_sql = '';
		}
		
		$score_sql = "
			SELECT	$select_sql,
					
					$term_weight * (
					# direct terms
					COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					) + 100 * (
					# indirect terms
					CASE COUNT( DISTINCT
						CASE seed_tr.object_id <> object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					WHEN	0
					THEN	0
					ELSE
						COUNT( DISTINCT seed_tt.term_taxonomy_id )
						- COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					END
					) as term_score,
					
					$seed_weight * (
					# direct seeds
					COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tr.object_id
						ELSE	NULL
						END )
					) + 100 * (
					# indirect seeds
					COUNT( DISTINCT
						CASE seed_tr.object_id <> object_tr.object_id
						WHEN	TRUE
						THEN	seed_tr.object_id
						ELSE	NULL
						END )
					) as seed_score,
					
					$path_weight * (
					# direct paths
					COUNT( DISTINCT
						CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					) + 100 * (
					# indirect paths
					COUNT( DISTINCT seed_tr.object_id, seed_tt.term_taxonomy_id )
					- COUNT(
						DISTINCT CASE seed_tr.object_id = object_tr.object_id
						WHEN	TRUE
						THEN	seed_tt.term_taxonomy_id
						ELSE	NULL
						END )
					) as path_score
					
			# fetch object's terms
			FROM	$wpdb->term_relationships as object_tr
			JOIN	$wpdb->term_taxonomy as object_tt
			ON		object_tt.term_taxonomy_id = object_tr.term_taxonomy_id
			AND		object_tt.count <= $noise_terms
			AND		object_tt.taxonomy = 'post_tag'
			
			# fetch seed objects: objects with at least one term in common with the object, including object
			JOIN	$wpdb->term_relationships as seed_tr
			ON		seed_tr.term_taxonomy_id = object_tt.term_taxonomy_id
			
			# fetch seed object terms
			JOIN	$wpdb->term_relationships as seed_term_tr
			ON		seed_term_tr.object_id = seed_tr.object_id
			JOIN	$wpdb->term_taxonomy as seed_tt
			ON		seed_tt.term_taxonomy_id = seed_term_tr.term_taxonomy_id
			AND		seed_tt.count <= $noise_terms
			AND		seed_tt.taxonomy = 'post_tag'
			# filter out object's unique terms
			AND		( seed_tr.object_id <> object_tr.object_id OR seed_tt.term_taxonomy_id = object_tt.term_taxonomy_id )
			
			# fetch related objects: objects with at least one term in common with a seed
			JOIN	$wpdb->term_relationships as related_tr
			ON		related_tr.term_taxonomy_id = seed_tt.term_taxonomy_id
			# object is not related to itself
			AND		related_tr.object_id <> object_tr.object_id
			
			# join on posts and applicable filters
			$join_sql
			
			LEFT JOIN $wpdb->postmeta as widgets_exclude
			ON		widgets_exclude.post_id = related_post.ID
			AND		widgets_exclude.meta_key = '_widgets_exclude'
			
			# seed the mess
			WHERE	object_tr.object_id = $post_id
			
			# manage excludes
			AND		widgets_exclude.post_ID IS NULL
			
			# generate statistics
			GROUP BY related_tr.object_id
			
			# strip out unique tags
			HAVING	COUNT(DISTINCT seed_term_tr.object_id, related_tr.object_id) > 1
			
			# order by relevance
			ORDER BY term_score DESC, seed_score DESC, path_score DESC, related_post.post_title
			
			# limit
			$limit_sql
			";
		
#		$wpdb->show_errors();
#		$res = $wpdb->get_results($score_sql);
#		$query = end($wpdb->queries);
#		dump(
#			$query,
#			$res
#			);
		
		return $score_sql;
	} # get_score_sql()
	
	
	/**
	 * update()
	 *
	 * @param array $new_instance new widget options
	 * @param array $old_instance old widget options
	 * @return array $instance
	 **/

	function update($new_instance, $old_instance) {
		$instance = related_widget::defaults();
		
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['amount'] = min(max((int) $new_instance['amount'], 1), 10);
		$instance['desc'] = isset($new_instance['desc']);
		
		$type_filter = explode('-', $new_instance['type_filter']);
		$type = array_shift($type_filter);
		$filter = array_pop($type_filter);
		$filter = intval($filter);
		
		$instance['type'] = in_array($type, array('posts', 'pages')) ? $type : 'posts';
		$instance['filter'] = $filter ? $filter : false;
		
		related_widget::flush_cache();
		
		return $instance;
	} # update()
	
	
	/**
	 * form()
	 *
	 * @param array $instance widget options
	 * @return void
	 **/

	function form($instance) {
		$instance = wp_parse_args($instance, related_widget::defaults());
		static $pages;
		static $categories;
		
		if ( !isset($pages) ) {
			global $wpdb;
			$pages = $wpdb->get_results("
				SELECT	posts.*,
						COALESCE(post_label.meta_value, post_title) as post_label
				FROM	$wpdb->posts as posts
				LEFT JOIN $wpdb->postmeta as post_label
				ON		post_label.post_id = posts.ID
				AND		post_label.meta_key = '_widgets_label'
				WHERE	posts.post_type = 'page'
				AND		posts.post_status = 'publish'
				AND		posts.post_parent = 0
				ORDER BY posts.menu_order, posts.post_title
				");
			update_post_cache($pages);
		}
		
		if ( !isset($categories) ) {
			$categories = get_terms('category', array('parent' => 0));
		}
		
		extract($instance, EXTR_SKIP);
		
		echo '<p>'
			. '<label>'
			. __('Title:', 'related-widgets') . '<br />' . "\n"
			. '<input type="text" size="20" class="widefat"'
				. ' id="' . $this->get_field_id('title') . '"'
				. ' name="' . $this->get_field_name('title') . '"'
				. ' value="' . esc_attr($title) . '"'
				. ' />'
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. __('Display:', 'related-widgets') . '<br />' . "\n"
			. '<select name="' . $this->get_field_name('type_filter') . '" class="widefat">' . "\n";
		
		echo '<optgroup label="' . __('Posts', 'related-widgets') . '">' . "\n"
			. '<option value="posts"' . selected($type == 'posts' && !$filter, true, false) . '>'
			. __('Related Posts / All Categories', 'related-widgets')
			. '</option>' . "\n";
		
		foreach ( $categories as $category ) {
			echo '<option value="posts-' . intval($category->term_id) . '"'
					. selected($type == 'posts' && $filter == $category->term_id, true, false)
					. '>'
				. sprintf(__('Related Posts / %s', 'related-widgets'), strip_tags($category->name))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '<optgroup label="' . __('Pages', 'related-widgets') . '">' . "\n"
			. '<option value="pages"' . selected($type == 'pages' && !$filter, true, false) . '>'
			. __('Related Pages / All Sections', 'related-widgets')
			. '</option>' . "\n";
		
		foreach ( $pages as $page ) {
			echo '<option value="pages-' . intval($page->ID) . '"'
					. selected($type == 'pages' && $filter == $page->ID, true, false)
					. '>'
				. sprintf(__('Related Pages / %s', 'related-widgets'), strip_tags($page->post_label))
				. '</option>' . "\n";
		}
		
		echo '</optgroup>' . "\n";
		
		echo '</select>' . "\n"
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. sprintf(__('%s Related Items', 'related-widgets'),
				'<input type="text" size="3" name="' . $this->get_field_name('amount') . '"'
					. ' value="' . intval($amount) . '"'
					. ' />')
			. '</label>'
			. '</p>' . "\n";
		
		echo '<p>'
			. '<label>'
			. '<input type="checkbox" name="' . $this->get_field_name('desc') . '"'
				. checked($desc, true, false)
				. ' />'
			. '&nbsp;'
			. __('Show Descriptions', 'related-widgets')
			. '</label>'
			. '</p>' . "\n";
	} # form()
	
	
	/**
	 * defaults()
	 *
	 * @return array $instance default options
	 **/

	function defaults() {
		return array(
			'title' => __('Related Posts', 'related-widgets'),
			'type' => 'posts',
			'filter' => false,
			'amount' => 5,
			'desc' => false,
			'widget_contexts' => array(
				'home' => false,
				'blog' => false,
				'post' => true,
				'page' => true,
				'category' => false,
				'tag' => false,
				'author' => false,
				'archive' => false,
				'search' => false,
				'404_error' => false,
				),
			);
	} # defaults()
	
	
	/**
	 * save_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function save_post($post_id) {
		if ( !get_transient('cached_section_ids') )
			return;
		
		$post_id = (int) $post_id;
		$post = get_post($post_id);
		
		if ( $post->post_type != 'page' )
			return;
		
		$section_id = get_post_meta($post_id, '_section_id', true);
		$refresh = false;
		if ( !$section_id ) {
			$refresh = true;
		} else {
			_get_post_ancestors($post);
			if ( !$post->ancestors ) {
				if ( $section_id != $post_id )
					$refresh = true;
			} elseif ( $section_id != $post->ancestors[0] ) {
				$refresh = true;
			}
		}
		
		if ( $refresh ) {
			global $wpdb;
			if ( !$post->post_parent )
				$new_section_id = $post_id;
			else
				$new_section_id = get_post_meta($post->post_parent, '_section_id', true);
			
			if ( $new_section_id ) {
				update_post_meta($post_id, '_section_id', $new_section_id);
				wp_cache_delete($post_id, 'posts');
				
				# mass-process children
				if ( $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_parent = $post_id AND post_type = 'page' LIMIT 1") )
					delete_transient('cached_section_ids');
			} else {
				# fix corrupt data
				delete_transient('cached_section_ids');
			}
		}
	} # save_post()
	
	
	/**
	 * cache_section_ids()
	 *
	 * @return void
	 **/

	function cache_section_ids() {
		global $wpdb;
		
		$pages = $wpdb->get_results("
			SELECT	*
			FROM	$wpdb->posts
			WHERE	post_type = 'page'
			AND		post_status <> 'trash'
			");
		
		update_post_cache($pages);
		
		$to_cache = array();
		foreach ( $pages as $page )
			$to_cache[] = $page->ID;
		
		update_postmeta_cache($to_cache);
		
		foreach ( $pages as $page ) {
			$parent = $page;
			while ( $parent->post_parent && $parent->ID != $parent->post_parent )
				$parent = get_post($parent->post_parent);
			
			if ( "$parent->ID" !== get_post_meta($page->ID, '_section_id', true) )
				update_post_meta($page->ID, '_section_id', "$parent->ID");
		}
		
		set_transient('cached_section_ids', 1);
	} # cache_section_ids()
	
	
	/**
	 * pre_flush_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function pre_flush_post($post_id) {
		$post_id = (int) $post_id;
		if ( !$post_id )
			return;
		
		$post = get_post($post_id);
		if ( !$post || wp_is_post_revision($post_id) )
			return;
		
		$old = wp_cache_get($post_id, 'pre_flush_post');
		if ( $old === false )
			$old = array();
		
		$update = false;
		foreach ( array(
			'post_title',
			'post_status',
			) as $field ) {
			if ( !isset($old[$field]) ) {
				$old[$field] = $post->$field;
				$update = true;
			}
		}
		
		if ( !isset($old['permalink']) ) {
			$old['permalink'] = apply_filters('the_permalink', get_permalink($post_id));
			$update = true;
		}
		
		foreach ( array(
			'widgets_label', 'widgets_desc',
			'widgets_exclude',
			) as $key ) {
			if ( !isset($old[$key]) ) {
				$old[$key] = get_post_meta($post_id, "_$key", true);
				$update = true;
			}
		}
		
		foreach ( array('post_tag') as $taxonomy ) {
			if ( !isset($o[$taxonomy]) ) {
				$terms = wp_get_object_terms($post_id, $taxonomy);
				$old[$taxonomy] = array();
				if ( !is_wp_error($terms) ) {
					foreach ( $terms as $term )
						$old[$taxonomy][] = $term->term_id;
				}
				$update = true;
			}
		}
		
		
		if ( $update )
			wp_cache_set($post_id, $old, 'pre_flush_post');
	} # pre_flush_post()
	
	
	/**
	 * flush_post()
	 *
	 * @param int $post_id
	 * @return void
	 **/

	function flush_post($post_id) {
		$post_id = (int) $post_id;
		if ( !$post_id )
			return;
		
		# prevent mass-flushing when the permalink structure hasn't changed
		remove_action('generate_rewrite_rules', array('related_widget', 'flush_cache'));
		
		$post = get_post($post_id);
		if ( !$post || wp_is_post_revision($post_id) )
			return;
		
		$old = wp_cache_get($post_id, 'pre_flush_post');
		
		if ( $post->post_status != 'publish' && ( !$old || $old['post_status'] != 'publish' ) )
			return;
		
		if ( $old === false )
			return related_widget::flush_cache();
		
		extract($old, EXTR_SKIP);
		foreach ( array_keys($old) as $key ) {
			switch ( $key ) {
			case 'widgets_label':
			case 'widgets_desc':
			case 'widgets_exclude':
				if ( $$key != get_post_meta($post_id, "_$key", true) )
					return related_widget::flush_cache();
				break;
			
			case 'permalink':
				if ( $$key != apply_filters('the_permalink', get_permalink($post_id)) )
					return related_widget::flush_cache();
				break;
			
			case 'post_tag':
				$terms = wp_get_object_terms($post_id, $key);
				$term_ids = array();
				if ( !is_wp_error($terms) ) {
					foreach ( $terms as $term )
						$term_ids[] = $term->term_id;
				}
				if ( $term_ids != $$key )
					return related_widget::flush_cache();
				break;
			
			case 'post_title':
			case 'post_status':
				if ( $$key != $post->$key )
					return related_widget::flush_cache();
			}
		}
	} # flush_post()
	
	
	/**
	 * flush_cache()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/

	function flush_cache($in = null) {
		static $done = false;
		if ( $done )
			return $in;
		
		$done = true;
		$option_name = 'related_widget';
		
		$widgets = get_option("widget_$option_name");
		
		if ( !$widgets )
			return $in;
		
		unset($widgets['_multiwidget']);
		unset($widgets['number']);
		
		if ( !$widgets )
			return $in;
		
		$cache_ids = array();
		
		global $_wp_using_ext_object_cache;
		foreach ( array_keys($widgets) as $widget_id ) {
			$cache_id = "$option_name-$widget_id";
			delete_post_meta_by_key("_$cache_id");
			if ( $_wp_using_ext_object_cache )
				$cache_ids[] = $cache_id;
		}
		
		if ( $cache_ids ) {
			$post_ids = wp_cache_get('post_ids', 'widget_queries');
			if ( $post_ids === false ) {
				global $wpdb;
				$post_ids = $wpdb->get_col("
					SELECT	ID
					FROM	$wpdb->posts
					WHERE	post_type <> 'revision'
					AND		post_status <> 'trash'
					");
				wp_cache_set('post_ids', $post_ids, 'widget_queries');
			}
			foreach ( $cache_ids as $cache_id ) {
				foreach ( $post_ids as $post_id )
					wp_cache_delete($post_id, $cache_id);
			}
		}
		
		return $in;
	} # flush_cache()
	
	
	/**
	 * upgrade()
	 *
	 * @param array $ops
	 * @return array $ops
	 **/

	function upgrade($ops) {
		$widget_contexts = class_exists('widget_contexts')
			? get_option('widget_contexts')
			: false;
		
		foreach ( $ops as $k => $o ) {
			if ( isset($widget_contexts['related-widget-' . $k]) ) {
				$ops[$k]['widget_contexts'] = $widget_contexts['related-widget-' . $k];
			}
		}
		
		if ( is_admin() ) {
			$sidebars_widgets = get_option('sidebars_widgets', array('array_version' => 3));
		} else {
			if ( !$GLOBALS['_wp_sidebars_widgets'] )
				$GLOBALS['_wp_sidebars_widgets'] = get_option('sidebars_widgets', array('array_version' => 3));
			$sidebars_widgets =& $GLOBALS['_wp_sidebars_widgets'];
		}
		
		$keys = array_keys($ops);
		
		foreach ( $sidebars_widgets as $sidebar => $widgets ) {
			if ( !is_array($widgets) )
				continue;
			foreach ( $keys as $k ) {
				$key = array_search("related-widget-$k", $widgets);
				if ( $key !== false ) {
					$sidebars_widgets[$sidebar][$key] = 'related_widget-' . $k;
					unset($keys[array_search($k, $keys)]);
				}
			}
		}
		
		if ( is_admin() )
			update_option('sidebars_widgets', $sidebars_widgets);
		
		return $ops;
	} # upgrade()
} # related_widget

add_action('widgets_init', array('related_widget', 'widgets_init'));

foreach ( array('post.php', 'post-new.php', 'page.php', 'page-new.php') as $hook )
	add_action('load-' . $hook, array('related_widget', 'editor_init'));

foreach ( array(
		'switch_theme',
		'update_option_active_plugins',
		'update_option_show_on_front',
		'update_option_page_on_front',
		'update_option_page_for_posts',
		'update_option_sidebars_widgets',
		'update_option_sem5_options',
		'update_option_sem6_options',
		'generate_rewrite_rules',
		
		'flush_cache',
		'after_db_upgrade',
		) as $hook )
	add_action($hook, array('related_widget', 'flush_cache'));

add_action('pre_post_update', array('related_widget', 'pre_flush_post'));

foreach ( array(
		'save_post',
		'delete_post',
		) as $hook )
	add_action($hook, array('related_widget', 'flush_post'), 1); // before _save_post_hook()

register_activation_hook(__FILE__, array('related_widget', 'flush_cache'));
register_deactivation_hook(__FILE__, array('related_widget', 'flush_cache'));

add_action('save_post', array('related_widget', 'save_post'));

if ( is_admin() && get_option('related_widgets_activated') === false )
	related_widget::activate();

wp_cache_add_non_persistent_groups(array('widget_queries', 'pre_flush_post'));
?>