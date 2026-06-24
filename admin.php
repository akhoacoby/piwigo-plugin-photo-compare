<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $page, $conf, $template;

// This page manages plugin settings and stored data — administrators only.
check_status(ACCESS_ADMINISTRATOR);

// Config was unserialized + merged with defaults in image_comparison_init().
$cfg = $conf['image_comparison'];

// Whitelist the requested tab.
$tab = (isset($_GET['tab']) && in_array($_GET['tab'], array('config', 'pairs'), true))
  ? $_GET['tab']
  : 'config';
$page['tab'] = $tab;

// +-----------------------------------------------------------------------+
// | Save settings                                                         |
// +-----------------------------------------------------------------------+
if ($tab == 'config' && isset($_POST['submit']))
{
  check_pwg_token();

  $cfg['display_size'] = image_comparison_valid_size(
    isset($_POST['display_size']) ? $_POST['display_size'] : ''
  );
  $cfg['default_mode'] = (isset($_POST['default_mode'])
    && in_array($_POST['default_mode'], image_comparison_modes(), true))
    ? $_POST['default_mode']
    : 'side_by_side';
  $cfg['sync_zoom']           = isset($_POST['sync_zoom']);
  $cfg['show_picture_button'] = isset($_POST['show_picture_button']);
  $cfg['show_index_button']   = isset($_POST['show_index_button']);

  conf_update_param('image_comparison', $cfg, true);
  $conf['image_comparison'] = $cfg;
  $page['infos'][] = l10n('Settings saved');
}

// +-----------------------------------------------------------------------+
// | Delete a saved comparison                                             |
// +-----------------------------------------------------------------------+
if ($tab == 'pairs' && isset($_POST['delete_pair']))
{
  check_pwg_token();

  $pair_id = (int)$_POST['delete_pair'];
  if ($pair_id > 0)
  {
    pwg_query('DELETE FROM ' . IMAGE_COMPARISON_TABLE . ' WHERE id = ' . $pair_id . ';');
    $page['infos'][] = l10n('Comparison deleted');
  }
}

// +-----------------------------------------------------------------------+
// | Tabsheet                                                              |
// +-----------------------------------------------------------------------+
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');
$tabsheet = new tabsheet();
$tabsheet->set_id('image_comparison_tabs');
$tabsheet->add('config', '<span class="icon-cog"></span>' . l10n('Configuration'), IMAGE_COMPARISON_ADMIN . '-config');
$tabsheet->add('pairs', l10n('Saved comparisons'), IMAGE_COMPARISON_ADMIN . '-pairs');
$tabsheet->select($tab);
$tabsheet->assign();

$template->assign('PWG_TOKEN', get_pwg_token());

if ($tab == 'config')
{
  $template->assign(array(
    'F_ACTION'     => IMAGE_COMPARISON_ADMIN . '-config',
    'cfg'          => $cfg,
    'size_options' => image_comparison_available_sizes(),
    'mode_options' => image_comparison_modes(),
  ));
  $template->set_filename('image_comparison_content', IMAGE_COMPARISON_REALPATH . '/admin/template/configuration.tpl');
}
else
{
  $rows = array();
  foreach (image_comparison_load_pairs() as $p)
  {
    $imgs = image_comparison_load_images(array($p['left_id'], $p['right_id']));
    $left  = isset($imgs[$p['left_id']])  ? $imgs[$p['left_id']]  : null;
    $right = isset($imgs[$p['right_id']]) ? $imgs[$p['right_id']] : null;

    $rows[] = array(
      'id'          => (int)$p['id'],
      'title'       => $p['title'],
      'mode'        => $p['mode'],
      'created_on'  => $p['created_on'],
      'left_thumb'  => $left  ? DerivativeImage::thumb_url($left)  : '',
      'right_thumb' => $right ? DerivativeImage::thumb_url($right) : '',
      'left_name'   => $left  ? image_comparison_image_name($left)  : '#' . (int)$p['left_id'],
      'right_name'  => $right ? image_comparison_image_name($right) : '#' . (int)$p['right_id'],
      'view_url'    => image_comparison_compare_url(array(
        'left'  => (int)$p['left_id'],
        'right' => (int)$p['right_id'],
        'mode'  => $p['mode'],
      )),
      'available'   => ($left && $right),
    );
  }

  $template->assign(array(
    'F_ACTION' => IMAGE_COMPARISON_ADMIN . '-pairs',
    'ic_pairs' => $rows,
  ));
  $template->set_filename('image_comparison_content', IMAGE_COMPARISON_REALPATH . '/admin/template/pairs.tpl');
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'image_comparison_content');
