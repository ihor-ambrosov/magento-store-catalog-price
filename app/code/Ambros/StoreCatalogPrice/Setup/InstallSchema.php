<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Setup;

/**
 * Install schema
 */
class InstallSchema extends \Ambros\Common\Setup\AbstractInstallSchema
{
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCatalogPrice\Setup\Operation\InstallSchema $operation
     * @return void
     */
    public function __construct(
        \Ambros\StoreCatalogPrice\Setup\Operation\InstallSchema $operation
    )
    {
        parent::__construct($operation);
    }
    
}