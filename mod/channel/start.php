<?php
/**
 * Minds Channel Profiles
 *
 * @package channel
 */

elgg_register_event_handler('init', 'system', 'channel_init', 1);

// Metadata on users needs to be independent
// outside of init so it happens earlier in boot. See #3316
register_metadata_as_independent('user');

/**
 * Channel init function
 */
function channel_init() {
	
	global $CONFIG;
	$CONFIG->minusername = 2;
	
	if(isset($_COOKIE['_elgg_to_friend']) && elgg_is_logged_in()){
		$friend = elgg_get_logged_in_user_entity()->addFriend($_COOKIE['_elgg_to_friend']);
		if($friend instanceof ElggUser){
			forward($friend->getURL());
		}
	}
	
	//run_function_once('channels_update_avatar_widgets');

	// Register a URL handler for users - this means that profile_url()
	// will dictate the URL for all ElggUser objects
	elgg_register_entity_url_handler('user', 'all', 'channel_url');

	elgg_register_plugin_hook_handler('entity:icon:url', 'user', 'channel_override_avatar_url');
	elgg_unregister_plugin_hook_handler('entity:icon:url', 'user', 'user_avatar_hook');
	
	elgg_register_plugin_hook_handler('register', 'menu:user_hover', 'channel_hover_menu_setup');
	
	//setup the channel elements menu content with defaults
	elgg_register_plugin_hook_handler('register', 'menu:channel_elements', 'channel_elements_menu_setup');
	
	elgg_register_library('channels:suggested', elgg_get_plugins_path() . 'channel/lib/suggested.php');
	
	$item = new ElggMenuItem('channels', elgg_echo('channels'), 'channels');
	elgg_register_menu_item('site', $item);

	elgg_register_simplecache_view('icon/user/default/tiny');
	elgg_register_simplecache_view('icon/user/default/topbar');
	elgg_register_simplecache_view('icon/user/default/small');
	elgg_register_simplecache_view('icon/user/default/medium');
	elgg_register_simplecache_view('icon/user/default/large');
	elgg_register_simplecache_view('icon/user/default/xlarge');
	elgg_register_simplecache_view('icon/user/default/master');

	elgg_register_page_handler('profile', 'channel_page_handler');
	elgg_register_page_handler('channel', 'channel_page_handler');
	//register a page handler for channels search and suggestions
	elgg_register_page_handler('channels', 'channels_page_handler');
	
	elgg_register_event_handler('create','object','channels_widget_added_action');

	elgg_extend_view('page/elements/head', 'channel/metatags');
	elgg_extend_view('css/elgg', 'channel/css');
	elgg_extend_view('js/elgg', 'channel/js');
	//elgg_extend_view('page/layouts/widgets/add_button', 'channel/top', 1);//add to the top of the widget
	
	elgg_register_js('minicolors', elgg_get_site_url() . 'mod/channel/vendors/miniColors/jquery.miniColors.min.js');
	elgg_register_css('minicolors', elgg_get_site_url() . 'mod/channel/vendors/miniColors/jquery.miniColors.css');

	// allow ECML in parts of the profile
	elgg_register_plugin_hook_handler('get_views', 'ecml', 'channel_ecml_views_hook');

	// allow admins to set default widgets for users on profiles
	elgg_register_plugin_hook_handler('get_list', 'default_widgets', 'channel_default_widgets_hook');
	
	//setup a channel info widget
	elgg_register_widget_type(
			'channel_info',
			elgg_echo('channel:widget:info:title'),
			elgg_echo('channel:widget:info:desc')
	);
	//setup the profile icon widget
	$username = get_input('handler');
	$user = get_user_by_username($username);
	elgg_register_widget_type(
			'channel_avatar',
			elgg_echo('channel:widget:avatar:title', array($user->name)),
			elgg_echo('channel:widget:avatar:desc')
	);
	
	//set a new file size
	elgg_set_config('icon_sizes', array(	
											'topbar' => array('w'=>16, 'h'=>16, 'square'=>TRUE, 'upscale'=>TRUE),

											'tiny' => array('w'=>25, 'h'=>25, 'square'=>TRUE, 'upscale'=>TRUE),

											'small' => array('w'=>40, 'h'=>40, 'square'=>TRUE, 'upscale'=>TRUE),

											'medium' => array('w'=>100, 'h'=>100, 'square'=>TRUE, 'upscale'=>TRUE),

											'large' => array('w'=>425, 'h'=>425, 'square'=>FALSE, 'upscale'=>FALSE),
											
											//'xlarge'=> array('w'=>400, 'h'=>400, 'square'=>false, 'upscale'=>false),

											'master' => array('w'=>550, 'h'=>550, 'square'=>FALSE, 'upscale'=>FALSE),
						));
							
	if(elgg_get_context() == 'channel' || elgg_get_context() == 'avatar' || elgg_get_context() == 'profile'){
		elgg_register_menu_item('page', array(	'name' => 'backtochannel',
											'text' => elgg_echo('channel:return'),
											'href' => 'channel/' . elgg_get_logged_in_user_entity()->username,
											));		
		elgg_register_menu_item('page', array(	'name' => 'custom_channel',
											'text' => elgg_echo('channel:custom'),
											'href' => 'channel/' . elgg_get_logged_in_user_entity()->username . '/custom'
											));		
	}

	elgg_register_action("channel/custom", elgg_get_plugins_path() . "channel/actions/custom.php");
							
}

