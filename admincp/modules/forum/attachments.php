<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id: index.php 2992 2007-04-05 14:43:48Z chris $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item("Attachments", "index.php?".SID."&amp;module=forum/attachments");

if($mybb->input['action'] == "stats" || $mybb->input['action'] == "orphans" || !$mybb->input['action'])
{
	$sub_tabs['find_attachments'] = array(
		'title' => "Find Attachments",
		'link' => "index.php?".SID."&amp;module=forum/attachments",
		'description' => "Using the attachments search system you can search for specific files users have attached to your forums. Begin by entering some search terms below. All fields are optional and won't be included in the criteria unless they contain a value."
	);

	$sub_tabs['find_orphans'] = array(
		'title' => "Find Orphaned Attachments",
		'link' => "index.php?".SID."&amp;module=forum/attachments&amp;action=orphans",
		'description' => "Orphaned attachments are attachments which are for some reason missing in the database or the file system. This utility will assist you in locating and removing them."
	);

	$sub_tabs['stats'] = array(
		'title' => "Attachment Statistics",
		'link' => "index.php?".SID."&amp;module=forum/attachments&amp;action=stats",
		'description' => "Below are some general statistics for the attachments currently on your forum."
	);
}

if($mybb->input['action'] == "delete")
{
	if(!is_array($mybb->input['aids']))
	{
		$mybb->input['aids'] = array(intval($mybb->input['aid']));
	}
	else
	{
		array_walk($mybb->input['aids'], "intval");
	}

	if(count($mybb->input['aids']) < 1)
	{
		flash_message('You did not select any attachments to delete', 'error');
		admin_redirect("index.php?".SID."&module=user/group_promotions");
	}

	if($mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/functions_upload.php";

		$query = $db->simple_select("attachments", "aid,pid,posthash", "aid IN (".implode(",", $mybb->input['aids']).")");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['pid'])
			{
				remove_attachment(null, $attachment['posthash'], $attachment['aid']);
			}
			else
			{
				remove_attachment($attachment['pid'], null, $attachment['aid']);
			}
		}
		flash_message('The selected attachments have successfully been deleted', 'success');
		admin_redirect("index.php?".SID."&module=forum/attachments");
	}
	else
	{
		foreach($mybb->input['aids'] as $aid)
		{
			$aids .= "&amp;aids[]=$aid";
		}
		$page->output_confirm_action("index.php?".SID."&amp;module=forum/attachments&amp;action=delete&amp;aids={$aids}", 'Are you sure you wish to delete the selected attachments?'); 
	}
}

