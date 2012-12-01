<?php
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################


require_once(DIR . '/includes/blog_functions.php');
require_once(DIR . '/includes/blog_functions_post.php');
require_once(DIR . '/includes/blog_functions_shared.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/class_rss_poster.php');
require_once(DIR . '/includes/functions_wysiwyg.php');


if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0)
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}
@set_time_limit(0);

define('VBBLOG_PERMS', true);


// #############################################################################
// slurp all enabled feeds from the database

$feeds_result = $vbulletin->db->query_read("
	SELECT feed.*, feed.options AS foptions, user.*
	FROM " . TABLE_PREFIX . "ssgtiblogfeedposter AS feed
	INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = feed.userid)
	WHERE feed.feedoptions & " . $vbulletin->bf_misc_ssgtiblogfeedposter2['enabled'] . "
");
while ($feed = $vbulletin->db->fetch_array($feeds_result))
{
	// only process feeds that are due to be run (lastrun + TTL earlier than now)
	if ($feed['lastrun'] < TIMENOW - $feed['ttl'])
	{
		// counter for maxresults
		$feed['counter'] = 0;

		// add to $feeds slurp array
		$feeds["$feed[blogfeedid]"] = $feed;
	}
}
$vbulletin->db->free_result($feeds_result);


// #############################################################################
// extract items from feeds

