{*
 * PlugnPay Smart Screens v2: hosted checkout payment option.
 *}
<div class="row">
  <div class="col-xs-12">
    {if $plugnpayss2_error}
      <p class="alert alert-danger">
        {$plugnpayss2_error|escape:'htmlall':'UTF-8'}
      </p>
    {/if}

    <p class="payment_module">
      <a
        class="plugnpayss2"
        href="{$plugnpayss2_redirect_url|escape:'htmlall':'UTF-8'}"
        title="{l s='Pay securely by credit card with PlugnPay' mod='plugnpayss2'}"
      >
        {l s='Pay securely by credit card with PlugnPay' mod='plugnpayss2'}
      </a>
    </p>
  </div>
</div>
