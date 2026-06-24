{* ----------------------------------------------------------------- *}
{* Image Comparison — admin settings (ADMIN-THEME conventions).        *}
{* Renders in admin/themes/{default,clear,roma}; NOT the gallery theme.*}
{* Auto-escape is OFF — escape dynamic values.                         *}
{* ----------------------------------------------------------------- *}

<form method="post" action="{$F_ACTION}" class="properties">

  <fieldset>
    <legend>{'Comparison viewer'|@translate}</legend>
    <ul>
      <li>
        <label>
          {'Default mode'|@translate}
          <select name="default_mode">
            {foreach from=$mode_options item=opt}
              <option value="{$opt|@escape}"{if $cfg.default_mode == $opt} selected="selected"{/if}>{if $opt == 'side_by_side'}{'Side by side'|@translate}{else}{'Slider'|@translate}{/if}</option>
            {/foreach}
          </select>
        </label>
      </li>

      <li>
        <label>
          {'Image size shown in the viewer'|@translate}
          <select name="display_size">
            {foreach from=$size_options item=opt}
              <option value="{$opt|@escape}"{if $cfg.display_size == $opt} selected="selected"{/if}>{$opt|@escape}</option>
            {/foreach}
          </select>
        </label>
      </li>

      <li>
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="checkbox" name="sync_zoom" id="sync_zoom"{if $cfg.sync_zoom} checked="checked"{/if}>
          {'Synchronise zoom &amp; pan by default'|@translate}
        </label>
      </li>
    </ul>
  </fieldset>

  <fieldset>
    <legend>{'Compare buttons'|@translate}</legend>
    <ul>
      <li>
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="checkbox" name="show_picture_button" id="show_picture_button"{if $cfg.show_picture_button} checked="checked"{/if}>
          {'Show a Compare button on photo pages'|@translate}
        </label>
      </li>
      <li>
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="checkbox" name="show_index_button" id="show_index_button"{if $cfg.show_index_button} checked="checked"{/if}>
          {'Show a Compare button on album pages'|@translate}
        </label>
      </li>
    </ul>
  </fieldset>

  <p class="formButtons">
    <input class="submit" type="submit" name="submit" value="{'Save Settings'|@translate}">
  </p>

  <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
</form>
