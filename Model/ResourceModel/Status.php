<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Status extends AbstractDb
{
    use LookupStoreRowsTrait;

    /**
     * @var string
     */
    protected string $_eventPrefix = 'rma_status_resource_model';

    /**
     * @var string
     */
    protected string $labelsTable = '';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('rma_status', 'entity_id');
        $this->_useIsObjectNew = true;
        $this->labelsTable = $this->getTable('rma_status_label');
        $this->labelForeignKey = 'status_id';
    }

    /**
     * @param AbstractModel $object
     * @return array
     */
    public function getStoreLabels(AbstractModel $object): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->labelsTable, ['store_id', 'label'])
            ->where('status_id = ?', (int)$object->getId())
            ->where('label IS NOT NULL');

        return $connection->fetchPairs($select);
    }

    /**
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(AbstractModel $object): static
    {
        parent::_afterLoad($object);

        if ($object->getId()) {
            $object->setData('store_labels', $this->getStoreLabels($object));
            $object->setData('store_values', $this->getStoreValues($object));
        }

        return $this;
    }

    /**
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object): static
    {
        parent::_afterSave($object);

        $this->saveStoreRows($object);

        return $this;
    }
}
