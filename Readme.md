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

You need to set public consumer key and secret in backOffice of SiretManagement module
if you want to use the INSEE API to check SIRET numbers anbd get company information.

To create an account and get the keys, got to https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee

## Template integration

To display input fields to your customer, you have to change register.html and account-update.html.

In register.html, add the following hook call :

`{hook name="siret.check" mode='create'}`

To allow automatic filling of address fields, add the followinf hook call :

`{hook name="siret.js"}`

In account-update.html, add the following hook call :

`{hook name="siret.check" mode='update'}`

The siret.js hook call is not required, as the address fields are not present in the account-update.html file.

A template is provided for defualt and modern template.

This module has no dependency on JQuery.

You can override the siret.html file in your own template for a custom integration.

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

### Change recommended for `modern` template

#### In register.html

```
                </fieldset>

                {hook name="siret.check" mode='update'}

                {form_field field="newsletter"}
```

#### In account-update.html

```
{block name="javascript" append}
  {encore_entry_script_tags entry="register"}
  {hook name="siret.js"}
{/block}
```

```
      </div>

      {hook name="siret.check" mode='create'}

      <fieldset id="register-login">
```

#### In account-update.html

```
          </fieldset>

          {hook name="siret.check" mode='update'}

          {form_field field="newsletter"}
```
