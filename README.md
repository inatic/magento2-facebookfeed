Disclaimer: this module is based on the work of [adampmoss/MageFoxGoogleShopping](https://github.com/adampmoss/MageFoxGoogleShopping) and [Quazz/MageFoxGoogleShopping](https://github.com/Quazz/MageFoxGoogleShopping). 

The following is a short explanation of how this module works. Any module contains the following files: `registration.php`, responsible for registering the namespace of the module with the system, `etc/module.xml`, containing the name and possible other details on the module, and `composer.json`, which provides information on the module as well as its requirements for a `composer` installation.

# Route to XML file

The module generates an XML file containing information on the products you would like to add to a Facebook shop. This XML document is downloaded by Facebook from the Magento installation, and thus a route to the document need to be added. In this case the route will be `yourstore.com/inaticfacebookfeed` but can easily be modified to something else. The file needs to be accessible from the frontend and so it is configured in `etc/frontend/routes.xml`.

```etc/frontend/routes.xml
<router id="standard">
    <route id="inaticfacebookfeed" frontName="inaticfacebookfeed">
        <module name="Inatic_FacebookFeed"/>
    </route>
</router>
```

This creates a route from the specific URL to the module in question. Once arrived at the module, a controller accepts the request and takes care of further processing. The controller prepares a response for the request, sets a header to specify that XML content is being returned, and then gets the content for the response from the `xmlFeed` object for which the class will be created next.

```Controller/Index/Index.php
$resultRaw->setHeader('Content-Type', 'text/xml');
$resultRaw->setContents($this->xmlFeed->getFeedFile());
return $resultRaw;
```

# Fetch product data

Before proceeding to create the actual XML content we'll have a look at a class to help fetching product data from the database. Products in this case are filtered on their status (enabled or not), visibility, and the store they belong to. The `store` table in the database can tell you the `store_id` for each of the stores. Although this is not done here, a different XML file could easily be generated for each store by filtering on `store_id`. The class used to generate product collection can be found under `vendor/magento/module-catalog/Model/ResourceModel/Product`.

```Helper/Products.php
public function getFilteredProducts()
{
    $collection = $this->productCollectionFactory->create();
    $collection->addAttributeToSelect('*');
    $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
    $collection->addAttributeToFilter('visibility', ['eq' => Visibility::VISIBILITY_BOTH]);
    $collection->addStoreFilter(1);
    $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

    return $collection;
}
```

Code can then iterate over each of the products in the collection

```
foreach ($productCollection as $product) {
    $product->getName();
    $product->getProductUrl();
    $product->getDescription();
    $product->getImage();
    $product->getTaxClassId();
    $product->getCondition();
    $product->getData('ean');
    $product->getAttributeText('merk');
}
```

# Example XML file

Content for the XML file is generated in `Model\XmlFeed.php` and this is where most of the module's work takes place. To know what kind of content needs to be added to a Facebook feed, one can look at an example by going to your [Facebook Commerce Manager](https://business.facebook.com/commerce/) account, selecting a *catalog* of choice and then going to **Catalog | Data sources | Data feed**. Click **Next**, then select **No, I need a feed template**, and click **Next** again. An XML template can then be downloaded. It should look somewhat like the following:

```facebookfeedexample.xml
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item>
      <id>K456653443_0</id>
      <title>Blue Facebook T-Shirt (Unisex)</title>
      <description>A vibrant blue crewneck T-shirt for all shapes and sizes. Made from 100% cotton.</description>
      <availability>in stock</availability>
      <condition>new</condition>
      <price>10.00 USD</price>
      <link>https://www.facebook.com/facebook_t_shirt</link>
      <image_link>https://www.facebook.com/t_shirt_image_001.jpg</image_link>
      <brand>Facebook</brand>
      <quantity_to_sell_on_facebook>75</quantity_to_sell_on_facebook>
      <google_product_category>Apparel &amp; Accessories &gt; Clothing &gt; Shirts &amp; Tops</google_product_category>
      <sale_price>10.00 USD</sale_price>
      <item_group_id>K456653443_0</item_group_id>
      <gender>unisex</gender>
      <color>royal blue</color>
      <size>M</size>
      <age_group>adult</age_group>
      <material>cotton</material>
      <pattern>stripes</pattern>
    </item>
  </channel>
</rss>
```

Each XML text file should start with a header and end with a footer, as can be seen in above example. Between these comes the data for each of the products that will be added to the feed.

```
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
...
</channel></rss>
```

## Attribute mapping

Some of these values are required for every module, so these will be implemented first, after which the module can be tested. For some of the required values there is a corresponding attribute in a default Magento installation, while others still need to be added.

* `id`
* `title`
* `description`
* `availability`
* `condition`
* `price`
* `link`
* `image_link`
* `brand`

## Optional values

Other values are optional, being:

* `sale_price`
* `sale_price_effective_date`
* `item_group_id`
* `status`
* `additional_image_link`
* `google_product_category`
* `fb_product_category`
* `color`
* `gender`
* `size`
* `age_group`
* `material`
* `pattern`
* `shipping`
* `shipping_weight`
* `custom_label_0, custom_label_1, custom_label_2, custom_label_3, custom_label_4`
* `custom_number_0, custom_number_1, custom_number_2, custom_number_3, custom_number_4`
* `rich_text_description`
* `marked_for_product_launch`
* `product_type`
* `video`
* `additional_variant_attribute`
* `unit_price`
* `gtin`
* `mpn`
* `expiration_date`
* `return_policy_info`
* `mobile_link`
* `disabled_capabilities`
* `commerce_tax_category`

## Cron

A cron job takes care of generating the Facebook feed on a daily basis. This is configured in `/etc/crontab.xml` and the file in this case is set to be generated every day at noon. 

```
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/crontab.xsd">
    <group id="default">
        <job name="inatic_facebook_xml" instance="Inatic\FeedFacebook\Cron\GenerateFeed" method="execute">
            <schedule>0 12 * * *</schedule>
        </job>
    </group>
</config>
```

The time for a cron job to run is set as follows:

```
*    *    *    *    *  command to be executed
┬    ┬    ┬    ┬    ┬
│    │    │    │    │
│    │    │    │    │
│    │    │    │    └───── day of week (0 - 7) (0 or 7 are Sunday, or use names)
│    │    │    └────────── month (1 - 12)
│    │    └─────────────── day of month (1 - 31)
│    └──────────────────── hour (0 - 23)
└───────────────────────── min (0 - 59)
```


[Source](https://developers.facebook.com/docs/marketing-api/catalog/reference/#da-commerce)
