<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Lifecycle handler for the Image Comparison plugin.
 *
 * Owns exactly two pieces of state: the serialized config blob
 * $conf['image_comparison'] and the comparison-pairs table. A clean
 * install -> uninstall cycle must leave nothing else behind.
 */
class image_comparison_maintain extends PluginMaintain
{
  /**
   * Default configuration. Kept here so update() can merge in any new
   * keys introduced by a later version without clobbering user values.
   *
   * @var array
   */
  private $default_conf = array(
    'derivative_size'     => 'xlarge', // IMG_* size key used by the viewer
    'default_mode'        => 'sidebyside', // 'sidebyside' | 'slider'
    'show_picture_button' => true,
    'show_index_button'   => true,
    'show_metadata'       => true,
    'picker_limit'        => 200, // max thumbnails listed in the album picker
  );

  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  /**
   * Create the pairs table (idempotent) and seed the config blob.
   *
   * @param string $plugin_version
   * @param array  $errors
   * @return void
   */
  function install($plugin_version, &$errors = array())
  {
    global $conf, $prefixeTable;

    pwg_query('
CREATE TABLE IF NOT EXISTS `'.$prefixeTable.'image_comparison_pairs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `image_left` int(11) unsigned NOT NULL,
  `image_right` int(11) unsigned NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pair` (`image_left`,`image_right`),
  KEY `idx_left` (`image_left`),
  KEY `idx_right` (`image_right`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
    );

    // merge defaults under any pre-existing values (re-install keeps user choices)
    $current = array();
    if (isset($conf['image_comparison']))
    {
      $current = safe_unserialize($conf['image_comparison']);
      if (!is_array($current))
      {
        $current = array();
      }
    }

    conf_update_param('image_comparison', array_merge($this->default_conf, $current), true);
  }

  /**
   * Runs on every (re)activation; delegate to the idempotent install.
   *
   * @param string $plugin_version
   * @param array  $errors
   * @return void
   */
  function activate($plugin_version, &$errors = array())
  {
    $this->install($plugin_version, $errors);
  }

  /**
   * No teardown needed on deactivation; data is preserved.
   *
   * @return void
   */
  function deactivate()
  {
  }

  /**
   * Idempotent migration: ensure the table exists and backfill any new
   * config keys without overwriting values the user has changed.
   *
   * @param string $old_version
   * @param string $new_version
   * @param array  $errors
   * @return void
   */
  function update($old_version, $new_version, &$errors = array())
  {
    $this->install($new_version, $errors);
  }

  /**
   * Remove ONLY what this plugin created.
   *
   * @return void
   */
  function uninstall()
  {
    global $prefixeTable;

    pwg_query('DROP TABLE IF EXISTS `'.$prefixeTable.'image_comparison_pairs`;');
    conf_delete_param('image_comparison');
  }
}
