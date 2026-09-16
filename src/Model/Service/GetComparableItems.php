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

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\Compare\ListCompare;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\CompareListGraphQl\Model\Service\Collection\GetComparableItemsCollection as ComparableItemsCollection;
use Magento\CompareListGraphQl\Model\Service\GetComparableItems as SourceGetComparableItems;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\Phrase;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;

class GetComparableItems extends SourceGetComparableItems
{
    // each type resolves against its own product attribute, so a product may carry three different files
    private const array IMAGE_TYPES = ['thumbnail', 'small_image', 'image'];

    /**
     * @param ListCompare $listCompare
     * @param ComparableItemsCollection $comparableItemsCollection
     * @param ProductRepository $productRepository
     * @param Image $imageBuilder
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     */
    public function __construct(
        ListCompare $listCompare,
        private readonly ComparableItemsCollection $comparableItemsCollection,
        private readonly ProductRepository $productRepository,
        private readonly Image $imageBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Emulation $emulation
    ) {
        parent::__construct($listCompare, $comparableItemsCollection, $productRepository);
    }

    /**
     * core's two helpers are private, so overriding this loop is what binds it to the ones below
     * {@inheritdoc}
     */
    public function execute(int $listId, ContextInterface $context)
    {
        $itemsCollection = $this->comparableItemsCollection->execute($listId, $context);
        $comparableAttributes = $itemsCollection->getComparableAttributes();

        $items = [];
        foreach ($itemsCollection as $item) {
            /** @var Product $item */
            $items[] = [
                'uid' => $item->getId(),
                'product' => $this->getProductData((int)$item->getId()),
                'attributes' => $this->getProductComparableAttributes($item, $comparableAttributes)
            ];
        }

        return $items;
    }

    /**
     * @param int $productId
     * @return array
     * @throws GraphQlInputException
     * @throws RuntimeException
     */
    private function getProductData(int $productId): array
    {
        try {
            $item = $this->productRepository->getById($productId);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        if (!$item instanceof Product) {
            throw new RuntimeException(sprintf(
                'product %d loaded as %s, which carries none of the data this resolver reads',
                $productId,
                get_debug_type($item)
            ));
        }

        $productData = $item->getData();
        $productData['model'] = $item;
        $productData['stock_status'] = $this->getStockStatus($item);

        foreach ($this->getImageUrls($item) as $imageType => $url) {
            $productData[$imageType] = [
                'path' => $item->getData($imageType),
                'url' => $url
            ];
        }

        return $productData;
    }

    /**
     * ProductInterface, not Product: only the interface documents the product's own extension attributes
     * @param ProductInterface $product
     * @return string
     * @throws RuntimeException
     */
    private function getStockStatus(ProductInterface $product): string
    {
        $stockItem = $product->getExtensionAttributes()->getStockItem();

        if ($stockItem === null) {
            throw new RuntimeException(sprintf(
                'no stock item on product %d, so the extension attribute was not populated',
                (int)$product->getId()
            ));
        }

        return $stockItem->getIsInStock() ? 'IN_STOCK' : 'OUT_OF_STOCK';
    }

    /**
     * starting emulation reloads the store design, so the three builds share one cycle
     * @param Product $product
     * @return string[]
     */
    private function getImageUrls(Product $product): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $urls = [];

        try {
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

            foreach (self::IMAGE_TYPES as $imageType) {
                $urls[$imageType] = $this->getImageUrl($imageType, $product);
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }

        return $urls;
    }

    /**
     * runs inside the caller's emulation, so the image config it reads is the frontend theme's
     * @param string $imageType
     * @param Product $product
     * @return string
     */
    private function getImageUrl(string $imageType, Product $product): string
    {
        $imagePath = $product->getData($imageType);

        if (!isset($imagePath) || $imagePath === 'no_selection') {
            return $this->imageBuilder->getDefaultPlaceholderUrl($imageType);
        }

        return $this->imageBuilder
            ->init($product, sprintf('scandipwa_%s', $imageType), ['type' => $imageType])
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false)
            ->getUrl();
    }

    /**
     * @param Product $product
     * @param AbstractAttribute[] $comparableAttributes
     * @return array
     */
    private function getProductComparableAttributes(Product $product, array $comparableAttributes): array
    {
        $attributes = [];
        foreach ($comparableAttributes as $attribute) {
            $attributes[] = [
                'code' => $attribute->getAttributeCode(),
                'value' => $this->getAttributeValue($product, $attribute)
            ];
        }

        return $attributes;
    }

    /**
     * the rule of ListCompare::getProductAttributeValue(), answering null where core answers its N/A sentinel
     * @param Product $product
     * @param AbstractAttribute $attribute
     * @return Phrase|string|null
     */
    private function getAttributeValue(Product $product, AbstractAttribute $attribute): Phrase|string|null
    {
        $code = $attribute->getAttributeCode();

        if (!$product->hasData($code)) {
            return null;
        }

        $usesOptions = $attribute->getSourceModel()
            || in_array($attribute->getFrontendInput(), ['select', 'boolean', 'multiselect']);
        $value = $usesOptions ? $attribute->getFrontend()->getValue($product) : $product->getData($code);

        if (is_array($value)) {
            return null;
        }

        return (string)$value === '' ? __('No') : (string)$value;
    }
}
