<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Configuration helpers                                                 |
// +-----------------------------------------------------------------------+

/**
 * Default plugin configuration. Kept in sync with the copy in
 * maintain.class.php (which seeds it at install time); duplicated because the
 * maintain class is loaded by core independently of this file.
 *
 * @return array
 */
function image_comparison_default_conf()
{
  return array(
    'display_size'        => 'large',
    'default_mode'        => 'side_by_side',
    'sync_zoom'           => true,
    'show_picture_button' => true,
    'show_index_button'   => true,
  );
}

/**
 * Derivative size keys the viewer is allowed to request (subset of the core
 * IMG_* size types). Used to whitelist the configured display size.
 *
 * @return string[]
 */
function image_comparison_available_sizes()
{
  return array('small', 'medium', 'large', 'xlarge', 'xxlarge');
}

/**
 * Comparison display modes.
 *
 * @return string[]
 */
function image_comparison_modes()
{
  return array('side_by_side', 'slider');
}

/**
 * Coerce a stored display-size value to a valid one, falling back to "large".
 *
 * @param string $size
 * @return string
 */
function image_comparison_valid_size($size)
{
  return in_array($size, image_comparison_available_sizes(), true) ? $size : 'large';
}

/**
 * Load language strings and normalise the serialized config into an array,
 * merged over the defaults so missing keys never trip the rest of the plugin.
 *
 * @return void
 */
function image_comparison_init()
{
  global $conf;

  load_language('plugin.lang', IMAGE_COMPARISON_PATH);

  $cfg = isset($conf['image_comparison']) ? safe_unserialize($conf['image_comparison']) : array();
  if (!is_array($cfg))
  {
    $cfg = array();
  }
  $conf['image_comparison'] = array_merge(image_comparison_default_conf(), $cfg);
}

// +-----------------------------------------------------------------------+
// | Data access (permission-filtered)                                     |
// +-----------------------------------------------------------------------+

/**
 * Human-readable name for an image row (its title, else derived from the file).
 *
 * @param array $row
 * @return string
 */
function image_comparison_image_name($row)
{
  if (isset($row['name']) && $row['name'] !== '' && $row['name'] !== null)
  {
    return $row['name'];
  }
  return get_name_from_file(isset($row['file']) ? $row['file'] : '');
}

/**
 * Load image rows for the given ids, filtered by the visitor's permissions.
 * An image is returned only if the current user can see it in at least one
 * album (fail-closed). Rows are keyed by id and include `rotation` (required
 * by SrcImage/DerivativeImage).
 *
 * @param int[] $ids
 * @return array<int,array> rows keyed by image id
 */
function image_comparison_load_images($ids)
{
  $ids = array_unique(array_filter(array_map('intval', (array)$ids), 'image_comparison_is_positive'));
  if (empty($ids))
  {
    return array();
  }

  return query2array('
SELECT i.id, i.name, i.file, i.path, i.width, i.height, i.representative_ext, i.rotation
  FROM ' . IMAGES_TABLE . ' AS i
  INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON i.id = ic.image_id
  WHERE i.id IN (' . implode(',', $ids) . ')
    ' . get_sql_condition_FandF(
          array(
            'forbidden_categories' => 'ic.category_id',
            'visible_categories'   => 'ic.category_id',
            'visible_images'       => 'i.id',
          ),
          'AND'
        ) . '
  GROUP BY i.id
;', 'id');
}

/**
 * array_filter callback: keep strictly positive integers.
 *
 * @param int $v
 * @return bool
 */
function image_comparison_is_positive($v)
{
  return $v > 0;
}

/**
 * Visible image rows belonging to a category (the picker pool).
 *
 * @param int $cat_id
 * @param int $limit
 * @return array list of rows
 */
function image_comparison_category_images($cat_id, $limit = 60)
{
  $cat_id = (int)$cat_id;
  if ($cat_id <= 0)
  {
    return array();
  }

  return query2array('
SELECT i.id, i.name, i.file, i.path, i.width, i.height, i.representative_ext, i.rotation
  FROM ' . IMAGES_TABLE . ' AS i
  INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON i.id = ic.image_id
  WHERE ic.category_id = ' . $cat_id . '
    ' . get_sql_condition_FandF(
          array(
            'forbidden_categories' => 'ic.category_id',
            'visible_categories'   => 'ic.category_id',
            'visible_images'       => 'i.id',
          ),
          'AND'
        ) . '
  GROUP BY i.id
  ORDER BY ic.rank ASC, i.id ASC
  LIMIT ' . (int)$limit . '
;');
}

