<?php
/*

Plugin Thanks 3.9.1
(c) 2008-2011 by Huji Lee, SaeedGh (SaeehGhMail@Gmail.com)
Last edit: 11-26-2011

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

*/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

if(isset($GLOBALS['templatelist']))
{
	$GLOBALS['templatelist'] .= ", thanks_postbit_count";
}

$plugins->add_hook("postbit", "thx");
$plugins->add_hook("xmlhttp", "do_action");
$plugins->add_hook("showthread_start", "direct_action");
$plugins->add_hook("class_moderation_delete_post", "deletepost_edit");
$plugins->add_hook('admin_tools_action_handler', 'thx_admin_action');
$plugins->add_hook('admin_tools_menu', 'thx_admin_menu');
$plugins->add_hook('admin_tools_permissions', 'thx_admin_permissions');
$plugins->add_hook('admin_load', 'thx_admin');

function thx_info()
{
	return array(
		'name'			=>	'<img border="0" src="../images/Thanks.gif" align="absbottom" alt="" /> Thanks',
		'description'	=>	'Add a Thanks button to user posts.',
		'website'		=>	'http://www.mybb.com',
		'author'		=>	'Huji Lee, SaeedGh',
		'authorsite'	=>	'mailto:SaeedGhMail@Gmail.com',
		'version'		=>	'3.9.1',
		'guid'		    =>	'd82cb3ceedd7eafa8954449cd02a449f',
        'compatibility' =>	'14*,16*'
	);
}


function thx_install()
{
	global $db;
	if (!$db->table_exists('thx')) {
		$db->query("CREATE TABLE ".TABLE_PREFIX."thx ( 
			txid serial, 
			uid int NOT NULL, 
			adduid int NOT NULL, 
			pid int NOT NULL, 
			time int NOT NULL DEFAULT 0, 
			PRIMARY KEY (txid));"
		);
		// For Postgres: add CONCURRENTLY
		$db->query("CREATE INDEX idx_u ON ".TABLE_PREFIX."thx (adduid, pid, time)");
	}
	if (!$db->field_exists("thx", "users"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."users ADD thx INT DEFAULT 0, ADD thxcount INT DEFAULT 0, ADD thxpost INT  DEFAULT 0";
	}
	elseif (!$db->field_exists("thxpost", "users"))		
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."users ADD thxpost int DEFAULT 0";
	}
	
	if($db->field_exists("thx", "posts"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."posts DROP thx";
	}
	
	if(!$db->field_exists("pthx", "posts"))
	{
		$sq[] = "ALTER TABLE ".TABLE_PREFIX."posts ADD pthx int DEFAULT 0";
	}
	
	if(is_array($sq))
	{
		foreach($sq as $q)
		{
			$db->query($q);
		}
	}
}


function thx_is_installed()
{
	global $db;
	if($db->field_exists('thxpost', "users"))
	{
		return true;
	}
	return false;
}


