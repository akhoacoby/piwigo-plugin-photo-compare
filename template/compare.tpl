{* ----------------------------------------------------------------- *}
{* Image Comparison — public compare page.                            *}
{* Three views: viewer (two images), picker (choose from an album),   *}
{* featured (saved pairs). Theme-neutral, color-inheriting CSS.        *}
{* Smarty auto-escape is OFF — every dynamic value is escaped here.    *}
{* ----------------------------------------------------------------- *}

{combine_css path=$IMAGE_COMPARISON_PATH|@cat:'template/public.css'}

<div id="imageComparisonBlock"
     class="image-comparison{if $IMAGE_COMPARISON_IS_BOOTSTRAP} image-comparison--bootstrap{elseif $IMAGE_COMPARISON_THEME == 'modus'} image-comparison--modus{/if}">

{if $IC_VIEW == 'viewer'}

  {combine_script id='image_comparison' load='footer' path=$IMAGE_COMPARISON_PATH|@cat:'js/compare.js'}

  <div class="ic-viewer"
       data-mode="{$IC_MODE|@escape}"
       data-sync="{if $IC_SYNC_ZOOM}1{else}0{/if}"
       data-ws-url="{$IC_WS_URL|@escape}"
       data-save-method="{$IC_SAVE_METHOD|@escape}"
       data-token="{$IC_PWG_TOKEN|@escape}"
       data-left-id="{$IC_LEFT.id|@intval}"
       data-right-id="{$IC_RIGHT.id|@intval}"
       data-msg-title="{'Title for this comparison (optional):'|@translate|escape:'html'}"
       data-msg-saved="{'Comparison saved'|@translate|escape:'html'}"
       data-msg-error="{'Could not save the comparison'|@translate|escape:'html'}">

    <div class="ic-toolbar" role="toolbar" aria-label="{'Comparison controls'|@translate|escape}">
      <div class="ic-toolbar-group">
        <a class="ic-btn{if $IC_MODE == 'side_by_side'} ic-btn--active{/if}" href="{$IC_SBS_URL|@escape:'url'}">{'Side by side'|@translate|escape}</a>
        <a class="ic-btn{if $IC_MODE == 'slider'} ic-btn--active{/if}" href="{$IC_SLIDER_URL|@escape:'url'}">{'Slider'|@translate|escape}</a>
      </div>

      <div class="ic-toolbar-group ic-zoom-controls">
        <button type="button" class="ic-btn ic-zoom-out" aria-label="{'Zoom out'|@translate|escape}" title="{'Zoom out'|@translate|escape}">&#8722;</button>
        <button type="button" class="ic-btn ic-zoom-reset" title="{'Reset zoom'|@translate|escape}">100%</button>
        <button type="button" class="ic-btn ic-zoom-in" aria-label="{'Zoom in'|@translate|escape}" title="{'Zoom in'|@translate|escape}">&#43;</button>
      </div>

      <div class="ic-toolbar-group">
        <label class="ic-sync-toggle" title="{'Synchronise zoom and pan'|@translate|escape}">
          <input type="checkbox" class="ic-sync-input"{if $IC_SYNC_ZOOM} checked="checked"{/if}>
          {'Sync zoom &amp; pan'|@translate}
        </label>
        <a class="ic-btn" href="{$IC_SWAP_URL|@escape:'url'}" title="{'Swap left and right'|@translate|escape}">{'Swap'|@translate|escape}</a>
        <a class="ic-btn" href="{$IC_RESET_URL|@escape:'url'}" title="{'Choose other photos'|@translate|escape}">{'New comparison'|@translate|escape}</a>
        {if $IC_CAN_SAVE}
          <button type="button" class="ic-btn ic-save">{'Save comparison'|@translate|escape}</button>
        {/if}
      </div>
    </div>

    <div class="ic-stage ic-stage--{$IC_MODE|@escape}">
      <figure class="ic-pane ic-pane-left">
        <div class="ic-viewport">
          <img class="ic-img" src="{$IC_LEFT.url|@escape:'url'}" alt="{$IC_LEFT.name|@escape}" draggable="false">
        </div>
        <figcaption class="ic-pane-label"><a href="{$IC_LEFT.page|@escape:'url'}">{$IC_LEFT.name|@escape}</a></figcaption>
      </figure>

      <figure class="ic-pane ic-pane-right">
        <div class="ic-viewport">
          <img class="ic-img" src="{$IC_RIGHT.url|@escape:'url'}" alt="{$IC_RIGHT.name|@escape}" draggable="false">
        </div>
        <figcaption class="ic-pane-label"><a href="{$IC_RIGHT.page|@escape:'url'}">{$IC_RIGHT.name|@escape}</a></figcaption>
      </figure>

      <div class="ic-divider" role="slider" tabindex="0" aria-label="{'Reveal slider'|@translate|escape}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50">
        <span class="ic-divider-line"></span>
        <span class="ic-divider-handle" aria-hidden="true">&#8596;</span>
      </div>
    </div>

    <p class="ic-hint">{'Scroll to zoom, drag to pan. In slider mode, drag the divider to reveal.'|@translate|escape}</p>
  </div>

{elseif $IC_VIEW == 'picker'}

  <div class="ic-picker">
    {if $IC_PICK_STAGE == 'right'}
      <div class="ic-panel">
        <p class="ic-pick-instructions">
          {'First photo selected. Now choose the second photo to compare.'|@translate|escape}
          <a class="ic-btn" href="{$IC_CHANGE_LEFT_URL|@escape:'url'}">{'Change first photo'|@translate|escape}</a>
        </p>
        {if $IC_PICK_LEFT}
          <div class="ic-chosen">
            <img src="{$IC_PICK_LEFT.thumb|@escape:'url'}" alt="{$IC_PICK_LEFT.name|@escape}">
            <span>{$IC_PICK_LEFT.name|@escape}</span>
          </div>
        {/if}
      </div>
    {else}
      <p class="ic-panel ic-pick-instructions">{'Choose the first photo to compare.'|@translate|escape}</p>
    {/if}

    {if $IC_PICK_ITEMS}
      <ul class="ic-thumb-grid">
        {foreach from=$IC_PICK_ITEMS item=it}
          <li>
            <a href="{$it.url|@escape:'url'}" title="{$it.name|@escape}">
              <img src="{$it.thumb|@escape:'url'}" alt="{$it.name|@escape}" loading="lazy">
              <span class="ic-thumb-label">{$it.name|@escape}</span>
            </a>
          </li>
        {/foreach}
      </ul>
    {else}
      <p class="ic-panel">{'No photos available in this album.'|@translate|escape}</p>
    {/if}
  </div>

{else}

  <div class="ic-featured">
    {if $IC_FEATURED}
      <p class="ic-panel">{'Saved comparisons'|@translate|escape}</p>
      <ul class="ic-featured-grid">
        {foreach from=$IC_FEATURED item=pair}
          <li>
            <a href="{$pair.url|@escape:'url'}" title="{$pair.title|@escape}">
              <span class="ic-featured-thumbs">
                <img src="{$pair.left_thumb|@escape:'url'}" alt="" loading="lazy">
                <img src="{$pair.right_thumb|@escape:'url'}" alt="" loading="lazy">
              </span>
              <span class="ic-thumb-label">{$pair.title|@escape}</span>
            </a>
          </li>
        {/foreach}
      </ul>
    {else}
      <div class="ic-panel">
        <p>{'No comparison selected yet.'|@translate|escape}</p>
        <p>{'Open a photo or an album and use the Compare button to pick two photos.'|@translate|escape}</p>
      </div>
    {/if}
  </div>

{/if}

</div>
