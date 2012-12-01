<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('VB_PRODUCT', 'vbblog');
define('THIS_SCRIPT', 'phpkd_vbbfp');
define('CSRF_PROTECTION', true);
define('VBBLOG_PERMS', true);
define('VBBLOG_STYLE', true);
define('VBBLOG_SCRIPT', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('vbblogglobal');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'BLOG',
	'blog_css',
	'blog_cp_css',
	'blog_usercss',
	'blog_header_custompage_link',
	'blog_sidebar_category_link',
	'blog_sidebar_comment_link',
	'blog_sidebar_custompage_link',
	'blog_sidebar_entry_link',
	'blog_sidebar_user',
	'blog_sidebar_user_block_archive',
	'blog_sidebar_user_block_category',
	'blog_sidebar_user_block_comments',
	'blog_sidebar_user_block_entries',
	'blog_sidebar_user_block_search',
	'blog_sidebar_user_block_tagcloud',
	'blog_sidebar_user_block_visitors',
	'blog_sidebar_user_block_custom',
	'blog_sidebar_calendar',
	'blog_sidebar_calendar_day',
	'blog_tag_cloud_link',
	'ad_blogsidebar_start',
	'ad_blogsidebar_middle',
	'ad_blogsidebar_end',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editfeeds' => array(
		'phpkd_vbbfp_editfeeds',
		'phpkd_vbbfp_editfeeds_bits',
	),
	'modifyfeed' => array(
		'phpkd_vbbfp_modifyfeed',
	),
	'updatefeed' => array(
		'phpkd_vbbfp_modifyfeed',
	),
	'addfeed' => array(
		'newpost_preview',
		'newpost_errormessage',
		'phpkd_vbbfp_modifyfeed',
		'phpkd_vbbfp_preview_bits',
	),
);

$actiontemplates['none'] =& $actiontemplates['editfeeds'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');
require_once(DIR . '/includes/blog_functions_phpkd_vbbfp.php');

verify_blog_url();

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ### STANDARD INITIALIZATIONS ###
$checked = array();

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'editfeeds';
}

if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']) OR !$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

