{*
 * PlugnPay Remote API: onsite card form (Authorize.net AIM checkout pattern).
 * Do not put raw {n,m} in HTML attributes — Smarty treats braces as tags.
 *}
<div class="row">
  <div class="col-xs-12">
    <div class="plugnpayapi-wrapper">
      {if $plugnpayapi_error}
        <p class="alert alert-danger">
          {$plugnpayapi_error|escape:'htmlall':'UTF-8'}
        </p>
      {/if}

      {if $plugnpayapi_secure != 1}
        <p class="alert alert-warning">
          {l s='Credit card payment requires HTTPS. Enable SSL in Preferences > General, then submit the form.' mod='plugnpayapi'}
        </p>
      {/if}

      <form name="plugnpayapi_form" id="plugnpayapi-form" action="{$plugnpayapi_action|escape:'htmlall':'UTF-8'}" method="post">
        <p class="plugnpayapi-title">{l s='Secured card payment' mod='plugnpayapi'}</p>
        <input type="hidden" name="plugnpayapi_token" value="{$plugnpayapi_checkout_token|escape:'htmlall':'UTF-8'}" />

        <div class="plugnpayapi-fields">
          <p class="plugnpayapi-row">
            <label class="plugnpayapi-label" for="plugnpayapi-card-owner">{l s='Cardholder name' mod='plugnpayapi'}</label>
            <input
              type="text"
              id="plugnpayapi-card-owner"
              name="plugnpayapi_card_owner"
              value="{$plugnpayapi_card_owner|escape:'htmlall':'UTF-8'}"
              maxlength="100"
              autocomplete="cc-name"
              required="required"
            />
          </p>

          <p class="plugnpayapi-row">
            <label class="plugnpayapi-label" for="plugnpayapi-card-number">{l s='Card number' mod='plugnpayapi'}</label>
            <input
              type="text"
              id="plugnpayapi-card-number"
              name="plugnpayapi_card_number"
              maxlength="23"
              autocomplete="cc-number"
              required="required"
            />
          </p>

          <p class="plugnpayapi-row">
            <label class="plugnpayapi-label" for="plugnpayapi-expiry-month">{l s='Expiration date' mod='plugnpayapi'}</label>
            <span class="plugnpayapi-expiry">
              <select id="plugnpayapi-expiry-month" name="plugnpayapi_expiry_month" autocomplete="cc-exp-month" required="required">
                {foreach from=$plugnpayapi_months item=month}
                  <option value="{$month|escape:'htmlall':'UTF-8'}">{$month|escape:'htmlall':'UTF-8'}</option>
                {/foreach}
              </select>
              <span aria-hidden="true"> / </span>
              <select name="plugnpayapi_expiry_year" autocomplete="cc-exp-year" required="required">
                {foreach from=$plugnpayapi_years item=year}
                  <option value="{$year|escape:'htmlall':'UTF-8'}">20{$year|escape:'htmlall':'UTF-8'}</option>
                {/foreach}
              </select>
            </span>
          </p>

          {if $plugnpayapi_use_cvv == 1}
            <p class="plugnpayapi-row">
              <label class="plugnpayapi-label" for="plugnpayapi-cvv">{l s='CVV' mod='plugnpayapi'}</label>
              <input
                type="password"
                id="plugnpayapi-cvv"
                name="plugnpayapi_cvv"
                maxlength="4"
                autocomplete="cc-csc"
                required="required"
              />
            </p>
          {/if}

          <p class="plugnpayapi-pci-notice">
            {l s='Your card details are encrypted in transit and sent directly to PlugnPay.' mod='plugnpayapi'}
          </p>

          <p class="plugnpayapi-submit-row">
            <button type="submit" id="plugnpayapi-submit" class="button btn btn-default standard-checkout button-medium"{if $plugnpayapi_secure != 1} disabled="disabled"{/if}>
              <span>{l s='Complete Purchase' mod='plugnpayapi'}</span>
            </button>
          </p>
        </div>
      </form>
    </div>
  </div>
</div>

<script type="text/javascript">
  (function () {
    var form = document.getElementById('plugnpayapi-form');
    if (!form) {
      return;
    }
    form.addEventListener('submit', function () {
      var button = document.getElementById('plugnpayapi-submit');
      if (button) {
        button.disabled = true;
      }
    });
  }());
</script>
