{* custommessages.tpl *}
{if isset($custom_message) && !empty($custom_message)}
<div class="custom-message">
    {$custom_message nofilter}
</div>
{/if}