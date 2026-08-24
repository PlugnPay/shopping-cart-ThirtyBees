{if $plugnpayss2_status == 'ok'}
  <p class="alert alert-success">
    {l s='Your PlugnPay Smart Screens payment was approved. Order reference:' mod='plugnpayss2'}
    <strong>{$plugnpayss2_order_reference|escape:'htmlall':'UTF-8'}</strong>
  </p>
{else}
  <p class="alert alert-danger">
    {l s='Your payment could not be completed. Please contact the merchant.' mod='plugnpayss2'}
  </p>
{/if}
