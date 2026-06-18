<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Register the plugin's web-service methods.
 *
 * Lazy-loaded by main.inc.php only when ws.php fires `ws_add_methods`.
 *
 * @param array $arr [0] => the Pwg web-service instance (by reference)
 * @return void
 */
function image_comparison_ws_add_methods($arr)
{
  $service = &$arr[0];

  $service->addMethod(
    'image_comparison.savePair',
    'ws_image_comparison_save_pair',
    array(
      'left'      => array('type' => WS_TYPE_ID),
      'right'     => array('type' => WS_TYPE_ID),
      'label'     => array('default' => '', 'flags' => WS_PARAM_OPTIONAL),
      'pwg_token' => array(),
    ),
    'Save a comparison of two photos so it can be re-opened later.',
    null,
    array('post_only' => true)
  );

  $service->addMethod(
    'image_comparison.deletePair',
    'ws_image_comparison_delete_pair',
    array(
      'pair_id'   => array('type' => WS_TYPE_ID),
      'pwg_token' => array(),
    ),
    'Delete a saved comparison (creator or administrator only).',
    null,
    array('post_only' => true)
  );
}

/**
 * Web service: save a comparison pair.
 *
 * State-changing POST → CSRF token is mandatory. Object permissions are
 * re-checked inside image_comparison_save_pair() (type validation is not
 * authorization).
 *
 * @param array  $params  validated 'left', 'right', 'label'
 * @param object $service web-service instance
 * @return array|PwgError
 */
function ws_image_comparison_save_pair($params, &$service)
{
  if (get_pwg_token() != $params['pwg_token'])
  {
    return new PwgError(403, 'Invalid security token');
  }

  if (is_a_guest())
  {
    return new PwgError(403, l10n('You must be logged in to save a comparison.'));
  }

  $error = '';
  $pair_id = image_comparison_save_pair($params['left'], $params['right'], $params['label'], $error);

  if ($pair_id === false)
  {
    return new PwgError(WS_ERR_INVALID_PARAM, $error);
  }

  return array(
    'pair_id' => $pair_id,
    'message' => l10n('Comparison saved.'),
  );
}

/**
 * Web service: delete a saved comparison pair.
 *
 * @param array  $params  validated 'pair_id'
 * @param object $service web-service instance
 * @return array|PwgError
 */
function ws_image_comparison_delete_pair($params, &$service)
{
  if (get_pwg_token() != $params['pwg_token'])
  {
    return new PwgError(403, 'Invalid security token');
  }

  if (is_a_guest())
  {
    return new PwgError(403, l10n('You are not allowed to delete this comparison.'));
  }

  $error = '';
  if (!image_comparison_delete_pair($params['pair_id'], $error))
  {
    return new PwgError(403, $error);
  }

  return array(
    'message' => l10n('Comparison deleted.'),
  );
}