function thx_activate()
{
	global $db;
	//Adding templates
	require MYBB_ROOT."inc/adminfunctions_templates.php";
	
	if(!find_replace_templatesets("postbit", '#'.preg_quote('{$seperator}').'#', '{$post[\'thxdsp_inline\']}{$seperator}{$post[\'thxdsp_outline\']}'))
	{
		find_replace_templatesets("postbit", '#button_delete_pm(.*)<\/tr>(.*)<\/table>#is', 'button_delete_pm$1</tr>{\$post[\'thxdsp_inline\']}$2</table>{$post[\'thxdsp_outline\']}');
	}
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'button_quote\']}').'#', '{$post[\'button_quote\']}{$post[\'thanks\']}');
	find_replace_templatesets("postbit_classic", '#button_delete_pm(.*)<\/tr>(.*)<\/table>#is', 'button_delete_pm$1</tr>{\$post[\'thxdsp_inline\']}$2</table>{$post[\'thxdsp_outline\']}');
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'button_quote\']}').'#', '{$post[\'button_quote\']}{$post[\'thanks\']}');
		
	find_replace_templatesets("headerinclude", "#".preg_quote('{$newpmmsg}').'#',
		'<script type="text/javascript" src="jscripts/thx.js"></script>{$newpmmsg}');
	
	$templatearray = array(
		'title' => 'thanks_postbit_count',
		'template' => "<div><span class=\"smalltext\">{\$lang->thx_thank} {\$post[\'thank_count\']}<br />
	{\$post[\'thanked_count\']}<br /></span></div>",
		'sid' => '-1',
		);
	$db->insert_query("templates", $templatearray);

	$templatearray = array(
		'title' => 'thanks_postbit_inline',
		'template' => "<tr id=\"thx{\$post[\'pid\']}\" style=\"{\$display_style}\" class=\"trow2 tnx_style tnx_newstl\"><td><span class=\"smalltext\">{\$lang->thx_givenby}</span>&nbsp;<span id=\"thx_list{\$post[\'pid\']}\">\$entries</span></td></tr>",
		'sid' => '-1',
		);	
	$db->insert_query("templates", $templatearray);
	
	$templatearray = array(
		'title' => 'thanks_postbit_inline_classic',
		'template' => "<tr id=\"thx{\$post[\'pid\']}\" style=\"{\$display_style}\" class=\"trow2 tnx_style tnx_classic\"><td><span class=\"smalltext\">{\$lang->thx_givenby}</span></td><td class=\"trow2 tnx_style\" id=\"thx_list{\$post[\'pid\']}\">\$entries</td></tr>",
		'sid' => '-1',
		);	
	$db->insert_query("templates", $templatearray);

	$templatearray = array(
		'title' => 'thanks_postbit_outline',
		'template' => "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\" id=\"thx{\$post[\'pid\']}\" style=\"{\$display_style};margin-top:5px;\"><tr><td>
		<table border=\"0\" cellspacing=\"{\$theme[\'borderwidth\']}\" cellpadding=\"{\$theme[\'tablespace\']}\" class=\"tborder thxdsp_outline\"><tr class=\"trow1 tnx_style\"><td valign=\"top\" width=\"1%\" nowrap=\"nowrap\"><img src=\"{\$mybb->settings[\'bburl\']}/images/rose.gif\" align=\"absmiddle\" /> &nbsp;<span class=\"smalltext\">{\$lang->thx_givenby}</span></td><td class=\"trow2 tnx_style\" id=\"thx_list{\$post[\'pid\']}\">\$entries</td></tr></table>
		</td></tr></table>",
		'sid' => '-1',
		);
	$db->insert_query("templates", $templatearray);
	// ALTER SEQUENCE settinggroups_gid_seq  RESTART WITH nn 
	$thx_group = array(
		"name"			=> "Thanks",
		"title"			=> "Thanks",
		"description"	=> "Displays thank you note below each post.",
		"disporder"		=> "1",
		"isdefault"		=> "1"
	);	
	$gid = $db->insert_query("settinggroups", $thx_group);	
	$thx[]= array(
		"name"			=> "thx_active",
		"title"			=> "Activate/Deactivate this plugin",
		"description"	=> "Activate or deactivate plugin but no delete table",
		"optionscode" 	=> "onoff",
		"value"			=> '1',
		"disporder"		=> '1',
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_count",
		"title"			=> "Show count thanks",
		"description"	=> "Show count thanks in any post",
		"optionscode" 	=> "onoff",
		"value"			=> '1',
		"disporder"		=> '2',
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_del",
		"title"			=> "Users can remove their thanks",
		"description"	=> "Every one can delete his thanks",
		"optionscode" 	=> "onoff",
		"value"			=> '1',
		"disporder"		=> '3',
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_hidemode",
		"title"			=> "Show date on mouse over",
		"description"	=> "Show date of thanks just when mouse is over it",
		"optionscode" 	=> "onoff",
		"value"			=> '1',
		"disporder"		=> '4',
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_autolayout",
		"title"			=> "Auto detect layout",
		"description"	=> "Detect postbit layout and try to correct related HTML code! (just works if \"Separate table\" is ON)",
		"optionscode" 	=> "onoff",
		"value"			=> '1',
		"disporder"		=> '5',
		"gid"			=> intval($gid),
	);
	
	$thx[] = array(
		"name"			=> "thx_outline",
		"title"			=> "Separate table",
		"description"	=> "If you want to show thanks between of tow post (not in end of a post), switch this option on.",
		"optionscode"	=> "onoff",
		"value"			=> '1',
		"disporder"		=> '6',
		"gid"			=> intval($gid),
	);
	
	foreach($thx as $t)
	{
		$db->insert_query("settings", $t);
	}
	
	rebuild_settings();
}


function thx_deactivate()
{
	global $db;
	require '../inc/adminfunctions_templates.php';
	
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'thxdsp_inline\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'thxdsp_outline\']}').'#', '', 0);
	find_replace_templatesets("postbit", '#'.preg_quote('{$post[\'thanks\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'thxdsp_inline\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'thxdsp_outline\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote('{$post[\'thanks\']}').'#', '', 0);
	find_replace_templatesets("headerinclude", "#".preg_quote('<script type="text/javascript" src="jscripts/thx.js"></script>').'#', '', 0);
	
	$db->delete_query("settings", "name IN ('thx_active', 'thx_count', 'thx_del', 'thx_hidemode', 'thx_autolayout', 'thx_outline')");
	$db->delete_query("settinggroups", "name='Thanks'");
	$db->delete_query("templates", "title='thanks_postbit_count'");
	$db->delete_query("templates", "title='thanks_postbit_inline'");
	$db->delete_query("templates", "title='thanks_postbit_inline_classic'");
	$db->delete_query("templates", "title='thanks_postbit_outline'");
	
	rebuild_settings();
}


function thx_uninstall()
{
	global $db;

	/*$db->query("drop TABLE ".TABLE_PREFIX."thx");*/

	if($db->field_exists("thx", "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP thx, DROP thxcount, DROP thxpost");
	}
	
	if($db->field_exists("pthx", "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts DROP pthx");
	}
}


function thx(&$post) 
{
	global $db, $mybb, $lang ,$session, $theme, $altbg, $templates, $thx_cache;
	
	if(!$mybb->settings['thx_active'] || !empty($session->is_spider))
	{
		return false;
	}
	
	$lang->load("thx");
	
	if($b = $post['pthx'])
	{
		$entries = build_thank($post['pid'], $b);
	}
	else 
	{
		$entries = "";
	}
	
 	if($mybb->user['uid'] != 0 && $mybb->user['uid'] != $post['uid']) 
	{
		if(!$b)
		{
			$post['thanks'] = "<a id=\"a{$post['pid']}\" onclick=\"javascript:return thx({$post['pid']});\" href=\"showthread.php?action=thank&tid={$post['tid']}&pid={$post['pid']}\">
			<img src=\"{$mybb->settings['bburl']}/{$theme['imgdir']}/postbit_thx.gif\" border=\"0\" alt=\"$lang->thx_main\" title=\"$lang->thx_main\" id=\"i{$post['pid']}\" /></a>";
		}
		else if($mybb->settings['thx_del'] == "1")
		{
			$post['thanks'] = "<a id=\"a{$post['pid']}\" onclick=\"javascript:return rthx({$post['pid']});\" href=\"showthread.php?action=remove_thank&tid={$post['tid']}&pid={$post['pid']}\">
			<img src=\"{$mybb->settings['bburl']}/{$theme['imgdir']}/postbit_rthx.gif\" border=\"0\" alt=\"$lang->thx_remove\" title=\"$lang->thx_remove\" id=\"i{$post['pid']}\" /></a>";
		}
		else
		{
			$post['thanks'] = "<!-- remove thanks disabled by administrator -->";
		}
	}
	
	$display_style = $entries ?  "" : "display:none; border:0;";
	$playout = $mybb->settings['postlayout'];
	
	if(!$mybb->settings['thx_outline'])
	{
		eval("\$post['thxdsp_inline'] .= \"".$templates->get("thanks_postbit_inline")."\";");
									
		if($mybb->settings['thx_autolayout'] && $playout == "classic")
		{
			eval("\$post['thxdsp_inline'] .= \"".$templates->get("thanks_postbit_inline_classic")."\";");
		}
	}
	else
	{	
		eval("\$post['thxdsp_outline'] .= \"".$templates->get("thanks_postbit_outline")."\";");
	}
	
	if($mybb->settings['thx_count'] == "1")
	{
		if(!isset($thx_cache['postbit'][$post['uid']]))
		{
			$post['thank_count'] = $post['thx'];
			$post['thanked_count'] = $lang->sprintf($lang->thx_thanked_count, $post['thxcount'], $post['thxpost']);
			eval("\$x = \"".$templates->get("thanks_postbit_count")."\";");
			$thx_cache['postbit'][$post['uid']] = $x;
		}
		
		$post['user_details'] .= $thx_cache['postbit'][$post['uid']];
	}
}

function do_action()
{
	global $mybb, $lang, $theme;
	
	if(($mybb->input['action'] != "thankyou"  &&  $mybb->input['action'] != "remove_thankyou") || $mybb->request_method != "post")
	{
		return false;
	}
		
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))	
	{
		$lang->load("thx");
	}
	else 
	{
		$l = $lang->language;
		$lang->set_language();
		$lang->load("thx");
		$lang->set_language($l);
	}
	
	$pid = intval($mybb->input['pid']);
	
	if ($mybb->input['action'] == "thankyou" )
	{
		do_thank($pid);
	}
	else if($mybb->settings['thx_del'] == "1")
	{
		del_thank($pid);
	}
	
	$nonead = 0;
	$list = build_thank($pid, $nonead);
	header('Content-Type: text/xml');
	$output = "<thankyou>
				<list><![CDATA[$list]]></list>
				<display>".($list ? "1" : "0")."</display>
				<image>{$mybb->settings['bburl']}/{$theme['imgdir']}/";
	
	if($mybb->input['action'] == "thankyou")
	{
		$output .= "postbit_rthx.gif";
	}
	else
	{
		$output .= "postbit_thx.gif";
	}
	
	$output .= "</image>
			  <del>{$mybb->settings['thx_del']}</del>	
			 </thankyou>";
	echo $output;
}