/**
 * Channel page handler (for profiles)
 *
 * @param array $page Array of URL segments passed by the page handling mechanism
 * @return bool
 */
function channel_page_handler($page) {
	
	elgg_set_context('channel');

	if (isset($page[0])) {
		$username = $page[0];
		$user = get_user_by_username($username);
		elgg_set_page_owner_guid($user->guid);
	}

	// short circuit if invalid or banned username
	if (!$user || ($user->isBanned() && !elgg_is_admin_logged_in())) {
		register_error(elgg_echo('channel:notfound'));
		forward();
	}

	$action = NULL;
	if (isset($page[1])) {
		$action = $page[1];
	}

	if ($action == 'edit') {
		// use the core profile edit page
		$base_dir = elgg_get_root_path();
		require "{$base_dir}mod/channel/pages/edit.php";
		return true;
	}
		
	if ($action == 'custom') {
		// use the core profile edit page
		$base_dir = elgg_get_root_path();
		require "{$base_dir}mod/channel/pages/custom.php";
		return true;
	}
		

	// main profile page
	$params = array(
		//'content' => elgg_view('channel/wrapper'),
		'num_columns' => 2,
	);
	$content = elgg_view_layout('widgets', $params);

	$body = elgg_view_layout('one_column', array('content' => $content));
	echo elgg_view_page($user->name, $body);
	return true;
}


/**
 * Channels page handler (for suggested and search etc)
 *
 * @param array $page url segments
 * @return bool
 */
function channels_page_handler($page) {
	$base = elgg_get_plugins_path() . 'channel/pages';

	if (!isset($page[0])) {
		$page[0] = 'popular';
	}

	$vars = array();
	$vars['page'] = $page[0];

	if ($page[0] == 'search') {
		$vars['search_type'] = $page[1];
		require_once "$base/search.php";
	} else {
		require_once "$base/index.php";
	}
	return true;
}


/**
 * Channel URL generator for $user->getUrl();
 *
 * @param ElggUser $user
 * @return string User URL
 */
function channel_url($user) {
	$url = elgg_get_site_url();
	$newurl = str_replace('www.', '', $url);
	$split = explode('://', $newurl);
	//return $split[0] . '://' . $user->username . '.' . $split[1];
	return elgg_get_site_url() . $user->username;
}

/**
 * Use a URL for avatars that avoids loading Elgg engine for better performance
 *
 * @param string $hook
 * @param string $entity_type
 * @param string $return_value
 * @param array  $params
 * @return string
 */
function channel_override_avatar_url($hook, $entity_type, $return_value, $params) {

	// if someone already set this, quit
	if ($return_value) {
		return null;
	}

	$user = $params['entity'];
	$size = $params['size'];
	
	if (!elgg_instanceof($user, 'user')) {
		return null;
	}

	$user_guid = $user->getGUID();
	$icon_time = $user->icontime;

	if (!$icon_time) {
		return "_graphics/icons/user/default{$size}.gif";
	}

	if ($user->isBanned()) {
		return null;
	}

	$filehandler = new ElggFile();
	$filehandler->owner_guid = $user_guid;
	$filehandler->setFilename("profile/{$user_guid}{$size}.jpg");

	try {
		if ($filehandler->exists()) {
			$join_date = $user->getTimeCreated();
			return "mod/channel/icondirect.php?lastcache=$icon_time&joindate=$join_date&guid=$user_guid&size=$size";
		}
	} catch (InvalidParameterException $e) {
		elgg_log("Unable to get profile icon for user with GUID $user_guid", 'ERROR');
		return "_graphics/icons/default/$size.png";
	}

	return null;
}

