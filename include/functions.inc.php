<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Bootstrap                                                             |
// +-----------------------------------------------------------------------+

/**
 * Plugin init: load translations and normalise the config blob.
 *
 * @return void
 */
function image_comparison_init()
{
  global $conf;

  load_language('plugin.lang', IMAGE_COMPARISON_PATH);

  if (isset($conf['image_comparison']))
  {
    $conf['image_comparison'] = safe_unserialize($conf['image_comparison']);
  }
}

/**
 * Return the plugin configuration merged over its defaults.
 *
 * Defensive: a partial or missing blob still yields every expected key,
 * so handlers never have to guard each lookup.
 *
 * @return array
 */
function image_comparison_get_conf()
{
  global $conf;

  $defaults = array(
    'derivative_size'     => IMG_XLARGE,
    'default_mode'        => 'sidebyside',
    'show_picture_button' => true,
    'show_index_button'   => true,
    'show_metadata'       => true,
    'picker_limit'        => 200,
  );

  $current = isset($conf['image_comparison']) ? $conf['image_comparison'] : array();
  if (!is_array($current))
  {
    $current = safe_unserialize($current);
  }
  if (!is_array($current))
  {
    $current = array();
  }

  return array_merge($defaults, $current);
}

/**
 * The list of derivative size keys the viewer may use, intersected with
 * the sizes actually enabled in this gallery. Used to validate config and
 * to fall back safely if an enabled size was later disabled by the admin.
 *
 * @return string[]
 */
function image_comparison_allowed_sizes()
{
  $candidates = array(IMG_MEDIUM, IMG_LARGE, IMG_XLARGE, IMG_XXLARGE);
  $enabled = ImageStdParams::get_all_types();

  $allowed = array();
  foreach ($candidates as $size)
  {
    if (in_array($size, $enabled, true))
    {
      $allowed[] = $size;
    }
  }

  // never return empty: fall back to the candidate list if the gallery
  // somehow exposes none of them
  return count($allowed) ? $allowed : $candidates;
}

// +-----------------------------------------------------------------------+
// | URL helpers                                                           |
// +-----------------------------------------------------------------------+

/**
 * Build a URL to the comparison page (virtual "compare" section).
 *
 * @param int|null    $left  left image id
 * @param int|null    $right right image id
 * @param int|null    $cat   album id scoping the picker
 * @param string|null $mode  'sidebyside' | 'slider'
 * @return string raw (un-escaped) URL — escape at the point of output
 */
function image_comparison_compare_url($left = null, $right = null, $cat = null, $mode = null)
{
  $url = get_root_url().'index.php?/compare';

  $params = array();
  if (!empty($left))
  {
    $params['left'] = (int)$left;
  }
  if (!empty($right))
  {
    $params['right'] = (int)$right;
  }
  if (!empty($cat))
  {
    $params['cat'] = (int)$cat;
  }
  if (!empty($mode))
  {
    $params['mode'] = $mode;
  }

  foreach ($params as $key => $value)
  {
    $url .= '&'.$key.'='.rawurlencode($value);
  }

  return $url;
}

// +-----------------------------------------------------------------------+
// | Image / album lookups (permission-aware)                             |
// +-----------------------------------------------------------------------+

/**
 * Load a single image the current user is allowed to see.
 *
 * Joins image_category and applies get_sql_condition_FandF so private
 * albums and privacy levels are enforced: a hidden image returns null
 * (fail closed — never reveal that it exists).
 *
 * @param int $image_id
 * @return array|null image row, or null when missing/forbidden
 */
