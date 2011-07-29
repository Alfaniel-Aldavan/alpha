<?php
/*
 * update like statistics for the given post
 * $mid = message id
 * 
 * todo: needed db updates for installer
 * messages: likes_count INT(4), likes_status VARCHAR(120)
 * members:  likes_received INT(4);
 */

loadLanguage('Like');

function LikesUpdate($mid)
{
	global $context;
	global $settings, $user_info, $sourcedir, $smcFunc;
	$first = true;
	$like_string = '';
	$count = 0;
	$likers = array();
	
	$request = $smcFunc['db_query']('', '
		SELECT l.id_msg AS like_message, l.id_user AS like_user, m.member_name FROM {db_prefix}likes AS l
			LEFT JOIN {db_prefix}members AS m on m.id_member = l.id_user WHERE l.id_msg = '.$mid.' ORDER BY l.updated DESC');

	while ($row = $smcFunc['db_fetch_assoc']($request)) {
		if(empty($row['member_name']))
			continue;
		$likers[$count] = $row['like_user'].'__//__' . $row['member_name'];
		$count++;
		if($count > 3)
			break;
	}
	$like_string = implode("__||__", $likers);
	$smcFunc['db_free_result']($request);
	
	
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_msg) as count
			FROM {db_prefix}likes AS l WHERE l.id_msg = '.$mid);
	$count = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$totalcount = $count[0];
	
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages SET likes_count = '.$totalcount.', like_status = "'.$like_string.'" WHERE id_msg = '.$mid);
	//$names = explode("__||__", $like_string);
	//echo "count = $totalcount, status = $like_string";
	//var_dump($names);
	$result['count'] = $totalcount;
	$result['status'] = $like_string;
	return($result);
}

/*
 * generate readable output from the like_status database field
 * store it in $output
 * $have liked indicates that the current user has liked the post.
 */
function LikesGenerateOutput($like_status, &$output, $total_likes, $mid, $have_liked)
{
	global $user_info, $scripturl, $like_template, $txt;
	
	$like_template = array();
	$like_template[1] = $txt['1like'];
	$like_template[2] = $txt['2likes'];
	$like_template[3] = $txt['3likes'];
	$like_template[4] = $txt['4likes'];
	$like_template[5] = $txt['5likes'];

	$likers = explode("__||__", $like_status);
	$n = 1;
	$results = array();
	
	foreach($likers as $liker) {
		if(!empty($liker)) {
			$liker_components = explode("__//__", $liker);
			if($liker_components[1] === $user_info['name']) {
				$results[0] = $txt['you_liker'];
				continue;
			}
			$results[$n++] = '<a class="mcard" data-id="'.$liker_components[0].'" href="'.$scripturl.'?action=profile;u='.intval($liker_components[0]).'">'.$liker_components[1].'</a>';
		}
	}
	
	$count = count($results);
	if($count == 0)
		return($output);
		
	if($have_liked && !isset($results[0])) {
		array_pop($results);
		$results[0] = $txt['you_liker'];
		$count = count($results);
	}
	
	ksort($results);
	if(isset($results[0]) && $count == 1)
		$output = $txt['you_like_it'];
	else if($total_likes > 4) {
		$output = vsprintf($like_template[5], $results);
		$output .= ('<a href="'.$scripturl.'?action=getlikes;m='.$mid.'">'. ($total_likes - $count).$txt['like_others']);
	}
	else
		$output = vsprintf($like_template[$count], $results);
	return($output);
}
?>
