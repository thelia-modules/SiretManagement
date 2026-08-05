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
use Psr\Log\LoggerInterface;
use SiretManagement\Event\CheckDataEvent;
use SiretManagement\Form\CustomerFormManagementTrait;
use SiretManagement\Model\SiretCustomer;
use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\Service\IntraCommunityVatChecker;
use SiretManagement\Service\SiretAPIManagement;
use SiretManagement\Service\VatExistenceChecker;
use SiretManagement\SiretManagement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerProfileUpdateForm;

class RegisterListener implements EventSubscriberInterface
{
    use CustomerFormManagementTrait;

    public function __construct(
        protected RequestStack $requestStack,
        protected SiretAPIManagement $siretAPIManagement,
        protected IntraCommunityVatChecker $intraCommunityVatChecker,
        protected VatExistenceChecker $vatExistenceChecker,
        protected EventDispatcherInterface $dispatcher,
        protected LoggerInterface $logger,
        protected SecurityContext $securityContext
    ) {
    }

    protected function getStoredTvaIntra(): ?string
    {
        $customerId = $this->securityContext->getCustomerUser()?->getId();
        if (null === $customerId) {
            return null;
        }

        return SiretCustomerQuery::create()->filterByCustomerId($customerId)->findOne()?->getCodeTvaIntra();
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
            $vatNumber = $this->intraCommunityVatChecker->check($event->getDataToCheck());
            $event->setData($vatNumber);

            if (!(bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, false)) {
                return;
            }

            // The back-office customer-edit form has no confirmation checkbox: an admin
            // editing a customer's VAT number directly is trusted and must never be
            // permanently blocked by a VIES "not found"/outage result they have no UI to
            // acknowledge. Only the front self-service forms enforce the checkbox/required rule.
            $isAdminRequest = $this->requestStack->getCurrentRequest()?->fromAdmin() === true;

            $vatRequired = !$isAdminRequest && (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, false);

            try {
                $notice = $this->vatExistenceChecker->check($vatNumber);
            } catch (\RuntimeException $ex) {
                if ($vatRequired) {
                    // VAT is mandatory: VIES must confirm the number exists, so an outage
                    // must block since we cannot verify the input.
                    $event->setError($ex->getMessage());
                } else {
                    // VAT is optional (or admin context): don't let a third-party outage
                    // block registration/profile updates, just log it and let the number
                    // through unconfirmed.
                    $this->logger->warning('VIES unavailable while checking optional VAT number', ['exception' => $ex]);
                }

                return;
            }

            if (null === $notice || $isAdminRequest) {
                if ($isAdminRequest && null !== $notice) {
                    $this->logger->warning('Admin saved a VAT number not found by VIES', ['vatNumber' => $vatNumber]);
                }

                return;
            }

            if ($vatRequired) {
                // VAT is mandatory: VIES must confirm the number exists, no override possible.
                $event->setError(Translator::getInstance()?->trans(
                    'not found',
                    [],
                    SiretManagement::DOMAIN_NAME
                ));

                return;
            }

            if (!$event->isNotFoundConfirmed()) {
                // Non-blocking by design (VatExistenceChecker::check), but the user must
                // explicitly acknowledge it via the confirmation checkbox before we accept it.
                $event->setError(Translator::getInstance()?->trans(
                    'This VAT number was not found. Please check the confirmation box to proceed anyway.',
                    [],
                    SiretManagement::DOMAIN_NAME
                ));
            }
        } catch (\InvalidArgumentException $ex) {
            // Expected business error: invalid format (IntraCommunityVatChecker).
            // Surfaced as a form validation error.
            $event->setError($ex->getMessage());
        } catch (\Throwable $ex) {
            // Anything else is a real bug, not a user input problem: log the full detail
            // distinctly, but don't leak internals (e.g. TypeError) to the client.
            $this->logger->error('Unexpected error while checking VAT number', ['exception' => $ex]);
            $event->setError(Translator::getInstance()?->trans(
                'An unexpected error occurred while validating this field.',
                [],
                SiretManagement::DOMAIN_NAME
            ));
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
