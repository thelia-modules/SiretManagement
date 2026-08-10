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
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerProfileUpdateForm;
use Thelia\Model\Customer;

class RegisterListener implements EventSubscriberInterface
{
    use CustomerFormManagementTrait;

    /**
     * Flexy has no equivalent of the legacy thelia_customer_create/thelia_customer_profile_update
     * forms, and uses two distinct forms of its own for the SIRET/VAT fields: the post-registration
     * step (FlexyBundle\Form\CustomerInformationsForm, address/preferences) and the /account "My
     * contact information" edit (FlexyBundle\Form\CustomerUpdateForm, firstname/lastname/email only
     * otherwise). Both are referenced by string, not by class, so this module doesn't need a hard
     * dependency on thelia/flexy.
     */
    private const FLEXY_INFORMATIONS_FORM_NAME = 'flexybundle_form_customer_informations_form';
    private const FLEXY_UPDATE_FORM_NAME = 'flexybundle_form_customer_update_form';

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

    protected function getStoredSiret(): ?string
    {
        return $this->getStoredSiretCustomer()?->getCodeSiret();
    }

    protected function getStoredTvaIntra(): ?string
    {
        return $this->getStoredSiretCustomer()?->getCodeTvaIntra();
    }

    private function getStoredSiretCustomer(): ?SiretCustomer
    {
        $customerId = $this->securityContext->getCustomerUser()?->getId();
        if (null === $customerId) {
            return null;
        }

        return SiretCustomerQuery::create()->filterByCustomerId($customerId)->findOne();
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
        $data = $this->getPostedFormData([CustomerCreateForm::getName(), self::FLEXY_INFORMATIONS_FORM_NAME]);

        $this->saveCustomerData(
            $data[SiretManagement::SIRET] ?? '',
            $data[SiretManagement::TVA_INTRA] ?? '',
            $event->getCustomer(),
            $event->getCompany()
        );
    }

    public function updateCustomer(CustomerCreateOrUpdateEvent $event): void
    {
        $data = $this->getPostedFormData([
            CustomerProfileUpdateForm::getName(),
            self::FLEXY_INFORMATIONS_FORM_NAME,
            self::FLEXY_UPDATE_FORM_NAME,
        ]);

        $this->saveCustomerData(
            $data[SiretManagement::SIRET] ?? '',
            $data[SiretManagement::TVA_INTRA] ?? '',
            $event->getCustomer(),
            $event->getCompany()
        );
    }

    /**
     * Flexy's own registration flow (CustomerController::informationsCreate(), templates/frontOffice/
     * flexy/src/Controller/CustomerController.php) validates flexybundle_form_customer_informations_form
     * — siret/tva_intra included — but only ever dispatches ADDRESS_CREATE via AddressService::
     * createAddress(), never CUSTOMER_CREATEACCOUNT. That's the only Thelia event fired at that step,
     * so it's the only hook available from this module to persist the submitted data, without touching
     * the Flexy theme's own controller.
     *
     * ADDRESS_CREATE is generic though: it also fires for /account/addresses/new, checkout addresses,
     * etc. getPostedFormData() returning [] for any of those (the request simply won't carry our form's
     * name) is what keeps this safe — an unrelated address creation must never overwrite an already
     * saved SIRET/VAT with empty strings.
     */
    public function saveSiretDataFromAddressCreation(AddressCreateOrUpdateEvent $event): void
    {
        $data = $this->getPostedFormData([self::FLEXY_INFORMATIONS_FORM_NAME]);

        if ([] === $data) {
            return;
        }

        $this->saveCustomerData(
            $data[SiretManagement::SIRET] ?? '',
            $data[SiretManagement::TVA_INTRA] ?? '',
            $event->getCustomer(),
            $event->getCompany()
        );
    }