/**
 * Parse ECML on parts of the profile
 *
 * @param string $hook
 * @param string $entity_type
 * @param array  $return_value
 * @return array
 */
function channel_ecml_views_hook($hook, $entity_type, $return_value) {
	$return_value['channel/profile_content'] = elgg_echo('profile');

	return $return_value;
}

/**
 * Register channel widgets with default widgets
 * These should be the same as profiles.
 *
 * @param string $hook
 * @param string $type
 * @param array  $return
 * @return array
 */
function channel_default_widgets_hook($hook, $type, $return) {
	$return[] = array(
		'name' => elgg_echo('channel'),
		'widget_context' => 'channel',
		'widget_columns' => 2,

		'event' => 'create',
		'entity_type' => 'user',
		'entity_subtype' => ELGG_ENTITIES_ANY_VALUE,
	);

	return $return;
}

function channel_hover_menu_setup($hook, $type, $return, $params) {
	$user = $params['entity'];

	if (elgg_is_logged_in() && $user->canEdit()) {
		/*elgg_load_js('lightbox');
		elgg_load_css('lightbox');*/
		$url = "channel/$user->username/custom/";
		$item = new ElggMenuItem('send', elgg_echo('channel:custom'), $url);
		$item->setSection('action');
		//$item->setLinkClass('elgg-lightbox');
		$return[] = $item;
	}

	return $return;
}

function channel_elements_menu_setup($hook, $type, $return, $params) {
	$user = elgg_get_page_owner_entity();

	//archive
	$url = "archive/owner/$user->username/";
	$item = new ElggMenuItem('archive', elgg_echo('archive'), $url);
	$item->setPriority(1);
	$return[] = $item;
	
	//blogs
	$url = "blog/owner/$user->username/";
	$item = new ElggMenuItem('blog', elgg_echo('blog'), $url);
	$item->setPriority(2);
	$return[] = $item;
	
	//bookmarks
	$url = "bookmarks/owner/$user->username/";
	$item = new ElggMenuItem('bookmark', elgg_echo('bookmarks'), $url);
	$item->setPriority(3);
	$return[] = $item;
	
	//events
	$url = "event_calendar/owner/$user->username/";
	$item = new ElggMenuItem('events', elgg_echo('events'), $url);
	$item->setPriority(4);
	$return[] = $item;
	
	//files
	$url = "file/owner/$user->username/";
	$item = new ElggMenuItem('file', elgg_echo('file'), $url);
	$item->setPriority(5);
	$return[] = $item;
	
	//groups
	$url = "groups/owner/$user->username/";
	$item = new ElggMenuItem('group', elgg_echo('groups'), $url);
	$item->setPriority(6);
	$return[] = $item;
	
	//images
	$url = "photos/owner/$user->username/";
	$item = new ElggMenuItem('images', elgg_echo('photos'), $url);
	$item->setPriority(7);
	$return[] = $item;
	
	//market
	$url = "market/owner/$user->username/";
	$item = new ElggMenuItem('market', elgg_echo('market'), $url);
	$item->setPriority(8);
	$return[] = $item;
	
	//pages
	$url = "pages/owner/$user->username/";
	$item = new ElggMenuItem('pages', elgg_echo('pages'), $url);
	$item->setPriority(9);
	$return[] = $item;
	
	//voting
	$url = "voting/owner/$user->username/";
	$item = new ElggMenuItem('voting', elgg_echo('poll'), $url);
	$item->setPriority(10);
	$return[] = $item;

	return $return;
}

/**
 * run once function to update all users avatar widgets
 */
function channels_update_avatar_widgets(){
	$options = array(
		'type' => 'object',
		'subtype' => 'widget',
		'limit' => 0
	);
	$widgets = elgg_get_entities($options);
	foreach($widgets as $widget){
		if($widget->handler == 'channel_avatar'){
			$owner = $widget->getOwnerEntity();
			$widget->title = $owner->name;
			$widget->save();
		}
	}
}
/**
  * A function to be called every time a user adds a widget so we can add a title to the avatar one. 
  */
function channels_widget_added_action($event, $object_type, $object){
	if($object instanceof ElggWidget){
		if(get_input('handler') == 'channel_avatar'){
			$owner = $object->getOwnerEntity();
			$object->title = $owner->name;
			$object->save();
		}
	}
}

