{if $plugnpayapi_status == 'ok'}
  <p class="alert alert-success">
    {l s='Your PlugnPay payment was approved. Order reference:' mod='plugnpayapi'}
    <strong>{$plugnpayapi_order_reference|escape:'htmlall':'UTF-8'}</strong>
  </p>
{else}
  <p class="alert alert-danger">
    {l s='Your payment could not be completed. Please contact the merchant.' mod='plugnpayapi'}
  </p>
{/if}