/**
 * First album of an image that the current user is allowed to see, or 0.
 *
 * @param int $image_id
 * @return int
 */
function image_comparison_first_category_of($image_id)
{
  $image_id = (int)$image_id;
  if ($image_id <= 0)
  {
    return 0;
  }

  $rows = query2array('
SELECT ic.category_id
  FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic
  WHERE ic.image_id = ' . $image_id . '
    ' . get_sql_condition_FandF(
          array(
            'forbidden_categories' => 'ic.category_id',
            'visible_categories'   => 'ic.category_id',
          ),
          'AND'
        ) . '
  LIMIT 1
;');

  return empty($rows) ? 0 : (int)$rows[0]['category_id'];
}

/**
 * Load saved comparison pairs, newest first.
 *
 * @param int $limit 0 = no limit
 * @return array list of pair rows
 */
function image_comparison_load_pairs($limit = 0)
{
  $sql = '
SELECT id, left_id, right_id, mode, title, created_by, created_on
  FROM ' . IMAGE_COMPARISON_TABLE . '
  ORDER BY id DESC';
  if ((int)$limit > 0)
  {
    $sql .= ' LIMIT ' . (int)$limit;
  }
  return query2array($sql . ';');
}

// +-----------------------------------------------------------------------+
// | URL + view-model builders                                             |
// +-----------------------------------------------------------------------+

/**
 * Build a URL to the virtual compare page, dropping empty parameters.
 *
 * @param array $params left|right|cat|mode => value
 * @return string
 */
function image_comparison_compare_url($params = array())
{
  $url = get_root_url() . 'index.php?/compare';
  foreach ($params as $key => $value)
  {
    if ($value === null || $value === '' || $value === 0 || $value === '0')
    {
      continue;
    }
    $url .= '&' . rawurlencode($key) . '=' . rawurlencode($value);
  }
  return $url;
}

/**
 * Build the template view-model for a single image at the given derivative size.
 * Only derivative URLs are exposed — never original file paths.
 *
 * @param array  $row  image row (must include rotation, path, etc.)
 * @param string $size derivative size key
 * @return array
 */
function image_comparison_image_descriptor($row, $size)
{
  return array(
    'id'    => (int)$row['id'],
    'name'  => image_comparison_image_name($row),
    'url'   => DerivativeImage::url($size, $row),
    'thumb' => DerivativeImage::thumb_url($row),
    'page'  => make_picture_url(array('image_id' => (int)$row['id'], 'image_file' => $row['file'])),
    'width' => (int)$row['width'],
    'height'=> (int)$row['height'],
  );
}

// +-----------------------------------------------------------------------+
// | Virtual "compare" section                                             |
// +-----------------------------------------------------------------------+

/**
 * Claim the /compare virtual section so index.php renders our own body.
 *
 * @return void
 */
function image_comparison_loc_end_section_init()
{
  global $tokens, $page, $conf;

  if (!isset($tokens[0]) || $tokens[0] != 'compare')
  {
    return;
  }

  $page['section'] = 'compare';
  $page['title'] = l10n('Image Comparison');
  $page['section_title'] =
    '<a href="' . get_absolute_root_url() . '">' . l10n('Home') . '</a>'
    . $conf['level_separator'] . l10n('Image Comparison');
  $page['body_id'] = 'theImageComparisonPage';
  $page['is_external'] = true;
}

/**
 * Render the comparison page. Runs early (loc_begin_index) so combine_css from
 * the template is registered before <head> is emitted. Picks one of three
 * views: the dual viewer (two images chosen), the picker (choose images from
 * an album), or the featured list (saved comparison pairs).
 *
 * @return void
 */
