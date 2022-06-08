<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Setup\Operation;

/**
 * Install schema setup operation
 */
class InstallSchema extends \Ambros\Common\Setup\Operation\CompoundOperation
{
    
    /**
     * Constructor
     * 
     * @param \Ambros\Common\Setup\Operation\OperationFactory $operationFactory
     * @param array $operations
     * @return void
     */
    public function __construct(
        \Ambros\Common\Setup\Operation\OperationFactory $operationFactory,
        array $operations = [
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 10,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_entity_tier_price',
                    'tableName' => 'ambros_store__catalog_product_entity_tier_price',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 20,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_cfg_opt_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_cfg_opt_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 30,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_cfg_opt_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_cfg_opt_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 40,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 50,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_opt_agr_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_agr_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 60,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_opt_agr_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_agr_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 70,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_opt_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 80,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_opt_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 90,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_replica',
                    'tableName' => 'ambros_store__catalog_product_index_price_replica',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 100,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price',
                    'tableName' => 'ambros_store__catalog_product_index_price',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 110,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\CreateCopyTable::class,
                'sortOrder' => 120,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_website',
                    'tableName' => 'ambros_store__catalog_product_index_store',
                    'tableComment' => 'Catalog Product Store Index Table',
                    'columnsMap' => [
                        'website_id' => [
                            'name' => 'store_id',
                            'comment' => 'Store ID',
                            'ref_table_name' => 'store',
                            'ref_name' => 'store_id',
                        ],
                        'website_date' => [
                            'name' => 'store_date',
                            'comment' => 'Store Date',
                        ],
                    ],
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 130,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_tier_price',
                    'tableName' => 'ambros_store__catalog_product_index_tier_price',
                ],
            ],
            
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 10,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_bundle_selection_price',
                    'tableName' => 'ambros_store__catalog_product_bundle_selection_price',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 20,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_bundle_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 30,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_bundle_opt_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_opt_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 40,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_bundle_opt_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_opt_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 50,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_bundle_sel_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_sel_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 60,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_bundle_sel_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_sel_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 70,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_bundle_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 140,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_downlod_idx',
                    'tableName' => 'ambros_store__catalog_product_index_price_downlod_idx',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 150,
                'arguments' => [
                    'subjectTableName' => 'catalog_product_index_price_downlod_tmp',
                    'tableName' => 'ambros_store__catalog_product_index_price_downlod_tmp',
                ],
            ],
            [
                'class' => \Ambros\StoreCommon\Setup\Operation\CreateStoreCopyTable::class,
                'sortOrder' => 160,
                'arguments' => [
                    'subjectTableName' => 'downloadable_link_price',
                    'tableName' => 'ambros_store__downloadable_link_price',
                ],
            ],
            
        ]
    )
    {
        parent::__construct($operationFactory, $operations);
    }
   
}