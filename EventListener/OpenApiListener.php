<?php

namespace SiretManagement\EventListener;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Events\ModelExtendDataEvent;
use OpenApi\Model\Api\ModelFactory;
use SiretManagement\Model\SiretCustomerQuery;
use SiretManagement\Service\IntraCommunityVatChecker;
use SiretManagement\Service\SiretAPIManagement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Event\CustomerEvent;

class OpenApiListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ModelFactory $modelFactory,
        private readonly RequestStack $requestStack,
        private readonly SiretAPIManagement $siretAPIManagement,
        private readonly IntraCommunityVatChecker  $intraCommunityVatChecker
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
            $this->intraCommunityVatChecker->check($siretCustomerData['codeTvaIntra']);
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