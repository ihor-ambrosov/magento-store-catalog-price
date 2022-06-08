<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\StoreCatalogPrice\Setup;

/**
 * Uninstall
 */
class Uninstall extends \Ambros\Common\Setup\AbstractUninstall
{
    
    /**
     * Constructor
     * 
     * @param \Ambros\StoreCatalogPrice\Setup\Operation\Uninstall $operation
     * @return void
     */
    public function __construct(
        \Ambros\StoreCatalogPrice\Setup\Operation\Uninstall $operation
    )
    {
        parent::__construct($operation);
    }
    
}