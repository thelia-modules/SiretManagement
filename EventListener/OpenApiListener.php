<?php

namespace SiretManagement\EventListener;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Events\ModelExtendDataEvent;
use OpenApi\Exception\OpenApiException;
use OpenApi\Model\Api\Error;
use OpenApi\Model\Api\ModelFactory;
use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\Service\IntraCommunityVatChecker;
use SiretManagement\Service\SiretAPIManagement;
use SiretManagement\Service\VatExistenceChecker;
use SiretManagement\SiretManagement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Event\CustomerEvent;

class OpenApiListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ModelFactory $modelFactory,
        private readonly RequestStack $requestStack,
        private readonly SiretAPIManagement $siretAPIManagement,
        private readonly IntraCommunityVatChecker  $intraCommunityVatChecker,
        private readonly VatExistenceChecker $vatExistenceChecker
    )
    {
    }

    #[Schema(
        schema: "SiretManagementExtendCustomer",
        properties: [
            new Property(
                property: "siretCustomer",
                ref: "#/components/schemas/SiretCustomer",
                type: "object"
            )
        ]
    )]
    public function addDataOnCustomer(ModelExtendDataEvent $event)
    {
        $siretCustomer = SiretCustomerQuery::create()->filterByCustomerId($event->getModel()->getId())->findOne();

        $companyData = $this->modelFactory->buildModel('SiretCustomer', $siretCustomer);

        if (!empty($companyData)) {
            $event->setExtendDataKeyValue('siretCustomer', $companyData);
        }
    }

    public function saveSiretCustomer(CustomerEvent $customerEvent)
    {
        $data = json_decode($this->requestStack->getCurrentRequest()->getContent(), true);

        if (!isset($data['customer']['siretCustomer'])) {
            return;
        }

        $siretCustomer = SiretCustomerQuery::create()
            ->filterByCustomerId($customerEvent->getModel()->getId())
            ->findOneOrCreate();

        $siretCustomerData = $data['customer']['siretCustomer'];

        $codeSiret = $siretCustomerData['codeSiret'] ?? null;
        if (null !== $codeSiret) {
            $this->siretAPIManagement->checkSiret($siretCustomerData['codeSiret']);
        }

        $codeTvaIntra = $siretCustomerData['codeTvaIntra'] ?? null;
        if (null !== $codeTvaIntra) {
            $this->intraCommunityVatChecker->check($codeTvaIntra);

            if ((bool) SiretManagement::getConfigValue(SiretManagement::VAT_API_CHECK_ENABLED, false)) {
                $notice = null;
                $vatRequired = (bool) SiretManagement::getConfigValue(SiretManagement::TVA_INTRA_REQUIRED, false);

                try {
                    $notice = $this->vatExistenceChecker->check($codeTvaIntra);
                } catch (\RuntimeException $exception) {
                    // Mirrors RegisterListener::checkVatNumber(): when the VAT number is
                    // optional, don't let a third-party outage block the save, just let the
                    // number through unconfirmed. When mandatory, surface a clean 400 instead
                    // of an unhandled 500, since VIES cannot confirm the input.
                    if ($vatRequired) {
                        /** @var Error $error */
                        $error = $this->modelFactory->buildModel('Error', [
                            'title' => Translator::getInstance()->trans('VAT number verification failed', [], SiretManagement::DOMAIN_NAME),
                            'description' => $exception->getMessage(),
                        ]);

                        throw new OpenApiException($error, 400);
                    }

                    $notice = null;
                }

                // Mirrors RegisterListener::checkVatNumber(): when the VAT number is
                // mandatory, VIES must confirm it exists, no override possible via the API.
                if (null !== $notice && $vatRequired) {
                    /** @var Error $error */
                    $error = $this->modelFactory->buildModel('Error', [
                        'title' => Translator::getInstance()->trans('VAT number verification failed', [], SiretManagement::DOMAIN_NAME),
                        'description' => $notice,
                    ]);

                    throw new OpenApiException($error, 400);
                }
            }
        }

        $siretCustomer->setCodeSiret($codeSiret)
            ->setCodeTvaIntra($codeTvaIntra)
            ->setDenominationUniteLegale($data['customer']['siretCustomer']['denominationUniteLegale'] ?? null)
            ->save();
    }

    public static function getSubscribedEvents()
    {
        $events = [];
        if (class_exists('OpenApi\Events\ModelExtendDataEvent')){
            $events[CustomerEvent::POST_SAVE] = ['saveSiretCustomer',0];
            $events[ModelExtendDataEvent::ADD_EXTEND_DATA_PREFIX.'customer'] = ['addDataOnCustomer',0];
        }

        return $events;
    }
}