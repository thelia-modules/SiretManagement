<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SiretManagement\Hook\Theme;

use SiretManagement\SiretManagement;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Twig\Environment;

/**
 * Flexy has no equivalent of the legacy Smarty siret.js/siret.check hooks (Hook/FrontHook.php):
 * its templates never call {hook}, only theme_hook(). This is the only extension point that
 * lets the module inject the live VAT-check script (debounced VIES lookup + spinner on the
 * tva_intra input) without modifying the Flexy theme's own files. Fired on every page
 * (layout.body.bottom, in base.html.twig) since Flexy's CustomerInformationForm has no
 * dedicated hook of its own to target; the script itself no-ops on any page that doesn't
 * render a [data-vat-input] field.
 */
final readonly class SiretManagementThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
        private TemplateHelperInterface $templateHelper,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return 'layout.body.bottom' === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        if (!(bool) SiretManagement::getConfigValue(SiretManagement::USE_TVA_INTRA, true)
            || !(bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, false)
        ) {
            return '';
        }

        // The translations for the "not found" message live under I18n/frontOffice/{template}/,
        // one directory per front template (see Model\Module::getFrontOfficeTemplateTranslationDomain()),
        // exactly like the {intl} tag resolves it for the Smarty themes. Hardcoding "default" here
        // silently fell back to the untranslated msgid on Flexy, since its active template is "flexy".
        $domain = \sprintf(
            '%s.fo.%s',
            SiretManagement::DOMAIN_NAME,
            $this->templateHelper->getActiveFrontTemplate()->getName()
        );

        // Translated in PHP rather than via the Twig `trans` filter: on the front office, the
        // `translator` service is BackOfficeDefaultTwigBundle\Translation\ModuleAwareTranslator,
        // which only routes module-domain lookups to Thelia's translator for /admin requests —
        // everywhere else it falls through to the plain Symfony translator, which never loaded
        // this module's catalogues. Calling Thelia's Translator directly sidesteps that entirely.
        $notFoundMessage = Translator::getInstance()->trans('This VAT number was not found.', [], $domain);

        return $this->twig->render('@SiretManagementModule/theme-hook/vat-check.html.twig', [
            'vatNotFoundMessage' => $notFoundMessage,
        ]);
    }
}