if($mybb->input['action'] == "stats")
{

	$query = $db->simple_select("attachments", "COUNT(*) AS total_attachments, SUM(filesize) as disk_usage, SUM(downloads) as total_downloads", "visible='1'");
	$attachment_stats = $db->fetch_array($query);

		$page->add_breadcrumb_item("Statistics");
		$page->output_header("Attachments - Attachment Statistics");
		
		$page->output_nav_tabs($sub_tabs, 'stats');

	if($attachment_stats['total_attachments'] == 0)
	{
		$page->output_inline_error(array("There aren't any attachments on your forum yet. Once an attachment is posted you'll be able to access this section."));
		$page->output_footer();
		exit;
	}

	$table = new Table;

	$table->construct_cell("<strong>No. Uploaded Attachments</strong>", array('width' => '25%'));
	$table->construct_cell(my_number_format($attachment_stats['total_attachments']), array('width' => '25%'));
	$table->construct_cell("<strong>Attachment Space Used</strong>", array('width' => '200'));
	$table->construct_cell(get_friendly_size($attachment_stats['disk_usage']), array('width' => '200'));
	$table->construct_row();
	
	$table->construct_cell("<strong>Estimated Bandwidth Usage</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachment_stats['disk_usage']*$attachment_stats['total_downloads'])), array('width' => '25%'));
	$table->construct_cell("<strong>Average Attachment Size</strong>", array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachment_stats['disk_usage']/$attachment_stats['total_attachments'])), array('width' => '25%'));
	$table->construct_row();
	
	$table->output("General Statistics");

	// Fetch the most popular attachments
	$table = new Table;
	$table->construct_header("<span class=\"float_right\">Size</span>Attachment", array('colspan' => 2));
	$table->construct_header("Posted By", array('width' => '20%', 'class' => 'align_center'));
	$table->construct_header("Thread", array('width' => '25%', 'class' => 'align_center'));
	$table->construct_header("Downloads", array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header("Date Uploaded", array("class" => "align_center"));

	$query = $db->query("
		SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		ORDER BY a.downloads DESC
		LIMIT 5
	");
	while($attachment = $db->fetch_array($query))
	{
		build_attachment_row($attachment, &$table);
	}
	$table->output("Top 5 Most Popular Attachments");

	// Fetch the largest attachments
	$table = new Table;
	$table->construct_header("<span class=\"float_right\">Size</span>Attachment", array('colspan' => 2));
	$table->construct_header("Posted By", array('width' => '20%', 'class' => 'align_center'));
	$table->construct_header("Thread", array('width' => '25%', 'class' => 'align_center'));
	$table->construct_header("Downloads", array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header("Date Uploaded", array("class" => "align_center"));

	$query = $db->query("
		SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		ORDER BY a.filesize DESC
		LIMIT 5
	");
	while($attachment = $db->fetch_array($query))
	{
		build_attachment_row($attachment, &$table);
	}
	$table->output("Top 5 Largest Attacments");

	// Fetch users who've uploaded the most attachments
	$table = new Table;
	$table->construct_header("Username");
	$table->construct_header("Total Size", array('width' => '20%', 'class' => 'align_center'));

	$query = $db->query("
		SELECT a.*, u.uid, u.username, SUM(a.filesize) as totalsize
		FROM ".TABLE_PREFIX."attachments a  
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		GROUP BY a.uid
		ORDER BY totalsize DESC
		LIMIT 5
	");
	while($user = $db->fetch_array($query))
	{
		$table->construct_cell(build_profile_link($user['username'], $user['uid']));
		$table->construct_cell("<a href=\"index.php?".SID."&amp;module=forum/attachments&results=1&username=".urlencode($user['username'])."\">".get_friendly_size($user['totalsize'])."</a>", array('class' => 'align_center'));
		$table->construct_row();
	}
	$table->output("Top 5 Users Using the Most Disk Space");

	$page->output_footer();
}

if($mybb->input['action'] == "delete_orphans" && $mybb->request_method == "post")
{
	// Deleting specific attachments from uploads directory
	if(is_array($mybb->input['orphaned_files']))
	{
		function clean_filename($string)
		{
			return str_replace(array(".."), "", $string);
		}
		array_walk($mybb->input['orphaned_files'], "clean_filename");
		foreach($mybb->input['orphaned_files'] as $file)
		{
			if(!@unlink(MYBB_ROOT.$mybb->settings['uploadspath']."/".$file))
			{
				$error = true;
			}
		}
	}

	// Deleting physical attachments which exist in database
	if(is_array($mybb->input['orphaned_attachments']))
	{
		array_walk($mybb->input['orphaned_attachments'], "intval");
		require_once MYBB_ROOT."inc/functions_upload.php";

		$query = $db->simple_select("attachments", "aid,pid,posthash", "aid IN (".implode(",", $mybb->input['orphaned_attachments']).")");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['pid'])
			{
				remove_attachment(null, $attachment['posthash'], $attachment['aid']);
			}
			else
			{
				remove_attachment($attachment['pid'], null, $attachment['aid']);
			}
		}
	}
	if($error == true)
	{
		flash_message('Only some orphaned attachments were successfully deleted, others could not be removed from the uploads directory.', 'error');
	}
	else
	{
		flash_message('The selected orphaned attachments have been deleted.', 'success');
	}
	admin_redirect("index.php?".SID."&module=forum/attachments");
}

if($mybb->input['action'] == "orphans")
{
	// Oprhans are defined as:
	// - Uploaded files in the uploads directory that don't exist in the database
	// - Attachments for which the uploaded file is missing
	// - Attachments for which the thread or post has been deleted
	// - Files uploaded > 24h ago not attached to a real post

	// This process is quite intensive so we split it up in to 2 steps, one which scans the file system and the other which scans the database.

	// Finished second step, show results
	if($mybb->input['step'] == 3)
	{
		$reults = 0;
		// Incoming attachments which exist as files but not in database
		if($mybb->input['bad_attachments'])
		{
			$bad_attachments = unserialize($mybb->input['bad_attachments']);
			$results = count($bad_attachments);
		}

		$aids = array();
		if($mybb->input['missing_attachment_files'])
		{
			$missing_attachment_files = unserialize($mybb->input['missing_attachment_files']);
			$aids = array_merge($aids, $missing_attachment_files);
		}

		if($mybb->input['missing_threads'])
		{
			$missing_threads = unserialize($mybb->input['missing_threads']);
			$aids = array_merge($aids, $missing_threads);
		}

		if($mybb->input['incomplete_attachments'])
		{
			$incomplete_attachments = unserialize($mybb->input['incomplete_attachments']);
			$aids = array_merge($aids, $incomplete_attachments);
		}
		$results += count($aids);

		if($results == 0)
		{
			flash_message('You do not have any orphaned attachments on your forums', 'success');
			admin_redirect("index.php?".SID."&module=forum/attachments");
		}

		$page->output_header("Orphaned Attachment Search - Results");
		$page->output_nav_tabs($sub_tabs, 'find_orphans');

		$form = new Form("index.php?".SID."&amp;module=forum/attachments&amp;action=delete_orphans", "post");

		$table = new Table;
		$table->construct_header($form->generate_check_box('checkall', '1', '', array('class' => 'checkall')), array( 'width' => 1));
		$table->construct_header("<span class=\"float_right\">Size</span>Attachment", array('colspan' => 2));
		$table->construct_header("Reason Orphaned", array('width' => '20%', 'class' => 'align_center'));
		$table->construct_header("Date Uploaded", array("class" => "align_center"));

		if(is_array($bad_attachments))
		{
			foreach($bad_attachments as $file)
			{
				$file_path = MYBB_ROOT.$mybb->settings['uploadspath']."/".$file;
				$filesize = get_friendly_size(filesize($file_path));
				$table->construct_cell($form->generate_check_box('orphaned_files[]', $file, '', array('checked' => true)));
				$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
				$table->construct_cell("<span class=\"float_right\">{$filesize}</span>{$file}");
				$table->construct_cell("Not in attachments table", array('class' => 'align_center'));
				$table->construct_cell(my_date($mybb->settings['dateformat'], filemtime($file_path)).", ".my_date($mybb->settings['timeformat'], filemtime($file_path)), array('class' => 'align_center'));
				$table->construct_row();
			}
		}

		if(count($aids) > 0)
		{
			$query = $db->simple_select("attachments", "*", "aid IN (".implode(",", $aids).")");
			while($attachment = $db->fetch_array($query))
			{
				if($missing_attachment_files[$attachment['aid']])
				{
					$reason = "Attached file missing";
				}
				else if($missing_threads[$attachment['aid']])
				{
					$reason = "Thread been deleted";
				}
				else if($incomplete_attachments[$attachment['aid']])
				{
					$reason = "Post never made";
				}
				$table->construct_cell($form->generate_check_box('orphaned_attachments[]', $attachment['aid'], '', array('checked' => true)));
				$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
				$table->construct_cell("<span class=\"float_right\">".get_friendly_size($attachment['filesize'])."</span><a href=\"../attachment.php?aid={$attachment['aid']}\">{$attachment['filename']}</a>", array('class' => $cell_class));
				$table->construct_cell($reason, array('class' => 'align_center'));
				if($attachment['dateuploaded'])
				{
					$table->construct_cell(my_date($mybb->settings['dateformat'], $attachment['dateuploaded']).", ".my_date($mybb->settings['timeformat'], $attachment['dateuploaded']), array('class' => 'align_center'));
				}
				else
				{
					$table->construct_cell("Unknown", array('class' => 'align_center'));
				}
				$table->construct_row();
			}
		}

		$table->output("Orphaned Attachments Search - {$results} Results");

		$buttons[] = $form->generate_submit_button("Delete Checked Orphans");
		$form->output_submit_wrapper($buttons);
		$form->end();
		$page->output_footer();
	}

	// Running second step - scan the database
	else if($mybb->input['step'] == 2)
	{
		$page->output_header("Orphaned Attachment Search - Step 2");
	
		$page->output_nav_tabs($sub_tabs, 'find_orphans');
		echo "<h3>Step 2 of 2 - Database Scan</h3>";
		echo "<p class=\"align_center\">Please wait, the database is currently being scanned for orphaned attachments.</p>";
		echo "<p class=\"align_center\">You'll automatically be redirected to the next step once this process is complete.</p>";
		echo "<p class=\"align_center\"><img src=\"styles/{$page->style}/images/spinner_big.gif\" alt=\"Scanning..\" id=\"spinner\" /></p>";

		$page->output_footer(false);
		flush();

		$missing_attachment_files = array();
		$missing_threads = array();
		$incomplete_attachments = array();

		$query = $db->query("
			SELECT a.*, a.pid AS attachment_pid, p.pid
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			ORDER BY a.aid");
		while($attachment = $db->fetch_array($query))
		{
			// Check if the attachment exists in the file system
			if(!file_exists(MYBB_ROOT.$mybb->settings['uploadspath']."/{$attachment['attachname']}"))
			{
				$missing_attachment_files[$attachment['aid']] = $attachment['aid'];
			}
			// Check if the thread/post for this attachment is missing
			else if(!$attachment['pid'] && $attachment['attachment_pid'])
			{
				$missing_threads[$attachment['aid']] = $attachment['aid'];
			}
			// Check if the attachment was uploaded > 24 hours ago but not assigned to a thread
			else if(!$attachment['attachment_pid'] && $attachment['dateuploaded'] < time()-60*60*24 && $attachment['dateuploaded'] != 0)
			{
				$incomplete_attachments[$attachment['aid']] = $attachment['aid'];
			}
		}

		// Now send the user to the final page
		$form = new Form("index.php?".SID."&amp;module=forum/attachments&amp;action=orphans&amp;step=3", "post", 0, "", "redirect_form");
		// Scan complete
		if($mybb->input['bad_attachments'])
		{
			echo $form->generate_hidden_field("bad_attachments", $mybb->input['bad_attachments']);
		}
		if(is_array($missing_attachment_files) && count($missing_attachment_files) > 0)
		{
			$missing_attachment_files = serialize($missing_attachment_files);
			echo $form->generate_hidden_field("missing_attachment_files", $missing_attachment_files);
		}
		if(is_array($missing_threads) && count($missing_threads) > 0)
		{
			$missing_threads = serialize($missing_threads);
			echo $form->generate_hidden_field("missing_threads", $missing_threads);
		}
		if(is_array($incomplete_attachments) && count($incomplete_attachments) > 0)
		{
			$incomplete_attachments = serialize($incomplete_attachments);
			echo $form->generate_hidden_field("incomplete_attachments", $incomplete_attachments);
		}
		$form->end();
		echo "<script type=\"text/javascript\">Event.observe(window, 'load', function() {
				window.setTimeout(
					function() {
						$('redirect_form').submit();
					}, 100
				);
			});</script>";
		exit;
	}
	// Running first step, scan the file system
	else
	{
		function scan_attachments_directory($dir="")
		{
			global $db, $mybb, $bad_attachments, $attachments_to_check;
			
			$real_dir = MYBB_ROOT.$mybb->settings['uploadspath'];
			$false_dir = "";
			if($dir)
			{
				$real_dir .= "/".$dir;
				$false_dir = $dir."/";
			}

			if($dh = opendir($real_dir))
			{
				while(false !== ($file = readdir($dh)))
				{
					if($file == "." || $file == "..") continue;
					if(is_dir($real_dir.$file))
					{
						scan_attachments_directory($false_dir.$file);
					}
					else if(my_substr($file, -7, 7) == ".attach")
					{
						$attachments_to_check["$false_dir$file"] = $false_dir.$file;
						// In lots of 20, query the database for these attachments
						if(count($attachments_to_check) >= 20)
						{
							array_walk($attachments_to_check, array($db, "escape_string"));
							$attachment_names = "'".implode("','", $attachments_to_check)."'";
							$query = $db->simple_select("attachments", "aid, attachname", "attachname IN ($attachment_names)");
							while($attachment = $db->fetch_array($query))
							{
								unset($attachments_to_check[$attachment['attachname']]);
							}

							// Now anything left is bad!
							if(count($attachments_to_check) > 0)
							{
								if($bad_attachments)
								{
									$bad_attachments = @array_merge($bad_attachments, $attachments_to_check);
								}
								else
								{
									$bad_attachments = $attachments_to_check;
								}
							}
							$attachments_to_check = array();
						}
					}
				}
				closedir($dh);
				// Any reamining to check?
				if(count($attachments_to_check) > 0)
				{
					array_walk($attachments_to_check, array($db, "escape_string"));
					$attachment_names = "'".implode("','", $attachments_to_check)."'";
					$query = $db->simple_select("attachments", "aid, attachname", "attachname IN ($attachment_names)");
					while($attachment = $db->fetch_array($query))
					{
						unset($attachments_to_check[$attachment['attachname']]);
					}

					// Now anything left is bad!
					if(count($attachments_to_check) > 0)
					{
						if($bad_attachments)
						{
							$bad_attachments = @array_merge($bad_attachments, $attachments_to_check);
						}
						else
						{
							$bad_attachments = $attachments_to_check;
						}
					}
				}
			}
		}
	
		$page->output_header("Orphaned Attachment Search - Step 1");
	
		$page->output_nav_tabs($sub_tabs, 'find_orphans');
		echo "<h3>Step 1 of 2 - File System Scan</h3>";
		echo "<p class=\"align_center\">Please wait, the file system is currently being scanned for orphaned attachments.</p>";
		echo "<p class=\"align_center\">You'll automatically be redirected to the next step once this process is complete.</p>";
		echo "<p class=\"align_center\"><img src=\"styles/{$page->style}/images/spinner_big.gif\" alt=\"Scanning..\" id=\"spinner\" /></p>";

		$page->output_footer(false);
		
		flush();
		
		scan_attachments_directory();
		global $bad_attachments;

		$form = new Form("index.php?".SID."&amp;module=forum/attachments&amp;action=orphans&amp;step=2", "post", 0, "", "redirect_form");
		// Scan complete
		if(is_array($bad_attachments) && count($bad_attachments) > 0)
		{
			$bad_attachments = serialize($bad_attachments);
			echo $form->generate_hidden_field("bad_attachments", $bad_attachments);
		}
		$form->end();
		echo "<script type=\"text/javascript\">Event.observe(window, 'load', function() {
				window.setTimeout(
					function() {
						$('redirect_form').submit();
					}, 100
				);
			});</script>";
		exit;
	}
}

