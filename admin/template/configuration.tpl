{* Image Comparison — admin settings & saved-pairs management *}
<div id="theImageComparisonAdmin">

{if $IC_TAB eq 'config'}

  <form method="post" action="" class="properties">
    <input type="hidden" name="pwg_token" value="{$IC_PWG_TOKEN}">

    <fieldset>
      <legend>{'Comparison viewer'|@translate}</legend>

      <p class="ic-field">
        <label for="ic_derivative_size">{'Image size used in the viewer'|@translate}</label>
        <select id="ic_derivative_size" name="derivative_size">
          {html_options options=$IC_SIZE_OPTIONS selected=$IC_DERIVATIVE_SIZE}
        </select>
        <span class="ic-help">{'Larger sizes show more detail at 100% zoom but load more slowly.'|@translate}</span>
      </p>

      <p class="ic-field">
        <label>{'Default comparison mode'|@translate}</label>
        <label class="ic-radio"><input type="radio" name="default_mode" value="sidebyside"{if $IC_DEFAULT_MODE eq 'sidebyside'} checked="checked"{/if}> {'Side by side'|@translate}</label>
        <label class="ic-radio"><input type="radio" name="default_mode" value="slider"{if $IC_DEFAULT_MODE eq 'slider'} checked="checked"{/if}> {'Slider overlay'|@translate}</label>
      </p>

      <p class="ic-field">
        <label><input type="checkbox" name="show_metadata" value="1"{if $IC_SHOW_METADATA} checked="checked"{/if}> {'Show file name, dimensions and size under each photo'|@translate}</label>
      </p>
    </fieldset>

    <fieldset>
      <legend>{'Gallery buttons'|@translate}</legend>

      <p class="ic-field">
        <label><input type="checkbox" name="show_picture_button" value="1"{if $IC_SHOW_PICTURE} checked="checked"{/if}> {'Add a "Compare" button on the photo page'|@translate}</label>
      </p>

      <p class="ic-field">
        <label><input type="checkbox" name="show_index_button" value="1"{if $IC_SHOW_INDEX} checked="checked"{/if}> {'Add a "Compare photos" button on album pages'|@translate}</label>
      </p>
    </fieldset>

    <fieldset>
      <legend>{'Picker'|@translate}</legend>

      <p class="ic-field">
        <label for="ic_picker_limit">{'Maximum photos listed in the picker'|@translate}</label>
        <input type="number" id="ic_picker_limit" name="picker_limit" min="1" max="1000" value="{$IC_PICKER_LIMIT}">
      </p>
    </fieldset>

    <p>
      <input type="submit" class="submit" name="ic_save_config" value="{'Save Settings'|@translate}">
    </p>
  </form>

{else}

  <div class="ic-pairs">
    <h3>{'Saved comparisons'|@translate}</h3>

    {if empty($IC_PAIRS)}
      <p class="ic-empty">{'No comparison has been saved yet.'|@translate}</p>
    {else}
      <table class="table2">
        <thead>
          <tr>
            <th>{'Left photo'|@translate}</th>
            <th>{'Right photo'|@translate}</th>
            <th>{'Label'|@translate}</th>
            <th>{'Date'|@translate}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$IC_PAIRS item=pair}
          <tr>
            <td>{$pair.LEFT_NAME|@escape} <span class="ic-id">#{$pair.image_left}</span></td>
            <td>{$pair.RIGHT_NAME|@escape} <span class="ic-id">#{$pair.image_right}</span></td>
            <td>{if $pair.label}{$pair.label|@escape}{else}&mdash;{/if}</td>
            <td>{$pair.created_on|@escape}</td>
            <td class="ic-actions">
              <a class="ic-open" href="{$pair.U_COMPARE|@escape}" target="_blank" rel="noopener">{'Open'|@translate}</a>
              <form method="post" action="" class="ic-inline-form" onsubmit="return confirm('{'Delete this comparison?'|@translate|escape:'javascript'}');">
                <input type="hidden" name="pwg_token" value="{$IC_PWG_TOKEN}">
                <input type="hidden" name="pair_id" value="{$pair.id}">
                <button type="submit" name="ic_delete_pair" class="ic-delete">{'Delete'|@translate}</button>
              </form>
            </td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    {/if}
  </div>

{/if}

</div>
