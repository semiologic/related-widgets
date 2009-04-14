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
			$plugin_path = plugin_dir_url(__FILE__);
			
			wp_enqueue_script( 'page_tags', $plugin_path . 'page-tags.js', array('suggest', 'jquery-ui-tabs', 'wp-lists'), '20090414' );

			wp_localize_script( 'page_tags', 'page_tagsL10n', array(
				'tagsUsed' =>  __('Tags used on this page:'),
				'addTag' => attribute_escape(__('Add new tag')),
			));
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

	function display_page_tags($post)
	{
		$tax_name = 'post_tag';
		$taxonomy = get_taxonomy($tax_name);
		$helps = isset($taxonomy->helps) ? attribute_escape($taxonomy->helps) : __('Separate tags with commas.');
		?>
		<div class="tagsdiv" id="<?php echo $tax_name; ?>">
			<p class="jaxtag">
				<label class="hidden" for="newtag"><?php _e( 'Page Tags' ); ?></label>
				<input type="hidden" name="<?php echo "tax_input[$tax_name]"; ?>" class="the-tags" id="tax-input[<?php echo $tax_name; ?>]" value="<?php echo get_terms_to_edit( $post->ID, $tax_name ); ?>" />

			<span class="ajaxtag">
				<input type="text" name="newtag[<?php echo $tax_name; ?>]" class="newtag form-input-tip" size="16" autocomplete="off" value="<?php _e('Add new tag'); ?>" />
				<input type="button" class="button tagadd" value="<?php _e('Add'); ?>" tabindex="3" />
			</span></p>
			<p class="howto"><?php echo $helps; ?></p>
			<div class="tagchecklist"></div>
		</div>
		<p class="tagcloud-link hide-if-no-js"><a href="#titlediv" class="tagcloud-link" id="link-<?php echo $tax_name; ?>"><?php printf( __('Choose from the most used tags in %s'), __('Post Tags') ); ?></a></p>
		<?php	
	} # page_tags()


} # page_tags

page_tags::init();
?>