function direct_action()
{
	global $mybb, $lang;
	
	if($mybb->input['action'] != "thank"  &&  $mybb->input['action'] != "remove_thank")
	{
		return false;
	}
		
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))	
	{
		$lang->load("thx");
	}
	else 
	{
		$l = $lang->language;
		$lang->set_language();
		$lang->load("thx");
		$lang->set_language($l);
	}
	$pid=intval($mybb->input['pid']);
	
	if($mybb->input['action'] == "thank" )
	{
		do_thank($pid);
	}
	else if($mybb->settings['thx_del'] == "1")
	{
		del_thank($pid);
	}
	redirect($_SERVER['HTTP_REFERER']);
}

function build_thank($pid, &$is_thx)
{
	global $db, $mybb, $lang, $thx_cache;
	$is_thx = 0;
	
	$pid = intval($pid);
	
	if(file_exists($lang->path."/".$lang->language."/thx.lang.php"))
	{
		$lang->load("thx");
	}
	else
	{
		$l=$lang->language;
		$lang->set_language();
		$lang->load("thx");
		$lang->set_language($l);
	}
	$dir = $lang->thx_dir;
	
	$query=$db->query("SELECT th.txid, th.uid, th.adduid, th.pid, th.time, u.username, u.usergroup, u.displaygroup 
		FROM ".TABLE_PREFIX."thx th 
		JOIN ".TABLE_PREFIX."users u 
		ON th.adduid=u.uid 
		WHERE th.pid=$pid 
		ORDER BY th.time ASC" 
	);

	while($record = $db->fetch_array($query))
	{
		if($record['adduid'] == $mybb->user['uid'])
		{
			$is_thx++;
		}
		$date = my_date($mybb->settings['dateformat'].' '.$mybb->settings['timeformat'], $record['time']);
		if(!isset($thx_cache['showname'][$record['username']]))
		{
			$url = get_profile_link($record['adduid']);
			$name = format_name($record['username'], $record['usergroup'], $record['displaygroup']);
			$thx_cache['showname'][$record['username']] = "<a href=\"$url\" dir=\"$dir\">$name</a>";
		}
		
		if($mybb->settings['thx_hidemode'])
		{
			$entries .= $r1comma." <span title=\"".$date."\">".$thx_cache['showname'][$record['username']]."</span>";
		}
		else
		{
			$entries .= $r1comma.$thx_cache['showname'][$record['username']]." <span class=\"smalltext\">(".$date.")</span>";
		}
		
		$r1comma = $lang->thx_comma;
	}
	
	return $entries;
}

