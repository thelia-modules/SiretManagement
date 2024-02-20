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

namespace SiretManagement\EventListener;

use Psr\EventDispatcher\EventDispatcherInterface;
use SiretManagement\Event\CheckDataEvent;
use SiretManagement\Form\CustomerFormManagementTrait;
use SiretManagement\Model\SiretCustomer;
use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\Service\IntraCommunityVatChecker;
use SiretManagement\Service\SiretAPIManagement;
use SiretManagement\SiretManagement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerProfileUpdateForm;

class RegisterListener implements EventSubscriberInterface
{
    use CustomerFormManagementTrait;

    public function __construct(
        protected RequestStack $requestStack,
        protected SiretAPIManagement $siretAPIManagement,
        protected IntraCommunityVatChecker $intraCommunityVatChecker,
        protected EventDispatcherInterface $dispatcher
    ) {
    }

    protected function getDispatcher()
    {
        return $this->dispatcher;
    }
    public function addSiretFieldsToCustomerForm(TheliaFormEvent $event): void
    {
        if ($this->requestStack->getCurrentRequest()?->fromAdmin() === true) {
            return;
        }

        $this->setupCustomerForm($event->getForm()->getFormBuilder());
    }

    public function createCustomer(CustomerCreateOrUpdateEvent $event): void
    {
        $this->saveCustomerData(
            $this->requestStack->getCurrentRequest()->get(CustomerCreateForm::getName())[SiretManagement::SIRET] ?? '',
            $this->requestStack->getCurrentRequest()->get(CustomerCreateForm::getName())[SiretManagement::TVA_INTRA] ?? '',
            $event
        );
    }

    public function updateCustomer(CustomerCreateOrUpdateEvent $event): void
    {
        $this->saveCustomerData(
            $this->requestStack->getCurrentRequest()->get(CustomerProfileUpdateForm::getName())[SiretManagement::SIRET] ?? '',
            $this->requestStack->getCurrentRequest()->get(CustomerProfileUpdateForm::getName())[SiretManagement::TVA_INTRA] ?? '',
            $event
        );
    }

    /**
     * @throws \Exception
     */
    protected function saveCustomerData(string $siret, string $vatNumber, CustomerCreateOrUpdateEvent $event): void
    {
        $customerId = $event->getCustomer()?->getId();

        if (null === $siretCustomer = SiretCustomerQuery::create()->filterByCustomerId($customerId)->findOne()) {
            $siretCustomer = (new SiretCustomer())->setCustomerId($customerId);
        }

        $siretCustomer
            ->setCodeSiret($siret)
            ->setCodeTvaIntra($vatNumber)
            ->setDenominationUniteLegale($event->getCompany())
            ->save();
    }

    /**
     * @throws \Exception
     */
    public function checkSiret(CheckDataEvent $event): void
    {
        try {
            $event->setData(
                $this->siretAPIManagement->checkSiret($event->getDataToCheck())
            );
        } catch (\Exception $ex) {
            $event->setError($ex->getMessage());
        }
    }

    public function checkVatNumber(CheckDataEvent $event): void
    {
        try {
            $event->setData(
                $this->intraCommunityVatChecker->check($event->getDataToCheck())
            );
        } catch (\Exception $ex) {
            $event->setError($ex->getMessage());
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_customer_create" => ['addSiretFieldsToCustomerForm', 1],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_customer_profile_update" => ['addSiretFieldsToCustomerForm', 1],

            TheliaEvents::CUSTOMER_UPDATEPROFILE => ['updateCustomer', 50],
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['createCustomer', 50],

            SiretManagement::CHECK_SIRET_EVENT => ['checkSiret', 128],
            SiretManagement::CHECK_VAT_EVENT => ['checkVatNumber', 128],
        ];
    }
}
