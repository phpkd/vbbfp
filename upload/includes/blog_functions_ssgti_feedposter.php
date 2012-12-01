<?php
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}


/**
* Order Feeds
*
* @param	integer		Userid
* @param	bool		Force cache to be rebuilt, ignoring copy that may already exist
* @param	bool		Include admin feeds when userid > 0
*
* @return	void
*/
function ssgti_blogfeedposter_fetch_ordered_feeds($userid = 0, $force = false, $admin = true)
{
	global $vbulletin;

	if (isset($vbulletin->vbblog['ssgtifeedcache']["$userid"]) AND !$force)
	{
		return;
	}

	$userids = array();
	if ($userid)
	{
		$userids[] = $userid;
	}

	if ($userid == 0 OR $admin)
	{
		$userids[] = 0;
	}

	$vbulletin->vbblog['ssgtifeedcache']["$userid"] = array();
	$vbulletin->vbblog['issgtifeedcache']["$userid"] = array();
	$vbulletin->vbblog['ssgtifeedcount']["$userid"] = 0;

	$ssgtifeeddata = array();

	$feeds = $vbulletin->db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "ssgtiblogfeedposter
		WHERE userid IN(" . implode(", ", $userids) . ")
		ORDER BY userid, displayorder
	");

	while ($feed = $vbulletin->db->fetch_array($feeds))
	{
		$vbulletin->vbblog['issgtifeedcache']["$userid"]["$feed[blogfeedid]"] = $feed['blogfeedid'];
		$ssgtifeeddata["$feed[blogfeedid]"] = $feed;
	}

	$vbulletin->vbblog['ssgtifeedorder']["$userid"] = array();

	if (is_array($vbulletin->vbblog['issgtifeedcache']["$userid"]))
	{
		foreach ($vbulletin->vbblog['issgtifeedcache']["$userid"] AS $blogfeedid => $blogfeeddata)
		{
			$vbulletin->vbblog['ssgtifeedcache']["$userid"]["$blogfeedid"] = $ssgtifeeddata["$blogfeedid"];
			if ($ssgtifeeddata["$blogfeedid"]['userid'])
			{
				$vbulletin->vbblog['ssgtifeedcount']["$userid"]++;
			}
		}
	}
}
?>