if(!$mybb->input['action'])
{
	if($mybb->request_method == "post" || $mybb->input['results'] == 1)
	{
		$search_sql = '1=1';

		// Build the search SQL for users

		// List of valid LIKE search fields
		$user_like_fields = array("filename", "mimetype");
		foreach($like_fields as $search_field)
		{
			if($mybb->input[$search_field])
			{
				$search_sql .= " AND a.{$search_field} LIKE '%".$db->escape_string_like($mybb->input[$search_field])."%'";
			}
		}

		// Username matching
		if($mybb->input['username'])
		{
			$query = $db->simple_select("users", "uid", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'");
			$user = $db->fetch_array($query);
			if(!$user['uid'])
			{
				$errors[] = "The username you entered is invalid.";
			}
			else
			{
				$search_sql .= " AND a.uid='{$user['uid']}'";
			}
		}

		$forum_cache = cache_forums();

		// Searching for attachments in a specific forum, we need to fetch all child forums too
		if($mybb->input['forum'])
		{
			if(!is_array($mybb->input['forum']))
			{
				$mybb->input['forum'] = array($mybb->input['forum']);
			}

			$fid_in = array();
			foreach($mybb->input['forum'] as $fid)
			{
				if(!$forum_cache[$fid])
				{
					$errors[] = "One or more forums you selected are invalid.";
					break;
				}
				$child_forums = get_child_list($fid);
				$child_forums[] = $fid;
				$fid_in = array_merge($fid_in, $child_forums);
			}

			if(count($fid_in) > 0)
			{
				$search_sql .= " AND p.fid IN (".implode(",", $fid_in).")";
			}
		}

		// LESS THAN or GREATER THAN
		if($mybb->input['dateuploaded'])
		{
			$mybb->input['dateuploaded'] = time()-60*60*24;
		}
		if($mybb->input['filesize'])
		{
			$mybb->input['filesize'] *= 1024;
		}

		$direction_fields = array("dateuploaded", "filesize", "downloads");
		foreach($direction_fields as $search_field)
		{
			$direction_field = $search_field."_dir";
			if($mybb->input[$search_field] && $mybb->input[$direction_field])
			{
				switch($mybb->input[$direction_field])
				{
					case "greater_than":
						$direction = ">";
						break;
					case "less_than":
						$direction = "<";
						break;
					default:
						$direction = "=";
				}
				$search_sql .= " AND a.{$search_field}{$direction}'".$db->escape_string($mybb->input[$search_field])."'";
			}
		}
		if(!$errors)
		{
			// Lets fetch out how many results we have
			$query = $db->query("
				SELECT COUNT(a.aid) AS num_results
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
				WHERE {$search_sql}
			");
			$num_results = $db->fetch_field($query, "num_results");

			// No matching results then show an error
			if(!$num_results)
			{
				$errors[] = "No results were found with the specified search criteria.";
			}
		}

		// Now we fetch the results if there were 100% no errors
		if(!$errors)
		{
			if(!$mybb->input['perpage'])
			{
				$mybb->input['perpage'] = 20;
			}
			$mybb->input['perpage'] = intval($mybb->input['perpage']);

			$mybb->input['page'] = intval($mybb->input['page']);
			if($mybb->input['page'])
			{
				$start = ($mybb->input['page'] - 1) * $mybb->input['perpage'];
			}
			else
			{
				$start = 0;
				$mybb->input['page'] = 1;
			}

			switch($mybb->input['sortby'])
			{
				case "lastactive":
					$sort_field = "a.filesize";
					break;
				case "downloads":
					$sort_field = "a.downloads";
					break;
				case "dateuploaded":
					$sort_field = "a.dateuploaded";
					break;
				case "username":
					$sort_field = "u.username";
					break;
				default:
					$sort_field = "a.filename";
					$mybb->input['sortby'] = "filename";
			}

			if($mybb->input['sortorder'] != "desc")
			{
				$mybb->input['sortorder'] = "asc";
			}

			$page->add_breadcrumb_item("Results");
			$page->output_header("Attachments - Find Attachments");
			
			$page->output_nav_tabs($sub_tabs, 'find_attachments');
			
			$form = new Form("index.php?".SID."&amp;module=forum/attachments&amp;action=delete", "post");

			$table = new Table;
			$table->construct_header($form->generate_check_box('checkall', '1', '', array('class' => 'checkall')), array( 'width' => 1));
			$table->construct_header("<span class=\"float_right\">Size</span>Attachment", array('colspan' => 2));
			$table->construct_header("Posted By", array('width' => '20%', 'class' => 'align_center'));
			$table->construct_header("Thread", array('width' => '25%', 'class' => 'align_center'));
			$table->construct_header("Downloads", array('width' => '10%', 'class' => 'align_center'));
			$table->construct_header("Date Uploaded", array("class" => "align_center"));

			// Fetch matching attachments
			$query = $db->query("
				SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
				WHERE {$search_sql}
				ORDER BY {$sort_field} {$mybb->input['sortorder']}
				LIMIT {$start}, {$mybb->input['perpage']}
			");
			while($attachment = $db->fetch_array($query))
			{
				build_attachment_row($attachment, &$table, &$form);
			}

			// Need to draw pagination for this result set
			if($num_results > $mybb->input['perpage'])
			{
				$pagination_url = "index.php?".SID."&module=forum/attachments&results=1";
				$pagination_vars = array('filename', 'mimetype', 'username', 'fid', 'downloads', 'downloads_dir', 'dateuploaded', 'dateuploaded_dir', 'filesize', 'filesize_dir');
				foreach($pagination_vars as $var)
				{
					if($mybb->input[$var])
					{
						$pagination_url .= "&{$var}=".urlencode($mybb->input[$var]);
					}
				}
				$pagination = draw_admin_pagination($mybb->input['page'], $mybb->input['perpage'], $num_results, $pagination_url);
			}

			echo $pagination;
			$table->output("Results");
			echo $pagination;
			
			$buttons[] = $form->generate_submit_button("Delete Checked Attachments");

			$form->output_submit_wrapper($buttons);
			$form->end();

			$page->output_footer();
		}
	}

	$page->output_header("Find Attachments");
	
	$page->output_nav_tabs($sub_tabs, 'find_attachments');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?".SID."&module=forum/attachments", "post");

	$form_container = new FormContainer("Find attachments where...");
	$form_container->output_row("File name contains", "To search by wildcard enter *.[file extension]. Example: *.zip.", $form->generate_text_box('filename', $mybb->input['filename'], array('id' => 'filename')), 'filename');
	$form_container->output_row("File type contains", "", $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row("Forum is", "", $form->generate_forum_select('forum[]', $mybb->input['forum'], array('multiple' => true, 'size' => 5)), 'forum');
	$form_container->output_row("Posters' username is", "", $form->generate_text_box('username', $mybb->input['username'], array('id' => 'username')), 'username');

	$more_options = array(
		"greater_than" => "More than",
		"less_than" => "Less than"
	);

	$greater_options = array(
		"greater_than" => "Greater than",
		"is_exactly" => "Is exactly",
		"less_than" => "Less than"
	);

	$form_container->output_row("Date posted is", "", $form->generate_select_box('dateuploaded_dir', $more_options, $mybb->input['dateuploaded_dir'], array('id' => 'dateuploaded_dir'))." ".$form->generate_text_box('dateuploaded', $mybb->input['dateuploaded'], array('id' => 'dateuploaded'))." days ago", 'dateuploaded');
	$form_container->output_row("File size is", "", $form->generate_select_box('filesize_dir', $greater_options, $mybb->input['filesize_dir'], array('id' => 'filesize_dir'))." ".$form->generate_text_box('filesize', $mybb->input['filesize'], array('id' => 'filesize'))." KB", 'dateuploaded');
	$form_container->output_row("Download count is", "", $form->generate_select_box('downloads_dir', $greater_options, $mybb->input['downloads_dir'], array('id' => 'downloads_dir'))." ".$form->generate_text_box('downloads', $mybb->input['downloads'], array('id' => 'downloads'))."", 'dateuploaded');
	$form_container->end();

	$form_container = new FormContainer("Display Options");
	$sort_options = array(
		"filename" => "File Name",
		"filesize" => "File Size",
		"downloads" => "Download Count",
		"dateuploaded" => "Date Uploaded",
		"username" => "Post Username"
	);
	$sort_directions = array(
		"asc" => "Ascending",
		"desc" => "Descending"
	);
	$form_container->output_row("Sort results by", "", $form->generate_select_box('sortby', $sort_options, $mybb->input['sortby'], array('id' => 'sortby'))." in ".$form->generate_select_box('order', $sort_directions, $mybb->input['order'], array('id' => 'order')), 'sortby');
	$form_container->output_row("Results per page", "", $form->generate_text_box('perpage', $mybb->input['perpage'], array('id' => 'perpage')), 'perpage');
	$form_container->end();

	$buttons[] = $form->generate_submit_button("Find Attachments");
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

function build_attachment_row($attachment, $table, $form=null)
{
	global $mybb;
	$attachment['filename'] = htmlspecialchars($attachment['filename']);

	// Here we do a bit of detection, we want to automatically check for removal any missing attachments and any not assigned to a post uploaded > 24hours ago
	// Check if the attachment exists in the file system
	$checked = false;
	$title = $cell_class = '';
	if(!file_exists(MYBB_ROOT.$mybb->settings['uploadspath']."/{$attachment['attachname']}"))
	{
		$cell_class = "bad_attachment";
		$title = "Attachment file could not be found in the uploads directory.";
		$checked = true;
	}
	elseif(!$attachment['pid'] && $attachment['dateuploaded'] < time()-60*60*24 && $attachment['dateuploaded'] != 0)
	{
		$cell_class = "bad_attachment";
		$title = "Attaachment was uploaded over 24 hours ago but not attached to a post.";
		$checked = true;
	}
	else if(!$attachment['tid'] && $attachment['pid'])
	{
		$cell_class = "bad_attachment";
		$title = "Thread or post for this attachment no longer exists.";
		$checked = true;
	}
	elseif($attachment['visible'] == 0)
	{
		$cell_class = "invisible_attachment";
	}

	if(is_object($form))
	{
		$table->construct_cell($form->generate_check_box('aids[]', $attachment['aid'], '', array('checked' => $checked)));
	}
	$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
	$table->construct_cell("<span class=\"float_right\">".get_friendly_size($attachment['filesize'])."</span><a href=\"../attachment.php?aid={$attachment['aid']}\">{$attachment['filename']}</a>", array('class' => $cell_class));

	if($attachment['user_username'])
	{
		$attachment['username'] = $attachment['username'];
	}
	$table->construct_cell(build_profile_link($attachment['username'], $attachment['uid']), array("class" => "align_center"));
	$table->construct_cell("<a href=\"".get_post_link($attachment['pid'])."\">".htmlspecialchars($attachment['subject'])."</a>", array("class" => "align_center"));
	$table->construct_cell(my_number_format($attachment['downloads']), array("class" => "align_center"));
	if($attachment['dateuploaded'] > 0)
	{
		$date = my_date($mybb->settings['dateformat'], $attachment['dateuploaded']).", ".my_date($mybb->settings['timeformat'], $attachment['dateuploaded']);
	}
	else
	{
		$date = $lang->unknown;
	}
	$table->construct_cell($date, array("class" => "align_center"));
	$table->construct_row();
}
?>
