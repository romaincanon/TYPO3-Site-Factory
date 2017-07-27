<?php
/*
 * 2016 Romain CANON <romain.hydrocanon@gmail.com>
 *
 * This file is part of the TYPO3 Site Factory project.
 * It is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License, either
 * version 3 of the License, or any later version.
 *
 * For the full copyright and license information, see:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Romm\SiteFactory\Utility;

use Romm\SiteFactory\Core\CacheManager;
use Romm\SiteFactory\Core\Core;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Handles the TypoScript configuration's construction of the extension.
 */
class TypoScriptUtility
{

    const EXTENSION_CONFIGURATION_PATH = 'module.tx_sitefactory';

    /**
     * Storage for the pages' configuration.
     *
     * @var TemplateService[]
     */
    private static $pageConfiguration = [];

    /**
     * Storage for the pages' TypoScript configuration arrays.
     *
     * @var    array
     */
    private static $pageTypoScriptConfiguration = [];

    /**
     * Storage for the pages' TypoScript constants arrays.
     *
     * @var    array
     */
    private static $pageTypoScriptConstants = [];

    /**
     * Calls the function "getConfigurationFromPath", but uses the Site Factory
     * configuration path as root path.
     *
     * @param    string        $path      The path to the configuration value.
     * @param    int|null|bool $pageUid   The uid of the page you want the TypoScript configuration from. If "null" is given, only the static configuration is returned.
     * @param    string        $delimiter The delimiter for the path. Default is ".".
     * @return    mixed|null
     */
    public static function getExtensionConfigurationFromPath($path, $pageUid = null, $delimiter = '.')
    {
        return self::getConfigurationFromPath(self::EXTENSION_CONFIGURATION_PATH . '.' . $path, $pageUid, $delimiter);
    }

    /**
     * Returns the TypoScript configuration value at a the given path.
     * Example: config.tx_myext.some_conf
     *
     * @param    string        $path      The path to the configuration value.
     * @param    int|null|bool $pageUid   The uid of the page you want the TypoScript configuration from. If "null" is given, only the static configuration is returned.
     * @param    string        $delimiter The delimiter for the path. Default is ".".
     * @return    mixed|null
     */
    public static function getConfigurationFromPath($path, $pageUid = null, $delimiter = '.')
    {
        $result = null;
        $cacheIdentifier = md5($path . (string)$pageUid);

        $cacheInstance = CacheManager::getCacheInstance(CacheManager::CACHE_MAIN);
        if ($cacheInstance) {
            if ($cacheInstance->has($cacheIdentifier)) {
                $result = $cacheInstance->get($cacheIdentifier);
            } elseif (ArrayUtility::isValidPath(self::getTypoScriptConfiguration($pageUid), $path, $delimiter)) {
                $result = ArrayUtility::getValueByPath(self::getTypoScriptConfiguration($pageUid), $path, $delimiter);
                $cacheInstance->set($cacheIdentifier, $result);
            }
        }

        return $result;
    }

    /**
     * Returns the TypoScript configuration, including the static configuration
     * from files (see function "getExtensionConfiguration").
     *
     * As this function does not save the configuration in cache, we advise not
     * to call it, and prefer using the function "getConfigurationFromPath"
     * instead, which has its own caching system.
     * It can still be useful to get the whole TypoScript configuration, so the
     * function remains public, but use with caution!
     *
     * @param    int|null|bool $pageUid The uid of the page you want the TypoScript configuration from. If "null" is given, only the static configuration is returned.
     * @return    array                        The configuration.
     */
    public static function getTypoScriptConfiguration($pageUid = null)
    {
        if (!array_key_exists($pageUid, self::$pageTypoScriptConfiguration)) {
            $configuration = self::generateConfiguration($pageUid);

            /** @var TypoScriptService $typoScriptService */
            $typoScriptService = Core::getObjectManager()->get(TypoScriptService::class);
            self::$pageTypoScriptConfiguration[$pageUid] = $typoScriptService->convertTypoScriptArrayToPlainArray($configuration->setup);
        }

        return self::$pageTypoScriptConfiguration[$pageUid];
    }

    /**
     * Returns the TypoScript constants at a given path.
     *
     * @param    int|null|bool $pageUid The uid of the page you want the TypoScript constants from. If "null" is given, only the static constants is returned.
     * @return    array
     */
    public static function getTypoScriptConstants($pageUid = null)
    {
        if (!array_key_exists($pageUid, self::$pageTypoScriptConstants)) {
            $configuration = self::generateConfiguration($pageUid);
            /** @var TypoScriptService $typoScriptService */
            $typoScriptService = Core::getObjectManager()->get(TypoScriptService::class);
            self::$pageTypoScriptConstants[$pageUid] = $typoScriptService->convertTypoScriptArrayToPlainArray($configuration->setup_constants);
        }

        return self::$pageTypoScriptConstants[$pageUid];
    }

    /**
     * Generates a TemplateService from a given page uid, by running through
     * the pages root line.
     *
     * @param    int|null|bool $pageUid The uid of the page you want the TypoScript configuration from. If "null" is given, only the static configuration is returned.
     * @return    TemplateService
     */
    private static function generateConfiguration($pageUid = null)
    {
        if (!array_key_exists($pageUid, self::$pageConfiguration)) {
            $objectManager = Core::getObjectManager();

            $rootLine = null;
            if ($pageUid && MathUtility::canBeInterpretedAsInteger($pageUid) && $pageUid > 0) {
                /** @var PageRepository $pageRepository */
                $pageRepository = $objectManager->get(PageRepository::class);
                $rootLine = $pageRepository->getRootLine($pageUid);
            }

            /** @var ExtendedTemplateService $templateService */
            $templateService = $objectManager->get(ExtendedTemplateService::class);

            $templateService->tt_track = 0;
            $templateService->init();
            if ($rootLine !== null) {
                $templateService->runThroughTemplates($rootLine);
            }
            $templateService->generateConfig();
            $templateService->generateConfig_constants();

            self::$pageConfiguration[$pageUid] = $templateService;
        }

        return self::$pageConfiguration[$pageUid];
    }
}
