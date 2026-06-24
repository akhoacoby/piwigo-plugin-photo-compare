{* ----------------------------------------------------------------- *}
{* Image Comparison — saved comparisons management (admin theme).      *}
{* Auto-escape is OFF — escape dynamic values.                         *}
{* ----------------------------------------------------------------- *}

<div class="image-comparison-admin">
  {if $ic_pairs}
    <table class="table2">
      <thead>
        <tr>
          <th>{'Preview'|@translate}</th>
          <th>{'Title'|@translate}</th>
          <th>{'Photos'|@translate}</th>
          <th>{'Mode'|@translate}</th>
          <th>{'Created'|@translate}</th>
          <th>{'Actions'|@translate}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$ic_pairs item=pair}
          <tr>
            <td>
              {if $pair.available}
                <a href="{$pair.view_url|@escape:'url'}">
                  {if $pair.left_thumb}<img src="{$pair.left_thumb|@escape:'url'}" alt="" style="height:42px;width:auto;border-radius:3px;">{/if}
                  {if $pair.right_thumb}<img src="{$pair.right_thumb|@escape:'url'}" alt="" style="height:42px;width:auto;border-radius:3px;">{/if}
                </a>
              {else}
                <em>{'Image unavailable'|@translate}</em>
              {/if}
            </td>
            <td>{if $pair.title}{$pair.title|@escape}{else}&mdash;{/if}</td>
            <td>{$pair.left_name|@escape} / {$pair.right_name|@escape}</td>
            <td>{if $pair.mode == 'slider'}{'Slider'|@translate}{else}{'Side by side'|@translate}{/if}</td>
            <td>{if $pair.created_on}{$pair.created_on|@escape}{else}&mdash;{/if}</td>
            <td>
              {if $pair.available}<a class="icon-eye" href="{$pair.view_url|@escape:'url'}">{'View'|@translate}</a>{/if}
              <form method="post" action="{$F_ACTION}" style="display:inline;" onsubmit="return confirm('{'Delete this comparison?'|@translate|escape:'javascript'}');">
                <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
                <input type="hidden" name="delete_pair" value="{$pair.id|@intval}">
                <input type="submit" class="submit" value="{'Delete'|@translate}">
              </form>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {else}
    <p>{'No saved comparisons yet. Open two photos in the comparison viewer and use the Save button.'|@translate}</p>
  {/if}
</div>
