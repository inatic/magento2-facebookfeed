<?php

namespace Inatic\FacebookFeed\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Inatic\FacebookFeed\Helper\Data;
use Inatic\FacebookFeed\Helper\Products;

class XmlFeed
{

    /**
     * Category Collection
     *
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private $categoryCollection;

    /**
     * Store Manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * General Helper
     *
     * @var \Inatic\FacebookFeed\Helper\Data
     */
    private $helper;

    /**
     * Product Helper
     *
     * @var \Inatic\FacebookFeed\Helper\Products
     */
    private $productFeedHelper;

    public function __construct(
        Data $helper,
        Products $productFeedHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $categoryCollection,
        TaxCalculationInterface $taxCalculation,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->helper = $helper;
        $this->productFeedHelper = $productFeedHelper;
        $this->storeManager = $storeManager;
        $this->categoryCollection = $categoryCollection;
        $this->taxCalculation = $taxCalculation;
        $this->scopeConfig = $scopeConfig;
    }

    public function getFeed(): string
    {
        $xml = $this->getXmlHeader();
        $xml .= $this->getProductsXml();
        $xml .= $this->getXmlFooter();

        return $xml;
    }

    public function getFeedFile(): string
    {
        $xml = '';
        $fileName = "feedfacebook.xml";
        if (file_exists($fileName)){
            $xml = file_get_contents($fileName); //phpcs:ignore
        }
        // commented out for testing
        //if (strlen($xml) < 500) {
            $xml = $this->getFeed();
        //}
        return $xml;
    }

    public function getXmlHeader(): string
    {
        header("Content-Type: application/xml; charset=utf-8"); //phpcs:ignore

        $xml =  '<?xml version="1.0"?>';
        $xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
        $xml .= '<channel>';
        $xml .= '<title>'.$this->helper->getConfig('google_default_title').'</title>';
        $xml .= '<link>'.$this->helper->getConfig('google_default_url').'</link>';
        $xml .= '<description>'.$this->helper->getConfig('google_default_description').'</description>';

        return $xml;
    }

    public function getXmlFooter(): string
    {
        return  '</channel></rss>';
    }

    public function getProductsXml(): string
    {
        $productCollection = $this->productFeedHelper->getFilteredProducts();
        $xml = "";

        foreach ($productCollection as $product) {
            if ($this->isValidProduct($product)) {
                $xml .= "<item>".$this->buildProductXml($product)."</item>";
            }
        }

        return $xml;
    }

    private function isValidProduct($product): bool
    {
        if ($product->getImage() === "no_selection"
            || (string) $product->getImage() === ""
            || $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE
        ) {
            return false;
        }
        if (empty($product->getData('ean'))) {
            return false;
        }

        return true;
    }

    public function buildProductXml($product): string
    {
        $storeId = 1;

        //Prepare values
        $base_url = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA,true);
        $image_link = $base_url . 'catalog/product' . $product->getImage();

        //price calculation
        if ($taxAttribute = $product->getTaxClassId()) {
            $productRateId = (int) $taxAttribute;
        }
        $rate = $this->taxCalculation->getCalculatedRate($productRateId);
        if ((int) $this->scopeConfig->getValue('tax/calculation/price_includes_tax', ScopeInterface::SCOPE_STORE) === 1) {
            // Product price in catalog is including tax
            $regularPriceExcludingTax = $product->getPriceInfo()->getPrice('regular_price')->getValue() / (1 + ($rate / 100));
            $specialPriceExcludingTax = $product->getPriceInfo()->getPrice('final_price')->getValue() / (1 + ($rate / 100));
        } else {
            // Product price in catalog is excluding tax
            $regularPriceExcludingTax = $product->getPriceInfo()->getPrice('regular_price')->getValue();
            $specialPriceExcludingTax = $product->getPriceInfo()->getPrice('final_price')->getValue();
        }
        $regularPrice = $regularPriceExcludingTax * (1 + $rate / 100);
        $specialPrice = $specialPriceExcludingTax * (1 + $rate / 100);
        $currencySymbol = $this->productFeedHelper->getCurrentCurrencySymbol();

        //Required values
        $xml = '';
        $xml .= $this->createNode("id", $product->getId());
        $xml .= $this->createNode("title", $product->getName(), true);
        $xml .= $this->createNode("description", $this->fixDescription($product->getDescription()), true);
        $xml .= $this->createNode("availability", $this->isInStock($product));
        $xml .= $this->createNode("condition", "new");
        $xml .= $this->createNode("price",number_format($regularPrice,2,'.','').' '.$currencySymbol);
        $xml .= $this->createNode("link", $product->getProductUrl());
        $xml .= $this->createNode("image_link", $image_link);
        $xml .= $this->createNode("brand", $product->getAttributeText('merk'));

        //Optional values
        if (($specialPrice < $regularPrice) && !empty($specialPrice)) {
            $xml .= $this->createNode('sale_price',number_format($specialPrice,2,'.','').' '.$currencySymbol);
        }
        $xml .= $this->createNode("color", ucfirst($product->getAttributeText('kleur')));
        $xml .= $this->createNode("custom_label_0", $this->getProductCategories($product), true);
        $xml .= $this->createNode("product_type", $this->getProductCategories($product), true);
        if (!empty($product->getData('ean'))) {
            $xml .= $this->createNode("gtin", $product->getData('ean'));
        }
        //$xml .= $this->createNode("g:product_type", $this->productFeedHelper->getAttributeSet($product), true);
        //$xml .= $this->createNode(
        //    'g:google_product_category',
        //    $this->productFeedHelper->getProductValue($product, 'google_product_category'),
        //    true
        //);
    
        return $xml;
    }

    private function isInStock($product): string
    {
        $inStock = 'out of stock';
        if ($product->isSaleable()) {
            $inStock = 'in stock';
        }
        return $inStock;
    }

    private function getCondition($product)
    {
        $_condition = $this->productFeedHelper->getProductValue($product, 'google_condition');
        if (is_array($_condition)) {
            $condition = $_condition[0];
        } elseif ($_condition === "Refurbished") {
            $condition = "refurbished";
        } else {
            $condition = $this->helper->getConfig('default_google_condition');
        }
        return $condition;
    }

    public function fixDescription($data): string
    {
        $description = $data;
        $encode = mb_detect_encoding($data);
        return mb_convert_encoding($description, 'UTF-8', $encode);
    }

    public function createNode(string $nodeName, string $value, bool $cData = false): string
    {
        if (empty($value) || empty($nodeName)) {
            return false;
        }

        $cDataStart = "";
        $cDataEnd = "";

        if ($cData === true) {
            $cDataStart = "<![CDATA[";
            $cDataEnd = "]]>";
        }

        return "<".$nodeName.">".$cDataStart.$value.$cDataEnd."</".$nodeName.">";
    }

    public function getFilteredCollection(array $categoryIds)
    {
        $collection = $this->categoryCollection->create();
        return $collection
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'entity_id',
                ['in' => $categoryIds]
            )
            ->setOrder('level', 'ASC')
            ->load();
    }

    private function getProductCategories($product): string
    {
        $categoryIds = $product->getCategoryIds();
        $categoryCollection = $this->getFilteredCollection($categoryIds);
        $fullcategory = "";
        $i = 0;
        foreach ($categoryCollection as $category) {
            $i++;
            if ($i !== (int) $categoryCollection->getSize()) {
                $fullcategory .= $category->getData('name') . ' > ';
            } else {
                $fullcategory .= $category->getData('name');
            }
        }
        return $fullcategory;
    }
}
