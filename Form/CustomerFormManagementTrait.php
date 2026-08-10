<?php
/*************************************************************************************/
/*      Copyright (c) OpenStudio                                                     */
/*      web : https://www.openstudio.fr                                              */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE      */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 29/09/2023
 */

namespace SiretManagement\Form;

use SiretManagement\Event\CheckDataEvent;
use SiretManagement\SiretManagement;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

trait CustomerFormManagementTrait
{
    protected function setupCustomerForm(FormBuilderInterface $formBuilder): void
    {
        $removeSpacesTransformer = new CallbackTransformer(
            function ($string): string {
                return preg_replace("/\s/", '', $string);
            },
            function ($string): string {
                return preg_replace("/\s/", '', $string);
            }
        );

        $useSiret = (bool) SiretManagement::getConfigValue(SiretManagement::USE_SIRET, true);
        $siretRequired = $useSiret && (bool) SiretManagement::getConfigValue(SiretManagement::SIRET_REQUIRED, null);
        $siretConstraints = [
            new Callback([$this, 'checkSiretInput']),
        ];

        if ($siretRequired) {
            $siretConstraints[] = new NotBlank();
        }

        $useTvaIntra = (bool) SiretManagement::getConfigValue(SiretManagement::USE_TVA_INTRA, true);
        $vatRequired = $useTvaIntra && (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, null);
        $vatConstraints = [
            new Callback([$this, 'checkVatInput']),
        ];

        if ($vatRequired) {
            $vatConstraints[] = new NotBlank();
        }

        $formBuilder
            ->add(
                SiretManagement::SIRET,
                TextType::class,
                [
                    'label' => Translator::getInstance()?->trans('SIRET', [], SiretManagement::DOMAIN_NAME),
                    'constraints' => $siretConstraints,
                    'required' => $siretRequired,
                    'label_attr' => [
                        'help' => Translator::getInstance()?->trans(
                            'Enter the customer SIRET (14 digits)'
                        ),
                    ],
                ]
            )
            ->add(
                SiretManagement::TVA_INTRA,
                TextType::class,
                [
                    'label' => Translator::getInstance()?->trans('Intra-Community VAT Number', [], SiretManagement::DOMAIN_NAME),
                    'constraints' => $vatConstraints,
                    'required' => $vatRequired,
                    'label_attr' => [
                        'help' => Translator::getInstance()?->trans(
                            'Enter the customer Intra-Community VAT Number'
                        ),
                    ],
                    // Targeted by the front live-check script (debounced VIES lookup + spinner),
                    // shared across every theme that uses this trait since it's set here rather
                    // than hardcoded per-template.
                    'attr' => [
                        'data-vat-input' => '',
                    ],
                ]
            )
        ;

        // The confirmation checkbox only makes sense when the VIES check can actually produce
        // a "not found" outcome for the customer to override: RegisterListener::checkVatNumber()
        // never reads it when the VIES check is disabled, and never honors it when the VAT
        // number is mandatory (a required VAT number must be confirmed by VIES, no override
        // possible). Adding it unconditionally would show a meaningless checkbox on every theme
        // that renders the form via form_rest() (Flexy), since there's no per-field template
        // control to hide it there the way the Smarty themes do.
        $vatApiCheckEnabled = (bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, null);

        if ($vatApiCheckEnabled && !$vatRequired) {
            $formBuilder->add(
                SiretManagement::VAT_NOT_FOUND_CONFIRMED,
                CheckboxType::class,
                [
                    'label' => Translator::getInstance()?->trans(
                        'I confirm this VAT number was not found and I still want to proceed',
                        [],
                        SiretManagement::DOMAIN_NAME
                    ),
                    'required' => false,
                    'attr' => [
                        'data-vat-not-found-confirm' => '',
                    ],
                ]
            );
        }

        $formBuilder
            ->get(SiretManagement::SIRET)
            ->addModelTransformer($removeSpacesTransformer);

        $formBuilder
            ->get(SiretManagement::TVA_INTRA)
            ->addModelTransformer($removeSpacesTransformer);

        // Flexy's CustomerUpdateForm/CustomerInformationsForm are bound to a plain array built by
        // the theme's own component (firstname/lastname/email/...), which never includes 'siret' or
        // 'tva_intra' since those fields don't exist on that form until we add them here. Without
        // this, the fields we just added would always render empty on /account, even for a
        // customer who already has stored values (see RegisterListener::getStoredSiret/TvaIntra).
        // PRE_SET_DATA only fires for the initial view render, never for a submitted POST, so it
        // can't clobber a value the customer is currently correcting after a validation error.
        $formBuilder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            if (!\array_key_exists(SiretManagement::SIRET, $data) && null !== $storedSiret = $this->getStoredSiret()) {
                $data[SiretManagement::SIRET] = $storedSiret;
            }

            if (!\array_key_exists(SiretManagement::TVA_INTRA, $data) && null !== $storedTvaIntra = $this->getStoredTvaIntra()) {
                $data[SiretManagement::TVA_INTRA] = $storedTvaIntra;
            }

            $event->setData($data);
        });
    }

    public function checkSiretInput($value, ExecutionContextInterface $context): void
    {
        $this->checkItem(
            SiretManagement::CHECK_SIRET_EVENT,
            Translator::getInstance()?->trans('SIRET', [], SiretManagement::DOMAIN_NAME),
            $value,
            $context
        );
    }

    public function checkVatInput($value, ExecutionContextInterface $context): void
    {
        $normalizedValue = preg_replace('/\s/', '', (string) $value);

        if ('' !== $normalizedValue && $normalizedValue === $this->getStoredTvaIntra()) {
            // Unchanged since the last accepted save: don't force the customer to re-tick
            // the VIES "not found" confirmation box just to edit an unrelated form field.
            return;
        }

        $rootData = $context->getRoot()->getData();
        $confirmed = (bool) ($rootData[SiretManagement::VAT_NOT_FOUND_CONFIRMED] ?? false);

        $this->checkItem(
            SiretManagement::CHECK_VAT_EVENT,
            Translator::getInstance()?->trans('Intra-Community VAT Number', [], SiretManagement::DOMAIN_NAME),
            $value,
            $context,
            $confirmed
        );
    }

    protected function getStoredSiret(): ?string
    {
        return null;
    }

    protected function getStoredTvaIntra(): ?string
    {
        return null;
    }

    protected function checkItem($eventName, $itemName, $value, ExecutionContextInterface $context, bool $notFoundConfirmed = false): void
    {
        if (empty(trim($value))) {
            return;
        }

        $event = (new CheckDataEvent($value))->setNotFoundConfirmed($notFoundConfirmed);

        $this->getDispatcher()->dispatch($event, $eventName);

        if (!$event->isValid()) {
            $context->addViolation(
                Translator::getInstance()?->trans(
                    'Failed to validate %item : %err',
                    [
                        '%err' => $event->getError(),
                        '%item' => $itemName
                    ],
                    SiretManagement::DOMAIN_NAME
                )
            );
        }
    }
}
