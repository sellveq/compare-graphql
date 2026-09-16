<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CompareGraphQl
 * @copyright   Copyright © Magento, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\CompareGraphQl\Model\Service;

use Magento\CompareListGraphQl\Model\Service\GetCompareList as SourceGetCompareList;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;

class GetCompareList extends SourceGetCompareList
{
    /**
     * {@inheritdoc}
     */
    public function execute(int $listId, ContextInterface $context)
    {
        $compareList = parent::execute($listId, $context);

        $comparableAttributes = [];
        foreach ($compareList['attributes'] as $attribute) {
            if ($this->hasValueForAnyItem($attribute['code'], $compareList['items'])) {
                $comparableAttributes[] = $attribute;
            }
        }

        $compareList['attributes'] = $comparableAttributes;

        // an attribute dropped above stays on every item: the theme looks item values up by code
        foreach ($compareList['items'] as $itemIndex => $item) {
            foreach ($item['attributes'] as $attributeIndex => $attribute) {
                if ($attribute['value'] === null) {
                    $compareList['items'][$itemIndex]['attributes'][$attributeIndex]['value'] = __('-');
                }
            }
        }

        return $compareList;
    }

    /**
     * @param string $code
     * @param array $items
     * @return bool
     */
    private function hasValueForAnyItem(string $code, array $items): bool
    {
        foreach ($items as $item) {
            foreach ($item['attributes'] as $attribute) {
                if ($attribute['code'] === $code && $attribute['value'] !== null) {
                    return true;
                }
            }
        }

        return false;
    }
}
