<?php
/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/


Hat tips
--------

	* David Young -- http://www.inspirationaljournal.com


IMPORTANT
---------

1. DO NOT USE THIS PLUGIN FOR COMMERCIAL PURPOSES or otherwise breach Yahoo!ís terms of use:
   http://developer.yahoo.net/terms/
2. Note that your server's IP is eligible to 5,000 calls per 24h
   http://developer.yahoo.net/documentation/rate.html
**/

if ( !class_exists('extract_terms') ) :

class extract_terms
{
	#
	# init()
	#

	function init()
	{
		global $wpdb;
		
		$wpdb->yt_cache = 'yterms';
		
		register_taxonomy('yahoo_terms', 'yterms', array('update_count_callback' => array('extract_terms', 'update_taxonomy_count')));
	} # end init()
	
	
	#
	# update_taxonomy_count()
	#
	
	function update_taxonomy_count($terms)
	{
		global $wpdb;
		
		if ( !method_exists($wpdb, 'prepare') ) return;
		
		foreach ( $terms as $term ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type IN ('post', 'page') AND term_taxonomy_id = %d", $term ) );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
		}
	} # update_taxonomy_count()


	#
	# get_terms()
	#

	function get_terms($context = '', $query = '')
	{
		# clean up
		
		foreach ( array('context', 'query') as $var )
		{
			foreach ( array('script', 'style') as $junk )
			{
				$$var = preg_replace("/
					<\s*$junk\b
					.*
					<\s*\/\s*$junk\s*>
					/isUx", '', $$var);
			}
			$$var = strip_tags($$var);
			$$var = html_entity_decode($$var, ENT_NOQUOTES);
			$$var = str_replace("\r", "\n", $$var);
			$$var = trim($$var);
		}

		# query vars

		$vars = array(
			"appid" => "WordPress/Extract Terms Plugin (http://www.semiologic.com)",
			"context" => $context,
			"query" => $query
			);
		
		global $wpdb;
		
		if ( get_option('yt_cache_created') < 2 )
		{
			$charset_collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				if ( ! empty($wpdb->charset) )
					$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
				if ( ! empty($wpdb->collate) )
					$charset_collate .= " COLLATE $wpdb->collate";
			}
			
			$old_yt_cache = $wpdb->prefix . 'yt_cache';
			
			$wpdb->query("
				DROP TABLE IF EXISTS $old_yt_cache;
			");
			$wpdb->query("
				CREATE TABLE $wpdb->yt_cache (
					cache_id		varchar(32) PRIMARY KEY,
					cache_content	text NOT NULL DEFAULT ''
				) $charset_collate;
				");
			
			update_option('yt_cache_created', 2);
		}
		
		$cache_id = md5(preg_replace("/\s+/", " ", $context . $query));

		if ( !( $xml = $wpdb->get_var("SELECT cache_content FROM $wpdb->yt_cache WHERE cache_id = '$cache_id'") ) )
		{
			# Process content

			foreach ( $vars as $key => $value )
			{
				$content .= rawurlencode($key)
					. "=" . rawurlencode($value)
					. ( ( ++$i < sizeof($vars) )
						? "&"
						: ""
						);
			}

			# Build header

			$headers = "POST /ContentAnalysisService/V1/termExtraction HTTP/1.1
Accept: */*
Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "
User-Agent: " . $vars['appid'] . "
Host: api.search.yahoo.com
Connection: Keep-Alive
Cache-Control: no-cache
Content-Length: " . strlen($content) . "

";

			# Open socket connection

			$fp = @fsockopen("api.search.yahoo.com", 80);

			# Discard the call if it times out

			if ( !$fp )
			{
				return false;
			}

			# Send headers and content

			fputs($fp, $headers);
			fputs($fp, $content);

			# Retrieve the result

			$xml = "";
			while ( !feof($fp) )
			{
				$xml .= fgets($fp, 1024);
			}
			fclose($fp);

			# Clean up

			$xml = preg_replace("/^[^<]*|[^>]*$/", "", $xml);

			# Cache
			
			$wpdb->query("INSERT INTO $wpdb->yt_cache (cache_id, cache_content) VALUES ( '$cache_id', '" . $wpdb->escape($xml) . "');");
		}

		preg_match_all("/<Result>([^<]+)<\/Result>/", $xml, $out);

		$terms = end($out);

		return $terms;
	} # end get_terms();


	#
	# get_post_terms()
	#

	function get_post_terms($post = null)
	{
		if ( !isset($post) )
		{
			if ( in_the_loop() )
			{
				$post =& $GLOBALS['post'];
			}
			elseif ( is_singular() )
			{
				$post = get_post($GLOBALS['wp_query']->get_queried_object_id());
			}
			else
			{
				return array();
			}
		}

		return extract_terms::get_terms($post->post_title . "\n\n" . apply_filters('the_content', $post->post_content));
	}
} # extract_terms

extract_terms::init();

endif;
?>