    /**
     * Reads the submitted form data, trying each candidate form name in turn: the legacy
     * Smarty themes and the Flexy theme post the same siret/tva_intra fields under different
     * form names (Flexy has no equivalent of thelia_customer_create/thelia_customer_profile_update,
     * see self::FLEXY_INFORMATIONS_FORM_NAME), so whichever theme handled the request, this
     * returns the first candidate that actually has data.
     *
     * $request->query and $request->request are InputBag (not the plain ParameterBag $request->
     * attributes is): InputBag::get() throws a BadRequestException as soon as the stored value is
     * an array instead of a scalar, which a submitted form's fields always are. all() is the
     * array-safe equivalent and simply returns [] when the key is absent.
     *
     * @param string[] $candidateFormNames
     */
    protected function getPostedFormData(array $candidateFormNames): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return [];
        }

        foreach ($candidateFormNames as $formName) {
            $data = $request->attributes->get($formName);

            if (\is_array($data)) {
                return $data;
            }

            if ([] !== $data = $request->query->all($formName)) {
                return $data;
            }

            if ([] !== $data = $request->request->all($formName)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * @throws \Exception
     */
    protected function saveCustomerData(string $siret, string $vatNumber, ?Customer $customer, ?string $company): void
    {
        $customerId = $customer?->getId();

        if (null === $siretCustomer = SiretCustomerQuery::create()->filterByCustomerId($customerId)->findOne()) {
            $siretCustomer = (new SiretCustomer())->setCustomerId($customerId);
        }

        $siretCustomer
            ->setCodeSiret($siret)
            ->setCodeTvaIntra($vatNumber)
            ->setDenominationUniteLegale($company)
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

            if (!(bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, null)) {
                return;
            }

            // The back-office customer-edit form has no confirmation checkbox: an admin
            // editing a customer's VAT number directly is trusted and must never be
            // permanently blocked by a VIES "not found"/outage result they have no UI to
            // acknowledge. Only the front self-service forms enforce the checkbox/required rule.
            $isAdminRequest = $this->requestStack->getCurrentRequest()?->fromAdmin() === true;

            $vatRequired = !$isAdminRequest && (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, null);

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
                    // Not logging $vatNumber itself: IntraCommunityVatChecker::check() doesn't
                    // guarantee a normalized [country code][digits] shape to extract a safe
                    // prefix from, and a VAT number identifies a business that shouldn't
                    // accumulate in clear text in centralized logging either way.
                    $this->logger->warning('Admin saved a VAT number not found by VIES');
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

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_customer_create" => ['addSiretFieldsToCustomerForm', 1],
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_customer_profile_update" => ['addSiretFieldsToCustomerForm', 1],
            // Flexy renders unlisted fields via form_rest(), in form-builder insertion order: adding
            // our fields on FORM_BEFORE_BUILD (i.e. before the form's own buildForm() runs) put them
            // ahead of firstname/lastname/email instead of after. FORM_AFTER_BUILD fires once the
            // form's own fields already exist, so ours get appended at the end. The legacy Smarty
            // templates above render every field explicitly by name, so their event doesn't matter.
            TheliaEvents::FORM_AFTER_BUILD . '.' . self::FLEXY_INFORMATIONS_FORM_NAME => ['addSiretFieldsToCustomerForm', 1],
            TheliaEvents::FORM_AFTER_BUILD . '.' . self::FLEXY_UPDATE_FORM_NAME => ['addSiretFieldsToCustomerForm', 1],

            TheliaEvents::CUSTOMER_UPDATEPROFILE => ['updateCustomer', 50],
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['createCustomer', 50],
            // See saveSiretDataFromAddressCreation() docblock: Flexy's registration flow never
            // dispatches CUSTOMER_CREATEACCOUNT, only ADDRESS_CREATE.
            TheliaEvents::ADDRESS_CREATE => ['saveSiretDataFromAddressCreation', 50],

            SiretManagement::CHECK_SIRET_EVENT => ['checkSiret', 128],
            SiretManagement::CHECK_VAT_EVENT => ['checkVatNumber', 128],
        ];
    }
}
