<?php

namespace SiretManagement\EventListeners;

use SiretManagement\Model\SiretCustomer;
use SiretManagement\Service\SiretAPIManagement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Form\CustomerCreateForm;

class RegisterListener implements EventSubscriberInterface
{
    protected $request;
    protected $siretAPIManagement;

    public function __construct (RequestStack $requestStack,SiretAPIManagement $siretAPIManagement){
        $this->request = $requestStack->getCurrentRequest();
        $this->siretAPIManagement = $siretAPIManagement;
    }

    public function AddFormCustomer(TheliaFormEvent $event)
    {
        $form = $event->getForm()->getFormBuilder();

        if ($this->request->fromAdmin() === true) {
            return;
        }

        $form
            ->add(
                "siret",
                HiddenType::class,
                [
                    'required' => true,
                    "constraints"=> [ new NotBlank() ],
                    'attr' => [
                        'id'=>'siret_field'
                    ]
                ],
            )
        ;
    }

    /**
     * @throws \Exception
     */
    public function saveSiretCustomer(CustomerCreateOrUpdateEvent $event){
        $siret = $this->request->get(CustomerCreateForm::getName())['siret'];
        $denomination = $event->getCompany();
        $customerId = $event->getCustomer()->getId();

        $siretCustomer = new SiretCustomer();
        $siretCustomer->setCodeSiret($siret);
        $siretCustomer->setCustomerId($customerId);
        $siretCustomer->setDenominationUniteLegale($denomination);
        $siretCustomer->save();
    }

    /**
     * @throws \Exception
     */
    public function checkSiret(CustomerCreateOrUpdateEvent $event){
        $siret = $this->request->get(CustomerCreateForm::getName())['siret'];
        $this->siretAPIManagement->checkSiret($siret);
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::FORM_BEFORE_BUILD . ".thelia_customer_create" => ['AddFormCustomer', 1],
            TheliaEvents::CUSTOMER_CREATEACCOUNT => [
                ['saveSiretCustomer', 1],
                ['checkSiret', 1000]
            ],
        ];
    }
}