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

namespace SiretManagement\Hook;

use SiretManagement\Form\Configuration;
use SiretManagement\Form\CustomerForm;
use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\SiretManagement;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BackHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'customer.edit' => [
                ['type' => 'back', 'method' => 'onCustomerEdit'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(Configuration::getName());
        $event->add($this->render('SiretManagement/module_configuration.html.twig', [
            'form' => $form->createView()->getView(),
        ]));
    }

    public function onCustomerEdit(HookRenderEvent $event): void
    {
        $tva = $siret = '';

        if (null !== $siretInfo = SiretCustomerQuery::create()->findOneByCustomerId(
            $event->getArgument('customer_id')
        )) {
            $tva = $siretInfo->getCodeTvaIntra();
            $siret = $siretInfo->getCodeSiret();
        }

        $form = $this->formFactory->createForm(CustomerForm::getName());

        $event->add(
            $this->render(
                'SiretManagement/customer-edit.html.twig',
                [
                    'form' => $form->createView()->getView(),
                    'use_tva_intra' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_TVA_INTRA, true),
                    'use_siret' => (bool) SiretManagement::getConfigValue(SiretManagement::USE_SIRET, true),
                    'tva_intra' => $tva,
                    'siret' => $siret,
                    'customer_id' => $event->getArgument('customer_id'),
                ]
            )
        );
    }
}
