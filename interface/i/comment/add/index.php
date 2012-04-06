<?php
/// Copyright (c) 2004-2012, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
define('__TEXTCUBE_IPHONE__', true);
require ROOT . '/library/preprocessor.php';
requireView('iphoneView');

requireStrictRoute();
$entryId = $suri['id'];
$IV = array(
	'GET' => array(
		"name_$entryId" => array('string', 'default' => null),
		"password_$entryId" => array('string', 'default' => ''),
		"secret_$entryId" => array('string', 'default' => null),
		"homepage_$entryId" => array('string', 'default' => 'http://'),
		"comment_$entryId" => array('string', 'default' => '')
	)
);
if(!Validator::validate($IV))
	Respond::NotFoundPage();
if (!doesHaveOwnership() && empty($_GET["name_$entryId"])) {
	printMobileErrorPage(_text('Comment write error.'), _text('Please enter your name.'), $context->getProperty('uri.blog')."/comment/$entryId");
} else if (!doesHaveOwnership() && empty($_GET["comment_$entryId"])) {
	printMobileErrorPage(_text('Comment write error.'), _text('Please enter content.'), $context->getProperty('uri.blog')."/comment/$entryId");
} else {
	$comment = array();
	$comment['entry'] = $entryId;
	$comment['parent'] = null;
	$comment['name'] = empty($_GET["name_$entryId"]) ? '' : $_GET["name_$entryId"];
	$comment['password'] = empty($_GET["password_$entryId"]) ? '' : $_GET["password_$entryId"];
	$comment['homepage'] = empty($_GET["homepage_$entryId"]) || ($_GET["homepage_$entryId"] == 'http://') ? '' : $_GET["homepage_$entryId"];
	$comment['secret'] = empty($_GET["secret_$entryId"]) ? 0 : 1;
	$comment['comment'] = $_GET["comment_$entryId"];
	$comment['ip'] = $_SERVER['REMOTE_ADDR'];
	$result = addComment($blogid, $comment);
	if (in_array($result, array('ip', 'name', 'homepage', 'comment', 'openidonly', 'etc'))) {
		if ($result == 'openidonly') {
			$blockMessage = _text('You have to log in with and OpenID to leave a comment.');
		} else {
			$blockMessage = _textf('Blocked %1', $result);
		}
		printMobileErrorPage(_text('Comment write blocked.'), $blockMessage, "$context->getProperty('uri.blog')/comment/$entryId");
	} else if ($result === false) {
		printMobileErrorPage(_text('Comment write error.'), _text('Cannot write comment.'), "$context->getProperty('uri.blog')/comment/$entryId");
	} else {
		setcookie('guestName', $comment['name'], time() + 2592000, $context->getProperty('uri.blog'));
		setcookie('guestHomepage', $comment['homepage'], time() + 2592000, $context->getProperty('uri.blog'));
		printMobileSimpleMessage(_text('Comment registered.'), _text('Go to comments page'), $context->getProperty('uri.blog')."/comment/$entryId");
	}
}
?>
