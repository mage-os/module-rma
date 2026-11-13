<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Model\Config\Source;

use ArrayIterator;
use MageOS\RMA\Model\Config\Source\Reason as ReasonSource;
use MageOS\RMA\Model\Reason;
use MageOS\RMA\Model\ResourceModel\Reason\Collection;
use MageOS\RMA\Model\ResourceModel\Reason\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Option lists of the lookups with the store view values of "is_active" and "sort_order".
 */
class LookupSourceStoreValuesTest extends TestCase
{
    private Collection&MockObject $collection;
    private AdapterInterface&MockObject $connection;
    private StoreInterface&MockObject $store;
    private ReasonSource $source;

    /**
     * @var array<int, array{0: string, 1: mixed}>
     */
    private array $filters = [];

    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->store = $this->createMock(StoreInterface::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);

        $this->collection->method('getConnection')->willReturn($this->connection);
        $this->collection->method('getTable')->willReturnArgument(0);
        $this->collection->method('setOrder')->willReturnSelf();
        $this->collection->method('addFieldToFilter')->willReturnCallback(
            function (string $field, mixed $condition): Collection {
                $this->filters[] = [$field, $condition];
                return $this->collection;
            }
        );

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->store);

        $this->source = new ReasonSource($collectionFactory, $storeManager);
    }

    public function testWithoutStoreValuesTheListIsFilteredInTheQueryAsBefore(): void
    {
        $this->store->method('getId')->willReturn(2);
        $this->entities([[1, 'Wrong size', 1, 10], [2, 'Damaged', 1, 20]]);
        $this->connection->method('fetchPairs')->willReturn([2 => 'Beschädigt']);
        $this->connection->method('fetchAll')->willReturn([]);

        $this->assertSame(
            [['value' => 1, 'label' => 'Wrong size'], ['value' => 2, 'label' => 'Beschädigt']],
            $this->source->toOptionArray()
        );
        $this->assertSame([['is_active', 1]], $this->filters);
    }

    public function testStoreViewCanDisableAnEntity(): void
    {
        $this->store->method('getId')->willReturn(2);
        $this->entities([[1, 'Wrong size', 1, 10], [2, 'Damaged', 1, 20]]);
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([
            ['reason_id' => '1', 'is_active' => '0', 'sort_order' => null],
        ]);

        $this->assertSame([['value' => 2, 'label' => 'Damaged']], $this->source->toOptionArray());
        $this->assertSame([], $this->filters, 'The "is_active" filter must not be applied in the query.');
    }

    public function testStoreViewCanEnableAnEntityThatIsDisabledByDefault(): void
    {
        $this->store->method('getId')->willReturn(2);
        $this->entities([[1, 'Wrong size', 1, 10], [2, 'Statutory withdrawal', 0, 20], [3, 'Hidden', 0, 30]]);
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([
            ['reason_id' => '2', 'is_active' => '1', 'sort_order' => null],
        ]);

        $this->assertSame(
            [['value' => 1, 'label' => 'Wrong size'], ['value' => 2, 'label' => 'Statutory withdrawal']],
            $this->source->toOptionArray()
        );
    }

    public function testStoreViewSortOrderReordersAndTiesKeepTheDefaultOrder(): void
    {
        $this->store->method('getId')->willReturn(2);
        $this->entities([[1, 'First', 1, 10], [2, 'Second', 1, 20], [3, 'Third', 1, 30]]);
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([
            ['reason_id' => '3', 'is_active' => null, 'sort_order' => '10'],
            ['reason_id' => '2', 'is_active' => null, 'sort_order' => '5'],
        ]);

        $this->assertSame([2, 1, 3], array_column($this->source->toOptionArray(), 'value'));
    }

    public function testAdminStoreUsesTheDefaultsAndReadsNoStoreRows(): void
    {
        $this->store->method('getId')->willReturn(0);
        $this->entities([[1, 'Wrong size', 1, 10]]);
        $this->connection->expects($this->never())->method('fetchPairs');
        $this->connection->expects($this->never())->method('fetchAll');

        $this->assertSame([['value' => 1, 'label' => 'Wrong size']], $this->source->toOptionArray());
        $this->assertSame([['is_active', 1]], $this->filters);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int, 3: int}> $rows entity id, label, is_active, sort_order
     */
    private function entities(array $rows): void
    {
        $entities = [];
        foreach ($rows as [$entityId, $label, $isActive, $sortOrder]) {
            $entity = $this->createMock(Reason::class);
            $entity->method('getEntityId')->willReturn($entityId);
            $entity->method('getLabel')->willReturn($label);
            $entity->method('getIsActive')->willReturn($isActive);
            $entity->method('getSortOrder')->willReturn($sortOrder);
            $entities[] = $entity;
        }

        $this->collection->method('getIterator')->willReturn(new ArrayIterator($entities));
    }
}
