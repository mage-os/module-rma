<?php

declare(strict_types=1);

namespace MageOS\RMA\Ui\DataProvider\Form\Modifier;

use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Form\Element\Input;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

/**
 * Store view values of "is_active" and "sort_order" on the lookup forms, next to the store view labels
 */
class StoreValues implements ModifierInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array $data
     * @return array
     */
    public function modifyData(array $data): array
    {
        return $data;
    }

    /**
     * @param array $meta
     * @return array
     */
    public function modifyMeta(array $meta): array
    {
        if ($this->storeManager->isSingleStoreMode()) {
            return $meta;
        }

        $meta['store_values'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Store View Specific Status and Sort Order'),
                        'componentType' => 'fieldset',
                        'collapsible' => true,
                        'opened' => false,
                        'sortOrder' => 30,
                    ],
                ],
            ],
            'children' => $this->getWebsiteFieldsets(),
        ];

        return $meta;
    }

    /**
     * @return array
     */
    protected function getWebsiteFieldsets(): array
    {
        $fields = [];
        $sortOrder = 0;

        foreach ($this->storeManager->getWebsites() as $website) {
            $fields['values_website_' . $website->getId()] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => 'fieldset',
                            'label' => $website->getName(),
                            'collapsible' => true,
                            'opened' => true,
                            'sortOrder' => $sortOrder++,
                        ],
                    ],
                ],
                'children' => $this->getWebsiteStoreFields($website),
            ];
        }

        return $fields;
    }

    /**
     * @param WebsiteInterface $website
     * @return array
     */
    protected function getWebsiteStoreFields(WebsiteInterface $website): array
    {
        $fields = [];
        $sortOrder = 0;

        foreach ($website->getGroups() as $group) {
            foreach ($group->getStores() as $store) {
                $fields['store_is_active_' . $store->getId()] = [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => Field::NAME,
                                'formElement' => Select::NAME,
                                'dataType' => 'text',
                                'label' => __('%1: Active', $store->getName()),
                                'dataScope' => 'store_values.' . $store->getId() . '.is_active',
                                'options' => [
                                    ['value' => '', 'label' => __('Use Default')],
                                    ['value' => '1', 'label' => __('Yes')],
                                    ['value' => '0', 'label' => __('No')],
                                ],
                                'sortOrder' => $sortOrder++,
                                'notice' => $group->getName(),
                            ],
                        ],
                    ],
                ];
                $fields['store_sort_order_' . $store->getId()] = [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => Field::NAME,
                                'formElement' => Input::NAME,
                                'dataType' => 'text',
                                'label' => __('%1: Sort Order', $store->getName()),
                                'dataScope' => 'store_values.' . $store->getId() . '.sort_order',
                                'validation' => ['validate-digits' => true],
                                'sortOrder' => $sortOrder++,
                                'notice' => __('Leave empty to use the default sort order.'),
                            ],
                        ],
                    ],
                ];
            }
        }

        return $fields;
    }
}
