{* Image Comparison — public dual viewer with synchronized zoom & pan *}
<div class="ic-app" data-mode="{$ic.MODE|@escape}" data-ws="{$ic.WS_URL|@escape}" data-token="{$ic.PWG_TOKEN|@escape}"
  {if $ic.READY}data-ready="1"{/if}
  {if $ic.LEFT}data-left="{$ic.LEFT.ID}"{/if}
  {if $ic.RIGHT}data-right="{$ic.RIGHT.ID}"{/if}>

  <div class="ic-toolbar" role="toolbar" aria-label="{'Comparison controls'|@translate}">

    <div class="ic-group ic-modes" role="group" aria-label="{'Comparison mode'|@translate}">
      <button type="button" class="ic-btn ic-mode-btn" data-mode="sidebyside" aria-pressed="{if $ic.MODE eq 'sidebyside'}true{else}false{/if}">{'Side by side'|@translate}</button>
      <button type="button" class="ic-btn ic-mode-btn" data-mode="slider" aria-pressed="{if $ic.MODE eq 'slider'}true{else}false{/if}">{'Slider overlay'|@translate}</button>
    </div>

    {if $ic.READY}
    <div class="ic-group ic-zoom" role="group" aria-label="{'Zoom'|@translate}">
      <button type="button" class="ic-btn ic-zoom-out" aria-label="{'Zoom out'|@translate}">&minus;</button>
      <span class="ic-zoom-level" aria-live="polite">100%</span>
      <button type="button" class="ic-btn ic-zoom-in" aria-label="{'Zoom in'|@translate}">+</button>
      <button type="button" class="ic-btn ic-fit">{'Fit'|@translate}</button>
      <button type="button" class="ic-btn ic-actual">{'100%'|@translate}</button>
    </div>

    <div class="ic-group ic-extra">
      {if $ic.U_SWAP}<a class="ic-btn ic-swap" href="{$ic.U_SWAP|@escape}" rel="nofollow">{'Swap'|@translate}</a>{/if}
      {if $ic.CAN_SAVE}
        {if $ic.EXISTING_PAIR_ID}
          <button type="button" class="ic-btn ic-remove" data-pair="{$ic.EXISTING_PAIR_ID}">{'Remove saved comparison'|@translate}</button>
        {else}
          <button type="button" class="ic-btn ic-save" data-prompt="{'Optional label for this comparison:'|@translate|escape:'html'}">{'Save comparison'|@translate}</button>
        {/if}
      {/if}
      <span class="ic-status" role="status" aria-live="polite"></span>
    </div>
    {/if}
  </div>

  {if $ic.READY}
  <div class="ic-stage ic-mode-{$ic.MODE|@escape}" tabindex="0" aria-label="{'Comparison viewer — drag to pan, scroll to zoom'|@translate}">
    <div class="ic-viewport ic-viewport-left">
      <div class="ic-canvas">
        <img class="ic-img" data-side="left" src="{$ic.LEFT.URL|@escape}" alt="{$ic.LEFT.NAME|@escape}" draggable="false">
      </div>
      <span class="ic-tag ic-tag-left">A</span>
    </div>
    <div class="ic-viewport ic-viewport-right">
      <div class="ic-canvas">
        <img class="ic-img" data-side="right" src="{$ic.RIGHT.URL|@escape}" alt="{$ic.RIGHT.NAME|@escape}" draggable="false">
      </div>
      <span class="ic-tag ic-tag-right">B</span>
    </div>
    <div class="ic-divider" role="separator" aria-label="{'Drag to reveal'|@translate}" aria-hidden="true">
      <span class="ic-divider-handle"></span>
    </div>
  </div>

  {if $ic.SHOW_METADATA}
  <div class="ic-meta">
    <div class="ic-meta-col">
      <span class="ic-meta-tag">A</span>
      <a class="ic-meta-name" href="{$ic.LEFT.U_PAGE|@escape}">{$ic.LEFT.NAME|@escape}</a>
      <span class="ic-meta-detail">{$ic.LEFT.FILE|@escape} &middot; {$ic.LEFT.WIDTH}&times;{$ic.LEFT.HEIGHT}{if $ic.LEFT.FILESIZE} &middot; {$ic.LEFT.FILESIZE} {'MB'|@translate}{/if}</span>
    </div>
    <div class="ic-meta-col">
      <span class="ic-meta-tag">B</span>
      <a class="ic-meta-name" href="{$ic.RIGHT.U_PAGE|@escape}">{$ic.RIGHT.NAME|@escape}</a>
      <span class="ic-meta-detail">{$ic.RIGHT.FILE|@escape} &middot; {$ic.RIGHT.WIDTH}&times;{$ic.RIGHT.HEIGHT}{if $ic.RIGHT.FILESIZE} &middot; {$ic.RIGHT.FILESIZE} {'MB'|@translate}{/if}</span>
    </div>
  </div>
  {/if}

  {else}
  <p class="ic-prompt">{'Pick two photos below to compare them side by side.'|@translate}</p>
  {/if}

  {if $ic.THUMBS}
  <div class="ic-picker">
    <h3 class="ic-picker-title">
      {if $ic.ALBUM_NAME}{$ic.ALBUM_NAME|@escape}{else}{'Choose photos'|@translate}{/if}
      <small>{'Use the A and B buttons to place a photo on each side.'|@translate}</small>
    </h3>
    <ul class="ic-thumbs">
      {foreach from=$ic.THUMBS item=thumb}
      <li class="ic-thumb{if $thumb.IS_LEFT} is-left{/if}{if $thumb.IS_RIGHT} is-right{/if}">
        <span class="ic-thumb-img" style="background-image:url('{$thumb.TN_URL|@escape}')" title="{$thumb.NAME|@escape}"></span>
        <span class="ic-thumb-name">{$thumb.NAME|@escape}</span>
        <span class="ic-thumb-actions">
          <a class="ic-pick ic-pick-a{if $thumb.IS_LEFT} active{/if}" href="{$thumb.U_SET_LEFT|@escape}" rel="nofollow" title="{'Place on side A'|@translate}">A</a>
          <a class="ic-pick ic-pick-b{if $thumb.IS_RIGHT} active{/if}" href="{$thumb.U_SET_RIGHT|@escape}" rel="nofollow" title="{'Place on side B'|@translate}">B</a>
        </span>
      </li>
      {/foreach}
    </ul>
  </div>
  {else}
  <p class="ic-prompt ic-prompt-empty">{'No album is available to pick photos from. Open a photo and use the Compare button instead.'|@translate}</p>
  {/if}

</div>