$show['moderatecomments'] = (!$vbulletin->options['vbblog_commentmoderation'] AND $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_followcommentmoderation'] ? true : false);
$show['pingback'] = ($vbulletin->options['vbblog_pingback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['trackback'] = ($vbulletin->options['vbblog_trackback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['notify'] = ($vbulletin->options['vbblog_notifylinks'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansendpingback'] AND $vbulletin->userinfo['guest_canviewmyblog'] ? true : false);
$show['parseurl'] = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode']);
$show['canmoderatefeeds'] = (can_moderate_blog('phpkd_vbbfp_canedit') OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']));
$show['candeletefeed'] = ($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['candelete']);
$show['canrunfeed'] = ($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['canrunnow']);
$show['canresetfeed'] = ($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['canreset']);
$show['canmassmanage'] = ($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['canmassmanage']);


($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_start')) ? eval($hook) : false;


// ############################################################################
// ################################  EDIT Feeds  ##############################
// ############################################################################
if ($_REQUEST['do'] == 'editfeeds')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid' => TYPE_UINT,
	));

	if ($vbulletin->GPC['userid'] AND $vbulletin->GPC['userid'] != $vbulletin->userinfo['userid'] AND can_moderate_blog('phpkd_vbbfp_canedit'))
	{
		$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
		cache_permissions($userinfo, false);
		$show['modedit'] = true;
	}
	else
	{
		$userinfo =& $vbulletin->userinfo;
		if (
			!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
				OR
			!($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['cancreate'])
		)
		{
			print_no_permission();
		}
		$show['blogcp'] = true;
	}

	phpkd_vbbfp_fetch_ordered_feeds($userinfo['userid']);

	$feedbits = '';
	foreach ($vbulletin->vbblog['phpkd_vbbfp_cache']["{$userinfo['userid']}"] AS $blogfeedid => $feed)
	{
		$parsedurl = @parse_url($feed['url']);
		$feed['checkedevery'] = $feed['ttl'] / 60;

		if ($feed['lastrun'])
		{
			$date = vbdate($vbulletin->options['dateformat'], $feed['lastrun'], true);
			$time = vbdate($vbulletin->options['timeformat'], $feed['lastrun']);
			$datestring = $date . ($vbulletin->options['yestoday'] == 2 ? '' : ", $time");
		}
		else
		{
			$datestring = "N/A";
		}

		$blogtype = $vbphrase['phpkd_vbbfp_' . $feed['blogtype']];
		$feed['enabled'] = (($feed['feedoptions'] & $vbulletin->bf_misc_phpkd_vbbfp2['enabled']) ? ' checked="checked"' : '');
		$feed['approved'] = ($feed['valid'] ? ' checked="checked"' : '');

		if ($feed['userid'] == 0)
		{
			// admin Feed
			continue;
		}

		$templater = vB_Template::create('phpkd_vbbfp_editfeeds_bits');
			$templater->register('blogfeedid', $blogfeedid);
			$templater->register('feed', $feed);
			$templater->register('parsedurl', $parsedurl);
			$templater->register('blogtype', $blogtype);
			$templater->register('datestring', $datestring);
		$feedbits .= $templater->render();
	}

	$phpkd_vbbfp_count = $vbulletin->vbblog['phpkd_vbbfp_count'][$userinfo['userid']];


	// Sidebar
	$sidebar =& build_user_sidebar($userinfo);

	if ($userinfo['userid'] == $vbulletin->userinfo['userid'])
	{
		$navbits = array('' => $vbphrase['phpkd_vbbfp_blog_feeds']);
	}
	else
	{
		$navbitsdone = true;
		$navbits = array(
				fetch_seo_url('bloghome', array())  => $vbphrase['blogs'],
				fetch_seo_url('blog', $userinfo)  => $userinfo['blog_title'],
				'' => $vbphrase['phpkd_vbbfp_blog_feeds'],
		);
	}

	($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_editfeeds')) ? eval($hook) : false;

	$templater = vB_Template::create('phpkd_vbbfp_editfeeds');
		$templater->register('feedbits', $feedbits);
		$templater->register('userinfo', $userinfo);
		$templater->register('bbuserinfo', $bbuserinfo);
		$templater->register('phpkd_vbbfp_count', $phpkd_vbbfp_count);
	$content = $templater->render();
}


// ############################################################################
// ################################  ADD Feeds  ###############################
// ############################################################################
if ($_POST['do'] == 'addfeed')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'blogfeedid'    => TYPE_UINT,
		'title'         => TYPE_STR,
		'description'   => TYPE_STR,
		'displayorder'  => TYPE_UINT,
		'url'           => TYPE_STR,
		'valid'         => TYPE_UINT,
		'dateline'      => TYPE_UINT,
		'port'          => TYPE_UINT,
		'ttl'           => TYPE_UINT,
		'maxresults'    => TYPE_UINT,
		'userid'        => TYPE_UINT,
		'titletemplate' => TYPE_STR,
		'bodytemplate'  => TYPE_STR,
		'searchwords'   => TYPE_STR,
		'blogtype'      => TYPE_STR,
		'emailupdate'   => TYPE_STR,
		'feedoptions'   => TYPE_ARRAY_BOOL,
		'set_options'   => TYPE_ARRAY_BOOL,
		'options'       => TYPE_ARRAY_BOOL,
		'lastrun'       => TYPE_UINT,
		'dodelete'      => TYPE_STR,
		'delete'        => TYPE_BOOL,
		'domoderate'    => TYPE_STR,
		'approve'       => TYPE_BOOL,
		'disapprove'    => TYPE_BOOL,
		'doreset'       => TYPE_STR,
		'reset'         => TYPE_BOOL,
		'dorunnow'      => TYPE_STR,
		'runnow'        => TYPE_BOOL,
		'dopreview'     => TYPE_STR
	));

	if ($vbulletin->GPC['userid'] AND $vbulletin->GPC['userid'] != $vbulletin->userinfo['userid'] AND can_moderate_blog('phpkd_vbbfp_canedit'))
	{
		$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
		cache_permissions($userinfo, false);
		$show['modedit'] = true;
	}
	else
	{
		$userinfo =& $vbulletin->userinfo;
		if (
			!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
				OR
			!($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['cancreate'])
		)
		{
			print_no_permission();
		}
		$show['blogcp'] = true;
	}

	$errors = array();

	phpkd_vbbfp_fetch_ordered_feeds($userinfo['userid']);

	$dataman =& datamanager_init('Blog_PHPKD_VBBFP', $vbulletin, ERRTYPE_ARRAY);

	($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_addfeed_first')) ? eval($hook) : false;


	if ($vbulletin->GPC['dopreview'])
	{
		define('PREVIEW', 1);
		$_REQUEST['do'] = 'modifyfeed';
	}

	if ($vbulletin->GPC['blogfeedid'])
	{
		if ($feedinfo = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "phpkd_vbbfp
			WHERE blogfeedid = " . $vbulletin->GPC['blogfeedid'] . "
				AND userid = $userinfo[userid]
		"))
		{
			$dataman->set_existing($feedinfo);

			if ($vbulletin->GPC['domoderate'] AND $show['canmoderatefeeds'])
			{
				if ($vbulletin->GPC['approve'] OR $vbulletin->GPC['disapprove'])
				{
					$dataman->set_condition("blogfeedid = '" . $vbulletin->GPC['blogfeedid'] . "'");
					$dataman->set('valid', ($vbulletin->GPC['approve'] ? 1 : 0));
					$dataman->save();
					$show['dodone'] = true;
				}
				else
				{
					define('PREVIEW', 1);
					$_REQUEST['do'] = 'modifyfeed';
				}
			}

			if ($vbulletin->GPC['dodelete'] AND ($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['candelete'] OR $show['canmoderatefeeds']))
			{
				if ($vbulletin->GPC['delete'])
				{
					$dataman->set_condition("blogfeedid = '" . $vbulletin->GPC['blogfeedid'] . "'");
					$dataman->delete();
					$show['dodone'] = true;
				}
				else
				{
					define('PREVIEW', 1);
					$_REQUEST['do'] = 'modifyfeed';
				}
			}

			if ($vbulletin->GPC['doreset'] AND ($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['canreset'] OR $show['canmoderatefeeds']))
			{
				if ($vbulletin->GPC['reset'])
				{
					$dataman->set_condition("blogfeedid = '" . $vbulletin->GPC['blogfeedid'] . "'");
					$dataman->set('lastrun', 0);
					$dataman->save();
					$show['dodone'] = true;
				}
				else
				{
					define('PREVIEW', 1);
					$_REQUEST['do'] = 'modifyfeed';
				}
			}

			if ($show['dodone'])
			{
				$vbulletin->url = 'blog_phpkd_vbbfp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editfeeds' . ($vbulletin->GPC['userid'] ? "&amp;u=$userinfo[userid]" : '');
				print_standard_redirect('redirect_blog_profileupdate');
			}
		}
		else
		{
			standard_error(fetch_error('invalidid', 'blogfeedid', $vbulletin->options['contactuslink']));
		}
	}
	else
	{
		$count = 0;
		foreach($vbulletin->vbblog['phpkd_vbbfp_cache'][$userinfo['userid']] AS $feedcheck)
		{
			if ($feedcheck['userid'] == $userinfo['userid'])
			{
				$count++;
			}
		}

		if ($count >= $userinfo['permissions']['phpkd_vbbfp_max'])
		{
			standard_error(fetch_error('phpkd_vbbfp_blog_feed_limit', $userinfo['permissions']['phpkd_vbbfp_max']));
		}

		$dataman->set('userid', $userinfo['userid']);
	}


	// Check Feed URL Validity
	require_once(DIR . '/includes/class_rss_poster.php');
	$xml = new vB_RSS_Poster($vbulletin);
	$xml->fetch_xml($vbulletin->GPC['url']);
	if (empty($xml->xml_string))
	{
		$dataman->error('unable_to_open_url');
	}
	else if ($xml->parse_xml() === false)
	{
		$dataman->error('xml_error_x_at_line_y', ($xml->feedtype == 'unknown' ? 'Unknown Feed Type' : $xml->xml_object->error_string()), $xml->xml_object->error_line());
	}


	($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_addfeed_second')) ? eval($hook) : false;


	if (empty($errors) AND !defined('PREVIEW'))
	{
		$dataman->set('title', $vbulletin->GPC['title']);
		$dataman->set('description', $vbulletin->GPC['description']);
		$dataman->set('displayorder', $vbulletin->GPC['displayorder']);
		$dataman->set('url', $vbulletin->GPC['url']);

		if (!$vbulletin->GPC['blogfeedid'])
		{
			$dataman->set('valid', !($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['moderatefeeds']));
		}

		$dataman->set('dateline', TIMENOW);
		$dataman->set('port', $vbulletin->GPC['port']);
		$dataman->set('ttl', $vbulletin->GPC['ttl']);
		$dataman->set('maxresults', $vbulletin->GPC['maxresults']);
		$dataman->set('titletemplate', $vbulletin->GPC['titletemplate']);
		$dataman->set('bodytemplate', $vbulletin->GPC['bodytemplate']);
		$dataman->set('searchwords', $vbulletin->GPC['searchwords']);
		$dataman->set('blogtype', $vbulletin->GPC['blogtype']);
		$dataman->set('emailupdate', $vbulletin->GPC['emailupdate']);


		// Feed Main Options bitfield(s)
		foreach($vbulletin->GPC['feedoptions'] AS $key => $val)
		{
			$dataman->set_bitfield('feedoptions', $key, $val);
		}

		// Feed Entry Options bitfield(s)
		foreach ($vbulletin->bf_misc_phpkd_vbbfp AS $key => $val)
		{
			if (isset($vbulletin->GPC['options']["$key"]) OR isset($vbulletin->GPC['set_options']["$key"]))
			{
				$value = $vbulletin->GPC['options']["$key"];
				$dataman->set_bitfield('options', $key, $value);
			}
		}


		$dataman->pre_save();

		if (!empty($dataman->errors))
		{
			define('PREVIEW', 1);
			$_REQUEST['do'] = 'modifyfeed';
			require_once(DIR . '/includes/functions_newpost.php');
			$errorlist = construct_errors($dataman->errors);
		}
		else
		{
			$dataman->save();
			unset($dataman);

			$vbulletin->url = 'blog_phpkd_vbbfp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editfeeds' . ($show['modedit'] ? "&amp;u=$userinfo[userid]" : '');
			print_standard_redirect('redirect_blog_profileupdate');
		}
	}
}


// ############################################################################
// ################################  UPDATE Feeds  ############################
// ############################################################################
if ($_POST['do'] == 'updatefeed')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'addfeed'      => TYPE_STR,
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
		'userid'       => TYPE_UINT,
	));


	($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_updatefeed')) ? eval($hook) : false;


	if ($vbulletin->GPC['userid'] AND $vbulletin->GPC['userid'] != $vbulletin->userinfo['userid'] AND can_moderate_blog('phpkd_vbbfp_canedit'))
	{
		$userinfo = fetch_userinfo($vbulletin->GPC['userid']);
		cache_permissions($userinfo, false);
		$show['modedit'] = true;
	}
	else
	{
		$userinfo =& $vbulletin->userinfo;
		if (
			!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
				OR
			!($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['cancreate'])
		)
		{
			print_no_permission();
		}
		$show['blogcp'] = true;
	}

	if ($vbulletin->GPC['addfeed'])
	{
		// Add New FEED
		$_REQUEST['do'] = 'modifyfeed';
		define('CLICKED_ADD_BUTTON', true);
	}
	else
	{
		if ($show['canmoderatefeeds'] OR $show['canmassmanage'])
		{
			foreach ($vbulletin->GPC['set_enabled'] AS $key => $val)
			{
				$dataman =& datamanager_init('Blog_PHPKD_VBBFP', $vbulletin, ERRTYPE_ARRAY);
				$value = $vbulletin->GPC['enabled']["$key"];
				$dataman->set_condition("blogfeedid = '" . $key . "'");
				$dataman->set_bitfield('feedoptions', 'enabled', $value);
				$dataman->save();
				unset($dataman);
			}
		}

		if ($show['canmoderatefeeds'])
		{
			foreach ($vbulletin->GPC['set_approved'] AS $key => $val)
			{
				$dataman =& datamanager_init('Blog_PHPKD_VBBFP', $vbulletin, ERRTYPE_ARRAY);
				$value = $vbulletin->GPC['approved']["$key"];
				$dataman->set_condition("blogfeedid = '" . $key . "'");
				$dataman->set('valid', $value);
				$dataman->save();
				unset($dataman);
			}
		}

		if ($show['canmoderatefeeds'] OR ($show['canmassmanage'] AND $show['canresetfeed']))
		{
			$finalresetids = '';
			foreach ($vbulletin->GPC['set_reset'] AS $key => $val)
			{
				if (isset($vbulletin->GPC['reset']["$key"]))
				{
					$finalresetids .= ",$key";
				}
			}

			$dataman =& datamanager_init('Blog_PHPKD_VBBFP', $vbulletin, ERRTYPE_ARRAY);
			$dataman->condition = "blogfeedid IN (0$finalresetids)";
			$dataman->set('lastrun', 0);
			$dataman->save();
			unset($dataman);
		}


		if ($show['canmoderatefeeds'] OR ($show['canmassmanage'] AND $show['candeletefeed']))
		{
			$finaldeleteids = '';
			foreach ($vbulletin->GPC['set_delete'] AS $key => $val)
			{
				if (isset($vbulletin->GPC['delete']["$key"]))
				{
					$finaldeleteids .= ",$key";
				}
			}

			$dataman =& datamanager_init('Blog_PHPKD_VBBFP', $vbulletin, ERRTYPE_ARRAY);
			$dataman->condition = "blogfeedid IN (0$finaldeleteids)";
			$dataman->delete();
			unset($dataman);
		}


		// Update Display Order
		$casesql = array();
		foreach ($vbulletin->GPC['displayorder'] AS $blogfeedid => $displayorder)
		{
			$casesql[] = " WHEN blogfeedid = " . intval($blogfeedid) . " THEN $displayorder";
		}

		if (!empty($casesql))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "phpkd_vbbfp
				SET displayorder =
				CASE
					" . implode("\r\n", $casesql) . "
					ELSE displayorder
				END
				WHERE userid = $userinfo[userid]
			");
		}

		$vbulletin->url = 'blog_phpkd_vbbfp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editfeeds' . ($show['modedit'] ? "&amp;u=$userinfo[userid]" : '');
		print_standard_redirect('redirect_blog_profileupdate');
	}
}


// ############################################################################
// ################################  MANAGE Feeds  ############################
// ############################################################################
if ($_REQUEST['do'] == 'modifyfeed')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogfeedid' => TYPE_UINT
	));


	($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_modifyfeed_start')) ? eval($hook) : false;


	$feedinfo = array('displayorder' => 1);

	if ($vbulletin->GPC['blogfeedid'])
	{
		if (!($feedinfo = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "phpkd_vbbfp
			WHERE blogfeedid = " . $vbulletin->GPC['blogfeedid'] . "
		")))
		{
			standard_error(fetch_error('invalidid', 'blogfeedid', $vbulletin->options['contactuslink']));
		}


		if ($feedinfo['userid'] != $vbulletin->userinfo['userid'])
		{
			if (!can_moderate_blog('phpkd_vbbfp_canedit'))
			{
				standard_error(fetch_error('invalidid', 'blogfeedid', $vbulletin->options['contactuslink']));
			}

			$userinfo = fetch_userinfo($feedinfo['userid']);
			cache_permissions($userinfo, false);
		}
		else
		{
			$userinfo =& $vbulletin->userinfo;
			$show['blogcp'] = true;
		}
	}
	else if (!defined('CLICKED_ADD_BUTTON') AND !defined('PREVIEW'))
	{
		$userinfo =& $vbulletin->userinfo;
		if (
			!($userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
				OR
			!($userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['cancreate'])
		)
		{
			print_no_permission();
		}
		$show['blogcp'] = true;


		// make sure they have less than the limit
		if (!isset($vbulletin->vbblog['phpkd_vbbfp_cache'][$userinfo['userid']]))
		{
			phpkd_vbbfp_fetch_ordered_feeds($userinfo['userid']);
		}

		$count = 0;
		foreach($vbulletin->vbblog['phpkd_vbbfp_cache'][$userinfo['userid']] AS $feedcheck)
		{
			if ($feedcheck['userid'] == $userinfo['userid'])
			{
				$count++;
			}
		}

		if ($count >= $userinfo['permissions']['phpkd_vbbfp_max'])
		{
			standard_error(fetch_error('phpkd_vbbfp_blog_feed_limit', $userinfo['permissions']['phpkd_vbbfp_max']));
		}
	}


	if (defined('PREVIEW'))
	{
		$feedinfo = array(
			'blogfeedid'     => $vbulletin->GPC['blogfeedid'],
			'title'          => htmlspecialchars_uni($vbulletin->GPC['title']),
			'description'    => htmlspecialchars_uni($vbulletin->GPC['description']),
			'displayorder'   => $vbulletin->GPC['displayorder'],
			'url'            => $vbulletin->GPC['url'],
			'ttl'            => $vbulletin->GPC['ttl'],
			'maxresults'     => $vbulletin->GPC['maxresults'],
			'titletemplate'  => $vbulletin->GPC['titletemplate'],
			'bodytemplate'   => $vbulletin->GPC['bodytemplate'],
			'searchwords'    => $vbulletin->GPC['searchwords'],
			'blogtype'       => $vbulletin->GPC['blogtype'],
			'emailupdate'    => $vbulletin->GPC['emailupdate'],
			'feedoptions'    => $vbulletin->GPC['feedoptions'],
			'options'        => $vbulletin->GPC['options'],
		);

		$ttl = array($vbulletin->GPC['ttl'] => 'selected="selected"');
		$blogtype = array($vbulletin->GPC['blogtype'] => 'selected="selected"');
		$emailupdate = array($vbulletin->GPC['emailupdate'] => 'selected="selected"');


		if (!is_array($feedinfo['feedoptions']))
		{
			foreach ($vbulletin->GPC['feedoptions'] AS $variable => $key)
			{
				$feedinfo['feedoptions'] = array($variable => $key);
			}
		}

		foreach ($feedinfo['feedoptions'] AS $bitname => $bitvalue)
		{
			$checked["$bitname"] = ($bitvalue ? ' checked="checked"' : '');
		}


		if (!is_array($feedinfo['options']))
		{
			foreach ($vbulletin->GPC['options'] AS $variable => $key)
			{
				$feedinfo['options'] = array($variable => $key);
			}
		}

		foreach ($feedinfo['options'] AS $bitname => $bitvalue)
		{
			$checked["$bitname"] = ($bitvalue ? ' checked="checked"' : '');
		}


		$errors = array();
		$dataman =& datamanager_init('Blog_PHPKD_VBBFP', $vbulletin, ERRTYPE_ARRAY);
		$dataman->set('userid', $userinfo['userid']);

		($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_modifyfeed_preview')) ? eval($hook) : false;


		if (empty($errors))
		{
			$dataman->set('title', $vbulletin->GPC['title']);
			$dataman->set('description', $vbulletin->GPC['description']);
			$dataman->set('displayorder', $vbulletin->GPC['displayorder']);
			$dataman->set('url', $vbulletin->GPC['url']);

			if (!$vbulletin->GPC['blogfeedid'])
			{
				$dataman->set('valid', !($vbulletin->userinfo['permissions']['phpkd_vbbfp_perms'] & $vbulletin->bf_ugp_phpkd_vbbfp_perms['moderatefeeds']));
			}

			$dataman->set('dateline', TIMENOW);
			$dataman->set('port', $vbulletin->GPC['port']);
			$dataman->set('ttl', $vbulletin->GPC['ttl']);
			$dataman->set('maxresults', $vbulletin->GPC['maxresults']);
			$dataman->set('titletemplate', $vbulletin->GPC['titletemplate']);
			$dataman->set('bodytemplate', $vbulletin->GPC['bodytemplate']);
			$dataman->set('searchwords', $vbulletin->GPC['searchwords']);
			$dataman->set('blogtype', $vbulletin->GPC['blogtype']);
			$dataman->set('emailupdate', $vbulletin->GPC['emailupdate']);

			foreach($vbulletin->GPC['feedoptions'] AS $key => $val)
			{
				$dataman->set_bitfield('feedoptions', $key, $val);
			}

			foreach ($vbulletin->bf_misc_phpkd_vbbfp AS $key => $val)
			{
				if (isset($vbulletin->GPC['options']["$key"]) OR isset($vbulletin->GPC['set_options']["$key"]))
				{
					$value = $vbulletin->GPC['options']["$key"];
					$dataman->set_bitfield('options', $key, $value);
				}
			}

			// Check Feed URL Validity
			require_once(DIR . '/includes/class_rss_poster.php');
			$xml = new vB_RSS_Poster($vbulletin);
			$xml->fetch_xml($vbulletin->GPC['url']);
			if (empty($xml->xml_string))
			{
				$dataman->error('unable_to_open_url');
			}
			else if ($xml->parse_xml() === false)
			{
				$dataman->error('xml_error_x_at_line_y', ($xml->feedtype == 'unknown' ? 'Unknown Feed Type' : $xml->xml_object->error_string()), $xml->xml_object->error_line());
			}


			$dataman->pre_save();

			if (!empty($dataman->errors))
			{
				require_once(DIR . '/includes/functions_newpost.php');
				$errorlist = construct_errors($dataman->errors);
			}
			else
			{
				require_once(DIR . '/includes/class_bbcode.php');
				require_once(DIR . '/includes/class_wysiwygparser.php');

				$count = 0;
				$preview_bits = '';
				$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
				$html_parser = new vB_WysiwygHtmlParser($vbulletin);

				foreach ($xml->fetch_items() AS $item)
				{
					$show['preview_bits'] = true;
					if ($vbulletin->GPC['maxresults'] AND $count++ >= $vbulletin->GPC['maxresults'])
					{
						break;
					}

					if (!empty($item['content:encoded']))
					{
						$content_encoded = true;
					}

					$title = $bbcode_parser->parse(strip_bbcode($html_parser->parse_wysiwyg_html_to_bbcode($xml->parse_template($vbulletin->GPC['titletemplate'], $item)), 0, false));

					if ($vbulletin->GPC['options']['html2bbcode'])
					{
						$body_template = nl2br($vbulletin->GPC['bodytemplate']);
					}
					else
					{
						$body_template = $vbulletin->GPC['bodytemplate'];
					}

					$body = $xml->parse_template($body_template, $item);
					if ($vbulletin->GPC['options']['html2bbcode'])
					{
						$body = $html_parser->parse_wysiwyg_html_to_bbcode($body, false, true);
					}
					$body = $bbcode_parser->parse($body, 0, false);


					($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_modifyfeed_preview_bits')) ? eval($hook) : false;

					$templater = vB_Template::create('phpkd_vbbfp_preview_bits');
						$templater->register('title', $title);
						$templater->register('body', $body);
					$preview_bits = $templater->render();
				}
			}
		}
	}
	else
	{
		// If you're editing Feed
		if ($vbulletin->GPC['blogfeedid'])
		{
			$ttl = array($feedinfo['ttl'] => 'selected="selected"');
			$blogtype = array($feedinfo['blogtype'] => 'selected="selected"');
			$emailupdate = array($feedinfo['emailupdate'] => 'selected="selected"');


			if (!is_array($feedinfo['feedoptions']))
			{
				$feedinfo['feedoptions'] = convert_bits_to_array($feedinfo['feedoptions'], $vbulletin->bf_misc_phpkd_vbbfp2);
			}

			foreach ($feedinfo['feedoptions'] AS $bitname => $bitvalue)
			{
				$checked["$bitname"] = ($bitvalue ? ' checked="checked"' : '');
			}


			if (!is_array($feedinfo['options']))
			{
				$feedinfo['options'] = convert_bits_to_array($feedinfo['options'], $vbulletin->bf_misc_phpkd_vbbfp);
			}

			foreach ($feedinfo['options'] AS $bitname => $bitvalue)
			{
				$checked["$bitname"] = ($bitvalue ? ' checked="checked"' : '');
			}
		}
		else
		{
			// If you're adding Feed
			$checked['enabled'] = true;
			$ttl = array('1800' => 'selected="selected"');
			$blogtype = array('visible' => 'selected="selected"');
			$emailupdate = array($vbulletin->userinfo['blog_subscribeown'] => 'selected="selected"');
		}


		($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_modifyfeed_addedit')) ? eval($hook) : false;
	}


	// Sidebar
	$sidebar =& build_user_sidebar($userinfo);

	if ($userinfo['userid'] == $vbulletin->userinfo['userid'])
	{
		$navbits = array(
			'blog_phpkd_vbbfp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editfeeds' => $vbphrase['phpkd_vbbfp_blog_feeds'],
			'' => ($feedinfo['blogfeedid'] ? $vbphrase['phpkd_vbbfp_blog_feed_edit'] : $vbphrase['phpkd_vbbfp_blog_feed_add'])
		);
	}
	else
	{
		$navbitsdone = true;
		$navbits = array(
			'blog.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blogs'],
			'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]" => $userinfo['blog_title'],
		);

		if ($vbulletin->GPC['blogfeedid'])
		{
			$navbits['blog_phpkd_vbbfp.php?' . $vbulletin->session->vars['sessionurl'] . "do=editfeeds&amp;u=$userinfo[userid]"] = $vbphrase['phpkd_vbbfp_blog_feeds'];
			$navbits[] = $feedinfo['title'];
		}
		else
		{
			$navbits[] = $vbphrase['phpkd_vbbfp_blog_feeds'];
		}
	}


	($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_modifyfeed_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('phpkd_vbbfp_modifyfeed');
		$templater->register('feedinfo', $feedinfo);
		$templater->register('userinfo', $userinfo);
		$templater->register('bbuserinfo', $bbuserinfo);
		$templater->register('errorlist', $errorlist);
		$templater->register('preview_bits', $preview_bits);
		$templater->register('content_encoded', $content_encoded);
		$templater->register('checked', $checked);
		$templater->register('blogtype', $blogtype);
		$templater->register('ttl', $ttl);
		$templater->register('emailupdate', $emailupdate);
		$templater->register('errorlist', $errorlist);
	$content = $templater->render();
}


// #############################################################################
// spit out final HTML if we have got this far

// build navbar
if (empty($navbits))
{
	$navbits = array(
		fetch_seo_url('bloghome', array())  => $vbphrase['blogs']
	);
	if ($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
	{
		$navbits[fetch_seo_url('blog', $vbulletin->userinfo)] = $vbulletin->userinfo['blog_title'];
	}
	$navbits[''] = $vbphrase['blog_control_panel'];

}
else if (!$navbitsdone)
{
	$prenavbits = array(
		fetch_seo_url('bloghome', array())  => $vbphrase['blogs'],
	);
	if ($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
	{
		$prenavbits[fetch_seo_url('blog', $vbulletin->userinfo)] = $vbulletin->userinfo['blog_title'];
	}
	$prenavbits[fetch_seo_url('blogusercp', array())] = $vbphrase['blog_control_panel'];
	$navbits = array_merge($prenavbits, $navbits);
}
$navbits = construct_navbits($navbits);

$navbar = render_navbar_template($navbits);

($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_complete')) ? eval($hook) : false;

// CSS
$headinclude .= vB_Template::create('blog_css')->render();
$headinclude .= vB_Template::create('blog_cp_css')->render();

// shell template
$templater = vB_Template::create('BLOG');
	$templater->register_page_templates();
	$templater->register('abouturl', $abouturl);
	$templater->register('blogheader', $blogheader);
	$templater->register('bloginfo', $bloginfo);
	$templater->register('blogrssinfo', $blogrssinfo);
	$templater->register('bloguserid', $bloguserid);
	$templater->register('content', $content);
	$templater->register('navbar', $navbar);
	$templater->register('onload', $onload);
	$templater->register('pagetitle', $pagetitle);
	$templater->register('pingbackurl', $pingbackurl);
	$templater->register('sidebar', $sidebar);
	$templater->register('trackbackurl', $trackbackurl);
	$templater->register('usercss_profile_preview', $usercss_profile_preview);
print_output($templater->render());

?>