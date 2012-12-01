<?php
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 11111 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('vbblogglobal');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_functions_shared.php');

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderation']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'feeds';
}

if (!can_moderate_blog('ssgtifeedpostercanedit') OR !($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
{
	print_stop_message('no_permission');
}

$starttime = mktime(0, 0, 0, date('m'), date('d'), date('Y'));


// ###################### Start attachment moderation #######################
if ($_REQUEST['do'] == 'feeds')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'filter' => TYPE_STR,
		'userid' => TYPE_UINT,
	));

	print_form_header('blog_ssgtifeedposter', 'dofeeds');
	construct_hidden_code('filter', $vbulletin->GPC['filter']);
	construct_hidden_code('userid', $vbulletin->GPC['userid']);
	print_table_header($vbphrase['ssgti_blogfeedposter_feeds_moderation'], 9);


	switch ($vbulletin->GPC['filter'])
	{
		case 'alfeeds':
			$whereclause = "WHERE feed.valid = 1";
			break;
		case 'mdfeeds':
			$whereclause = "WHERE feed.valid = 0";
			break;
		case 'acfeeds':
			$whereclause = "WHERE (feed.feedoptions & " . $vbulletin->bf_misc_ssgtiblogfeedposter2['enabled'] . ")";
			break;
		case 'dsfeeds':
			$whereclause = "WHERE NOT (feed.feedoptions & " . $vbulletin->bf_misc_ssgtiblogfeedposter2['enabled'] . ")";
			break;
		case 'tdfeeds':
			$whereclause = "WHERE feed.dateline >= $starttime";
			break;
		case 'rtfeeds':
			$whereclause = "WHERE feed.lastrun >= $starttime";
			break;
		default:
			$whereclause = "";
			break;
	}


	if ($users = $db->query_read("
		SELECT feed.userid, user.username FROM " . TABLE_PREFIX . "ssgtiblogfeedposter AS feed
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING(userid)
		$whereclause
		" . (($vbulletin->GPC['userid'] AND $whereclause) ? " AND userid = " . $vbulletin->GPC['userid'] : ($vbulletin->GPC['userid'] ? " WHERE userid = " . $vbulletin->GPC['userid'] : "")) . "
		GROUP BY userid
		ORDER BY user.username, feed.userid
	"))
	{
		print_cells_row(array(
			'<span style="float: ' . $stylevar['right'] . '">' . $vbphrase['edit'] . '</span>' . $vbphrase['ssgti_blogfeedposter_blog_feed'],
			$vbphrase['ssgti_blogfeedposter_feed_blogtype'],
			$vbphrase['ssgti_blogfeedposter_feed_checked_every'],
			$vbphrase['display_order'],
			$vbphrase['ssgti_blogfeedposter_lastrun'],
			$vbphrase['ssgti_blogfeedposter_status'],
			$vbphrase['ssgti_blogfeedposter_moderation'],
			$vbphrase['ssgti_blogfeedposter_reset'],
			$vbphrase['ssgti_blogfeedposter_delete']
		), 1, 'tcat');

		$done = FALSE;
		while ($user = $db->fetch_array($users))
		{
			print_description_row('<span class="smallfont"><strong>' . ($user['username'] ? '<a href="' . (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) ? "../" . $vbulletin->config['Misc']['admincpdir'] . "/" : "") . 'user.php?' . $vbulletin->session->vars['sessionurl'] . 'do=edit&u=' . $user['userid'] . '" target="_blank">' . $user['username'] . '</a>' : '&nbsp;') . '</strong></span>', 0, 9, 'thead');

			$feeds = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "ssgtiblogfeedposter AS feed " . ($whereclause ? $whereclause . " AND " : " WHERE ") . "userid = $user[userid] ORDER BY displayorder");
			while ($feed = $db->fetch_array($feeds))
			{
				$x = @parse_url($feed['url']);
				$date = vbdate($vbulletin->options['dateformat'], $feed['lastrun'], true);
				$time = vbdate($vbulletin->options['timeformat'], $feed['lastrun']);
				$feed['enabled'] = (($feed['feedoptions'] & $vbulletin->bf_misc_ssgtiblogfeedposter2['enabled']) ? ' checked="checked"' : '');
				$feed['approved'] = ($feed['valid'] ? ' checked="checked"' : '');

				print_cells_row(array('<span style="float: ' . $stylevar['right'] . '"><a href="../blog_ssgtifeedposter.php?' . $vbulletin->session->vars['sessionurl'] . 'do=modifyfeed&amp;blogfeedid=' . $feed['blogfeedid'] . '" target="_blank">' . $vbphrase['edit'] . '</a></span><div><a href="../blog_ssgtifeedposter.php?' . $vbulletin->session->vars['sessionurl'] . 'do=modifyfeed&amp;blogfeedid=' . $feed['blogfeedid'] . '" title="' . $feed['url'] . '" target="_blank"><strong>' . $feed['title'] . '</strong></a></div><div class="smallfont"><a href="' . $feed['url'] . '" target="feed">' . $x['host'] . '</a></div>',
					'<div align="center">' . $vbphrase['ssgti_blogfeedposter_' . $feed['blogtype']] . '</div>',
					'<div align="center">' . construct_phrase($vbphrase['ssgti_blogfeedposter_x_minutes'], $feed['ttl'] / 60) . '</div>',
					'<div align="center"><input class="bginput" type="text" size="2" maxlength="3" name="displayorder[' . $feed['blogfeedid'] . ']" value="' . $feed['displayorder'] . '" style="text-align: center" /></div>',
					'<div align="center">' . ($feed['lastrun'] ? $date . ($vbulletin->options['yestoday'] == 2 ? '' : ", $time") : "N/A") . '</div>',
					'<div align="center"><label for="cb_enabled[' . $feed['blogfeedid'] . ']"><input type="checkbox" name="enabled[' . $feed['blogfeedid'] . ']" value="1" id="cb_enabled[' . $feed['blogfeedid'] . ']" tabindex="12" ' . $feed['enabled'] . ' />' . $vbphrase['ssgti_blogfeedposter_enabled'] . '</label><input type="hidden" name="set_enabled[' . $feed['blogfeedid'] . ']" value="1" /></div>',
					'<div align="center"><label for="cb_approved[' . $feed['blogfeedid'] . ']"><input type="checkbox" name="approved[' . $feed['blogfeedid'] . ']" value="1" id="cb_approved[' . $feed['blogfeedid'] . ']" tabindex="12" ' . $feed['approved'] . ' />' . $vbphrase['ssgti_blogfeedposter_approved'] . '</label><input type="hidden" name="set_approved[' . $feed['blogfeedid'] . ']" value="1" /></div>',
					'<div align="center"><input type="checkbox" name="reset[' . $feed['blogfeedid'] . ']" value="1" id="cb_reset[' . $feed['blogfeedid'] . ']" tabindex="12" /><input type="hidden" name="set_reset[' . $feed['blogfeedid'] . ']" value="1" /></div>',
					'<div align="center"><input type="checkbox" name="delete[' . $feed['blogfeedid'] . ']" value="1" id="cb_delete[' . $feed['blogfeedid'] . ']" tabindex="12" /><input type="hidden" name="set_delete[' . $feed['blogfeedid'] . ']" value="1" /></div>',
				));
			}

			$done = TRUE;
		}

		if (!$done)
		{
			print_description_row('<div align="center">' . $vbphrase['ssgti_blogfeedposter_no_feeds_found'] . '</div>', 0, 9);
			print_table_footer(9);
		}
		else
		{
			print_submit_row($vbphrase['save_changes'], $vbphrase['reset'], 9);
		}
	}
	else
	{
		print_cp_message($vbphrase['ssgti_blogfeedposter_no_feeds_found']);
	}
}


