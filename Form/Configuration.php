<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SiretManagement\Form;

use SiretManagement\SiretManagement;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                SiretManagement::API_CHECK_DISABLED,
                CheckboxType::class,
                [
                    'data' => (bool) SiretManagement::getConfigValue(SiretManagement::API_CHECK_DISABLED, false),
                    'label' => Translator::getInstance()->trans('Disable siret check', [], SiretManagement::DOMAIN_NAME),
                    'required' => false,
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'If this box is unchecked, the SIRET field will be checked by INSEE API at form submit, and will throw error if siret is invalid'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            )
            ->add(
                SiretManagement::PUBLIC_CONSUMER,
                TextType::class,
                [
                    'data' => SiretManagement::getConfigValue(SiretManagement::PUBLIC_CONSUMER, null),
                    'required' => false,
                    'label' => Translator::getInstance()->trans('Clef du consommateur', [], SiretManagement::DOMAIN_NAME),
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'Enter the public consumer key for INSEE Sirene API (more details here: https://api.gouv.fr/les-api/sirene_v3)'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            )
            ->add(
                SiretManagement::PRIVATE_CONSUMER,
                TextType::class,
                [
                    'data' => SiretManagement::getConfigValue(SiretManagement::PRIVATE_CONSUMER, null),
                    'required' => false,
                    'label' => Translator::getInstance()->trans('Secret du consommateur', [], SiretManagement::DOMAIN_NAME),
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'Enter the private consumer key for INSEE Sirene API (more details here: https://api.gouv.fr/les-api/sirene_v3)'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            )
            ->add(
                SiretManagement::SIRET_REQUIRED,
                CheckboxType::class,
                [
                    'data' => (bool) SiretManagement::getConfigValue(SiretManagement::SIRET_REQUIRED, false),
                    'label' => Translator::getInstance()->trans('SIRET is required', [], SiretManagement::DOMAIN_NAME),
                    'required' => false,
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'If this box is checked, the SIRET field will be mandatory in customer create and update forms'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            )
            ->add(
                SiretManagement::TVA_INTRA_REQUIRED,
                CheckboxType::class,
                [
                    'data' => (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, false),
                    'label' => Translator::getInstance()->trans('Intra-Community VAT Number is required', [], SiretManagement::DOMAIN_NAME),
                    'required' => false,
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'If this box is checked, the Intra-Community VAT Number field will be mandatory in customer create and update forms'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            )
            ->add(
                SiretManagement::USE_SIRET,
                CheckboxType::class,
                [
                    'data' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_SIRET, true),
                    'label' => Translator::getInstance()->trans('Use the SIRET number', [], SiretManagement::DOMAIN_NAME),
                    'required' => false,
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'If this box is checked, the SIRET field will be shown in customer create and update forms'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            )
            ->add(
                SiretManagement::USE_TVA_INTRA,
                CheckboxType::class,
                [
                    'data' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_TVA_INTRA, true),
                    'label' => Translator::getInstance()->trans('Use the Intra-Community VAT Number', [], SiretManagement::DOMAIN_NAME),
                    'required' => false,
                    'label_attr' => [
                        'help' => Translator::getInstance()->trans(
                            'If this box is checked, the Intra-Community VAT Number field will be shown in customer create and update forms'
                            , [], SiretManagement::DOMAIN_NAME
                        )
                    ]
                ]
            );
    }

    public static function getName()
    {
        return 'siretmanagement_config_form';
    }
}
