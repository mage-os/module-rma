<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Model\ResourceModel;

use MageOS\RMA\Model\ResourceModel\LookupStoreRowsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Rows written to a "*_label" table: the store view label plus the store view values of "is_active" and "sort_order".
 */
class LookupStoreRowsTraitTest extends TestCase
{
    private object $resource;

    protected function setUp(): void
    {
        $this->resource = new class {
            use LookupStoreRowsTrait;

            public function __construct()
            {
                $this->labelForeignKey = 'reason_id';
            }

            public function rows(int $entityId, array $labels, array $values): array
            {
                return $this->buildStoreRows($entityId, $labels, $values);
            }
        };
    }

    public function testLabelOnlyRowLeavesTheValuesNull(): void
    {
        $this->assertSame(
            [['reason_id' => 7, 'store_id' => 2, 'label' => 'Falsche Größe', 'is_active' => null, 'sort_order' => null]],
            $this->resource->rows(7, [2 => 'Falsche Größe'], [])
        );
    }

    public function testValuesOnlyRowLeavesTheLabelNull(): void
    {
        $this->assertSame(
            [['reason_id' => 7, 'store_id' => 3, 'label' => null, 'is_active' => 0, 'sort_order' => null]],
            $this->resource->rows(7, [], [3 => ['is_active' => '0', 'sort_order' => '']])
        );
    }

    public function testLabelAndValuesOfOneStoreViewShareARow(): void
    {
        $this->assertSame(
            [['reason_id' => 7, 'store_id' => 2, 'label' => 'Wrong size', 'is_active' => 1, 'sort_order' => 15]],
            $this->resource->rows(7, [2 => 'Wrong size'], [2 => ['is_active' => '1', 'sort_order' => '15']])
        );
    }

    public function testStoreViewsWithoutLabelAndWithoutValuesGetNoRow(): void
    {
        $this->assertSame(
            [],
            $this->resource->rows(7, [2 => '', 3 => null], [2 => ['is_active' => '', 'sort_order' => null], 4 => []])
        );
    }

    public function testEveryStoreViewOfLabelsAndValuesGetsItsRow(): void
    {
        $rows = $this->resource->rows(
            7,
            [2 => 'Label two'],
            [3 => ['sort_order' => 5], 4 => ['is_active' => 1]]
        );

        $this->assertSame([2, 3, 4], array_column($rows, 'store_id'));
        $this->assertSame(['Label two', null, null], array_column($rows, 'label'));
        $this->assertSame([null, null, 1], array_column($rows, 'is_active'));
        $this->assertSame([null, 5, null], array_column($rows, 'sort_order'));
    }

    public function testValuesAreNormalised(): void
    {
        $rows = $this->resource->rows(7, [], [
            2 => ['is_active' => '2', 'sort_order' => '-4'],
            3 => ['is_active' => 0, 'sort_order' => 0],
            4 => 'not an array',
        ]);

        $this->assertSame([2, 3], array_column($rows, 'store_id'));
        $this->assertSame([1, 0], array_column($rows, 'is_active'));
        $this->assertSame([0, 0], array_column($rows, 'sort_order'));
    }
}
