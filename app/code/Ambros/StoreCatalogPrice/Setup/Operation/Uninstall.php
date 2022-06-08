<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Setup\Operation;

/**
 * Uninstall setup operation
 */
class Uninstall extends \Ambros\Common\Setup\Operation\CompoundOperation
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
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 10,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_tier_price',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 20,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_store',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 30,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 40,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 50,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_replica',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 60,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 70,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 80,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_agr_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 90,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_opt_agr_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 100,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 130,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_cfg_opt_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 140,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_cfg_opt_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 170,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_entity_tier_price',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 10,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 20,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_sel_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 30,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_sel_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 40,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_opt_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 50,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_opt_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 60,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_bundle_idx',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 70,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_bundle_selection_price',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 10,
                'arguments' => [
                    'tableName' => 'ambros_store__downloadable_link_price',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 20,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_downlod_tmp',
                ],
            ],
            [
                'class' => \Ambros\Common\Setup\Operation\DropTable::class,
                'sortOrder' => 30,
                'arguments' => [
                    'tableName' => 'ambros_store__catalog_product_index_price_downlod_idx',
                ],
            ],
        ]
    )
    {
        parent::__construct($operationFactory, $operations);
    }
   
}