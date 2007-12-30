<?php
/**
 * Functions for plugins
 *
 * Everything that handles plugins. Loads, adds, removes,
 * recalculates lists, etc
 *
 * @author Ryan McCue <cubegames@gmail.com>
 * @package Lilina
 * @version 1.0
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

defined('LILINA') or die('Restricted access');
/**
* @todo Document globals
*/
global $activated_plugins, $registered_plugins, $hooked_plugins;
$activated_plugins = array();
$registered_plugins = array();
/**
 * All currently hooked plugins and associated functions
 * @global array $hooked_plugins
 */
$hooked_plugins = array('template_header' => array());
$activated_plugins	= @file_get_contents($settings['files']['plugins']) ;
$activated_plugins	= unserialize( base64_decode( $activated_plugins ) ) ;

/**
* Get all hooked plugins at hook
*
* @global array All currently hooked plugins
* @param string $hook Hook to look for plugin functions at
* @return array All the hooked plugins available at hook
*/
function get_hooked($hook) {
	global $hooked_plugins;
	if(isset($hooked_plugins[$hook])) {
		return $hooked_plugins[$hook];
	}
	return array();
}

/**
 * Applies filters specified by <tt>$filter_name</tt> on <tt>$string</tt>
 *
 * Thanks to WordPress for inspiration
 * @todo Document
 * @uses get_hooked Get the hooked plugins at the specifed plugin
 * @param string $filter_name Hook to call plugin functions for
 * @param string $string String to run through filters
 */
function apply_filters($filter_name, $string){
	global $filters;
	if(!isset($filters[$filter_name])) {
		return $string;
	}
	$args = func_get_args();
	foreach($filters[$filter_name] as $filter) {
		$filter_function = $filter['function'];
		$string = call_user_func_array($filter['function'], array_slice($args, 1, (int) $filter['num_args']));
	}
	return $string;
}

/**
 * Applies filters specified by <tt>$filter_name</tt> on <tt>$string</tt>
 *
 * Thanks to WordPress for inspiration
 * @todo Document
 * @uses get_hooked Get the hooked plugins at the specifed plugin
 * @param string $action_name Hook to call plugin functions for
 */
function do_action($action_name){
	global $actions;
	apply_filters($action_name, false);
}

/**
* Register plugin with system
*
* Adds plugin file to $registered_plugins array so we can load it later
*
* @param string $file Plugin file
* @param string $name Plugin name
*/
function register_plugin($file, $name) {
	global $registered_plugins;
	$registered_plugins[$name]	= array(
										'file'	=> $file
										);
}

/**
* Register plugin function with system
*
* Adds plugin function to $hooked_plugins under the specified hook
*
* @param string $function Plugin function to register
* @param string $hook Hook to register function under
*/
function register_plugin_function($function, $hook) {
	global $hooked_plugins;
	$hooked_plugins[$hook][]	= array(
										'func'	=> $function
										);
}

/**
* Register plugin function with system
*
* Adds plugin function to $hooked_plugins under the specified hook
*
* @param string $function Plugin function to register
* @param string $hook Hook to register function under
*/
function register_filter($filter, $function, $num_args) {
	global $filters;
	$filters[$filter][]	= array(
										'function'	=> $function,
										'num_args'	=> $num_args,
										);
}

/**
* Register plugin function with system
*
* Adds plugin function to $hooked_plugins under the specified hook
*
* @param string $function Plugin function to register
* @param string $function Hook to register function under
*/
function register_action($action, $function) {
	register_filter($action, $function, 0);
}

/**
* Activate plugin
*
* Adds plugin to $activated_plugins. Must call {@link update_plugins_info} afterwards
*
* @param string $plugin Plugin name to activate
*/
function activate_plugin($plugin) {
	global $activated_plugins;
	$activated_plugins[] 		= $plugin;
}

/**
* Get plugins and load them
*
* Gets all activated plugins and require_once()s their files
*/
function get_plugins() {
	global $activated_plugins, $registered_plugins;
	foreach($activated_plugins as $plugin_name) {
		require_once(LILINA_INCPATH . '/plugins/' . $registered_plugins[$plugin_name]['file']);
	}
}

/**
 * Gets metadata about plugins
 *
 * Thanks to Wordpress, admin-functions.php, lines 1525-1534
 * @author Wordpress Development Team
 * @param string $plugin_file Plugin file to search for metadata
 * @return array Plugin metadata
 */
function plugins_meta($plugin_file) {
	$plugin_data = implode('', file($plugin_file));
	preg_match("|Plugin Name:(.*)|i", $plugin_data, $plugin_name);
	preg_match("|Plugin URI:(.*)|i", $plugin_data, $plugin_uri);
	preg_match("|Description:(.*)|i", $plugin_data, $description);
	preg_match("|Author:(.*)|i", $plugin_data, $author_name);
	preg_match("|Author URI:(.*)|i", $plugin_data, $author_uri);
	//If the plugin sets the version...
	if (preg_match("|Version:(.*)|i", $plugin_data, $version)) {
		//...Let it
		$version = trim($version[1]);
	}
	else {
		//...Otherwise assume it's 1.0
		$version = 1.0;
	}
	//If the plugin sets the version...
	if (preg_match("|Min Version:(.*)|i", $plugin_data, $min_version)) {
		//...Let it
		$min_version = trim($min_version[1]); //F1
	}
	else {
		//...Otherwise assume it's the current version of Lilina
		$min_version = 1.0;
	}
	//Set the $plugin array for returning
	$plugin					= array();
	$plugin['name']			= $plugin_name[1];
	$plugin['uri']			= $plugin_uri[1];
	$plugin['description']	= $description[1];
	$plugin['author']		= $author_name[1];
	$plugin['author_uri']	= $author_uri[1];
	$plugin['version']		= $version[1];
	$plugin['min_version']	= $min_version[1];
	return $plugin;
}

/**
 * Returns available plugin file
 *
 * Gets a list of all PHP files within a given directory. Primarily used for plugins, but
 * can be used for other things, such as _by_ plugins. Probably needs to be renamed to
 * lilina_file_list() and also accept a file type parameter
 *
 * @param string $directory Directory to search for plugin files in
 */
function lilina_plugins_list($directory){
	//Make sure we open it correctly
	if ($handle = opendir($directory)) {
		//Go through all entries
		while (false !== ($file = readdir($handle))) {
			// just skip the reference to current and parent directory
			if ($file != '.' && $file != '..') {
				if (is_dir($directory . '/' . $file)) {
					//Found a directory, let's see if a plugin exists in it,
					//with the same name as the directory
					if(file_exists($directory . '/' . $file . '/' . $file . '.php')) {
						$plugin_list[] = $directory . '/' . $file . '/' . $file . '.php';
					}
				} else {
					//Only add plugin files
					if(strpos($file,'.php') !== FALSE) {
						$plugin_list[] = $directory . '/' . $file;
					}
				}
			}
		}
		// ALWAYS remember to close what you opened
		closedir($handle);
	}
	return $plugin_list;
}

function lilina_init_plugins() {
	$plugins		= lilina_plugins_list(LILINA_INCPATH . '/plugins');
	foreach($plugins as $the_plugin) {
		require_once($the_plugin);
	}
	$old_plugins	= array(); //lilina_old_plugins();
	//Note the order; array_diff() returns missing elements from _2nd_ param
	//Therefore:
	$new_plugins	= array_diff($plugins, $old_plugins);
	$gone_plugins	= array_diff($old_plugins, $plugins);
	//Load the plugin files
	get_plugins();
}
?>