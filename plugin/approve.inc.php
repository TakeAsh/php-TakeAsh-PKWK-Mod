<?php

define('PLUGIN_APPROVE_CONFIG_ROOT', 'plugin/approve/');
define('PLUGIN_APPROVE_KEY_PATTERN', 'pattern');
define('PLUGIN_APPROVE_KEY_REPLACE', 'replace');
define('PLUGIN_APPROVE_KEY_PAGE_REGEX', 'page_regex');
define('PLUGIN_APPROVE_KEY_LABEL', 'label');
define('PLUGIN_APPROVE_DEFAULT_LABEL', 'Approve');

if (! defined('LIB_DIR')) define('LIB_DIR', '');
require(LIB_DIR . 'yamlconfig.php');

// Show a button
function plugin_approve_inline($name)
{
	global $vars;

	if ($name == ''){
		return '<p>approve(): empty name.</p>';
	}
	$config_path = PLUGIN_APPROVE_CONFIG_ROOT . $name;
	$config = new YamlConfig($config_path);
	if (!$config->read()){
		return '<p>approve(): failed to load config. "' . $config_path . '"</p>';
	}
	$pattern = $config[PLUGIN_APPROVE_KEY_PATTERN];
	$page_regex = $config[PLUGIN_APPROVE_KEY_PAGE_REGEX];
	$label = isset($config[PLUGIN_APPROVE_KEY_LABEL]) ?
		$config[PLUGIN_APPROVE_KEY_LABEL] :
		PLUGIN_APPROVE_DEFAULT_LABEL;

	if ($pattern == ''){
		return '<p>approve(): empty pattern.</p>';
	}
	if ($page_regex == ''){
		return '<p>approve(): empty page_regex.</p>';
	}

	$page = $vars['page'];
	if ($page == ''){
		return '<p>approve(): empty page.</p>';
	}
	$source = get_source($page, FALSE, TRUE);
	$disabled = $page_regex != '' && !preg_match($page_regex, $page) || strpos($source, $pattern) === FALSE ?
		'disabled' :
		'';

	$form = '';
	$form .= '<form action="' . get_script_uri() . '?cmd=approve" method="post">';
	$form .= '<input type="submit" name="submit" value="' . $label . '" ' . $disabled . ' />';
	$form .= '<input type="hidden" name="name"  value="' . $name . '" />';
	$form .= '<input type="hidden" name="_page"  value="' . $page . '" />';
	$form .= '</form>';
	return $form;
}

// Approve
function plugin_approve_action()
{
	global $vars, $post;

	if (auth::check_role('readonly')) die_message(_('PKWK_READONLY prohibits editing'));
	if (auth::is_check_role(PKWK_CREATE_PAGE)) die_message(_('PKWK_CREATE_PAGE prohibits editing'));

	// Petit SPAM Check (Client(Browser)-Server Ticket Check)
	$spam = FALSE;
	if (function_exists('pkwk_session_start') && pkwk_session_start() != 0) {
		$s_tracker = md5(get_ticket() . 'Approve');
		error_log( "\$s_tracker: " . $s_tracker );
		error_log( "\$_SESSION['tracker']: " . $_SESSION['tracker'] );
	} else {
		if (isset($post['encode_hint']) && $post['encode_hint'] != '') {
			error_log( "\$post['encode_hint']: " . $post['encode_hint'] );
			if (PKWK_ENCODING_HINT != $post['encode_hint']) $spam = TRUE;
		} else {
			error_log( "PKWK_ENCODING_HINT: " . PKWK_ENCODING_HINT );
			if (PKWK_ENCODING_HINT != '') $spam = TRUE;
		}
		error_log( "is_spampost: " . is_spampost(array('body'), PLUGIN_TRACKER_REJECT_SPAMCOUNT) );
		if (is_spampost(array('body'), PLUGIN_TRACKER_REJECT_SPAMCOUNT)) $spam = TRUE;
	}
	error_log( "isSpam: " . $spam );
	if ($spam) {
		honeypot_write();
		return array('msg'=>'cannot write', 'body'=>'<p>prohibits editing</p>');
	}

	$name = isset($post['name']) ? $post['name'] : '';
	$page = isset($post['_page']) ? $post['_page'] : '';
	if ($name == ''){
		return '<p>approve(): empty name.</p>';
	}
	if ($page == ''){
		return '<p>approve(): empty page.</p>';
	}
	$config_path = PLUGIN_APPROVE_CONFIG_ROOT . $name;
	$config = new YamlConfig($config_path);
	if (!$config->read()){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): failed to load config. "' . $config_path . '"</p>');
	}
	$pattern = $config[PLUGIN_APPROVE_KEY_PATTERN];
	$replace = $config[PLUGIN_APPROVE_KEY_REPLACE];
	$page_regex = $config[PLUGIN_APPROVE_KEY_PAGE_REGEX];

	if ($page == ''){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): empty page.</p>');
	}
	if ($pattern == ''){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): empty pattern.</p>');
	}
	if ($page_regex == ''){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): empty page_regex.</p>');
	}
	if (!preg_match($page_regex, $page)){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): page not match.</p>');
	}

	if (PKWK_READONLY > 0 || is_freeze($vars['page']) || !plugin_approve_is_edit_authed($page)){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): prohibit editing. "' . $page . '"</p>');
	}

	$source = get_source($page, TRUE, TRUE);
	if ($source === FALSE){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): failed to load page. "' . $page . '"</p>');
	}
	if (strpos($source, $pattern) === FALSE){
		return array('msg'=>'Approve', 'body'=>'<p>approve(): pattern not match.</p>');
	}
	$source = str_replace($pattern, $replace, $source);
	//return array('msg'=>'Approve', 'body'=>$source);
	page_write($page, $source);
	pkwk_headers_sent();
	header('Location: ' . get_page_location_uri($page));
	exit;
}

/**
 * Check if a page is configured to require authentication
 *
 * @param string $page
 * @return boolean
 */
function plugin_approve_is_edit_authed($page)
{
	global $edit_auth, $edit_auth_pages, $auth_method_type;

	if (!$edit_auth) {
		return FALSE;
	}

	$target_str = '';
	if ($auth_method_type == 'pagename') {
		$target_str = $page; // Page name
	} else if ($auth_method_type == 'contents') {
		$target_str = join('', get_source($page)); // Its contents
	}

	$auth_key = auth::get_user_info();
	$user = $auth_key['key'];
	if ($user == ''){
		return FALSE;
	}

	foreach($edit_auth_pages as $regexp => $users) {
		if (preg_match($regexp, $target_str)) {
			return in_array($user, explode(',', $users)) ?
				TRUE :
				FALSE;
		}
	}
	return FALSE;
}

?>
