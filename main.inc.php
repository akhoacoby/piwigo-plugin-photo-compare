<?php
/*
Plugin Name: Image Comparison
Version: 1.0.0
Plugin URI: https://github.com/akhoacoby/piwigo-plugin-photo-compare
Author: akhoacoby
Author URI: https://github.com/akhoacoby
Description: Compare two photos side by side or with a draggable slider, featuring synchronised zoom & pan. Ideal for RAW-vs-JPEG checks and version culling, plus saveable comparison pairs.
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Folder name guard                                                     |
// +-----------------------------------------------------------------------+
// The plugin id is derived from the folder name and used everywhere
// (constants, config key, table name). Stay inert until it is consistent.
if (basename(dirname(__FILE__)) != 'image_comparison')
{
  add_event_handler('init', 'image_comparison_error');
  function image_comparison_error()
  {
    global $page;
    $page['errors'][] = 'Image Comparison folder name is incorrect, uninstall the plugin and rename it to "image_comparison"';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+
global $prefixeTable;

define('IMAGE_COMPARISON_ID', basename(dirname(__FILE__)));
define('IMAGE_COMPARISON_PATH', PHPWG_PLUGINS_PATH . IMAGE_COMPARISON_ID . '/');
define('IMAGE_COMPARISON_REALPATH', realpath(IMAGE_COMPARISON_PATH));
define('IMAGE_COMPARISON_ADMIN', get_root_url() . 'admin.php?page=plugin-' . IMAGE_COMPARISON_ID);
define('IMAGE_COMPARISON_TABLE', $prefixeTable . 'image_comparison_pairs');

// +-----------------------------------------------------------------------+
// | Register event handlers                                               |
// +-----------------------------------------------------------------------+
// Handlers live in include/functions.inc.php (loaded once here); main.inc.php
// only declares constants and wires events — no business logic at top level.
include_once(IMAGE_COMPARISON_PATH . 'include/functions.inc.php');

// Everything loaded: load language + unserialize config.
add_event_handler('init', 'image_comparison_init');

// Claim the virtual "compare" section (URL: index.php?/compare).
add_event_handler('loc_end_section_init', 'image_comparison_loc_end_section_init');

// Render the comparison page body early so combine_css lands in <head>.
add_event_handler('loc_begin_index', 'image_comparison_loc_begin_index');

// "Compare" buttons on album (index) and single-photo pages.
add_event_handler('loc_end_index', 'image_comparison_loc_end_index');
add_event_handler('loc_end_picture', 'image_comparison_loc_end_picture');

// Admin menu link.
add_event_handler('get_admin_plugin_menu_links', 'image_comparison_admin_menu');

// Web-service methods (save / delete comparison pairs).
add_event_handler('ws_add_methods', 'image_comparison_ws_add_methods');
