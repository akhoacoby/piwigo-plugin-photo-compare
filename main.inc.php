<?php
/*
Version: 1.0.0
Plugin Name: Image Comparison
Plugin URI: https://github.com/LintyDev
Author: Linty
Author URI: https://github.com/LintyDev
Description: Side-by-side and slider comparison of two photos with synchronized zoom and pan — ideal for RAW vs JPEG evaluation and version culling. Saveable comparison pairs.
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// check root directory: the plugin stays inert until the folder is named correctly
if (basename(dirname(__FILE__)) != 'image_comparison')
{
  add_event_handler('init', 'image_comparison_folder_error');
  function image_comparison_folder_error()
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
include_once(IMAGE_COMPARISON_PATH . 'include/functions.inc.php');

add_event_handler('init', 'image_comparison_init');

// public gallery integration
add_event_handler('loc_end_section_init', 'image_comparison_loc_end_section_init');
// render the comparison page on loc_begin_index: it runs BEFORE page_header.php,
// so our combined CSS lands in <head> (loc_end_index runs after the head is flushed).
add_event_handler('loc_begin_index', 'image_comparison_loc_begin_index');
add_event_handler('loc_end_index', 'image_comparison_loc_end_index');
add_event_handler('loc_end_picture', 'image_comparison_loc_end_picture');

// web services (callbacks lazy-loaded only when ws.php fires the event)
add_event_handler(
  'ws_add_methods',
  'image_comparison_ws_add_methods',
  EVENT_HANDLER_PRIORITY_NEUTRAL,
  IMAGE_COMPARISON_PATH . 'include/ws_functions.inc.php'
);

// admin menu link
add_event_handler('get_admin_plugin_menu_links', 'image_comparison_admin_menu');
