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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
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

        $siretRequired = (bool) SiretManagement::getConfigValue(SiretManagement::SIRET_REQUIRED, false);
        $siretConstraints = [
            new Callback([$this, 'checkSiretInput']),
        ];

        if ($siretRequired) {
            $siretConstraints[] = new NotBlank();
        }

        $vatRequired = (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, false);
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
                ]
            )
        ;

        $formBuilder
            ->get(SiretManagement::SIRET)
            ->addModelTransformer($removeSpacesTransformer);

        $formBuilder
            ->get(SiretManagement::TVA_INTRA)
            ->addModelTransformer($removeSpacesTransformer);
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
        $this->checkItem(
            SiretManagement::CHECK_VAT_EVENT,
            Translator::getInstance()?->trans('Intra-Community VAT Number', [], SiretManagement::DOMAIN_NAME),
            $value,
            $context
        );
    }

    protected function checkItem($eventName, $itemName, $value, ExecutionContextInterface $context): void
    {
        if (empty(trim($value))) {
            return;
        }

        $event = new CheckDataEvent($value);

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
