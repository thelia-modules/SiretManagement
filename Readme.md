# Siret Management

Manage the siret number and Intra Community VAT number for your customers, linked with INSEE API
to check SIRET and SIREN validity, and provide automatic illing of address fields.

## Installation

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/siret-management-module
```

## Configuration

You need to set the Sirene API key in the backOffice of the SiretManagement module
if you want to use the INSEE API to check SIRET numbers and get company information.

To create an account and get the key, go to https://simondevelop.github.io/sirene/#prerequis

> **Upgrading from an older version?** Prior versions used a separate public/private consumer
> key pair (`PUBLIC_CONSUMER`/`PRIVATE_CONSUMER`). This has been replaced by a single `API_KEY`
> field. The old keys are **not** migrated automatically: after upgrading, the SIRET check will
> silently stop working until you re-enter a valid key in the module's configuration screen.

You can also enable an Intra-Community VAT Number check against VIES (the European Commission's
official VAT validation service) by checking "Check VAT number existence" in the module
configuration. No account or API key is required for this check, and all EU member states are
covered, not just France.

## Template integration

To display input fields to your customer, you have to change register.html and account-update.html.

### register.html

In register.html, add the following hook call :

`{hook name="siret.check" mode='create'}`

To allow automatic filling of address fields, add the following hook call :

`{hook name="siret.js"}`

### account-update.html

In account-update.html, add the following hook call :

`{hook name="siret.check" mode='update'}`

The siret.js hook call is not required, as the address fields are not present in the account-update.html file.

A template is provided for the `default` Smarty theme.

> **Dropped in 2.0.0:** the `modern` Smarty theme template (and its translations) has been
> removed. Sites still running the `modern` theme will lose the SIRET/VAT fields on the
> registration form after upgrading, with no automatic fallback. If you need them, copy the
> `default` theme's `siret.html` into your `modern` theme before upgrading.

This module has no dependency on JQuery.

You can override the siret.html file in your own template for a custom integration.

### Flexy theme

Flexy's templates never use `{hook}` calls, so the steps above don't apply. Integration is
automatic instead:

- The SIRET/VAT fields are added directly to Flexy's `flexybundle_form_customer_informations_form`
  (registration) and `flexybundle_form_customer_update_form` (`/account`) forms — nothing to
  change in the theme's own templates.
- If "Check VAT number existence" is enabled in the module configuration, a live-check script
  (debounced VIES lookup + spinner on the VAT input, with a "not found, confirm anyway" checkbox
  shown only when relevant) is injected on every page via the `layout.body.bottom` theme hook. It
  no-ops on any page that doesn't render a VAT input, so nothing needs to be enabled per-template.

## Security note: the front VAT live-check endpoint

`CheckVatNumberController` (`/register/checkVatNumber`) is intentionally anonymous and
unauthenticated: it backs the live VAT-check UX on the registration form, which runs before
the customer has an account. It only relays a boolean `success`/`found` (never VIES's raw
message or the resolved company name), and is throttled to 1 request / 3 seconds per
session+IP via `ThrottleTrait`. This makes it a rate-limited, minimal-disclosure proxy to
VIES rather than a raw passthrough -- accepted as-is, no CSRF token is expected on it since
it performs no state-changing action.

## Running the tests

This module has no PHPUnit config or autoload of its own: its classes rely on Thelia's own
module autoloading, which only exists once the module sits under a real Thelia install's
`vendor/thelia/modules/` (or `local/modules/`). Run the tests from that host project instead
of trying to `composer install` this module standalone, using its own autoloader:

```
ddev exec ./vendor/bin/phpunit --bootstrap vendor/autoload.php \
    vendor/thelia/modules/SiretManagement/Tests/Unit
```

`Tests/Functional` is tagged `@group functional`: it makes real HTTP calls to the live VIES
API and is excluded from the command above on purpose. Run it explicitly, deliberately, when
you actually want to hit VIES:

```
ddev exec ./vendor/bin/phpunit --bootstrap vendor/autoload.php \
    vendor/thelia/modules/SiretManagement/Tests/Functional
```

### Suggestion for `default` template

#### In register.html
```
                </fieldset>

                {hook name="siret.check" mode='create'}

                <fieldset id="register-login" class="panel panel-info">
```

```
{block name="javascript-initialization"}
{hook name="register.javascript-initialization"}
{hook name="siret.js"}
{/block}
```

#### In account-update.html

```
          </fieldset>

          {hook name="siret.check" mode='update'}

          {form_field field="newsletter"}
```

