<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Ui\DataProvider\Form\Modifier;

use MageOS\RMA\Ui\DataProvider\Form\Modifier\StoreValues;
use Magento\Store\Model\Group;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StoreValuesTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private StoreValues $modifier;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->modifier = new StoreValues($this->storeManager);
    }

    public function testSingleStoreModeAddsNothing(): void
    {
        $this->storeManager->method('isSingleStoreMode')->willReturn(true);

        $this->assertSame(['general' => []], $this->modifier->modifyMeta(['general' => []]));
    }

    public function testEveryStoreViewGetsAnActiveAndASortOrderField(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getName')->willReturn('German');

        $group = $this->createMock(Group::class);
        $group->method('getName')->willReturn('Main Store');
        $group->method('getStores')->willReturn([$store]);

        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn(1);
        $website->method('getName')->willReturn('Main Website');
        $website->method('getGroups')->willReturn([$group]);

        $this->storeManager->method('isSingleStoreMode')->willReturn(false);
        $this->storeManager->method('getWebsites')->willReturn([$website]);

        $meta = $this->modifier->modifyMeta(['general' => []]);

        $this->assertSame(['general', 'store_values'], array_keys($meta));
        $this->assertSame('fieldset', $meta['store_values']['arguments']['data']['config']['componentType']);

        $fields = $meta['store_values']['children']['values_website_1']['children'];
        $this->assertSame(['store_is_active_2', 'store_sort_order_2'], array_keys($fields));

        $active = $fields['store_is_active_2']['arguments']['data']['config'];
        $this->assertSame('select', $active['formElement']);
        $this->assertSame('store_values.2.is_active', $active['dataScope']);
        $this->assertSame(['', '1', '0'], array_column($active['options'], 'value'));
        $this->assertSame('German: Active', (string)$active['label']);

        $sortOrder = $fields['store_sort_order_2']['arguments']['data']['config'];
        $this->assertSame('input', $sortOrder['formElement']);
        $this->assertSame('store_values.2.sort_order', $sortOrder['dataScope']);
        $this->assertSame(['validate-digits' => true], $sortOrder['validation']);
    }

    public function testDataIsLeftUntouched(): void
    {
        $data = [5 => ['entity_id' => 5, 'store_values' => [2 => ['is_active' => '0']]]];

        $this->assertSame($data, $this->modifier->modifyData($data));
    }
}
