# Siret Management

Manage the siret number for customer, basic usage like in register, update account, linked with API.
## Installation

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/siret-management-module:~1.0 
```


## Hook
You need to set public consumer key and secret in backOffice of siretManagement module if want to have an API control.

the API link (create account to have keys) : https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee

You need to declare the hook where you want like in register.html :
{hook name="siret.check"}
and don't forget to include js
like this
{block name="javascript" append}
{hook name="siret.js"}
{/block}