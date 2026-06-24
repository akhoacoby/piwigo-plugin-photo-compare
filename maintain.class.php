<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Lifecycle for the Image Comparison plugin: seeds one serialized config entry
 * (`$conf['image_comparison']`) and owns the comparison-pairs table. Every
 * operation is idempotent so install/activate/update can re-run safely, and
 * uninstall removes only what this plugin created.
 */
class image_comparison_maintain extends PluginMaintain
{
  /**
   * Default configuration, merged on update so existing users keep their values
   * while gaining any newly introduced keys.
   *
   * @var array
   */
  private $default_conf = array(
    'display_size'        => 'large',         // derivative size shown in the viewer
    'default_mode'        => 'side_by_side',  // side_by_side | slider
    'sync_zoom'           => true,            // synchronise zoom & pan by default
    'show_picture_button' => true,            // "Compare" button on photo pages
    'show_index_button'   => true,            // "Compare" button on album pages
  );

  /**
   * Fully qualified comparison-pairs table name.
   *
   * @var string
   */
  private $pairs_table;

  function __construct($plugin_id)
  {
    global $prefixeTable;
    parent::__construct($plugin_id);
    $this->pairs_table = $prefixeTable . 'image_comparison_pairs';
  }

  /**
   * Create the pairs table and seed default config. Idempotent.
   *
   * @param string $plugin_version
   * @param array  $errors
   * @return void
   */
  function install($plugin_version, &$errors = array())
  {
    global $conf;

    pwg_query('
CREATE TABLE IF NOT EXISTS `' . $this->pairs_table . '` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `left_id` int(11) NOT NULL,
  `right_id` int(11) NOT NULL,
  `mode` varchar(32) NOT NULL DEFAULT \'side_by_side\',
  `title` varchar(255) NOT NULL DEFAULT \'\',
  `created_by` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;');

    // Seed config only if absent, then merge to pick up any new keys.
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
   * Runs on every (re)activation; install() is idempotent so delegate to it.
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
   * Nothing to tear down on deactivation — config and data are preserved so the
   * plugin can be reactivated without loss.
   *
   * @return void
   */
  function deactivate()
  {
  }

  /**
   * Migrate config keys / schema idempotently. install() handles both the
   * table (CREATE IF NOT EXISTS) and the config merge.
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
   * Remove ONLY what this plugin created: its table and its config key.
   *
   * @return void
   */
  function uninstall()
  {
    pwg_query('DROP TABLE IF EXISTS `' . $this->pairs_table . '`;');
    conf_delete_param('image_comparison');
  }
}
