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

namespace SiretManagement;

use Propel\Runtime\Connection\ConnectionInterface;
use SiretManagement\Model\SiretCustomerQuery;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class SiretManagement extends BaseModule
{
    /** @var string */
    public const DOMAIN_NAME = 'siretmanagement';

    public const PRIVATE_CONSUMER = 'private_consumer';
    public const PUBLIC_CONSUMER = 'public_consumer';
    public const SIRET_REQUIRED = 'siret_required';
    public const TVA_INTRA_REQUIRED = 'tva_intra_required';
    public const API_CHECK_DISABLED = 'api_check_disabled';

    public const SIRET = 'siret';
    public const TVA_INTRA = 'tva_intra';
    const USE_SIRET = 'use_siret';
    const USE_TVA_INTRA= 'use_tva_intra';

    public const CHECK_SIRET_EVENT = 'SiretManagement.CHECK_SIRET_EVENT';
    public const CHECK_VAT_EVENT = 'SiretManagement.CHECK_VAT_EVENT';

    public function getHooks()
    {
        return [
            [
                'type' => TemplateDefinition::FRONT_OFFICE,
                'code' => 'siret.js',
                'title' => [
                    'en_US' => 'siret js',
                    'fr_FR' => 'Js pour siret',
                ],
                'block' => false,
                'active' => true,
            ],
            [
                'type' => TemplateDefinition::FRONT_OFFICE,
                'code' => 'siret.check',
                'title' => [
                    'en_US' => 'siret check hook',
                    'fr_FR' => 'siret check hook',
                ],
                'block' => false,
                'active' => true,
            ],
        ];
    }

    public function postActivation(ConnectionInterface $con = null): void
    {
        try {
            SiretCustomerQuery::create()->find();
        } catch (\Exception $e) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);
        }
    }

    /**
     * Defines how services are loaded in your modules.
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR.ucfirst(self::getModuleCode()).'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface|null $con
     * @return void
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__.DS.'Config'.DS.'update');

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
