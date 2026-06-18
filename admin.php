<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

check_status(ACCESS_ADMINISTRATOR);

global $page, $conf, $template;

// which tab — the admin router fills $_GET['tab'] from page=plugin-<id>-<tab>
$tab = (isset($_GET['tab']) && in_array($_GET['tab'], array('config', 'pairs'), true)) ? $_GET['tab'] : 'config';
$page['tab'] = $tab;

$conf_ic = image_comparison_get_conf();
$allowed_sizes = image_comparison_allowed_sizes();

// +-----------------------------------------------------------------------+
// | Handle POSTs                                                          |
// +-----------------------------------------------------------------------+
if (isset($_POST['ic_save_config']))
{
  check_pwg_token();

  $new = $conf_ic;

  $new['derivative_size'] = (isset($_POST['derivative_size']) && in_array($_POST['derivative_size'], $allowed_sizes, true))
    ? $_POST['derivative_size']
    : IMG_LARGE;

  $new['default_mode'] = (isset($_POST['default_mode']) && in_array($_POST['default_mode'], array('sidebyside', 'slider'), true))
    ? $_POST['default_mode']
    : 'sidebyside';

  $new['show_picture_button'] = !empty($_POST['show_picture_button']);
  $new['show_index_button']   = !empty($_POST['show_index_button']);
  $new['show_metadata']       = !empty($_POST['show_metadata']);

  $limit = isset($_POST['picker_limit']) ? (int)$_POST['picker_limit'] : 200;
  $new['picker_limit'] = min(1000, max(1, $limit));

  conf_update_param('image_comparison', $new, true);
  $conf_ic = $new;

  $page['infos'][] = l10n('Settings saved.');
}

if (isset($_POST['ic_delete_pair']))
{
  check_pwg_token();

  $pair_id = (isset($_POST['pair_id']) && preg_match(PATTERN_ID, $_POST['pair_id'])) ? (int)$_POST['pair_id'] : 0;
  $error = '';
  if (image_comparison_delete_pair($pair_id, $error))
  {
    $page['infos'][] = l10n('Comparison deleted.');
  }
  else
  {
    $page['errors'][] = $error;
  }
}

// +-----------------------------------------------------------------------+
// | Tabsheet                                                             |
// +-----------------------------------------------------------------------+
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
$tabsheet = new tabsheet();
$tabsheet->set_id('image_comparison_tab');
$tabsheet->add('config', '<span class="icon-cog"></span>'.l10n('Settings'), IMAGE_COMPARISON_ADMIN.'-config');
$tabsheet->add('pairs', '<span class="icon-link"></span>'.l10n('Saved comparisons'), IMAGE_COMPARISON_ADMIN.'-pairs');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Template data                                                        |
// +-----------------------------------------------------------------------+
$size_options = array();
foreach ($allowed_sizes as $size)
{
  $size_options[$size] = $size;
}

$template->assign(
  array(
    'IC_TAB'             => $page['tab'],
    'IC_PWG_TOKEN'       => get_pwg_token(),
    'IC_DERIVATIVE_SIZE' => $conf_ic['derivative_size'],
    'IC_SIZE_OPTIONS'    => $size_options,
    'IC_DEFAULT_MODE'    => $conf_ic['default_mode'],
    'IC_SHOW_PICTURE'    => (bool)$conf_ic['show_picture_button'],
    'IC_SHOW_INDEX'      => (bool)$conf_ic['show_index_button'],
    'IC_SHOW_METADATA'   => (bool)$conf_ic['show_metadata'],
    'IC_PICKER_LIMIT'    => (int)$conf_ic['picker_limit'],
  )
);

if ($page['tab'] === 'pairs')
{
  $template->assign('IC_PAIRS', image_comparison_list_pairs());
}

$template->set_filename('image_comparison_content', IMAGE_COMPARISON_REALPATH.'/admin/template/configuration.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'image_comparison_content');
