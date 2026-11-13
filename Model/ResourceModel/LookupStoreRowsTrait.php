<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;

/**
 * Store view rows of a lookup entity (status, reason, resolution type, item condition).
 *
 * A row of the "*_label" table holds the store view label and the optional store view values of
 * "is_active" and "sort_order". NULL in a column means "use the value of the entity".
 */
trait LookupStoreRowsTrait
{
    /**
     * @var string
     */
    protected string $labelForeignKey = '';

    /**
     * Store view values of "is_active" and "sort_order"; a store view or a field without a value is absent
     *
     * @param AbstractModel $object
     * @return array [store id => ['is_active' => '0'|'1', 'sort_order' => '10']]
     */
    public function getStoreValues(AbstractModel $object): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->labelsTable, ['store_id', 'is_active', 'sort_order'])
            ->where($this->labelForeignKey . ' = ?', (int)$object->getId());

        $values = [];
        foreach ($connection->fetchAll($select) as $row) {
            $storeValues = [];
            foreach (['is_active', 'sort_order'] as $field) {
                if ($row[$field] !== null) {
                    $storeValues[$field] = (string)$row[$field];
                }
            }

            if ($storeValues) {
                $values[(int)$row['store_id']] = $storeValues;
            }
        }

        return $values;
    }

    /**
     * Replace the store view rows of the entity; the part the object does not carry (labels or values) is kept
     *
     * @param AbstractModel $object
     * @return void
     */
    protected function saveStoreRows(AbstractModel $object): void
    {
        if (!$object->hasData('store_labels') && !$object->hasData('store_values')) {
            return;
        }

        $labels = $object->hasData('store_labels')
            ? (array)$object->getData('store_labels')
            : $this->getStoreLabels($object);
        $values = $object->hasData('store_values')
            ? (array)$object->getData('store_values')
            : $this->getStoreValues($object);

        $connection = $this->getConnection();
        $connection->delete($this->labelsTable, [$this->labelForeignKey . ' = ?' => (int)$object->getId()]);

        $rows = $this->buildStoreRows((int)$object->getId(), $labels, $values);
        if (!empty($rows)) {
            $connection->insertMultiple($this->labelsTable, $rows);
        }
    }

    /**
     * @param int $entityId
     * @param array $labels [store id => label]
     * @param array $values [store id => ['is_active' => mixed, 'sort_order' => mixed]]
     * @return array
     */
    protected function buildStoreRows(int $entityId, array $labels, array $values): array
    {
        $rows = [];

        foreach (array_unique(array_merge(array_keys($labels), array_keys($values))) as $storeId) {
            $label = !empty($labels[$storeId]) ? (string)$labels[$storeId] : null;
            $storeValues = is_array($values[$storeId] ?? null) ? $values[$storeId] : [];
            $isActive = $this->normalizeStoreValue($storeValues['is_active'] ?? null);
            $sortOrder = $this->normalizeStoreValue($storeValues['sort_order'] ?? null);

            if ($label === null && $isActive === null && $sortOrder === null) {
                continue;
            }

            $rows[] = [
                $this->labelForeignKey => $entityId,
                'store_id' => (int)$storeId,
                'label' => $label,
                'is_active' => $isActive === null ? null : (int)($isActive > 0),
                'sort_order' => $sortOrder,
            ];
        }

        return $rows;
    }

    /**
     * @param mixed $value
     * @return int|null null when the store view uses the value of the entity
     */
    private function normalizeStoreValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int)$value);
    }
}
