*Disclaimer:* this module is based on the work of [adampmoss/MageFoxGoogleShopping](https://github.com/adampmoss/MageFoxGoogleShopping) and [Quazz/MageFoxGoogleShopping](https://github.com/Quazz/MageFoxGoogleShopping). 

This repository contains a Magento 2 module for generating a Facebook feed in XML format. It can be installed as follows:

```
cd app/code
# Clone the module
git clone https://github.com/inatic/magento2-googleshopping Inatic/FacebookFeed
# Upgrade the magento installation
$ bin/magento setup:upgrade
# Clean cache and generated code
$ bin/magento cache:clean
```

To access the feed, go to : www.website.com/inaticfacebookfeed/

# Example XML file

For information on the type of content that is expected in a Facebook feed, you can get an example XML file by going to your [Facebook Commerce Manager account](https://business.facebook.com/commerce/), selecting a *catalog* of choice and going to **Catalog | Data sources | Data feed**. Click **Next**, then select **No, I need a feed template**, and click **Next** again. An XML template can then be downloaded, and it should look somewhat like the following:

```facebookfeedexample.xml
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item>
      <id>K456653443_0</id>
      <title>Blue Facebook T-Shirt (Unisex)</title>
      <description>A vibrant blue crewneck T-shirt for all shapes and sizes.</description>
      <availability>in stock</availability>
      <condition>new</condition>
      <price>10.00 USD</price>
      <link>https://www.facebook.com/facebook_t_shirt</link>
      <image_link>https://www.facebook.com/t_shirt_image_001.jpg</image_link>
      <brand>Facebook</brand>
      <quantity_to_sell_on_facebook>75</quantity_to_sell_on_facebook>
      <google_product_category>Apparel &amp; Accessories &gt; Clothing</google_product_category>
      <sale_price>10.00 USD</sale_price>
      <item_group_id>K456653443_0</item_group_id>
      <gender>unisex</gender>
      <Color>royal blue</color>
      <size>M</size>
      <age_group>adult</age_group>
      <material>cotton</material>
      <pattern>stripes</pattern>
    </item>
  </channel>
</rss>
```

# Product attributes

An overview of required and optional attributes for the Facebook feed can be found on [Facebook's developer website](https://developers.facebook.com/docs/marketing-api/catalog/reference/#da-commerce). A default Magento 2 installation provides a number of attributes that can be used in the Facebook feed (`id`, `title`, `description`...), and other attributes can be added manually from the website's admin panel by going to **Stores | Attributes | Product**.

## Required attributes

At minimum the attributes in below table need to be provided for every product in the Facebook feed. Most of them have a corresponding attribute in a default Magento installation, but some (like `condition` and `brand`) need to be added manually. You can edit the `Model/XmlFeed.php` file to use attribute values already present in your Magento installation - we for example set `condition` to `new` for all products and already have a `brand` attribute, though it was named differently. 

| Facebook          | Magento           | Comment
|-------------------|-------------------|----------------    
| `id`              | `id`              |
| `title`           | `name`            |
| `description`     | `description`     |
| `availability`    | `isSaleable()`    | 
| `condition`       | `new`             | create
| `price`           | `regular_price`   |
| `link`            | `product_url`     |
| `image_link`      | `image`           |
| `brand`           | `brand`		    | create

## Optional attributes

A range of optional attributes can furthermore be added to a product. Details of these attributes can be found on [Facebook's developer website](https://developers.facebook.com/docs/marketing-api/catalog/reference/#da-commerce). Some of the corresponding Magento attributes need to be added from the website's admin panel.

| Facebook                          | Magento                   | Comment
|===================================|===========================|===================
| `sale_price`                      | `final_price`             |
| `gtin`                            | `ean`                     | create
| `size`                            | `size`                    | create
| `material`                        | `material`                | create
| `color`                           | `color`                   | create
| `sale_price_effective_date`       |							|
| `item_group_id`                   |							|
| `status`                          |							|
| `additional_image_link`           |							|
| `google_product_category`         | `google_product_category` | create
| `fb_product_category`             | `fb_product_category`     | create
| `gender`                          |                           |
| `age_group`                       |							|
| `pattern`                         |							|
| `shipping`                        |							|
| `shipping_weight`                 |							|
| `custom_label_0`                  | `categories`              |
| `custom_label_[1-4]`              |							|
| `custom_number_[0-4]`             |							|
| `rich_text_description`           |							|
| `marked_for_product_launch`       |							|
| `product_type`                    |							|
| `video`                           |							|
| `additional_variant_attribute`    |							|
| `unit_price`                      |							|
| `mpn`                             |							|
| `expiration_date`                 |							|
| `return_policy_info`              |							|
| `mobile_link`                     |							|
| `disabled_capabilities`           |							|
| `commerce_tax_category`           |							|

## Add to Facebook feed

Not all products in the store are added to the Facebook feed, only those specifically selected by the website administrator. This is done by creating an attribute named `add_to_facebook_feed` that has `Yes/No` as possible values and determines if a given product is added to the feed. This attribute is used in `Helper/Products.php` to filter the product collection from which the feed is made.

```Helper/Products.php
public function getFilteredProducts()
{
    $collection = $this->productCollectionFactory->create();
    $collection->addAttributeToSelect('*');
    $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
    $collection->addAttributeToFilter('visibility', ['eq' => Visibility::VISIBILITY_BOTH]);
    $collection->addAttributeToFilter('add_to_facebook_feed', true);
    $collection->addStoreFilter(1);
    $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

    return $collection;
}
```

# How the module works

The following is a short explanation of how this module works. The module only contains a few files, and part of these are just there to add the module to the installation and are common to all Magento modules, being:

| registration.php		| responsible for registering the namespace of the module with the system
| etc/module.xml		| contains the name and possible other details on the module
| composer.json			| provides information on the module as well as its requirements and dependencies, for a `composer` installation

## Route to this module

The module generates an XML file containing information on the products you would like to synchronize with your Facebook account. When Facebook requests the XML feed from your website, Magento's frontend needs to to know that a request for the XML feed has to be routed to this particular module. By default the URL of the request for the XML feed is `yoursite.com/inaticfacebookfeed`, though you could easily change this into something else. Guiding the request on the frontend to a particular module is done in `etc/frontend/routes.xml`, and it is the `frontName` that determines the text behind the domain part of the URL.

```etc/frontend/routes.xml
<router id="standard">
    <route id="inaticfacebookfeed" frontName="inaticfacebookfeed">
        <module name="Inatic_FacebookFeed"/>
    </route>
</router>
```

## Accept the request

Once the request arraives at the module, a controller at `Controller/Index/Index.php` accepts the request and takes care of further processing. The controller prepares a response for the request, sets a header to specify that the content being returned in XML data, and gets the content for the response from the `xmlFeed` object. The latter takes care of generating the actual XML code for each of the products that has its `add_to_facebook_feed` attribute set to `Yes`.

```Controller/Index/Index.php
$resultRaw->setHeader('Content-Type', 'text/xml');
$resultRaw->setContents($this->xmlFeed->getFeedFile());
return $resultRaw;
```

## Fetch product data

A *helper* object creates a collection of the products that are added to the feed. It filters them based on the store they belong to, their status (enabled of not) and visibility, and the value of their `add_to_facebook_feed` attribute (which was created above). The `store` table in the database can tell you the `store_id` for each of the stores in your installation. Although this is not done here, a different XML file could easily be generated for each store by filtering on `store_id`. The class used to generate product collections can be found under `vendor/magento/module-catalog/Model/ResourceModel/Product`.

```Helper/Products.php
public function getFilteredProducts()
{
    $collection = $this->productCollectionFactory->create();
    $collection->addAttributeToSelect('*');
    $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
    $collection->addAttributeToFilter('visibility', ['eq' => Visibility::VISIBILITY_BOTH]);
    $collection->addAttributeToFilter('add_to_facebook_feed', true);
    $collection->addStoreFilter(1);
    $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());

    return $collection;
}
```

## XmlFeed

Each XML text file should start with a header and end with a footer, with data for each product coming between these in `<item>` tags.

```
<?xml version="1.0"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
<title>Product Feed</title>
<link>https://mystore.com/</link>
<description>Product Feed for Facebook</description>

<item>...</item>
<item>...</item>

</channel>
</rss>
```

### Loop over product collection

The code then goes through each product in the collection created in `Helper/Products.php`, skipping over products that have no image or `ean` code. You might want to have a look at the `isValidProduct` function, because the requirement of having an `ean` code for each product might not apply to your store.

```
foreach ($productCollection as $product) {
    if ($this->isValidProduct($product)) {
        $xml .= "<item>".$this->buildProductXml($product)."</item>";
    }
}
```

### Product data
The `buildProductXml` function then takes care of fetching the relevant feed data for each of the products and formats this data according to Facebook requirements. All product data is accessible from the `$product` object, either by a convenience method like `getName` or by use of the `getAttributeText()` method.

```
$product->getName();
$product->getProductUrl();
$product->getDescription();
$product->getImage();
$product->getCondition();
$product->getData('ean');
```

### Cron

A cron job takes care of generating the Facebook feed on a daily basis. This is configured in `/etc/crontab.xml` and the file in this case is set to be generated every day at half an hour past midnight. As you can see, the object being instantiated is `Inatic\FeedFacebook\Cron\GenerateFeed` and the `execute` method is called on this object. Latter method executes `xmlFeed->getFeed()` to get the XML feed data and saves it to the `pub` directory in the Magento installation.

```
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/crontab.xsd">
    <group id="default">
        <job name="inatic_facebook_xml" instance="Inatic\FeedFacebook\Cron\GenerateFeed" method="execute">
            <schedule>30 0 * * *</schedule>
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

