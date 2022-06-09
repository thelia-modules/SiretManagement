<?php

namespace SiretManagement;

use Propel\Runtime\Connection\ConnectionInterface;
use SireneCustomerManagement\Model\SirenneCustomerQuery;
use SiretManagement\Model\SiretCustomerQuery;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class SiretManagement extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'siretmanagement';

    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */
    public function getHooks()
    {
        return [
            [
                "type" => TemplateDefinition::FRONT_OFFICE,
                "code" => "siret.js",
                "title" => [
                    "en_US" => "siret js",
                    "fr_FR" => "Js pour siret",
                ],
                "block" => false,
                "active" => true,
            ],
            [
                "type" => TemplateDefinition::FRONT_OFFICE,
                "code" => "siret.check",
                "title" => [
                    "en_US" => "siret check hook",
                    "fr_FR" => "siret check hook",
                ],
                "block" => false,
                "active" => true,
            ],
        ];
    }

    public function postActivation(ConnectionInterface $con = null): void
    {
        try {
            $clubCustomer = SiretCustomerQuery::create()->find();

        } catch (\Exception $e) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . "/Config/update/0.0.1.sql"]);
        }
    }

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode() . '\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()) . "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__ . DS . 'Config' . DS . 'update');

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
