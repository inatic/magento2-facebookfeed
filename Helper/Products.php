<?php

namespace Inatic\FacebookFeed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\AttributeSetRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Inatic\FacebookFeed\Helper\Data;

class Products extends AbstractHelper
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\Eav\ModelAttributeSetRepository
     */
    protected $attributeSetRepo;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    public $storeManager;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    public $productStatus;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    public $productVisibility;

    /**
     * @var \Inatic\FacebookFeed\Helper\Data
     */
    protected $helper;

    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        AttributeSetRepository $attributeSetRepo,
        StoreManagerInterface $storeManager,
        Status $productStatus,
        Visibility $productVisibility,
        Data $helper
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->attributeSetRepo = $attributeSetRepo;
        $this->storeManager = $storeManager;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->helper = $helper;
        parent::__construct($context);
    }

    public function getFilteredProducts()
    {
        $collection = $this->productCollectionFactory->create();
        // $collection->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
        $collection->addAttributeToFilter('visibility', ['eq' => Visibility::VISIBILITY_BOTH]);
        $collection->addAttributeToFilter('add_to_facebook_feed', true);
        $collection->addStoreFilter(1);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

        return $collection;
    }

    public function getAttributeSet($product)
    {
        $attributeSetId = $product->getAttributeSetId();
        return $this->attributeSetRepo->get($attributeSetId)->getAttributeSetName();
    }

    public function getProductValue($product, $attributeCode)
    {
        $attributeCodeFromConfig = $this->helper->getConfig($attributeCode.'_attribute');
        $defaultValue = $this->helper->getConfig('default_'.$attributeCode);

        if (!empty($attributeCodeFromConfig)) {
            return $product->getAttributeText($attributeCodeFromConfig);
        }

        if (!empty($defaultValue)) {
            return $defaultValue;
        }

        return false;
    }

    public function getCurrentCurrencySymbol()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }
}