function do_thank($pid)
{
	global $db, $mybb;
	
	$pid = intval($pid);
	
	$check_query = $db->simple_select("thx", "count(*) as c" ,"adduid={$mybb->user['uid']} AND pid=$pid", array("limit"=>"1"));
			
	$tmp=$db->fetch_array($check_query);
	if($tmp['c'] != 0)
	{
		return false;
	}
		
	$check_query = $db->simple_select("posts", "uid", "pid=$pid", array("limit"=>1));
	if($db->num_rows($check_query) == 1)
	{
		
		$tmp=$db->fetch_array($check_query);
		
		if($tmp['uid'] == $mybb->user['uid'])
		{
			return false;
		}		
			
		$database = array (
			"uid" =>$tmp['uid'],
			"adduid" => $mybb->user['uid'],
			"pid" => $pid,
			"time" => time()
		);
		
		unset($tmp);
		
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx+1 WHERE uid={$mybb->user['uid']}",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount+1, thxpost=CASE( SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid={$pid}) WHEN 0 THEN thxpost+1 ELSE thxpost END WHERE uid={$database['uid']}",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx+1 WHERE pid={$pid}"
		);
				  
		foreach($sq as $q)
		{
			$db->query($q);
		}
		$db->insert_query("thx", $database);
	}	
}

