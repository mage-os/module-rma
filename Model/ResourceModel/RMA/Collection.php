<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\ResourceModel\RMA;

use MageOS\RMA\Model\RMA as Model;
use MageOS\RMA\Model\ResourceModel\RMA as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    const SALES_ORDER_TABLE = 'sales_order';
    const SALES_ORDER_ALIAS = 'so';
    const ORDER_INCREMENT_ID_COLUMN = 'order_increment_id';

    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'rma_entity_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * @return $this
     */
    public function joinSalesOrder(): static
    {
        $this->getSelect()->joinLeft(
            [self::SALES_ORDER_ALIAS => $this->getTable(self::SALES_ORDER_TABLE)],
            sprintf('%s.entity_id = main_table.order_id', self::SALES_ORDER_ALIAS),
            [self::ORDER_INCREMENT_ID_COLUMN => sprintf('%s.increment_id', self::SALES_ORDER_ALIAS)]
        );

        return $this;
    }
}
