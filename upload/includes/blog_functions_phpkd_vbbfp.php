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
function phpkd_vbbfp_fetch_ordered_feeds($userid = 0, $force = false, $admin = true)
{
	global $vbulletin;

	if (isset($vbulletin->vbblog['phpkd_vbbfp_cache']["$userid"]) AND !$force)
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

	$vbulletin->vbblog['phpkd_vbbfp_cache']["$userid"] = array();
	$vbulletin->vbblog['iphpkd_vbbfp_cache']["$userid"] = array();
	$vbulletin->vbblog['phpkd_vbbfp_count']["$userid"] = 0;

	$phpkd_vbbfp_data = array();

	$feeds = $vbulletin->db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "phpkd_vbbfp
		WHERE userid IN(" . implode(", ", $userids) . ")
		ORDER BY userid, displayorder
	");

	while ($feed = $vbulletin->db->fetch_array($feeds))
	{
		$vbulletin->vbblog['iphpkd_vbbfp_cache']["$userid"]["$feed[blogfeedid]"] = $feed['blogfeedid'];
		$phpkd_vbbfp_data["$feed[blogfeedid]"] = $feed;
	}

	$vbulletin->vbblog['phpkd_vbbfp_order']["$userid"] = array();

	if (is_array($vbulletin->vbblog['iphpkd_vbbfp_cache']["$userid"]))
	{
		foreach ($vbulletin->vbblog['iphpkd_vbbfp_cache']["$userid"] AS $blogfeedid => $blogfeeddata)
		{
			$vbulletin->vbblog['phpkd_vbbfp_cache']["$userid"]["$blogfeedid"] = $phpkd_vbbfp_data["$blogfeedid"];
			if ($phpkd_vbbfp_data["$blogfeedid"]['userid'])
			{
				$vbulletin->vbblog['phpkd_vbbfp_count']["$userid"]++;
			}
		}
	}
}
?>