// ###################### Start do attachment moderation #######################
if ($_POST['do'] == 'dofeeds')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'displayorder' => TYPE_ARRAY_UINT,
		'enabled'      => TYPE_ARRAY_UINT,
		'set_enabled'  => TYPE_ARRAY_UINT,
		'approved'     => TYPE_ARRAY_UINT,
		'set_approved' => TYPE_ARRAY_UINT,
		'runnow'       => TYPE_ARRAY_UINT,
		'set_runnow'   => TYPE_ARRAY_UINT,
		'reset'        => TYPE_ARRAY_UINT,
		'set_reset'    => TYPE_ARRAY_UINT,
		'delete'       => TYPE_ARRAY_UINT,
		'set_delete'   => TYPE_ARRAY_UINT,
		'filter'       => TYPE_STR,
		'userid'       => TYPE_UINT,
	));


	foreach ($vbulletin->GPC['set_enabled'] AS $key => $val)
	{
		$dataman =& datamanager_init('Blog_Ssgti_Feedposter', $vbulletin, ERRTYPE_ARRAY);
		$value = $vbulletin->GPC['enabled']["$key"];
		$dataman->set_condition("blogfeedid = '" . $key . "'");
		$dataman->set_bitfield('feedoptions', 'enabled', $value);
		$dataman->save();
		unset($dataman);
	}

	foreach ($vbulletin->GPC['set_approved'] AS $key => $val)
	{
		$dataman =& datamanager_init('Blog_Ssgti_Feedposter', $vbulletin, ERRTYPE_ARRAY);
		$value = $vbulletin->GPC['approved']["$key"];
		$dataman->set_condition("blogfeedid = '" . $key . "'");
		$dataman->set('valid', $value);
		$dataman->save();
		unset($dataman);
	}


	$finalresetids = '';
	foreach ($vbulletin->GPC['set_reset'] AS $key => $val)
	{
		if (isset($vbulletin->GPC['reset']["$key"]))
		{
			$finalresetids .= ",$key";
		}
	}

	$dataman =& datamanager_init('Blog_Ssgti_Feedposter', $vbulletin, ERRTYPE_ARRAY);
	$dataman->condition = "blogfeedid IN (0$finalresetids)";
	$dataman->set('lastrun', 0);
	$dataman->save();
	unset($dataman);


	$finaldeleteids = '';
	foreach ($vbulletin->GPC['set_delete'] AS $key => $val)
	{
		if (isset($vbulletin->GPC['delete']["$key"]))
		{
			$finaldeleteids .= ",$key";
		}
	}

	$dataman =& datamanager_init('Blog_Ssgti_Feedposter', $vbulletin, ERRTYPE_ARRAY);
	$dataman->condition = "blogfeedid IN (0$finaldeleteids)";
	$dataman->delete();
	unset($dataman);


	// Update Display Order
	$casesql = array();
	foreach ($vbulletin->GPC['displayorder'] AS $blogfeedid => $displayorder)
	{
		$casesql[] = " WHEN blogfeedid = " . intval($blogfeedid) . " THEN $displayorder";
	}

	if (!empty($casesql))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "ssgtiblogfeedposter
			SET displayorder =
			CASE
				" . implode("\r\n", $casesql) . "
				ELSE displayorder
			END
		");
	}


	define('CP_REDIRECT', 'blog_ssgtifeedposter.php?do=feeds&filter=' . ($vbulletin->GPC['filter'] ? $vbulletin->GPC['filter'] : 'all') . ($vbulletin->GPC['userid'] ? "&amp;u=" . $vbulletin->GPC['userid'] : ''));
	print_stop_message('ssgti_blogfeedposter_saved_feeds_successfully');
}

print_cp_footer();
?>