function image_comparison_loc_begin_index()
{
  global $page, $template, $conf, $user;

  if (!isset($page['section']) || $page['section'] != 'compare')
  {
    return;
  }

  $cfg = $conf['image_comparison'];

  $left  = isset($_GET['left'])  ? (int)$_GET['left']  : 0;
  $right = isset($_GET['right']) ? (int)$_GET['right'] : 0;
  $cat   = isset($_GET['cat'])   ? (int)$_GET['cat']   : 0;

  // Resolve the mode from the URL, falling back to the configured default;
  // validate the result so a stale/invalid stored value can never break layout.
  $mode = isset($_GET['mode'])
    ? $_GET['mode']
    : (isset($cfg['default_mode']) ? $cfg['default_mode'] : 'side_by_side');
  if (!in_array($mode, image_comparison_modes(), true))
  {
    $mode = 'side_by_side';
  }

  $size = image_comparison_valid_size(isset($cfg['display_size']) ? $cfg['display_size'] : 'large');

  // Resolve requested images, discarding any the visitor may not see.
  $wanted = array();
  if ($left > 0)  { $wanted[] = $left; }
  if ($right > 0) { $wanted[] = $right; }
  $imgs = !empty($wanted) ? image_comparison_load_images($wanted) : array();

  $left_img  = ($left > 0  && isset($imgs[$left]))  ? $imgs[$left]  : null;
  $right_img = ($right > 0 && isset($imgs[$right])) ? $imgs[$right] : null;
  $left  = $left_img  ? (int)$left_img['id']  : 0;
  $right = $right_img ? (int)$right_img['id'] : 0;

  $theme = isset($user['theme']) ? $user['theme'] : '';

  $template->assign(array(
    'IMAGE_COMPARISON_PATH'         => IMAGE_COMPARISON_PATH,
    'IMAGE_COMPARISON_THEME'        => $theme,
    'IMAGE_COMPARISON_IS_BOOTSTRAP' => ($theme === 'bootstrap_darkroom'),
    'IC_MODE'                       => $mode,
    'IC_SYNC_ZOOM'                  => !empty($cfg['sync_zoom']),
    'IC_PWG_TOKEN'                  => get_pwg_token(),
    'IC_CAN_SAVE'                   => is_admin(),
    'IC_SAVE_METHOD'                => 'image_comparison.savePair',
    'IC_WS_URL'                     => get_root_url() . 'ws.php?format=json',
  ));

  if ($left_img && $right_img)
  {
    image_comparison_assign_viewer($left_img, $right_img, $mode, $cat, $size);
  }
  else
  {
    image_comparison_assign_picker_or_featured($left, $left_img, $cat, $mode, $size);
  }

  $template->set_filename('image_comparison_page', IMAGE_COMPARISON_REALPATH . '/template/compare.tpl');
  $template->concat('PLUGIN_INDEX_CONTENT_BEGIN', $template->parse('image_comparison_page', true));
}

/**
 * Assign the dual-viewer view-model (two valid images).
 *
 * @param array  $left_img
 * @param array  $right_img
 * @param string $mode
 * @param int    $cat
 * @param string $size
 * @return void
 */
function image_comparison_assign_viewer($left_img, $right_img, $mode, $cat, $size)
{
  global $template;

  $lid = (int)$left_img['id'];
  $rid = (int)$right_img['id'];

  $template->assign(array(
    'IC_VIEW'       => 'viewer',
    'IC_LEFT'       => image_comparison_image_descriptor($left_img, $size),
    'IC_RIGHT'      => image_comparison_image_descriptor($right_img, $size),
    'IC_SWAP_URL'   => image_comparison_compare_url(array('left' => $rid, 'right' => $lid, 'mode' => $mode, 'cat' => $cat)),
    'IC_SBS_URL'    => image_comparison_compare_url(array('left' => $lid, 'right' => $rid, 'mode' => 'side_by_side', 'cat' => $cat)),
    'IC_SLIDER_URL' => image_comparison_compare_url(array('left' => $lid, 'right' => $rid, 'mode' => 'slider', 'cat' => $cat)),
    'IC_RESET_URL'  => image_comparison_compare_url(array('cat' => $cat, 'mode' => $mode)),
  ));
}

/**
 * Assign either the album picker (when there is a candidate pool) or the
 * featured saved-pairs list (the /compare landing page).
 *
 * @param int        $left      chosen left id (0 if none)
 * @param array|null $left_img  chosen left image row
 * @param int        $cat       album context id (0 if none)
 * @param string     $mode
 * @param string     $size
 * @return void
 */