function del_thank($pid)
{
	global $mybb, $db;
	
	$pid = intval($pid);
	if($mybb->settings['thx_del'] != "1")
	{
		return false;
	}

	$check_query = $db->simple_select("thx", "uid, txid" ,"adduid={$mybb->user['uid']} AND pid=$pid", array("limit"=>"1"));		
	
	if($db->num_rows($check_query))
	{
		$data = $db->fetch_array($check_query);
		$uid = intval($data['uid']);
		$thxid = intval($data['txid']);
		unset($data);
		
		$sq = array (
			"UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid={$mybb->user['uid']}",
			"UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, thxpost=CASE(SELECT COUNT(*) FROM ".TABLE_PREFIX."thx WHERE pid={$pid}) WHEN 0 THEN thxpost-1 ELSE thxpost END WHERE uid={$uid}",
			"UPDATE ".TABLE_PREFIX."posts SET pthx=pthx-1 WHERE pid={$pid}"
		);
		
		$db->delete_query("thx", "txid={$thxid}", "1");
		
		foreach($sq as $q)
		{
			$db->query($q);
		}
	}
}

function deletepost_edit($pid)
{
	global $db;
	
	$pid = intval($pid);
	$q = $db->simple_select("thx", "uid, adduid", "pid={$pid}");
	
	$postnum = $db->num_rows($q);
	if($postnum <= 0)
	{
		return false;
	}
	
	$adduids = array();
	
	while($r = $db->fetch_array($q))
	{
		$uid = intval($r['uid']);
		$adduids[] = $r['adduid'];
	}
	
	$adduids = implode(", ", $adduids);
	
	$sq = array();
	$sq[] = "UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount-1, thxpost=thxpost-1 WHERE uid={$uid}";
	$sq[] = "UPDATE ".TABLE_PREFIX."users SET thx=thx-1 WHERE uid IN ({$adduids})";
	
	foreach($sq as $q)
	{
		$db->query($q);
	}
	
	$db->delete_query("thx", "pid={$pid}", $postnum);
	
}

