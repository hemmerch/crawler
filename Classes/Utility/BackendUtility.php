<?php
namespace AOE\Crawler\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use AOE\Crawler\Backend\BackendModule;
use AOE\Crawler\ClickMenu\CrawlerClickMenu;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BackendUtility
 *
 * @codeCoverageIgnore
 */
class BackendUtility
{
    /**
     * Returns records from table, $theTable, where a field ($theField) equals the value, $theValue
     * The records are returned in an array
     * If no records were selected, the function returns nothing
     *
     * @param string $theTable Table name present in $GLOBALS['TCA']
     * @param string $theField Field to select on
     * @param string $theValue Value that $theField must match
     * @param string $whereClause Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
     * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
     * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
     * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
     * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
     * @param QueryBuilder|null $queryBuilder The queryBuilder must be provided, if the parameter $whereClause is given and the concept of prepared statement was used. Example within self::firstDomainRecord()
     * @return mixed Multidimensional array with selected records (if any is selected)
     */
    public static function getRecordsByField(
        $theTable,
        $theField,
        $theValue,
        $whereClause = '',
        $groupBy = '',
        $orderBy = '',
        $limit = '',
        $useDeleteClause = true,
        $queryBuilder = null
    ) {
        if (is_array($GLOBALS['TCA'][$theTable])) {
            if (null === $queryBuilder) {
                $queryBuilder = static::getQueryBuilderForTable($theTable);
            }

            // Remove deleted records from the query result
            if ($useDeleteClause) {
                $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            }

            // build fields to select
            $queryBuilder
                ->select('*')
                ->from($theTable)
                ->where($queryBuilder->expr()->eq($theField, $queryBuilder->createNamedParameter($theValue)));

            // additional where
            if ($whereClause) {
                $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($whereClause));
            }

            // group by
            if ($groupBy !== '') {
                $queryBuilder->groupBy(QueryHelper::parseGroupBy($groupBy));
            }

            // order by
            if ($orderBy !== '') {
                foreach (QueryHelper::parseOrderBy($orderBy) as $orderPair) {
                    list($fieldName, $order) = $orderPair;
                    $queryBuilder->addOrderBy($fieldName, $order);
                }
            }

            // limit
            if ($limit !== '') {
                if (strpos($limit, ',')) {
                    $limitOffsetAndMax = GeneralUtility::intExplode(',', $limit);
                    $queryBuilder->setFirstResult((int)$limitOffsetAndMax[0]);
                    $queryBuilder->setMaxResults((int)$limitOffsetAndMax[1]);
                } else {
                    $queryBuilder->setMaxResults((int)$limit);
                }
            }

            $rows = $queryBuilder->execute()->fetchAll();
            return $rows;
        }
        return null;
    }

    /**
     * @param string $table
     * @return QueryBuilder
     */
    protected static function getQueryBuilderForTable($table)
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }

    /**
     * Registers the crawler info module function
     *
     * @return void
     */
    public static function registerInfoModuleFunction()
    {
        ExtensionManagementUtility::insertModuleFunction(
            'web_info',
            BackendModule::class,
            null,
            'LLL:EXT:crawler/Resources/Private/Language/Backend.xlf:moduleFunction.tx_crawler_modfunc1'
        );
    }

    /**
     * Registers the crawler click menu item
     *
     * @return void
     */
    public static function registerClickMenuItem()
    {
        $GLOBALS['TBE_MODULES_EXT']['xMOD_alt_clickmenu']['extendCMclasses'][] = [
            'name' => CrawlerClickMenu::class
        ];
    }

    /**
     * Registers the context sensitive help for TCA fields
     *
     * @return void
     */
    public static function registerContextSensitiveHelpForTcaFields()
    {
        ExtensionManagementUtility::addLLrefForTCAdescr(
            'tx_crawler_configuration',
            'EXT:crawler/Resources/Private/Language/locallang_csh_tx_crawler_configuration.xlf'
        );
    }

    /**
     * Registers icons for use in the IconFactory
     *
     * @return void
     */
    public static function registerIcons()
    {
        self::registerStartIcon();
        self::registerStopIcon();
    }

    /**
     * Register Start Icon
     *
     * @return void
     */
    private static function registerStartIcon()
    {
        /** @var IconRegistry $iconRegistry */
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $iconRegistry->registerIcon(
            'tx-crawler-start',
            SvgIconProvider::class,
            ['source' => 'EXT:crawler/Resources/Public/Icons/crawler_start.svg']
        );
    }

    /**
     * Register Stop Icon
     *
     * @return void
     */
    private static function registerStopIcon()
    {
        /** @var IconRegistry $iconRegistry */
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $iconRegistry->registerIcon(
            'tx-crawler-stop',
            SvgIconProvider::class,
            ['source' => 'EXT:crawler/Resources/Public/Icons/crawler_stop.svg']
        );
    }
}