function image_comparison_assign_picker_or_featured($left, $left_img, $cat, $mode, $size)
{
  global $template;

  // Derive an album from the chosen left image when none was supplied.
  if ($cat <= 0 && $left > 0)
  {
    $cat = image_comparison_first_category_of($left);
  }

  $pool = $cat > 0 ? image_comparison_category_images($cat) : array();

  if (!empty($pool))
  {
    $items = array();
    foreach ($pool as $row)
    {
      $rid = (int)$row['id'];
      if ($left > 0 && $rid == $left)
      {
        continue; // never offer the already-chosen image as its own pair
      }
      if ($left > 0)
      {
        $target = image_comparison_compare_url(array('left' => $left, 'right' => $rid, 'mode' => $mode, 'cat' => $cat));
      }
      else
      {
        $target = image_comparison_compare_url(array('left' => $rid, 'cat' => $cat, 'mode' => $mode));
      }
      $items[] = array(
        'name'  => image_comparison_image_name($row),
        'thumb' => DerivativeImage::thumb_url($row),
        'url'   => $target,
      );
    }

    $template->assign(array(
      'IC_VIEW'            => 'picker',
      'IC_PICK_STAGE'      => $left > 0 ? 'right' : 'left',
      'IC_PICK_LEFT'       => $left > 0 ? image_comparison_image_descriptor($left_img, $size) : null,
      'IC_PICK_ITEMS'      => $items,
      'IC_CHANGE_LEFT_URL' => image_comparison_compare_url(array('cat' => $cat, 'mode' => $mode)),
    ));
    return;
  }

  // No pool to pick from: show the curated/saved comparisons.
  $featured = array();
  foreach (image_comparison_load_pairs(24) as $pair)
  {
    $pi = image_comparison_load_images(array($pair['left_id'], $pair['right_id']));
    if (!isset($pi[$pair['left_id']]) || !isset($pi[$pair['right_id']]))
    {
      continue; // skip pairs whose images the visitor cannot see
    }
    $lname = image_comparison_image_name($pi[$pair['left_id']]);
    $rname = image_comparison_image_name($pi[$pair['right_id']]);
    $featured[] = array(
      'title'       => $pair['title'] !== '' ? $pair['title'] : $lname . ' / ' . $rname,
      'left_thumb'  => DerivativeImage::thumb_url($pi[$pair['left_id']]),
      'right_thumb' => DerivativeImage::thumb_url($pi[$pair['right_id']]),
      'url'         => image_comparison_compare_url(array(
        'left'  => (int)$pair['left_id'],
        'right' => (int)$pair['right_id'],
        'mode'  => in_array($pair['mode'], image_comparison_modes(), true) ? $pair['mode'] : 'side_by_side',
      )),
    );
  }

  $template->assign(array(
    'IC_VIEW'     => 'featured',
    'IC_FEATURED' => $featured,
  ));
}

// +-----------------------------------------------------------------------+
// | Gallery buttons (album + photo pages)                                 |
// +-----------------------------------------------------------------------+

/**
 * Render a theme-neutral toolbar button. Always carries a visible text label
 * AND a title, because bootstrap_darkroom hides .pwg-button-text.
 *
 * @param string $url
 * @param string $label
 * @param string $icon  pwg-icon-* class
 * @return string
 */
function image_comparison_button_html($url, $label, $icon)
{
  $label_esc = htmlspecialchars($label, ENT_QUOTES, get_pwg_charset());
  return '<a class="pwg-state-default pwg-button" href="' . htmlspecialchars($url, ENT_QUOTES, get_pwg_charset()) . '"'
    . ' title="' . $label_esc . '" rel="nofollow">'
    . '<span class="pwg-icon ' . htmlspecialchars($icon, ENT_QUOTES, get_pwg_charset()) . '"></span>'
    . '<span class="pwg-button-text">' . $label_esc . '</span>'
    . '</a>';
}

/**
 * Add a "Compare photos" button on album pages.
 *
 * @return void
 */
function image_comparison_loc_end_index()
{
  global $page, $conf, $template;

  if (empty($conf['image_comparison']['show_index_button']))
  {
    return;
  }
  // Only on real albums, never on our own compare page.
  if (!isset($page['section']) || $page['section'] != 'categories')
  {
    return;
  }
  if (empty($page['category']['id']))
  {
    return;
  }

  $url = image_comparison_compare_url(array('cat' => (int)$page['category']['id']));
  $template->add_index_button(
    image_comparison_button_html($url, l10n('Compare photos'), 'pwg-icon-camera'),
    BUTTONS_RANK_NEUTRAL
  );
}

/**
 * Add a "Compare" button on single-photo pages (pre-selects this photo).
 *
 * @return void
 */
function image_comparison_loc_end_picture()
{
  global $page, $conf, $template;

  if (empty($conf['image_comparison']['show_picture_button']))
  {
    return;
  }
  if (empty($page['image_id']))
  {
    return;
  }

  $params = array('left' => (int)$page['image_id']);
  if (!empty($page['category']['id']))
  {
    $params['cat'] = (int)$page['category']['id'];
  }

  $url = image_comparison_compare_url($params);
  $template->add_picture_button(
    image_comparison_button_html($url, l10n('Compare'), 'pwg-icon-camera'),
    BUTTONS_RANK_NEUTRAL
  );
}

