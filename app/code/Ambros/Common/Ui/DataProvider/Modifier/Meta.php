<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Ui\DataProvider\Modifier;

/**
 * UI data provider meta modifier
 */
class Meta
{
    
    /**
     * Array manager
     * 
     * @var \Magento\Framework\Stdlib\ArrayManager
     */
    protected $arrayManager;
    
    /**
     * Data
     * 
     * @var array
     */
    protected $data = [];
    
    /**
     * Constructor
     * 
     * @param \Magento\Framework\Stdlib\ArrayManager $arrayManager
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Stdlib\ArrayManager $arrayManager,
        array $data = []
    )
    {
        $this->arrayManager = $arrayManager;
        $this->data = $data;
    }
    
    /**
     * Check if exists
     * 
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return (bool) $this->arrayManager->exists($path, $this->data);
    }
    
    /**
     * Get
     * 
     * @param string|null $path
     * @return mixed
     */
    public function get(string $path = null)
    {
        if ($path === null) {
            return $this->data;
        }
        return $this->arrayManager->get($path, $this->data);
    }
    
    /**
     * Set
     * 
     * @param mixed $value
     * @param string|null $path
     * @return $this
     */
    public function set($value, string $path = null)
    {
        if ($path === null) {
            $this->data = $value;
        }
        $this->data = $this->arrayManager->set($path, $this->data, $value);
        return $this;
    }
    
    /**
     * Merge meta
     * 
     * @param mixed $value
     * @param string $path
     * @return $this
     */
    public function merge($value, string $path)
    {
        $this->data = $this->arrayManager->merge($path, $this->data, $value);
        return $this;
    }
    
    /**
     * Replace
     * 
     * @param mixed $value
     * @param string $path
     * @return $this
     */
    public function replace($value, string $path)
    {
        $this->data = $this->arrayManager->replace($path, $this->data, $value);
        return $this;
    }
    
    /**
     * Remove
     * 
     * @param string $path
     * @return $this
     */
    public function remove(string $path)
    {
        $this->data = $this->arrayManager->remove($path, $this->data);
        return $this;
    }
    
    /**
     * Find path
     * 
     * @param array|mixed $indexes
     * @param string|null $startPath
     * @param string|null $internalPath
     * @return string|null
     */
    public function findPath($indexes, string $startPath = null, string $internalPath = null): ?string
    {
        return $this->arrayManager->findPath($indexes, $this->data, $startPath, $internalPath);
    }
    
    /**
     * Find paths
     * 
     * @param array|mixed $indexes
     * @param string|null $startPath
     * @param string|null $internalPath
     * @return array
     */
    public function findPaths($indexes, string $startPath = null, string $internalPath = null): array
    {
        return (array) $this->arrayManager->findPaths($indexes, $this->data, $startPath, $internalPath);
    }
    
    /**
     * Update component
     * 
     * @param string $path
     * @param array $config
     * @param array $children
     * @return $this
     */
    public function updateComponent(string $path, array $config = [], array $children = [])
    {
        if (!empty($config)) {
            $configPath = $path.'/arguments/data/config';
            if (!$this->has($configPath)) {
                $this->set([], $configPath);
            }
            $this->merge($config, $configPath);
        }
        if (!empty($children)) {
            $childrenPath = $path.'/children';
            if (!$this->has($childrenPath)) {
                $this->set([], $childrenPath);
            }
            $this->merge($children, $childrenPath);
        }
        return $this;
    }
    
    /**
     * Create component
     * 
     * @param array $config
     * @param array $children
     * @return array
     */
    public function createComponent(array $config = [], array $children = []): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => $config,
                ],
            ],
            'children' => $children,
        ];
    }
    
    /**
     * Create field set
     * 
     * @param array $config
     * @param array $children
     * @return array
     */
    public function createFieldset(array $config = [], array $children = []): array
    {
        return $this->createComponent(
            array_merge(
                [
                    'componentType' => \Magento\Ui\Component\Form\Fieldset::NAME,
                    'label' => '',
                    'collapsible' => true,
                    'opened' => false,
                    'visible' => true,
                    'sortOrder' => 0,
                ],
                $config
            ),
            $children
        );
    }
    
    /**
     * Create container
     * 
     * @param array $config
     * @param array $children
     * @return array
     */
    public function createContainer(array $config = [], array $children = []): array
    {
        return $this->createComponent(
            array_merge(
                [
                    'componentType' => \Magento\Ui\Component\Container::NAME,
                    'formElement' => \Magento\Ui\Component\Container::NAME,
                    'breakLine' => 'false',
                ],
                $config
            ),
            $children
        );
    }
    
    /**
     * Create field
     * 
     * @param array $config
     * @param array $children
     * @return array
     */
    public function createField(array $config = [], array $children = []): array
    {
        return $this->createComponent(
            array_merge(
                [
                    'componentType' => \Magento\Ui\Component\Form\Field::NAME,
                    'dataType' => \Magento\Ui\Component\Form\Element\DataType\Text::NAME,
                ],
                $config
            ),
            $children
        );
    }
    
}