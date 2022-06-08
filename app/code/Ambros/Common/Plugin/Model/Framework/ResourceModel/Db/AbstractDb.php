<?php
/**
 * @author Ihor Ambrosov <ihor.ambrosov@gmail.com>
 * @license https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Ambros\Common\Plugin\Model\Framework\ResourceModel\Db;

/**
 * Abstract database resource plugin
 */
class AbstractDb extends \Ambros\Common\Plugin\Plugin
{
    
    /**
     * Before save
     *
     * @param \Magento\Framework\Model\AbstractModel|\Magento\Framework\DataObject $object
     * @return $this
     */
    protected function beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->invokeSubjectMethod('_beforeSave', $object);
        return $this;
    }
    
    /**
     * After save
     *
     * @param \Magento\Framework\Model\AbstractModel|\Magento\Framework\DataObject $object
     * @return $this
     */
    protected function afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->invokeSubjectMethod('_afterSave', $object);
        return $this;
    }
    
    /**
     * Process after saves
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function processAfterSaves(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->afterSave($object);
        $object->afterSave();
        return $this;
    }
    
    /**
     * Save
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    protected function save(\Magento\Framework\Model\AbstractModel $object)
    {
        $subject = $this->getSubject();
        $objectRelationProcessor = $this->getSubjectPropertyValue('objectRelationProcessor');
        if ($object->isDeleted()) {
            $subject->delete($object);
            return $this;
        }
        $subject->beginTransaction();
        try {
            if (!$this->invokeSubjectMethod('isModified', $object)) {
                $this->invokeSubjectMethod('processNotModifiedSave', $object);
                $subject->commit();
                $object->setHasDataChanges(false);
                return $this;
            }
            $object->validateBeforeSave();
            $object->beforeSave();
            if ($object->isSaveAllowed()) {
                $this->invokeSubjectMethod('_serializeFields', $object);
                $this->beforeSave($object);
                $this->invokeSubjectMethod('_checkUnique', $object);
                $objectRelationProcessor->validateDataIntegrity($subject->getMainTable(), $object->getData());
                if ($this->invokeSubjectMethod('isObjectNotNew', $object)) {
                    $this->invokeSubjectMethod('updateObject', $object);
                } else {
                    $this->invokeSubjectMethod('saveNewObject', $object);
                }
                $subject->unserializeFields($object);
                $this->processAfterSaves($object);
            }
            $subject->addCommitCallback([$object, 'afterCommitCallback'])->commit();
            $object->setHasDataChanges(false);
        } catch (\Magento\Framework\DB\Adapter\DuplicateException $exception) {
            $subject->rollBack();
            $object->setHasDataChanges(true);
            throw new \Magento\Framework\Exception\AlreadyExistsException(__('Unique constraint violation found'), $exception);
        } catch (\Exception $exception) {
            $subject->rollBack();
            $object->setHasDataChanges(true);
            throw $exception;
        }
        return $this;
    }
    
    /**
     * Before delete
     *
     * @param \Magento\Framework\Model\AbstractModel|\Magento\Framework\DataObject $object
     * @return $this
     */
    protected function beforeDelete(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->invokeSubjectMethod('_beforeDelete', $object);
        return $this;
    }
    
    /**
     * After delete
     *
     * @param \Magento\Framework\Model\AbstractModel|\Magento\Framework\DataObject $object
     * @return $this
     */
    protected function afterDelete(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->invokeSubjectMethod('_afterDelete', $object);
        return $this;
    }
    
    /**
     * Delete
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     * @throws \Exception
     */
    protected function delete(\Magento\Framework\Model\AbstractModel $object)
    {
        $subject = $this->getSubject();
        $transactionManager = $this->getSubjectPropertyValue('transactionManager');
        $objectRelationProcessor = $this->getSubjectPropertyValue('objectRelationProcessor');
        $connection = $transactionManager->start($subject->getConnection());
        try {
            $object->beforeDelete();
            $this->beforeDelete($object);
            $objectRelationProcessor->delete(
                $transactionManager,
                $connection,
                $subject->getMainTable(),
                $connection->quoteInto($subject->getIdFieldName().' = ?', $object->getId()),
                $object->getData()
            );
            $this->afterDelete($object);
            $object->isDeleted(true);
            $object->afterDelete();
            $transactionManager->commit();
            $object->afterDeleteCommit();
        } catch (\Exception $exception) {
            $transactionManager->rollBack();
            throw $exception;
        }
        return $this;
    }
    
    /**
     * Around before save
     * 
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DataObject $object
     * @return void
     */
    public function aroundBeforeSave(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        \Closure $proceed,
        \Magento\Framework\DataObject $object
    )
    {
        $this->setSubject($subject);
        $this->beforeSave($object);
    }
    
    /**
     * Around after save
     * 
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DataObject $object
     * @return void
     */
    public function aroundAfterSave(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        \Closure $proceed,
        \Magento\Framework\DataObject $object
    )
    {
        $this->setSubject($subject);
        $this->afterSave($object);
    }
    
    /**
     * Around save
     * 
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    public function aroundSave(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $object
    )
    {
        $this->setSubject($subject);
        $this->save($object);
        return $subject;
    }
    
    /**
     * Around before delete
     * 
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DataObject $object
     * @return void
     */
    public function aroundBeforeDelete(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        \Closure $proceed,
        \Magento\Framework\DataObject $object
    )
    {
        $this->setSubject($subject);
        $this->beforeDelete($object);
    }
    
    /**
     * Around after delete
     * 
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\DataObject $object
     * @return void
     */
    public function aroundAfterDelete(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        \Closure $proceed,
        \Magento\Framework\DataObject $object
    )
    {
        $this->setSubject($subject);
        $this->afterDelete($object);
    }
    
    /**
     * Around delete
     * 
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    public function aroundDelete(
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $subject,
        \Closure $proceed,
        \Magento\Framework\Model\AbstractModel $object
    )
    {
        $this->setSubject($subject);
        $this->delete($object);
        return $subject;
    }
    
}