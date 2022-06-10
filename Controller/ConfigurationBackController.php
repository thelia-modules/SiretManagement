<?php

namespace SiretManagement\Controller;

use Exception;
use SiretManagement\Form\Configuration;
use SiretManagement\SiretManagement;
use Thelia\Controller\Admin\BaseAdminController;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;


class ConfigurationBackController extends BaseAdminController
{
    /**
     * @Route("/admin/module/siret/configuration/save", name="_cofiguration_siret", methods="POST")
     */
    public function saveAction(Session $session)
    {

        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('siretmanagement'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(Configuration::class);
        $response = null;

        try {
            $vform = $this->validateForm($form);
            $data = $vform->getData();
            $valuePublicConsumer = $data['public_consumer'];
            $valuePrivateConsumer = $data['private_consumer'];

            SiretManagement::setConfigValue('public_consumer', $valuePublicConsumer);
            SiretManagement::setConfigValue('private_consumer', $valuePrivateConsumer);
        } catch (Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("Syntax error"),
                $e->getMessage(),
                $form,
                $e
            );
        }

        return $this->generateSuccessRedirect($form);
    }

}