if (!empty($feeds))
{
	// array of items to be potentially inserted into the database
	$items = array();

	// array to store feed item logs sql
	$feedlog_insert_sql = array();

	// array to store list of inserted items
	$cronlog_items = array();

	// array to store list of users to be updated
	$update_userids = array();

	$feedcount = 0;
	$itemstemp = array();

	($hook = vBulletinHook::fetch_hook('blog_ssgti_feedposter_cron_start')) ? eval($hook) : false;


	foreach (array_keys($feeds) AS $blogfeedid)
	{
		$feed =& $feeds["$blogfeedid"];

		$feed['xml'] =& new vB_RSS_Poster($vbulletin);
		$feed['xml']->fetch_xml($feed['url']);
		if (empty($feed['xml']->xml_string))
		{
			if (defined('IN_CONTROL_PANEL'))
			{
				echo construct_phrase($vbphrase['x_unable_to_open_url'], $feed['title']);
			}
			continue;
		}
		else if ($feed['xml']->parse_xml() === false)
		{
			if (defined('IN_CONTROL_PANEL'))
			{
				echo construct_phrase($vbphrase['x_xml_error_y_at_line_z'], $feed['title'], ($feed['xml']->feedtype == 'unknown' ? 'Unknown Feed Type' : $feed['xml']->xml_object->error_string()), $feed['xml']->xml_object->error_line());
			}
			continue;
		}

		($hook = vBulletinHook::fetch_hook('blog_ssgti_feedposter_cron_feed')) ? eval($hook) : false;


		foreach ($feed['xml']->fetch_items() AS $item)
		{
			// attach the blogfeedid to each item
			$item['blogfeedid'] = $blogfeedid;

			if (!empty($item['summary']))
			{
				// ATOM
				$description = get_item_value($item['summary']);
			}
			elseif (!empty($item['content:encoded']))
			{
				$description = get_item_value($item['content:encoded']);
			}
			elseif (!empty($item['content']))
			{
				$description = get_item_value($item['content']);
			}
			else
			{
				$description = get_item_value($item['description']);
			}

			// backward compatability to RSS
			if (!isset($item['description']))
			{
				$item['description'] = $description;
			}

			if (!isset($item['guid']) AND isset($item['id']))
			{
				$item['guid'] =& $item['id'];
			}

			if (!isset($item['pubDate']))
			{
				if (isset($item['published']))
				{
					$item['pubDate'] =& $item['published'];
				}
				else if(isset($item['updated']))
				{
					$item['pubDate'] =& $item['updated'];
				}
			}


			// attach a content hash to each item
			$item['contenthash'] = (($feed['xml']->feedtype == 'atom') ? md5($item['title']['value'] . $description . $item['link']['href']) : md5($item['title'] . $description . $item['link']));

			// generate unique id for each item
			if (is_array($item['guid']) AND !empty($item['guid']['value']))
			{
				$uniquehash = md5($item['guid']['value']);
			}
			else if (!is_array($item['guid']) AND !empty($item['guid']))
			{
				$uniquehash = md5($item['guid']);
			}
			else
			{
				$uniquehash = $item['contenthash'];
			}


			// add item to the potential insert array
			if ($feed['maxresults'] == 0 OR $feed['counter'] < $feed['maxresults'])
			{
				$feed['counter']++;
				$items["$uniquehash"] = $item;
				$itemstemp["$uniquehash"] = $item;
			}

			if (++$feedcount % 10 == 0 AND !empty($itemstemp))
			{
				$feedlogs_result = $vbulletin->db->query_read("
					SELECT * FROM " . TABLE_PREFIX . "ssgtiblogfeedposter_log
					WHERE uniquehash IN ('" . implode("', '", array_map(array(&$vbulletin->db, 'escape_string'), array_keys($itemstemp))) . "')
				");

				while ($feedlog = $vbulletin->db->fetch_array($feedlogs_result))
				{
					// remove any items which have this unique id from the list of potential inserts.
					unset($items["$feedlog[uniquehash]"]);
				}
				$vbulletin->db->free_result($feedlogs_result);

				$itemstemp = array();
			}


			($hook = vBulletinHook::fetch_hook('blog_ssgti_feedposter_cron_item')) ? eval($hook) : false;
		}
	}


	if (!empty($itemstemp))
	{
		// query feed log table to find items that are already inserted
		$feedlogs_result = $vbulletin->db->query_read("
			SELECT * FROM " . TABLE_PREFIX . "ssgtiblogfeedposter_log
			WHERE uniquehash IN ('" . implode("', '", array_map(array(&$vbulletin->db, 'escape_string'), array_keys($itemstemp))) . "')
		");
		while ($feedlog = $vbulletin->db->fetch_array($feedlogs_result))
		{
			// remove any items with this unique id from the list of potential inserts
			unset($items["$feedlog[uniquehash]"]);
		}
		$vbulletin->db->free_result($feedlogs_result);
	}


	if (!empty($items))
	{
		$error_type = (defined('IN_CONTROL_PANEL') ? ERRTYPE_CP : ERRTYPE_SILENT);
		$feed_logs_inserted = false;

		if (defined('IN_CONTROL_PANEL'))
		{
			echo "<ol>";
		}


		// process the remaining list of items to be inserted
		foreach ($items AS $uniquehash => $item)
		{
			$feed =& $feeds["$item[blogfeedid]"];
			$feed['foptions'] = intval($feed['foptions']);

			$vbulletin->userinfo['bloguserid'] = $feed['userid'];


			if ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['html2bbcode'])
			{
				$body_template = nl2br($feed['bodytemplate']);
			}
			else
			{
				$body_template = $feed['bodytemplate'];
			}

			$pagetext = $feed['xml']->parse_template($body_template, $item);
			if ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['html2bbcode'])
			{
				$pagetext = convert_wysiwyg_html_to_bbcode($pagetext, false, true);
			}

			if ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['parseurl'])
			{
				$pagetext = convert_url_to_bbcode($pagetext);
			}


			// insert the userid of this item into an array for the build_blog_user_counters() function later
			$update_userids["$feed[userid]"] = true;


			if ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['origdateline'])
			{
				$feed['dateline'] = strtotime($item['pubDate']);
			}
			else
			{
				$feed['dateline'] = TIMENOW;
			}


			if (!$poststarttime)
			{
				$poststarttime = $feed['dateline'];
			}

			if (!$posthash)
			{
				$posthash = md5($poststarttime . $feed['userid'] . $feed['salt']);
			}


			$blog_title = $vbulletin->db->query_first_slave("
				SELECT title AS value FROM " . TABLE_PREFIX . "blog_user
				WHERE bloguserid = '" . $feed['userid'] . "'
			");
			$userinfo = fetch_userinfo($feed['userid']);
			$userinfo['bloguserid'] = $feed['userid'];
			$userinfo['blog_title'] = $blog_title['value'];
			cache_permissions($userinfo, false);


			// init blog/blogfirstpost datamanager
			$blogman =& datamanager_init('Blog_Firstpost', $vbulletin, $error_type, 'blog');
			$blogman->set_info('userinfo', $feed);
			$blogman->set_info('blogfeedid', $feed['blogfeedid']);
			$blogman->set_info('is_automated', 'feed');
			$blogman->set_info('skip_charcount', 'feed');
			$blogman->set_info('chop_title', true);
			$blogman->set('userid', $feed['userid']);
			$blogman->set('bloguserid', $feed['userid']);
			$blogman->set('postedby_userid', $feed['userid']);
			$blogman->set('dateline', $feed['dateline']);

			/* Drafts are exempt from initial moderation */
			if ($feed['blogtype'] == 'draft')
			{
				$blogman->set('state', 'draft');
			}
			/* moderation is on, usergroup permissions are following the scheme and its not a moderator who can simply moderate */
			else if (
				(
					$vbulletin->options['vbblog_postmoderation']
						OR
					!($userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_followpostmoderation'])
				)
					AND
				!can_moderate_blog('canmoderateentries', $userinfo)
			)
			{
				$blogman->set('state', 'moderation');
				$blogman->set_bitfield('options', 'membermoderate', true);
			}
			else
			{
				$blogman->set('state', 'visible');
			}

			$blogman->set_bitfield('options', 'allowcomments', ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['allowcomments']));
			$blogman->set_bitfield('options', 'moderatecomments', ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['moderatecomments']));
			$blogman->set_bitfield('options', 'allowpingback', ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['allowpingback']));
			$blogman->set_bitfield('options', 'private', ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['private']));
			$blogman->set('allowsmilie', ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['allowsmilies']));

			$blogman->set_info('posthash', $posthash);
			$blogman->set_info('emailupdate', $feed['emailupdate']);

			$blogman->set('title', strip_bbcode(convert_wysiwyg_html_to_bbcode($feed['xml']->parse_template($feed['titletemplate'], $item))));
			$blogman->set('pagetext', $pagetext);


			($hook = vBulletinHook::fetch_hook('blog_ssgti_feedposter_cron_process')) ? eval($hook) : false;


			if ($blogid = $blogman->save())
			{
				$bloginfo = fetch_bloginfo($blogid);

				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "blog
					SET ssgtiblogfeedposter = '" . $item['blogfeedid'] . "', ssgtiblogfeedposter_dateline = '" . TIMENOW . "'
					WHERE blogid = '$blogid'
				");

				if ($feed['blogtype'] == 'visible' AND fetch_entry_perm('edit', $bloginfo) AND ($feed['foptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter['notify']))
				{
					if ($vbulletin->options['vbblog_notifylinks'] AND $userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansendpingback'])
					{
						$urls = fetch_urls($pagetext);

						$counter = 0;
						foreach($urls AS $url)
						{
							if ($counter >= $vbulletin->options['vbblog_notifylinks'])
							{
								continue;
							}

							$url = htmlspecialchars($url);
							send_ping_notification($bloginfo, $url, $userinfo['blog_title'] ? $userinfo['blog_title'] : $userinfo['userid']);
							$counter++;
						}
					}
				}


				$blogtitle = $blogman->fetch_field('title');
				$bloglink = "../blog.php?b=$blogid";

				if (defined('IN_CONTROL_PANEL'))
				{
					echo "<li><a href=\"$bloglink\" target=\"feed\">$blogtitle</a></li>";
				}

				$feedlog_insert_sql[] = "($item[blogfeedid], $blogid, '" . $feed['blogtype'] . "', '" . $vbulletin->db->escape_string($uniquehash) . "', '" . $vbulletin->db->escape_string($item['contenthash']) . "', " . TIMENOW . ")";
				$cronlog_items["$item[blogfeedid]"][] = "\t<li>" . $vbphrase['ssgti_blogfeedposter_' . $feed['blogtype']] . " <a href=\"$bloglink\" target=\"logview\"><em>$blogtitle</em></a></li>";
			}


			if (!empty($feedlog_insert_sql))
			{
				// insert logs
				$vbulletin->db->query_replace(
					TABLE_PREFIX . 'ssgtiblogfeedposter_log',
					'(blogfeedid, blogid, blogtype, uniquehash, contenthash, dateline)',
					$feedlog_insert_sql
				);
				$feedlog_insert_sql = array();
				$feed_logs_inserted = true;
			}


			unset($blogman, $userinfo, $posthash, $poststarttime, $bloginfo, $helpusers);
		}


		if (defined('IN_CONTROL_PANEL'))
		{
			echo "</ol>";
		}

		if ($feed_logs_inserted)
		{
			// rebuild user counters
			foreach (array_keys($update_userids) AS $userid)
			{
				build_blog_user_counters($userid);
			}


			// build cron log
			$log_items = '<ul class="smallfont">';
			foreach ($cronlog_items AS $blogfeedid => $items)
			{
				$log_items .= "<li><strong>{$feeds[$blogfeedid][title]}</strong><ul class=\"smallfont\">\r\n";
				foreach ($items AS $item)
				{
					$log_items .= $item;
				}
				$log_items .= "</ul></li>\r\n";
			}
			$log_items .= '</ul>';
		}

		if (!empty($feeds))
		{
			// update lastrun time for feeds
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "ssgtiblogfeedposter
				SET lastrun = " . TIMENOW . "
				WHERE blogfeedid IN(" . implode(', ', array_keys($feeds)) . ")
			");
		}
	}
}


($hook = vBulletinHook::fetch_hook('blog_ssgti_feedposter_cron_complete')) ? eval($hook) : false;


// #############################################################################
// all done

if ($log_items)
{
	log_cron_action($log_items, $nextitem, 1);
}
?>