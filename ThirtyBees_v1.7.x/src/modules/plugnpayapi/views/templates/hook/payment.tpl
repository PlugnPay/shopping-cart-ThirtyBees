{*
 * PlugnPay Remote API onsite card form.
 *}
<div class="plugnpayapi-payment">
  {if $plugnpayapi_error}
    <div class="plugnpayapi-error">
      {$plugnpayapi_error|escape:'htmlall':'UTF-8'}
    </div>
  {/if}

  <form action="{$plugnpayapi_action|escape:'htmlall':'UTF-8'}" method="post" id="plugnpayapi-form">
    <input type="hidden" name="plugnpayapi_token" value="{$plugnpayapi_checkout_token|escape:'htmlall':'UTF-8'}" />

    <p class="payment_module">
      <a href="#" id="plugnpayapi-toggle">
        {l s='Pay securely by credit card with PlugnPay' mod='plugnpayapi'}
      </a>
    </p>

    <fieldset id="plugnpayapi-fields">
      <legend>{l s='Credit card details' mod='plugnpayapi'}</legend>

      <div class="plugnpayapi-field">
        <label for="plugnpayapi-card-owner">{l s='Cardholder name' mod='plugnpayapi'}</label>
        <input
          type="text"
          id="plugnpayapi-card-owner"
          name="plugnpayapi_card_owner"
          value="{$plugnpayapi_card_owner|escape:'htmlall':'UTF-8'}"
          maxlength="100"
          autocomplete="cc-name"
          required
        />
      </div>

      <div class="plugnpayapi-field">
        <label for="plugnpayapi-card-number">{l s='Card number' mod='plugnpayapi'}</label>
        <input
          type="text"
          id="plugnpayapi-card-number"
          name="plugnpayapi_card_number"
          inputmode="numeric"
          minlength="12"
          maxlength="23"
          autocomplete="cc-number"
          pattern="[0-9 ]{12,23}"
          required
        />
      </div>

      <div class="plugnpayapi-field plugnpayapi-expiry">
        <label for="plugnpayapi-expiry-month">{l s='Expiration date' mod='plugnpayapi'}</label>
        <select
          id="plugnpayapi-expiry-month"
          name="plugnpayapi_expiry_month"
          autocomplete="cc-exp-month"
          required
        >
          {foreach from=$plugnpayapi_months item=month}
            <option value="{$month|escape:'htmlall':'UTF-8'}">{$month|escape:'htmlall':'UTF-8'}</option>
          {/foreach}
        </select>
        <span aria-hidden="true"> / </span>
        <select name="plugnpayapi_expiry_year" autocomplete="cc-exp-year" required>
          {foreach from=$plugnpayapi_years item=year}
            <option value="{$year.value|escape:'htmlall':'UTF-8'}">
              {$year.label|escape:'htmlall':'UTF-8'}
            </option>
          {/foreach}
        </select>
      </div>

      {if $plugnpayapi_use_cvv}
        <div class="plugnpayapi-field">
          <label for="plugnpayapi-cvv">{l s='CVV' mod='plugnpayapi'}</label>
          <input
            type="password"
            id="plugnpayapi-cvv"
            name="plugnpayapi_cvv"
            inputmode="numeric"
            minlength="3"
            maxlength="4"
            autocomplete="cc-csc"
            pattern="[0-9]{3,4}"
            required
          />
        </div>
      {/if}

      <p class="plugnpayapi-pci-notice">
        {l s='Your card details are encrypted in transit and sent directly to PlugnPay.' mod='plugnpayapi'}
      </p>

      <button type="submit" class="button btn btn-primary">
        <span>{l s='Validate order' mod='plugnpayapi'}</span>
      </button>
    </fieldset>
  </form>
</div>

<script type="text/javascript">
  (function () {
    var toggle = document.getElementById('plugnpayapi-toggle');
    var fields = document.getElementById('plugnpayapi-fields');
    var form = document.getElementById('plugnpayapi-form');
    if (!toggle || !fields || !form) {
      return;
    }
    {if !$plugnpayapi_error}
      fields.style.display = 'none';
    {/if}
    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      fields.style.display = 'block';
    });
    form.addEventListener('submit', function () {
      var button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
      }
    });
  }());
</script>