// +-----------------------------------------------------------------------+
// | Admin menu                                                            |
// +-----------------------------------------------------------------------+

/**
 * Add the plugin to the admin Plugins menu (a "change" event — return $menu).
 *
 * @param array $menu
 * @return array
 */
function image_comparison_admin_menu($menu)
{
  $menu[] = array(
    'NAME' => 'Image Comparison',
    'URL'  => IMAGE_COMPARISON_ADMIN,
  );
  return $menu;
}

// +-----------------------------------------------------------------------+
// | Web services                                                          |
// +-----------------------------------------------------------------------+

/**
 * Register the save/delete web-service methods.
 *
 * @param array $arr [0] => Pwg web-service instance
 * @return void
 */
function image_comparison_ws_add_methods($arr)
{
  $service = &$arr[0];

  $service->addMethod(
    'image_comparison.savePair',
    'ws_image_comparison_save_pair',
    array(
      'left_id'  => array('type' => WS_TYPE_ID),
      'right_id' => array('type' => WS_TYPE_ID),
      'mode'     => array('default' => 'side_by_side'),
      'title'    => array('default' => ''),
      'pwg_token'=> array(),
    ),
    'Save a comparison pair (admin only).',
    null,
    array('post_only' => true, 'admin_only' => true)
  );

  $service->addMethod(
    'image_comparison.deletePair',
    'ws_image_comparison_delete_pair',
    array(
      'pair_id'  => array('type' => WS_TYPE_ID),
      'pwg_token'=> array(),
    ),
    'Delete a saved comparison pair (admin only).',
    null,
    array('post_only' => true, 'admin_only' => true)
  );
}

/**
 * Save a comparison pair. Admin-only; CSRF-checked via pwg_token; both images
 * must be visible to the caller before they are stored.
 *
 * @param array  $params
 * @param object $service
 * @return array|PwgError
 */
function ws_image_comparison_save_pair($params, &$service)
{
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }
  if (!isset($params['pwg_token']) || $params['pwg_token'] != get_pwg_token())
  {
    return new PwgError(403, 'Invalid security token');
  }

  $left_id  = (int)$params['left_id'];
  $right_id = (int)$params['right_id'];
  if ($left_id <= 0 || $right_id <= 0 || $left_id == $right_id)
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Two distinct images are required');
  }

  // Re-check visibility (type validation is not authorization).
  $imgs = image_comparison_load_images(array($left_id, $right_id));
  if (!isset($imgs[$left_id]) || !isset($imgs[$right_id]))
  {
    return new PwgError(403, 'One of the images is not accessible');
  }

  $mode = in_array($params['mode'], image_comparison_modes(), true) ? $params['mode'] : 'side_by_side';

  // Title comes from the request body — escape it before storing (single_insert
  // quotes but does not escape).
  $title = trim((string)$params['title']);
  if (function_exists('mb_substr'))
  {
    $title = mb_substr($title, 0, 255);
  }
  else
  {
    $title = substr($title, 0, 255);
  }
  $title = pwg_db_real_escape_string($title);

  global $user;

  single_insert(
    IMAGE_COMPARISON_TABLE,
    array(
      'left_id'    => $left_id,
      'right_id'   => $right_id,
      'mode'       => $mode,
      'title'      => $title,
      'created_by' => isset($user['id']) ? (int)$user['id'] : null,
      'created_on' => date('Y-m-d H:i:s'),
    )
  );

  return array(
    'id'      => (int)pwg_db_insert_id(),
    'message' => l10n('Comparison saved'),
  );
}

/**
 * Delete a saved comparison pair. Admin-only; CSRF-checked via pwg_token.
 *
 * @param array  $params
 * @param object $service
 * @return array|PwgError
 */
function ws_image_comparison_delete_pair($params, &$service)
{
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }
  if (!isset($params['pwg_token']) || $params['pwg_token'] != get_pwg_token())
  {
    return new PwgError(403, 'Invalid security token');
  }

  $pair_id = (int)$params['pair_id'];
  if ($pair_id <= 0)
  {
    return new PwgError(WS_ERR_INVALID_PARAM, 'Invalid pair id');
  }

  pwg_query('DELETE FROM ' . IMAGE_COMPARISON_TABLE . ' WHERE id = ' . $pair_id . ';');

  return array('deleted' => $pair_id);
}