function image_comparison_get_image($image_id)
{
  if (empty($image_id) || !preg_match(PATTERN_ID, (string)$image_id))
  {
    return null;
  }

  $rows = query2array('
SELECT id, name, file, path, width, height, filesize, rotation, representative_ext
  FROM '.IMAGES_TABLE.'
    INNER JOIN '.IMAGE_CATEGORY_TABLE.' ON id = image_id
  WHERE id = '.(int)$image_id.'
    '.get_sql_condition_FandF(
      array(
        'forbidden_categories' => 'category_id',
        'visible_categories'   => 'category_id',
        'visible_images'       => 'id',
      ),
      'AND'
    ).'
  GROUP BY id
;');

  return count($rows) ? $rows[0] : null;
}

/**
 * The id of one album the current user may see that contains the image.
 *
 * @param int $image_id
 * @return int|null
 */
function image_comparison_first_category($image_id)
{
  $rows = query2array('
SELECT category_id
  FROM '.IMAGE_CATEGORY_TABLE.'
    INNER JOIN '.IMAGES_TABLE.' ON id = image_id
  WHERE image_id = '.(int)$image_id.'
    '.get_sql_condition_FandF(
      array(
        'forbidden_categories' => 'category_id',
        'visible_categories'   => 'category_id',
        'visible_images'       => 'id',
      ),
      'AND'
    ).'
  ORDER BY category_id
  LIMIT 1
;');

  return count($rows) ? (int)$rows[0]['category_id'] : null;
}

/**
 * Fetch an album the current user may see.
 *
 * @param int $cat_id
 * @return array|null row with id, name
 */