function thx_admin_action(&$action)
{
	$action['recount_thanks'] = array ('active'=>'recount_thanks');
}

function thx_admin_menu(&$sub_menu)
{
	$sub_menu['45'] = array	(
		'id'	=> 'recount_thanks',
		'title'	=> 'Recount thanks',
		'link'	=> 'index.php?module=tools/recount_thanks'
	);
}

function thx_admin_permissions(&$admin_permissions)
{
	$admin_permissions['recount_thanks'] = 'Can recount thanks';
}

function thx_admin()
{
	global $mybb, $page, $db;
	require_once MYBB_ROOT.'inc/functions_rebuild.php';
	if($page->active_action != 'recount_thanks')
	{
		return false;
	}

	if($mybb->request_method == "post")
	{
		if(!isset($mybb->input['page']) || intval($mybb->input['page']) < 1)
		{
			$mybb->input['page'] = 1;
		}
		if(isset($mybb->input['do_recountthanks']))
		{
			if(!intval($mybb->input['thx_chunk_size']))
			{
				$mybb->input['thx_chunk_size'] = 500;
			}
			
			do_recount();
		}
		else if(isset($mybb->input['do_recountposts']))
		{
			if(!intval($mybb->input['post_chunk_size']))
			{
				$mybb->input['post_chunk_size'] = 500;
			}
			
			do_recount_post();
		}
	}
	
	$page->add_breadcrumb_item('Recount thanks', "index.php?module=tools/recount_thanks");
	$page->output_header('Recount thanks');
	
	$sub_tabs['thankyoulike_recount'] = array(
		'title'			=> 'Recount thanks',
		'link'			=> "index.php?module=tools/recount_thanks",
		'description'	=> 'Update the thanks counters'
	);
	
	$page->output_nav_tabs($sub_tabs, 'thankyoulike_recount');

	$form = new Form("index.php?module=tools/recount_thanks", "post");
	
	$form_container = new FormContainer('Recount thanks');
	$form_container->output_row_header('Name');
	$form_container->output_row_header('Chunk size', array('width' => 50));
	$form_container->output_row_header("&nbsp;");
	
	$form_container->output_cell("<label>Update thanks counters</label>
	<div class=\"description\">Updates the counters for the number of thanks given/received by users and the number of thanks given to each post.</div>");
	$form_container->output_cell($form->generate_text_box("thx_chunk_size", 100, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button('Go', array("name" => "do_recountthanks")));
	$form_container->construct_row();
	
	$form_container->output_cell("<label>Update post counters</label>
	<div class=\"description\">Updates the numer of posts in which a user has received thanks.</div>");
	$form_container->output_cell($form->generate_text_box("post_chunk_size", 500, array('style' => 'width: 150px;')));
	$form_container->output_cell($form->generate_submit_button('Go', array("name" => "do_recountposts")));
	$form_container->construct_row();
	
	$form_container->end();

	$form->end();
		
	$page->output_footer();

	exit;
}

function do_recount()
{
	global $db, $mybb;
	
	$cur_page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['thx_chunk_size']);
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;
	
	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET thx=0, thxcount=0");
		$db->write_query("UPDATE ".TABLE_PREFIX."posts SET pthx=0");
	}
	
	$query = $db->simple_select("thx", "COUNT(txid) AS thx_count");
	$thx_count = $db->fetch_field($query, 'thx_count');
	
	$query = $db->query("
		SELECT uid, adduid, pid 
		FROM ".TABLE_PREFIX."thx 
		ORDER BY time ASC 
		LIMIT $start, $per_page 
	");
	
	$post_thx = array();
	$user_thx = array();
	$user_thx_to = array();
	
	while($thx = $db->fetch_array($query))
	{
		if($post_thx[$thx['pid']])
		{
			$post_thx[$thx['pid']]++;
		}
		else
		{
			$post_thx[$thx['pid']] = 1;
		}
		if($user_thx[$thx['adduid']])
		{
			$user_thx[$thx['adduid']]++;
		}
		else
		{
			$user_thx[$thx['adduid']] = 1;
		}
		if($user_thx_to[$thx['uid']])
		{
			$user_thx_to[$thx['uid']]++;
		}
		else
		{
			$user_thx_to[$thx['uid']] = 1;
		}
	}
	
	if(is_array($post_thx))
	{
		foreach($post_thx as $pid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."posts SET pthx=pthx+$change WHERE pid=$pid");
		}
	}
	if(is_array($user_thx))
	{
		foreach($user_thx as $adduid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET thx=thx+$change WHERE uid=$adduid");
		}
	}
	if(is_array($user_thx_to))
	{
		foreach($user_thx_to as $uid => $change)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET thxcount=thxcount+$change WHERE uid=$uid");
		}
	}
	my_check_proceed($thx_count, $end, $cur_page+1, $per_page, "thx_chunk_size", "do_recountthanks", "Successfully updated the thanks counters");
}

