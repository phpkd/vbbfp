<?php
if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for blog Feeds
*
* @package	PHPKD - vB Blog Feed Poster
* @version	$Revision: 1111111 $
* @date		$Date: 2009-09-09 09:09:09 -0900 (Thu, 09 Sep 2009) $
*/
class vB_DataManager_Blog_PHPKD_VBBFP extends vB_DataManager
{
	/**
	* Array of recognised and required fields
	*
	* @var	array
	*/
	var $validfields = array(
		'blogfeedid'    => array(TYPE_UINT, REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'title'         => array(TYPE_STR,  REQ_YES,  VF_METHOD, 'verify_title'),
		'description'   => array(TYPE_STR,  REQ_NO,   VF_METHOD, 'verify_description'),
		'displayorder'  => array(TYPE_UINT, REQ_NO),
		'url'           => array(TYPE_STR,  REQ_YES),
		'valid'         => array(TYPE_UINT, REQ_NO),
		'dateline'      => array(TYPE_UINT, REQ_NO),
		'port'          => array(TYPE_UINT, REQ_NO),
		'ttl'           => array(TYPE_UINT, REQ_NO),
		'maxresults'    => array(TYPE_UINT, REQ_NO),
		'userid'        => array(TYPE_UINT, REQ_NO),
		'titletemplate' => array(TYPE_STR,  REQ_NO),
		'bodytemplate'  => array(TYPE_STR,  REQ_NO),
		'searchwords'   => array(TYPE_STR,  REQ_NO),
		'blogtype'      => array(TYPE_STR,  REQ_NO),
		'emailupdate'   => array(TYPE_STR,  REQ_NO),
		'feedoptions'   => array(TYPE_UINT, REQ_NO),
		'options'       => array(TYPE_UINT, REQ_NO),
		'lastrun'       => array(TYPE_STR,  REQ_NO),
	);


	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array(
		'feedoptions' => 'bf_misc_phpkd_vbbfp2',
		'options'     => 'bf_misc_phpkd_vbbfp',
	);


	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blogfeedid = %1$s', 'blogfeedid');


	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'phpkd_vbbfp';


	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_PHPKD_VBBFP(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_feeddata_start')) ? eval($hook) : false;
	}


	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_feeddata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		$thisbu = $this->registry->db->query_first("
			SELECT bu.bloguserid
			FROM " . TABLE_PREFIX . "blog_user AS bu
			WHERE bu.bloguserid = '" . $this->fetch_field('userid') . "'
		");

		if (empty($thisbu['bloguserid']))
		{
			$userdata =& datamanager_init('Blog_user', $this->registry, ERRTYPE_SILENT);
			// Create a record in blog_user if we need one
			$userdata->set('bloguserid', $this->fetch_field('userid'));
			$userdata->save();
		}

		($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_feeddata_postsave')) ? eval($hook) : false;
	}


	function delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('blog_phpkd_vbbfp_feeddata_delete')) ? eval($hook) : false;

		if ($feed = $this->registry->db->query_first_slave("SELECT blogfeedid, userid FROM " . TABLE_PREFIX . "phpkd_vbbfp WHERE " . $this->condition))
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "blog
				SET phpkd_vbbfp = 0
				WHERE phpkd_vbbfp = '" . $this->fetch_field('blogfeedid') . "'
			");

			$this->db_delete(TABLE_PREFIX, 'phpkd_vbbfp', $this->condition);
		}
		else
		{
			$this->error('phpkd_vbbfp_invalid_feed_specified');
		}
	}


	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		// censor, and htmlspecialchars Feed title
		$title = htmlspecialchars_uni(fetch_censored_text(trim($title)));

		// do word wrapping
		$title = fetch_word_wrapped_string($title, $this->registry->options['blog_wordwrap']);

		if (empty($title))
		{
			return false;
		}
		else
		{
			return true;
		}
	}


	/**
	* Verifies the description is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_description(&$desc)
	{
		// replace html-encoded spaces with actual spaces
		$desc = preg_replace('/&#(0*32|x0*20);/', ' ', $desc);

		if (!function_exists('fetch_no_shouting_text'))
		{
			require_once(DIR . '/includes/functions_newpost.php');
		}

		// censor, remove all caps subjects, and htmlspecialchars Feed description
		$desc = htmlspecialchars_uni(fetch_no_shouting_text(fetch_censored_text(trim($desc))));

		// do word wrapping
		$desc = fetch_word_wrapped_string($desc, $this->registry->options['blog_wordwrap']);

		return true;
	}
}
?>