function image_comparison_get_album($cat_id)
{
  $rows = query2array('
SELECT id, name
  FROM '.CATEGORIES_TABLE.'
  WHERE id = '.(int)$cat_id.'
    '.get_sql_condition_FandF(array('forbidden_categories' => 'id'), 'AND').'
;');

  return count($rows) ? $rows[0] : null;
}

/**
 * List the images of an album the current user may see, capped at $limit.
 *
 * @param int $cat_id
 * @param int $limit
 * @return array[] image rows
 */
function image_comparison_get_album_images($cat_id, $limit)
{
  $limit = max(1, (int)$limit);

  return query2array('
SELECT id, name, file, path, width, height, rotation, representative_ext
  FROM '.IMAGES_TABLE.'
    INNER JOIN '.IMAGE_CATEGORY_TABLE.' ON id = image_id
  WHERE category_id = '.(int)$cat_id.'
    '.get_sql_condition_FandF(
      array(
        'forbidden_categories' => 'category_id',
        'visible_categories'   => 'category_id',
        'visible_images'       => 'id',
      ),
      'AND'
    ).'
  GROUP BY id
  ORDER BY id
  LIMIT '.$limit.'
;');
}

/**
 * Human-readable, plain-text name for an image row (escape at output).
 *
 * @param array $row image row with at least 'file' (and maybe 'name')
 * @return string
 */
function image_comparison_image_name($row)
{
  if (!empty($row['name']))
  {
    return $row['name'];
  }
  return get_name_from_file($row['file']);
}

/**
 * Build the template view-struct for one side of the comparison.
 *
 * @param array|null $row  image row, or null when nothing is selected
 * @param string     $size derivative size key
 * @return array|false false when no image is selected
 */
function image_comparison_view_struct($row, $size)
{
  if ($row === null)
  {
    return false;
  }

  return array(
    'ID'        => (int)$row['id'],
    'NAME'      => image_comparison_image_name($row),
    'FILE'      => $row['file'],
    'URL'       => DerivativeImage::url($size, $row),
    'TN_URL'    => DerivativeImage::url(IMG_SQUARE, $row),
    'WIDTH'     => (int)$row['width'],
    'HEIGHT'    => (int)$row['height'],
    'FILESIZE'  => isset($row['filesize']) ? sprintf('%.2f', $row['filesize'] / 1024) : null,
    'U_PAGE'    => get_root_url().'picture.php?/'.(int)$row['id'],
  );
}

// +-----------------------------------------------------------------------+
// | Saved comparison pairs                                                |
// +-----------------------------------------------------------------------+

/**
 * Find a saved partner for an image (most recent pair), if the partner is
 * still visible to the current user.
 *
 * @param int $image_id
 * @return int|null partner image id
 */
function image_comparison_find_pair_partner($image_id)
{
  $id = (int)$image_id;

  $rows = query2array('
SELECT image_left, image_right
  FROM '.IMAGE_COMPARISON_TABLE.'
  WHERE image_left = '.$id.' OR image_right = '.$id.'
  ORDER BY id DESC
;');

  foreach ($rows as $row)
  {
    $partner = ((int)$row['image_left'] === $id) ? (int)$row['image_right'] : (int)$row['image_left'];
    if (image_comparison_get_image($partner) !== null)
    {
      return $partner;
    }
  }

  return null;
}

/**
 * Return the id of a saved pair matching this unordered image couple.
 *
 * @param int $a
 * @param int $b
 * @return int|null
 */
function image_comparison_find_exact_pair($a, $b)
{
  $a = (int)$a;
  $b = (int)$b;

  $rows = query2array('
SELECT id
  FROM '.IMAGE_COMPARISON_TABLE.'
  WHERE (image_left = '.$a.' AND image_right = '.$b.')
     OR (image_left = '.$b.' AND image_right = '.$a.')
  LIMIT 1
;');

  return count($rows) ? (int)$rows[0]['id'] : null;
}

/**
 * Persist a comparison pair. Shared by the web-service and the admin page.
 *
 * Both images must be visible to the current user (fail closed). The label
 * is free text from outside the request-escaping path, so it is escaped
 * before storage.
 *
 * @param int    $left
 * @param int    $right
 * @param string $label
 * @param string $error  set to a translated message on failure
 * @return int|false the pair id on success, false otherwise
 */
function image_comparison_save_pair($left, $right, $label, &$error)
{
  $left = (int)$left;
  $right = (int)$right;

  if ($left <= 0 || $right <= 0 || $left === $right)
  {
    $error = l10n('Pick two different photos to save a comparison.');
    return false;
  }

  // authorization: the saver must actually be allowed to see both photos
  if (image_comparison_get_image($left) === null || image_comparison_get_image($right) === null)
  {
    $error = l10n('One of the selected photos is not available.');
    return false;
  }

  $existing = image_comparison_find_exact_pair($left, $right);
  if ($existing !== null)
  {
    // already saved: treat as success, optionally refresh the label
    image_comparison_update_pair_label($existing, $label);
    return $existing;
  }

  $label = trim((string)$label);
  if ($label === '')
  {
    $label = null;
  }
  else
  {
    $label = pwg_db_real_escape_string($label);
  }

  single_insert(
    IMAGE_COMPARISON_TABLE,
    array(
      'image_left'  => $left,
      'image_right' => $right,
      'label'       => $label,
      'created_on'  => date('Y-m-d H:i:s'),
      'created_by'  => image_comparison_current_user_id(),
    )
  );

  return (int)pwg_db_insert_id();
}

/**
 * Update the label of an existing pair (no-op when empty).
 *
 * @param int    $pair_id
 * @param string $label
 * @return void
 */
function image_comparison_update_pair_label($pair_id, $label)
{
  $label = trim((string)$label);
  if ($label === '')
  {
    return;
  }

  single_update(
    IMAGE_COMPARISON_TABLE,
    array('label' => pwg_db_real_escape_string($label)),
    array('id' => (int)$pair_id)
  );
}

/**
 * Delete a saved pair. Only the creator or an administrator may delete one.
 *
 * @param int    $pair_id
 * @param string $error    set to a translated message on failure
 * @return bool
 */
function image_comparison_delete_pair($pair_id, &$error)
{
  $pair_id = (int)$pair_id;
  if ($pair_id <= 0)
  {
    $error = l10n('Invalid comparison.');
    return false;
  }

  $rows = query2array('
SELECT created_by
  FROM '.IMAGE_COMPARISON_TABLE.'
  WHERE id = '.$pair_id.'
;');

  if (!count($rows))
  {
    $error = l10n('This comparison no longer exists.');
    return false;
  }

  $owner = $rows[0]['created_by'];
  if (!is_admin() && (int)$owner !== image_comparison_current_user_id())
  {
    $error = l10n('You are not allowed to delete this comparison.');
    return false;
  }

  pwg_query('DELETE FROM '.IMAGE_COMPARISON_TABLE.' WHERE id = '.$pair_id.';');
  return true;
}

/**
 * All saved pairs, decorated with image names, for the admin list.
 * Names are read back from the DB, so they are escaped at output time
 * in the template (auto-escaping is off).
 *
 * @return array[]
 */
function image_comparison_list_pairs()
{
  $pairs = query2array('
SELECT id, image_left, image_right, label, created_on, created_by
  FROM '.IMAGE_COMPARISON_TABLE.'
  ORDER BY created_on DESC, id DESC
;');

  if (!count($pairs))
  {
    return array();
  }

  // gather the names of every referenced image in one query
  $ids = array();
  foreach ($pairs as $pair)
  {
    $ids[(int)$pair['image_left']] = true;
    $ids[(int)$pair['image_right']] = true;
  }
  $ids = array_keys($ids);

  $names = query2array('
SELECT id, name, file
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', array_map('intval', $ids)).')
;', 'id');

  foreach ($pairs as &$pair)
  {
    $left = (int)$pair['image_left'];
    $right = (int)$pair['image_right'];
    $pair['LEFT_NAME'] = isset($names[$left]) ? image_comparison_image_name($names[$left]) : ('#'.$left);
    $pair['RIGHT_NAME'] = isset($names[$right]) ? image_comparison_image_name($names[$right]) : ('#'.$right);
    $pair['U_COMPARE'] = image_comparison_compare_url($left, $right);
  }
  unset($pair);

  return $pairs;
}

/**
 * Current user id, or null for the anonymous guest.
 *
 * @return int|null
 */
function image_comparison_current_user_id()
{
  global $user;
  if (is_a_guest() || empty($user['id']))
  {
    return null;
  }
  return (int)$user['id'];
}

// +-----------------------------------------------------------------------+
// | Gallery buttons                                                       |
// +-----------------------------------------------------------------------+

/**
 * Render the small comparison icon + label as a themed gallery button.
 *
 * @param string $url   raw URL (escaped here)
 * @param string $label translated label
 * @param string $title translated title attribute
 * @return string HTML
 */
function image_comparison_button_html($url, $label, $title)
{
  return
    '<a href="'.htmlspecialchars($url, ENT_QUOTES, get_pwg_charset()).'"'
    .' title="'.htmlspecialchars($title, ENT_QUOTES, get_pwg_charset()).'"'
    .' class="pwg-state-default pwg-button" rel="nofollow">'
    .'<span class="pwg-icon icon-comparison" aria-hidden="true">&#8646;</span>'
    .'<span class="pwg-button-text">'.htmlspecialchars($label, ENT_QUOTES, get_pwg_charset()).'</span>'
    .'</a>';
}

/**
 * Add a "Compare" button to the single-photo page.
 *
 * Pre-selects the current photo on the left and, when known, a sensible
 * right-hand photo: a saved pair partner first, otherwise the next photo
 * in the current set.
 *
 * @return void
 */
function image_comparison_loc_end_picture()
{
  global $page, $template;

  $conf_ic = image_comparison_get_conf();
  if (empty($conf_ic['show_picture_button']) || empty($page['image_id']))
  {
    return;
  }

  $left = (int)$page['image_id'];
  $cat = isset($page['category']['id']) ? (int)$page['category']['id'] : null;

  $partner = image_comparison_find_pair_partner($left);
  $right = null;
  $paired = false;

  if ($partner !== null)
  {
    $right = $partner;
    $paired = true;
  }
  elseif (!empty($page['items']) && isset($page['current_rank']))
  {
    $items = array_values($page['items']);
    $rank = (int)$page['current_rank'];
    if (isset($items[$rank + 1]))
    {
      $right = (int)$items[$rank + 1];
    }
    elseif ($rank > 0 && isset($items[$rank - 1]))
    {
      $right = (int)$items[$rank - 1];
    }
  }

  $url = image_comparison_compare_url($left, $right, $cat, null);
  $title = $paired ? l10n('Compare with the paired photo') : l10n('Compare this photo with another');

  $template->add_picture_button(
    image_comparison_button_html($url, l10n('Compare'), $title),
    BUTTONS_RANK_NEUTRAL
  );
}

// +-----------------------------------------------------------------------+
// | Virtual "compare" section + index integration                        |
// +-----------------------------------------------------------------------+

/**
 * Claim the /compare virtual section so index.php renders our own body.
 *
 * @return void
 */
function image_comparison_loc_end_section_init()
{
  global $tokens, $page, $conf;

  if (!isset($tokens[0]) || $tokens[0] !== 'compare')
  {
    return;
  }

  $page['section'] = 'compare';
  $page['title'] = l10n('Compare photos');
  $page['section_title'] =
    '<a href="'.get_absolute_root_url().'">'.l10n('Home').'</a>'
    .$conf['level_separator'].l10n('Compare photos');
  $page['body_id'] = 'theImageComparisonPage';
  $page['is_external'] = true;
  $page['items'] = array();
}

/**
 * On the comparison page: enqueue assets (CSS must be added before the
 * page header is flushed) and build the page body into the CONTENT var.
 *
 * @return void
 */
function image_comparison_loc_begin_index()
{
  global $page, $template;

  if (!isset($page['section']) || $page['section'] !== 'compare')
  {
    return;
  }

  $template->func_combine_css(
    array(
      'id'   => 'image_comparison',
      'path' => IMAGE_COMPARISON_PATH.'template/style.css',
    )
  );
  $template->func_combine_script(
    array(
      'id'   => 'image_comparison',
      'path' => IMAGE_COMPARISON_PATH.'js/compare.js',
      'load' => 'footer',
    )
  );

  image_comparison_render_compare_page();
}

/**
 * On normal album pages: offer a "Compare photos" button that opens the
 * picker scoped to the current album.
 *
 * @return void
 */
function image_comparison_loc_end_index()
{
  global $page, $template;

  if (isset($page['section']) && $page['section'] === 'compare')
  {
    return;
  }

  $conf_ic = image_comparison_get_conf();
  if (empty($conf_ic['show_index_button']))
  {
    return;
  }

  if (empty($page['category']['id']) || empty($page['items']))
  {
    return;
  }

  $cat = (int)$page['category']['id'];
  $items = array_values($page['items']);
  $left = isset($items[0]) ? (int)$items[0] : null;
  $right = isset($items[1]) ? (int)$items[1] : null;

  $url = image_comparison_compare_url($left, $right, $cat, null);

  $template->add_index_button(
    image_comparison_button_html($url, l10n('Compare photos'), l10n('Compare two photos side by side')),
    BUTTONS_RANK_NEUTRAL
  );
}

/**
 * Build the comparison page body and render it into the CONTENT template var.
 *
 * @return void
 */
function image_comparison_render_compare_page()
{
  global $template, $page;

  $conf_ic = image_comparison_get_conf();

  // --- validated inputs -------------------------------------------------
  $left_id = (isset($_GET['left']) && preg_match(PATTERN_ID, $_GET['left'])) ? (int)$_GET['left'] : null;
  $right_id = (isset($_GET['right']) && preg_match(PATTERN_ID, $_GET['right'])) ? (int)$_GET['right'] : null;
  $cat_id = (isset($_GET['cat']) && preg_match(PATTERN_ID, $_GET['cat'])) ? (int)$_GET['cat'] : null;

  $mode = $conf_ic['default_mode'];
  if (isset($_GET['mode']) && in_array($_GET['mode'], array('sidebyside', 'slider'), true))
  {
    $mode = $_GET['mode'];
  }

  $size = $conf_ic['derivative_size'];
  if (!in_array($size, image_comparison_allowed_sizes(), true))
  {
    $size = IMG_LARGE;
  }

  // --- load the two photos (permission-aware) ---------------------------
  $left = ($left_id !== null) ? image_comparison_get_image($left_id) : null;
  $right = ($right_id !== null) ? image_comparison_get_image($right_id) : null;

  if ($left_id !== null && $left === null)
  {
    $page['infos'][] = l10n('The requested left photo is not available.');
  }
  if ($right_id !== null && $right === null)
  {
    $page['infos'][] = l10n('The requested right photo is not available.');
  }

  // --- scope the picker to an album -------------------------------------
  if ($cat_id === null && $left !== null)
  {
    $cat_id = image_comparison_first_category((int)$left['id']);
  }
  if ($cat_id === null && $right !== null)
  {
    $cat_id = image_comparison_first_category((int)$right['id']);
  }

  $album_name = null;
  $thumbs = array();
  if ($cat_id !== null)
  {
    $album = image_comparison_get_album($cat_id);
    if ($album !== null)
    {
      $album_name = $album['name'];
      $images = image_comparison_get_album_images($cat_id, (int)$conf_ic['picker_limit']);
      foreach ($images as $row)
      {
        $id = (int)$row['id'];
        $thumbs[] = array(
          'ID'         => $id,
          'NAME'       => image_comparison_image_name($row),
          'TN_URL'     => DerivativeImage::url(IMG_SQUARE, $row),
          'U_SET_LEFT' => image_comparison_compare_url($id, ($right !== null ? (int)$right['id'] : null), $cat_id, $mode),
          'U_SET_RIGHT'=> image_comparison_compare_url(($left !== null ? (int)$left['id'] : null), $id, $cat_id, $mode),
          'IS_LEFT'    => ($left !== null && (int)$left['id'] === $id),
          'IS_RIGHT'   => ($right !== null && (int)$right['id'] === $id),
        );
      }
    }
    else
    {
      // album exists but is not visible to this user: drop the scope silently
      $cat_id = null;
    }
  }

  $ready = ($left !== null && $right !== null);
  $existing_pair_id = $ready ? image_comparison_find_exact_pair((int)$left['id'], (int)$right['id']) : null;

  // --- expose to the template -------------------------------------------
  $template->assign(
    'ic',
    array(
      'MODE'             => $mode,
      'SHOW_METADATA'    => (bool)$conf_ic['show_metadata'],
      'SIZE'             => $size,
      'LEFT'             => image_comparison_view_struct($left, $size),
      'RIGHT'            => image_comparison_view_struct($right, $size),
      'READY'            => $ready,
      'THUMBS'           => $thumbs,
      'CAT_ID'           => $cat_id,
      'ALBUM_NAME'       => $album_name,
      'CAN_SAVE'         => (!is_a_guest() && $ready),
      'EXISTING_PAIR_ID' => $existing_pair_id,
      'U_SWAP'           => $ready ? image_comparison_compare_url((int)$right['id'], (int)$left['id'], $cat_id, $mode) : null,
      'WS_URL'           => get_root_url().'ws.php?format=json',
      'PWG_TOKEN'        => get_pwg_token(),
    )
  );

  $template->set_filename('image_comparison_compare', IMAGE_COMPARISON_REALPATH.'/template/compare.tpl');
  $template->assign_var_from_handle('CONTENT', 'image_comparison_compare');
}

// +-----------------------------------------------------------------------+
// | Admin menu                                                            |
// +-----------------------------------------------------------------------+

/**
 * Add the plugin to the admin Plugins menu (trigger_change handler).
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