function do_recount_post()
{
	global $db, $mybb;
	
	$cur_page = intval($mybb->input['page']);
	$per_page = intval($mybb->input['post_chunk_size']);
	$start = ($cur_page-1) * $per_page;
	$end = $start + $per_page;
	
	if ($cur_page == 1)
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET thxpost=0");
	}
	
	$query = $db->simple_select("thx", "COUNT(distinct pid) AS post_count");
	$post_count = $db->fetch_field($query, 'post_count');
	
	$query = $db->query("
		SELECT uid, pid 
		FROM ".TABLE_PREFIX."thx 
		GROUP BY pid 
		ORDER BY pid ASC 
		LIMIT $start, $per_page 
	");

	while($thx = $db->fetch_array($query))
	{
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET thxpost=thxpost+1 WHERE uid={$thx['uid']}");
	}
	
	my_check_proceed($post_count, $end, $cur_page+1, $per_page, "post_chunk_size", "do_recountposts", "Successfully updated the post counters");
}

function my_check_proceed($current, $finish, $next_page, $per_page, $name_chunk, $name_submit, $message)
{
	global $page;
	
	if($finish >= $current)
	{
		flash_message($message, 'success');
		admin_redirect("index.php?module=tools/recount_thanks");
	}
	else
	{
		$page->output_header();
		
		$form = new Form("index.php?module=tools/recount_thanks", 'post');
		
		echo $form->generate_hidden_field("page", $next_page);
		echo $form->generate_hidden_field($name_chunk, $per_page);
		echo $form->generate_hidden_field($name_submit, "Go");
		echo "<div class=\"confirm_action\">\n";
		echo "<p>Click \"Proceed\" to continue the recount and rebuild process.</p>\n";
		echo "<br />\n";
		echo "<p class=\"buttons\">\n";
		echo $form->generate_submit_button("Proceed", array('class' => 'button_yes'));
		echo "</p>\n";
		echo "</div>\n";
		
		$form->end();
		
		$page->output_footer();
		exit;
	}
}

?>