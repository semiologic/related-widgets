<?php
class page_tags
{
	#
	# init()
	#

	function init()
	{
		add_action('admin_menu', array('page_tags', 'meta_boxes'));
		
		add_action('admin_print_scripts', array('page_tags', 'register_scripts'));
	} # init()
	

	#
	# register_scripts()
	#

	function register_scripts()
	{
		global $wp_scripts;

		if ( is_object($wp_scripts)
			&& $wp_scripts->query( 'page', 'queue' ) //in_array('page', $wp_scripts->queue)
			)
		{
			$plugin_path = plugin_basename(__FILE__);
			$plugin_path = preg_replace("/[^\/]+$/", '', $plugin_path);
			$plugin_path = '/wp-content/plugins/' . $plugin_path;

			wp_enqueue_script( 'page_tags', $plugin_path . 'page-tags.js', array('suggest', 'jquery-ui-tabs', 'wp-lists'), '20080221' );

			wp_localize_script( 'page_tags', 'page_tagsL10n', array(
				'tagsUsed' =>  __('Tags used on this page:'),
				'add' => attribute_escape(__('Add')),
				'addTag' => attribute_escape(__('Add new tag')),
				'separate' => __('Separate tags with commas'),
			) );
		}
	} # register_scripts()


	#
	# meta_boxes()
	#

	function meta_boxes()
	{
		if ( !defined('page_tags_added') )
		{
			add_meta_box('tagsdiv', 'Tags', array('page_tags', 'display_page_tags'), 'page', 'normal');
			if ( class_exists('autotag') )
			{
				add_meta_box('autotag', 'Autotag', array('autotag', 'entry_editor'), 'page', 'normal');
			}

			define('page_tags_added', true);
		}
	} # meta_boxes()


	#
	# page_tags()
	#

	function display_page_tags()
	{
		$post_ID = isset($GLOBALS['post_ID']) ? $GLOBALS['post_ID'] : $GLOBALS['temp_ID'];
?>
		<p id="jaxtag"><input type="text" name="tags_input" class="tags-input" id="tags-input" size="40" tabindex="3" value="<?php echo get_tags_to_edit( $post_ID ); ?>" /></p>
		<p id="tagchecklist"></p>
<?php		
	} # page_tags()


} # page_tags

page_tags::init();
?>