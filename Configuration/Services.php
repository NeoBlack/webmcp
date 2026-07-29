<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Neoblack\Webmcp\Form\FormSchemaBuilder;
use Neoblack\Webmcp\Form\RegisterWebMcpForm;
use Neoblack\Webmcp\Form\WebMcpAwareFormPersistenceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManager;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Register the two services that reference EXT:form classes at container-build
 * time — the form-load event listener and the persistence-manager decorator —
 * only when EXT:form is installed. Everything else lives in Services.yaml and is
 * safe without EXT:form (no form types in constructors/signatures), so the
 * extension stays installable when the (suggested) form framework is absent.
 *
 * Presence is probed via class_exists, not ExtensionManagementUtility::isLoaded:
 * this file runs while the container is (re)built, which happens before the
 * package manager is pushed into ExtensionManagementUtility — so isLoaded() would
 * dereference an uninitialized static and fatally error on a cold container cache.
 * The decorator additionally ignores an invalid reference, so a build where the
 * form class autoloads but EXT:form is not an active package (e.g. functional
 * tests that pull it in via require-dev) does not fail to compile.
 */
return static function (ContainerConfigurator $configurator): void {
    if (!class_exists(FormPersistenceManager::class)) {
        return;
    }

    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Autoconfigure applies the #[AsEventListener] attribute.
    $services->set(RegisterWebMcpForm::class);

    $services->set(WebMcpAwareFormPersistenceManager::class)
        ->decorate(FormPersistenceManager::class, null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([service('.inner'), service(FormSchemaBuilder::class)]);
};
