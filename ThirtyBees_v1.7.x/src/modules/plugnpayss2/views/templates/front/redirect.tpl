<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>{l s='Redirecting to secure payment…' mod='plugnpayss2'}</title>
  <meta name="robots" content="noindex,nofollow" />
</head>
<body>
  <section id="plugnpayss2-redirect" class="plugnpayss2-redirect">
    <h1>{l s='Redirecting to secure payment…' mod='plugnpayss2'}</h1>
    <p>{l s='Please wait while we connect you to PlugnPay Smart Screens. Do not refresh this page.' mod='plugnpayss2'}</p>

    <form id="plugnpayss2-hosted-form" action="{$plugnpayss2_gateway_url|escape:'htmlall':'UTF-8'}" method="post">
      {foreach from=$plugnpayss2_hosted_fields key=name item=value}
        <input type="hidden" name="{$name|escape:'htmlall':'UTF-8'}" value="{$value|escape:'htmlall':'UTF-8'}" />
      {/foreach}
      <noscript>
        <button type="submit">
          {l s='Continue to PlugnPay' mod='plugnpayss2'}
        </button>
      </noscript>
    </form>
  </section>

  <script type="text/javascript">
    (function () {
      var form = document.getElementById('plugnpayss2-hosted-form');
      if (form) {
        form.submit();
      }
    }());
  </script>
</body>
</html>
