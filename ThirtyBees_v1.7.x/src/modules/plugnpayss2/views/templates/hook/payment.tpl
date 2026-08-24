{*
 * PlugnPay Smart Screens v2 payment option.
 *}
<div class="plugnpayss2-payment">
  {if $plugnpayss2_error}
    <div class="plugnpayss2-error">
      {$plugnpayss2_error|escape:'htmlall':'UTF-8'}
    </div>
  {/if}

  <p class="payment_module">
    <a href="{$plugnpayss2_redirect_url|escape:'htmlall':'UTF-8'}">
      {l s='Pay securely by credit card with PlugnPay Smart Screens' mod='plugnpayss2'}
    </a>
  </p>

  <p class="plugnpayss2-notice">
    {l s='You will be redirected to PlugnPay to complete payment. Card details are collected on the hosted payment page.' mod='plugnpayss2'}
  </p>
</div>
