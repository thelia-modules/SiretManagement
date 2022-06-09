<?php

namespace SiretManagement\Form;

use SiretManagement\SiretManagement;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class Configuration extends BaseForm
{

    protected function buildForm()
    {
        $form = $this->formBuilder;

        $valuePublicConsumer = SiretManagement::getConfigValue('public_consumer', null);
        $valuePrivateConsumer = SiretManagement::getConfigValue('private_consumer', null);
        $lang = $this->getRequest()->getSession()->get('thelia.admin.edition.lang');
        $form->add(
            'public_consumer',
            TextType::class,
            [
                'data' => $valuePublicConsumer,
                'label' => Translator::getInstance()->trans("Consumer public key", [], SiretManagement::DOMAIN_NAME,$lang->getLocale()),
                'label_attr' => array(
                    'for' => "public_consumer"
                ),
            ]
        )
            ->add(
                'private_consumer',
                TextType::class,
                [
                    'data' => $valuePrivateConsumer,
                    'label' => Translator::getInstance()->trans("Consumer private key", [], SiretManagement::DOMAIN_NAME),
                    'label_attr' => array(
                        'for' => "private_consumer"
                    ),
                ]
            );
    }

    public static function getName()
    {
        return 'siretmanagement';
    }
}