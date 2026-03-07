<?php

declare(strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */

namespace backend\design;

class WidgetsList
{
    public static function get($type)
    {
        $widgets = [];
        $method = $type;
        if ($type == 'invoice' || $type == 'creditnote' || $type == 'orders') {
            $method = 'orders';
        }

        if (method_exists(__CLASS__, $method)) {
            $widgets = self::$method();
        }
        if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
            $widgets = array_merge(self::main(), $widgets);
        }

        $path = DIR_FS_CATALOG . 'lib'
            . DIRECTORY_SEPARATOR . 'backend'
            . DIRECTORY_SEPARATOR . 'design'
            . DIRECTORY_SEPARATOR . 'boxes'
            . DIRECTORY_SEPARATOR . 'include';
        if (file_exists($path)) {
            $dir = scandir($path);
            foreach ($dir as $file) {
                if (file_exists($path . DIRECTORY_SEPARATOR . $file) && is_file($path . DIRECTORY_SEPARATOR . $file)) {
                    require $path . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        $widgets = array_merge($widgets, \common\helpers\Acl::getExtensionWidgets($type));

        if ($type == 'productListing') {
            $productListing = [];
            foreach ($widgets as $key => $widget) {
                if ($widget['type'] == 'productListing') {
                    $productListing[] = $widget;
                }
            }
            $widgets = $productListing;
        }

        if ($type == 'backendOrder') {
            $backendOrder = [];
            foreach ($widgets as $key => $widget) {
                if ($widget['type'] == 'backendOrder') {
                    $backendOrder[] = $widget;
                }
            }
            $widgets = $backendOrder;
        }

        if ($type == 'backendOrdersList') {
            $backendOrder = [];
            foreach ($widgets as $key => $widget) {
                if ($widget['type'] == 'backendOrdersList') {
                    $backendOrder[] = $widget;
                }
            }
            $widgets = $backendOrder;
        }

        $widgets = array_merge($widgets, \backend\design\Groups::getWidgetGroups($type));

        return $widgets;
    }

    private static function product()
    {
        $widgets = [];
        $widgets[] = ['name' => 'title', 'title' => PRODUCTS_WIDGETS, 'description' => '', 'type' => 'product'];
        $widgets[] = ['name' => 'product\Name', 'title' => TEXT_PRODUCTS_NAME, 'description' => '', 'type' => 'product', 'class' => 'name'];
        $widgets[] = ['name' => 'product\Images', 'title' => TEXT_PRODUCTS_IMAGES, 'description' => '', 'type' => 'product', 'class' => 'images'];
        $widgets[] = ['name' => 'product\ImagesAdditional', 'title' => TEXT_ADDITIONAL_IMAGES, 'description' => '', 'type' => 'product', 'class' => 'images'];
        $widgets[] = ['name' => 'product\Attributes', 'title' => TEXT_PRODUCTS_ATTRIBUTES, 'description' => '', 'type' => 'product', 'class' => 'attributes'];
        //        $widgets[] = array('name' => 'product\Inventory', 'title' => BOX_CATALOG_INVENTORY, 'description' => '', 'type' => 'product', 'class' => 'attributes'); // not work
        //        $widgets[] = array('name' => 'product\MultiInventory', 'title' => 'Multi Inventory', 'description' => '', 'type' => 'product', 'class' => 'multi-inventory-products'); // not work
        $widgets[] = ['name' => 'product\Bundle', 'title' => TEXT_PRODUCTS_BUNDLE, 'description' => '', 'type' => 'product', 'class' => 'bundle'];
        $widgets[] = ['name' => 'product\InBundles', 'title' => TEXT_PRODUCTS_IN_BUNDLE, 'description' => '', 'type' => 'product', 'class' => 'in-bundles'];
        $widgets[] = ['name' => 'product\Price', 'title' => TEXT_PRODUCTS_PRICE, 'description' => '', 'type' => 'product', 'class' => 'price'];
        $widgets[] = ['name' => 'product\QuantityDiscounts', 'title' => QUANTITY_DISCOUNTS, 'description' => '', 'type' => 'product', 'class' => 'price'];
        $widgets[] = ['name' => 'product\Quantity', 'title' => TEXT_QUANTITY_INPUT, 'description' => '', 'type' => 'product', 'class' => 'quantity'];
        $widgets[] = ['name' => 'product\Stock', 'title' => TEXT_STOCK_INDICATION, 'description' => '', 'type' => 'product', 'class' => 'stock'];
        $widgets[] = ['name' => 'product\Buttons', 'title' => TEXT_BUY_BUTTON, 'description' => '', 'type' => 'product', 'class' => 'buttons'];
        //$widgets[] = array('name' => 'product\WishlistButton', 'title' => TEXT_WISHLIST_BUTTON, 'description' => '', 'type' => 'product', 'class' => 'buttons');
        $widgets[] = ['name' => 'product\Description', 'title' => TEXT_PRODUCTS_DESCRIPTION, 'description' => '', 'type' => 'product', 'class' => 'description'];
        $widgets[] = ['name' => 'product\DescriptionShort', 'title' => TEXT_PRODUCTS_DESCRIPTION_SHORT, 'description' => '', 'type' => 'product', 'class' => 'description'];
        $widgets[] = ['name' => 'product\Reviews', 'title' => TEXT_PRODUCTS_REVIEWS, 'description' => '', 'type' => 'product', 'class' => 'reviews'];
        $widgets[] = ['name' => 'product\Properties', 'title' => TEXT_PRODUCTS_PROPERTIES, 'description' => '', 'type' => 'product', 'class' => 'properties'];
        $widgets[] = ['name' => 'product\Model', 'title' => TABLE_HEADING_PRODUCTS_MODEL, 'description' => '', 'type' => 'product', 'class' => 'properties'];
        $widgets[] = ['name' => 'product\Weight', 'title' => TEXT_WEIGHT, 'description' => '', 'type' => 'product', 'class' => 'properties'];
        $widgets[] = ['name' => 'product\PropertiesIcons', 'title' => TEXT_PROPERTIES_ICONS, 'description' => '', 'type' => 'product', 'class' => 'properties'];
        $widgets[] = ['name' => 'product\AlsoPurchased', 'title' => TEXT_ALSO_PURCHASED, 'description' => '', 'type' => 'product', 'class' => 'also-purchased'];
        $widgets[] = ['name' => 'product\Brand', 'title' => TEXT_LABEL_BRAND, 'description' => '', 'type' => 'product', 'class' => 'brands'];
        $widgets[] = ['name' => 'product\Video', 'title' => TEXT_VIDEO, 'description' => '', 'type' => 'product', 'class' => 'video'];
        $widgets[] = ['name' => 'product\Documents', 'title' => TAB_DOCUMENTS, 'description' => '', 'type' => 'product', 'class' => 'description'];
        $widgets[] = ['name' => 'product\Configurator', 'title' => TEXT_CONFIGURATOR, 'description' => '', 'type' => 'product', 'class' => 'configurator'];
        $widgets[] = ['name' => 'product\CustomersActivity', 'title' => TEXT_ACTIVE_CUSTOMERS, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\AvailableInWarehouses', 'title' => TEXT_AVAILABLE_AT_WAREHOUSES, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\Dimensions', 'title' => TEXT_DIMENSION, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'DeliveryDay', 'title' => DELIVERY_DAY, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'cart\FreeDelivery', 'title' => TEXT_FREE_DELIVERY, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\BazaarvoiceRatingSummary', 'title' => TEXT_BAZAARVOICE_RATING_SUMMARY, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\BazaarvoiceReview', 'title' => TEXT_BAZAARVOICE_REVIEWS, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\CompareButton', 'title' => TEXT_COMPARE_BUTTON, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\PriceFrom', 'title' => TEXT_PRICE_FROM, 'description' => '', 'type' => 'product', 'class' => ''];
        $widgets[] = ['name' => 'product\PayPalPayLater', 'title' => (defined('TEXT_PAYPAL_PARTNER_PAY_LATER_PLAN') ? TEXT_PAYPAL_PARTNER_PAY_LATER_PLAN : 'PAYPAL_PARTNER_PAY_LATER_PLAN'), 'description' => '', 'type' => 'product', 'class' => ''];

        return $widgets;
    }

    private static function inform()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => INFOPAGES_WIDGETS, 'description' => '', 'type' => 'inform'];
        $widgets[] = ['name' => 'info\Title', 'title' => TEXT_TITLE_, 'description' => '', 'type' => 'inform', 'class' => 'title'];
        $widgets[] = ['name' => 'info\Content', 'title' => TEXT_CONTENT, 'description' => '', 'type' => 'inform', 'class' => 'content'];
        $widgets[] = ['name' => 'contact\ContactForm', 'title' => CONTACT_FORM, 'description' => '', 'type' => 'general', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'info\Image', 'title' => TEXT_IMAGE_, 'description' => '', 'type' => 'inform', 'class' => 'images'];
        $widgets[] = ['name' => 'info\DescriptionShort', 'title' => TEXT_PRODUCTS_DESCRIPTION_SHORT, 'description' => '', 'type' => 'inform', 'class' => 'description'];
        $widgets[] = ['name' => 'contact\Map', 'title' => TEXT_MAP, 'description' => '', 'type' => 'inform', 'class' => 'map'];
        $widgets[] = ['name' => 'contact\Contacts', 'title' => TEXT_CONTACTS, 'description' => '', 'type' => 'inform', 'class' => 'contacts'];
        $widgets[] = ['name' => 'contact\StreetView', 'title' => GOOGLE_STREET_VIEW, 'description' => '', 'type' => 'inform', 'class' => 'street-view'];

        return $widgets;
    }

    private static function catalog()
    {
        $widgets = [];

        //$widgets[] = array('name' => 'product\WeddingRegistryButton', 'title' => TEXT_WEDDING_REGISTRY, 'description' => '', 'type' => 'catalog', 'class' => 'buttons');

        $widgets[] = ['name' => 'title', 'title' => CATALOGS_WIDGETS, 'description' => '', 'type' => 'catalog'];
        $widgets[] = ['name' => 'catalog\Title', 'title' => TEXT_TITLE_, 'description' => '', 'type' => 'catalog', 'class' => 'title'];
        $widgets[] = ['name' => 'catalog\Description', 'title' => TEXT_CATEGORY_DESCRIPTION, 'description' => '', 'type' => 'catalog', 'class' => 'description'];
        $widgets[] = ['name' => 'catalog\Image', 'title' => TEXT_CATEGORY_IMAGE, 'description' => '', 'type' => 'catalog', 'class' => 'image'];
        //$widgets[] = array('name' => 'PagingBar', 'title' => TEXT_PAGING_BAR, 'description' => '', 'type' => 'catalog', 'class' => 'paging-bar');
        $widgets[] = ['name' => 'catalog\Paging', 'title' => TEXT_PAGING, 'description' => '', 'type' => 'catalog', 'class' => 'paging-bar'];
        $widgets[] = ['name' => 'catalog\CountsItems', 'title' => COUNTS_ITEMS_ON_PAGE, 'description' => '', 'type' => 'catalog', 'class' => 'paging-bar'];
        $widgets[] = ['name' => 'Listing', 'title' => TEXT_PRODUCT_LISTING, 'description' => '', 'type' => 'catalog', 'class' => 'listing'];
        //$widgets[] = array('name' => 'ListingFunctionality', 'title' => TEXT_LISTING_FUNCTIONALITY_BAR, 'description' => '', 'type' => 'catalog', 'class' => 'listing-functionality');
        $widgets[] = ['name' => 'catalog\ListingLook', 'title' => TEXT_LISTING_LOOK, 'description' => '', 'type' => 'catalog', 'class' => 'listing-functionality'];
        $widgets[] = ['name' => 'catalog\CompareButton', 'title' => TEXT_COMPARE_BUTTON, 'description' => '', 'type' => 'catalog', 'class' => 'listing-functionality'];
        $widgets[] = ['name' => 'catalog\Sorting', 'title' => TEXT_SORTING, 'description' => '', 'type' => 'catalog', 'class' => 'listing-functionality'];
        $widgets[] = ['name' => 'catalog\ItemsOnPage', 'title' => TEXT_ITEMS_ON_PAGE, 'description' => '', 'type' => 'catalog', 'class' => 'listing-functionality'];
        $widgets[] = ['name' => 'Categories', 'title' => TEXT_CATEGORIES, 'description' => '', 'type' => 'catalog', 'class' => 'categories'];
        $widgets[] = ['name' => 'Filters', 'title' => TEXT_FILTERS, 'description' => '', 'type' => 'catalog', 'class' => 'filters'];
        $widgets[] = ['name' => 'catalog\B2bAddButton', 'title' => B2B_ADD_BUTTON, 'description' => '', 'type' => 'catalog', 'class' => ''];
        $widgets[] = ['name' => 'catalog\AdditionalImages', 'title' => TEXT_ADDITIONAL_IMAGES, 'description' => '', 'type' => 'catalog', 'class' => ''];

        return $widgets;
    }

    private static function cart()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => SHOPPING_CART_WIDGETS, 'description' => '', 'type' => 'cart'];
        $widgets[] = ['name' => 'cart\ContinueBtn', 'title' => CONTINUE_BUTTON, 'description' => '', 'type' => 'cart', 'class' => 'continue-button'];
        $widgets[] = ['name' => 'cart\CheckoutBtn', 'title' => CHECKOUT_BUTTON, 'description' => '', 'type' => 'cart', 'class' => 'checkout-button'];
        $widgets[] = ['name' => 'cart\Products', 'title' => TABLE_HEADING_PRODUCTS, 'description' => '', 'type' => 'cart', 'class' => 'products'];
        $widgets[] = ['name' => 'cart\SubTotal', 'title' => SUB_TOTAL_AND_GIFT_WRAP_PRICE, 'description' => '', 'type' => 'cart', 'class' => 'price'];
        $widgets[] = ['name' => 'cart\GiftCertificate', 'title' => GIFT_CERTIFICATE, 'description' => '', 'type' => 'cart', 'class' => 'gift-certificate'];
        $widgets[] = ['name' => 'cart\DiscountCoupon', 'title' => DISCOUNT_COUPON, 'description' => '', 'type' => 'cart', 'class' => 'discount-coupon'];
        $widgets[] = ['name' => 'cart\OrderReference', 'title' => TEXT_ORDER_REFERENCE, 'description' => '', 'type' => 'cart', 'class' => 'order-reference'];
        $widgets[] = ['name' => 'cart\GiveAway', 'title' => BOX_CATALOG_GIVE_AWAY, 'description' => '', 'type' => 'cart', 'class' => 'give-away'];
        $widgets[] = ['name' => 'cart\ShippingEstimator', 'title' => SHOW_SHIPPING_ESTIMATOR_TITLE, 'description' => '', 'type' => 'cart', 'class' => 'shipping-estimator'];
        $widgets[] = ['name' => 'cart\OrderTotal', 'title' => ORDER_PRICE_TOTAL, 'description' => '', 'type' => 'cart', 'class' => 'order-total'];
        $widgets[] = ['name' => 'cart\CartTabs', 'title' => TEXT_CART_TABS, 'description' => '', 'type' => 'cart', 'class' => ''];
        $widgets[] = ['name' => 'cart\CreditAmount', 'title' => CREDIT_AMOUNT, 'description' => '', 'type' => 'cart', 'class' => ''];
        $widgets[] = ['name' => 'cart\FreeDelivery', 'title' => TEXT_FREE_DELIVERY, 'description' => '', 'type' => 'cart', 'class' => ''];
        $widgets[] = ['name' => 'DeliveryDay', 'title' => DELIVERY_DAY, 'description' => '', 'type' => 'cart', 'class' => ''];
        $widgets[] = ['name' => 'cart\DependedProducts', 'title' => TEXT_DEPENDED_PRODUCTS, 'description' => '', 'type' => 'cart', 'class' => ''];
        $widgets[] = ['name' => 'cart\PayPalPayLater', 'title' => TEXT_PAYPAL_PARTNER_PAY_LATER_PLAN, 'description' => '', 'type' => 'cart', 'class' => ''];

        return $widgets;
    }

    private static function quote()
    {
        $widgets = [];

        $widgets[] = ['name' => 'quote\Products', 'title' => TABLE_HEADING_PRODUCTS, 'description' => 'Quote', 'type' => 'quote', 'class' => 'products'];
        $widgets[] = ['name' => 'cart\CartTabs', 'title' => TEXT_CART_TABS, 'description' => '', 'type' => 'quote', 'class' => ''];
        $widgets[] = ['name' => 'quote\CheckoutBtn', 'title' => CHECKOUT_BUTTON, 'description' => 'Quote', 'type' => 'quote', 'class' => 'checkout-button'];

        return $widgets;
    }

    private static function sample()
    {
        $widgets = [];

        $widgets[] = ['name' => 'cart\CartTabs', 'title' => TEXT_CART_TABS, 'description' => '', 'type' => 'sample', 'class' => ''];

        return $widgets;
    }

    private static function wishlist()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_WISHLIST, 'description' => '', 'type' => 'wishlist'];
        $widgets[] = ['name' => 'cart\CartTabs', 'title' => TEXT_CART_TABS, 'description' => '', 'type' => 'wishlist', 'class' => ''];
        $widgets[] = ['name' => 'account\Wishlist', 'title' => TEXT_WISHLIST, 'description' => '', 'type' => 'wishlist', 'class' => ''];

        return $widgets;
    }

    private static function checkout()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_CHECKOUT, 'description' => '', 'type' => 'checkout'];
        $widgets[] = ['name' => 'checkout\ContinueBtn', 'title' => CONTINUE_BUTTON, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\Shipping', 'title' => TEXT_CHOOSE_SHIPPING_METHOD, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\ShippingAddress', 'title' => ENTRY_SHIPPING_ADDRESS, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\BillingAddress', 'title' => TEXT_BILLING_ADDRESS, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\PaymentMethod', 'title' => TEXT_SELECT_PAYMENT_METHOD, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\CreditAmount', 'title' => CREDIT_AMOUNT, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\ContactInformation', 'title' => CATEGORY_CONTACT, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\Comments', 'title' => TABLE_HEADING_COMMENTS, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\Totals', 'title' => TEXT_TOTALS, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'cart\Products', 'title' => TABLE_HEADING_PRODUCTS, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'quote\Products', 'title' => TEXT_QUOTE_PRODUCTS, 'description' => '', 'type' => 'checkout', 'class' => 'products'];
        $widgets[] = ['name' => 'checkout\CreateAccount', 'title' => TEXT_CREATE_ACCOUNT, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\ShippingChoice', 'title' => TEXT_SHIPPING_CHOICE, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\Terms', 'title' => TEXT_TERMS_CONDITIONS, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'DeliveryDay', 'title' => DELIVERY_DAY, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'cart\FreeDelivery', 'title' => TEXT_FREE_DELIVERY, 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\LoginOnForm', 'title' => 'Login On Form', 'description' => '', 'type' => 'checkout', 'class' => ''];
        $widgets[] = ['name' => 'checkout\PayPalPayLater', 'title' => TEXT_PAYPAL_PARTNER_PAY_LATER_PLAN, 'description' => '', 'type' => 'cart', 'class' => ''];

        return $widgets;
    }

    private static function confirmation()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_CONFIRMATION, 'description' => '', 'type' => 'confirmation'];
        $widgets[] = ['name' => 'checkout\ConfirmBtn', 'title' => TEXT_CONFIRMATION_BUTTON, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\ShippingConfirm', 'title' => TEXT_CHOOSE_SHIPPING_METHOD, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\ShippingAddressConfirm', 'title' => ENTRY_SHIPPING_ADDRESS, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\BillingAddressConfirm', 'title' => TEXT_BILLING_ADDRESS, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\PaymentMethodConfirm', 'title' => TEXT_SELECT_PAYMENT_METHOD, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\CommentsConfirm', 'title' => TABLE_HEADING_COMMENTS, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\Totals', 'title' => TEXT_TOTALS, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'cart\Products', 'title' => TABLE_HEADING_PRODUCTS, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'checkout\EditBtn', 'title' => TEXT_EDIT_LINK, 'description' => '', 'type' => 'confirmation', 'class' => ''];
        $widgets[] = ['name' => 'quote\Products', 'title' => TEXT_QUOTE_PRODUCTS, 'description' => '', 'type' => 'confirmation', 'class' => 'products'];
        $widgets[] = ['name' => 'checkout\ContactConfirm', 'title' => TEXT_CONTACT_INFO, 'description' => '', 'type' => 'confirmation', 'class' => ''];

        return $widgets;
    }

    private static function success()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => CHECKOUT_SUCCESS_WIDGETS, 'description' => '', 'type' => 'success'];
        $widgets[] = ['name' => 'success\ContinueBtn', 'title' => CONTINUE_BUTTON, 'description' => '', 'type' => 'success', 'class' => 'continue-button'];
        $widgets[] = ['name' => 'success\PrintBtn', 'title' => PRINT_BUTTON, 'description' => '', 'type' => 'success', 'class' => 'print-button'];
        $widgets[] = ['name' => 'success\Download', 'title' => IMAGE_DOWNLOAD, 'description' => '', 'type' => 'success', 'class' => 'download-button'];
        $widgets[] = ['name' => 'order\Products', 'title' => 'Products', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\BillingAddress', 'title' => 'BillingAddress', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\DeliveryAddress', 'title' => 'DeliveryAddress', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\Email', 'title' => 'Customer Email', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\Name', 'title' => 'Customer Name', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\Telephone', 'title' => 'Customer Phone', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\OrderDate', 'title' => 'OrderDate', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\OrderDateTime', 'title' => 'OrderDateTime', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\PaymentMethod', 'title' => 'PaymentMethod', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\ShippingMethod', 'title' => 'ShippingMethod', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\Totals', 'title' => 'Totals', 'description' => '', 'type' => 'success', 'class' => ''];
        $widgets[] = ['name' => 'order\OrderNumber', 'title' => 'Order Number', 'description' => '', 'type' => 'success', 'class' => ''];

        return $widgets;
    }

    private static function email()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TABLE_HEADING_EMAIL_TEMPLATES, 'description' => '', 'type' => 'email'];
        $widgets[] = ['name' => 'email\Title', 'title' => TEXT_TITLE_, 'description' => '', 'type' => 'email', 'class' => 'title'];
        $widgets[] = ['name' => 'email\Date', 'title' => TEXT_CURRENT_DATE, 'description' => '', 'type' => 'email', 'class' => 'date'];
        $widgets[] = ['name' => 'email\Content', 'title' => TEXT_CONTENT, 'description' => '', 'type' => 'email', 'class' => 'content'];
        $widgets[] = ['name' => 'email\BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'email', 'class' => 'block-box'];
        $widgets[] = ['name' => 'Banner', 'title' => TEXT_BANNER, 'description' => '', 'type' => 'email', 'class' => 'banner'];
        $widgets[] = ['name' => 'email\Logo', 'title' => TEXT_LOGO, 'description' => '', 'type' => 'email', 'class' => 'logo'];
        $widgets[] = ['name' => 'email\Image', 'title' => TEXT_IMAGE_, 'description' => '', 'type' => 'email', 'class' => 'image'];
        $widgets[] = ['name' => 'Text', 'title' => TEXT_TEXT, 'description' => '', 'type' => 'email', 'class' => 'text'];
        $widgets[] = ['name' => 'Import', 'title' => IMPORT_BLOCK, 'description' => '', 'type' => 'email', 'class' => 'import'];
        $widgets[] = ['name' => 'Copyright', 'title' => COPYRIGHT, 'description' => '', 'type' => 'email', 'class' => 'copyright'];

        return $widgets;
    }

    private static function orders()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => INVOICE_TEMPLATE, 'description' => '', 'type' => 'invoice'];
        $widgets[] = ['name' => 'title', 'title' => TABLE_HEADING_ORDER, 'description' => '', 'type' => 'invoice'];
        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'invoice', 'class' => 'block-box'];
        $widgets[] = ['name' => 'Logo', 'title' => TEXT_LOGO, 'description' => '', 'type' => 'invoice', 'class' => 'logo'];
        $widgets[] = ['name' => 'email\Image', 'title' => TEXT_IMAGE_, 'description' => '', 'type' => 'invoice', 'class' => 'image'];
        $widgets[] = ['name' => 'Text', 'title' => TEXT_TEXT, 'description' => '', 'type' => 'invoice', 'class' => 'text'];
        $widgets[] = ['name' => 'invoice\Products', 'title' => TABLE_HEADING_PRODUCTS, 'description' => '', 'type' => 'invoice', 'class' => 'products'];
        $widgets[] = ['name' => 'invoice\StoreAddress', 'title' => TEXT_STORE_ADDRESS, 'description' => '', 'type' => 'invoice', 'class' => 'store-address'];
        $widgets[] = ['name' => 'invoice\CompanyTaxDetails', 'title' => CATEGORY_COMPANY, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\StorePhone', 'title' => TEXT_STORE_PHONE, 'description' => '', 'type' => 'invoice', 'class' => 'store-phone'];
        $widgets[] = ['name' => 'invoice\StoreEmail', 'title' => TEXT_STORE_EMAIL, 'description' => '', 'type' => 'invoice', 'class' => 'store-email'];
        $widgets[] = ['name' => 'invoice\StoreSite', 'title' => TEXT_STORE_SITE, 'description' => '', 'type' => 'invoice', 'class' => 'store-site'];
        $widgets[] = ['name' => 'invoice\ShippingAddress', 'title' => ENTRY_SHIPPING_ADDRESS, 'description' => '', 'type' => 'invoice', 'class' => 'shipping-address'];
        $widgets[] = ['name' => 'invoice\BillingAddress', 'title' => TEXT_BILLING_ADDRESS, 'description' => '', 'type' => 'invoice', 'class' => 'shipping-address'];
        $widgets[] = ['name' => 'invoice\ShippingMethod', 'title' => TEXT_CHOOSE_SHIPPING_METHOD, 'description' => '', 'type' => 'invoice', 'class' => 'shipping-method'];
        $widgets[] = ['name' => 'invoice\AddressQrcode', 'title' => ADDRESS_QRCODE, 'description' => '', 'type' => 'invoice', 'class' => 'address-qrcode'];
        $widgets[] = ['name' => 'invoice\OrderBarcode', 'title' => ORDER_BARCODE, 'description' => '', 'type' => 'invoice', 'class' => 'order-barcode'];
        $widgets[] = ['name' => 'invoice\CustomerName', 'title' => TEXT_CUSTOMER_NAME, 'description' => '', 'type' => 'invoice', 'class' => 'customer-name'];
        $widgets[] = ['name' => 'invoice\CustomerEmail', 'title' => TEXT_CUSTOMER_EMAIL, 'description' => '', 'type' => 'invoice', 'class' => 'customer-email'];
        $widgets[] = ['name' => 'invoice\CustomerPhone', 'title' => TEXT_CUSTOMER_PHONE, 'description' => '', 'type' => 'invoice', 'class' => 'customer-phone'];
        $widgets[] = ['name' => 'invoice\Totals', 'title' => TRXT_TOTALS, 'description' => '', 'type' => 'invoice', 'class' => 'totals'];
        $widgets[] = ['name' => 'invoice\OrderId', 'title' => TEXT_ORDER_ID, 'description' => '', 'type' => 'invoice', 'class' => 'order-id'];
        $widgets[] = ['name' => 'invoice\InvoiceId', 'title' => TEXT_INVOICE_PREFIX.'_'.TEXT_CREDIT_NOTE_PREFIX, 'description' => '', 'type' => 'invoice', 'class' => 'invoice-id'];
        $widgets[] = ['name' => 'invoice\PaymentDate', 'title' => TEXT_PAYMENT_DATE, 'description' => '', 'type' => 'invoice', 'class' => 'payment-date'];
        $widgets[] = ['name' => 'invoice\PaymentMethod', 'title' => TEXT_SELECT_PAYMENT_METHOD, 'description' => '', 'type' => 'invoice', 'class' => 'payment-method'];
        $widgets[] = ['name' => 'invoice\PaidMark', 'title' => TEXT_PAID_MARK, 'description' => '', 'type' => 'invoice', 'class' => 'paid-mark'];
        $widgets[] = ['name' => 'invoice\UnpaidMark', 'title' => TEXT_UNPAID_MARK, 'description' => '', 'type' => 'invoice', 'class' => 'unpaid-mark'];
        $widgets[] = ['name' => 'invoice\Container', 'title' => TEXT_CONTAINER, 'description' => '', 'type' => 'invoice', 'class' => 'container'];
        $widgets[] = ['name' => 'Import', 'title' => IMPORT_BLOCK, 'description' => '', 'type' => 'invoice', 'class' => 'import'];
        $widgets[] = ['name' => 'Copyright', 'title' => COPYRIGHT, 'description' => '', 'type' => 'invoice', 'class' => 'copyright'];
        $widgets[] = ['name' => 'invoice\IpAddress', 'title' => TEXT_IP_ADDRESS, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\TotalProductsQty', 'title' => TEXT_TOTAL_PRODUCTS_QTY, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\TotalProductsDelivered', 'title' => 'Total Products Delivered', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\TotalProductsCanceled', 'title' => 'Total Products Canceled', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\OrderType', 'title' => TEXT_ORDER_TYPE, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\Transactions', 'title' => TEXT_TRANSACTIONS, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\PurchaseOrderNo', 'title' => 'Purchase Order Number', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\Comments', 'title' => 'Comments', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\Currency', 'title' => 'Currency', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'order\OrderDate', 'title' => 'Order Date', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'order\OrderDateTime', 'title' => 'Order Date and Time', 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'order\PageNumber', 'title' => 'Page Number', 'description' => '', 'type' => 'invoice', 'class' => ''];

        return $widgets;
    }

    private static function packingslip()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_PACKINGSLIP, 'description' => '', 'type' => 'packingslip'];
        $widgets[] = ['name' => 'order\OrderDate', 'title' => 'Order Date', 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'packingslip', 'class' => 'block-box'];
        $widgets[] = ['name' => 'email\Image', 'title' => TEXT_IMAGE_, 'description' => '', 'type' => 'packingslip', 'class' => 'image'];
        $widgets[] = ['name' => 'Text', 'title' => TEXT_TEXT, 'description' => '', 'type' => 'packingslip', 'class' => 'text'];
        $widgets[] = ['name' => 'packingslip\Products', 'title' => TABLE_HEADING_PRODUCTS, 'description' => '', 'type' => 'packingslip', 'class' => 'products'];
        $widgets[] = ['name' => 'invoice\StoreAddress', 'title' => TEXT_STORE_ADDRESS, 'description' => '', 'type' => 'packingslip', 'class' => 'store-address'];
        $widgets[] = ['name' => 'invoice\CompanyTaxDetails', 'title' => CATEGORY_COMPANY, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\ShippingMethod', 'title' => TEXT_CHOOSE_SHIPPING_METHOD, 'description' => '', 'type' => 'packingslip', 'class' => 'shipping-method'];
        $widgets[] = ['name' => 'invoice\StorePhone', 'title' => TEXT_STORE_PHONE, 'description' => '', 'type' => 'packingslip', 'class' => 'store-phone'];
        $widgets[] = ['name' => 'invoice\StoreEmail', 'title' => TEXT_STORE_EMAIL, 'description' => '', 'type' => 'packingslip', 'class' => 'store-email'];
        $widgets[] = ['name' => 'invoice\StoreSite', 'title' => TEXT_STORE_SITE, 'description' => '', 'type' => 'packingslip', 'class' => 'store-site'];
        $widgets[] = ['name' => 'invoice\ShippingAddress', 'title' => ENTRY_SHIPPING_ADDRESS, 'description' => '', 'type' => 'packingslip', 'class' => 'shipping-address'];
        $widgets[] = ['name' => 'invoice\BillingAddress', 'title' => TEXT_BILLING_ADDRESS, 'description' => '', 'type' => 'packingslip', 'class' => 'shipping-address'];
        $widgets[] = ['name' => 'invoice\AddressQrcode', 'title' => ADDRESS_QRCODE, 'description' => '', 'type' => 'packingslip', 'class' => 'address-qrcode'];
        $widgets[] = ['name' => 'invoice\OrderBarcode', 'title' => ORDER_BARCODE, 'description' => '', 'type' => 'packingslip', 'class' => 'order-barcode'];
        $widgets[] = ['name' => 'invoice\CustomerName', 'title' => TEXT_CUSTOMER_NAME, 'description' => '', 'type' => 'packingslip', 'class' => 'customer-name'];
        $widgets[] = ['name' => 'invoice\CustomerEmail', 'title' => TEXT_CUSTOMER_EMAIL, 'description' => '', 'type' => 'packingslip', 'class' => 'customer-email'];
        $widgets[] = ['name' => 'invoice\CustomerPhone', 'title' => TEXT_CUSTOMER_PHONE, 'description' => '', 'type' => 'packingslip', 'class' => 'customer-phone'];
        $widgets[] = ['name' => 'invoice\OrderId', 'title' => TEXT_ORDER_ID, 'description' => '', 'type' => 'packingslip', 'class' => 'order-id'];
        $widgets[] = ['name' => 'invoice\PaymentMethod', 'title' => TEXT_SELECT_PAYMENT_METHOD, 'description' => '', 'type' => 'packingslip', 'class' => 'payment-method'];
        $widgets[] = ['name' => 'invoice\Container', 'title' => TEXT_CONTAINER, 'description' => '', 'type' => 'packingslip', 'class' => 'container'];
        $widgets[] = ['name' => 'Import', 'title' => IMPORT_BLOCK, 'description' => '', 'type' => 'packingslip', 'class' => 'import'];
        $widgets[] = ['name' => 'Copyright', 'title' => COPYRIGHT, 'description' => '', 'type' => 'packingslip', 'class' => 'copyright'];
        $widgets[] = ['name' => 'invoice\IpAddress', 'title' => TEXT_IP_ADDRESS, 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\TotalProductsQty', 'title' => TEXT_TOTAL_PRODUCTS_QTY, 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\TotalProductsDelivered', 'title' => 'Total Products Delivered', 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\TotalProductsCanceled', 'title' => 'Total Products Canceled', 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\OrderType', 'title' => TEXT_ORDER_TYPE, 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\Transactions', 'title' => TEXT_TRANSACTIONS, 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\PurchaseOrderNo', 'title' => 'Purchase Order Number', 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\Comments', 'title' => 'Comments', 'description' => '', 'type' => 'packingslip', 'class' => ''];
        $widgets[] = ['name' => 'invoice\Currency', 'title' => 'Currency', 'description' => '', 'type' => 'packingslip', 'class' => ''];

        return $widgets;
    }

    private static function gift()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_GIFT_CARD, 'description' => '', 'type' => 'gift'];
        $widgets[] = ['name' => 'gift\Form', 'title' => TEXT_FORM, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\AmountView', 'title' => AMOUNT_VIEW, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\MessageView', 'title' => MESSAGE_VIEW, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\CodeView', 'title' => CODE_VIEW, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'invoice\StoreAddress', 'title' => TEXT_STORE_ADDRESS, 'description' => '', 'type' => 'gift', 'class' => 'store-address'];
        $widgets[] = ['name' => 'invoice\CompanyTaxDetails', 'title' => CATEGORY_COMPANY, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\StorePhone', 'title' => TEXT_STORE_PHONE, 'description' => '', 'type' => 'gift', 'class' => 'store-phone'];
        $widgets[] = ['name' => 'invoice\StoreEmail', 'title' => TEXT_STORE_EMAIL, 'description' => '', 'type' => 'gift', 'class' => 'store-email'];
        $widgets[] = ['name' => 'invoice\StoreSite', 'title' => TEXT_STORE_SITE, 'description' => '', 'type' => 'gift', 'class' => 'store-site'];
        $widgets[] = ['name' => 'info\Title', 'title' => TEXT_TITLE_, 'description' => '', 'type' => 'gift', 'class' => 'title'];
        $widgets[] = ['name' => 'gift\Card', 'title' => TEXT_GIFT_CARD, 'description' => '', 'type' => 'gift', 'class' => 'title'];

        return $widgets;
    }

    private static function gift_card()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_GIFT_CARD, 'description' => '', 'type' => 'gift'];
        $widgets[] = ['name' => 'gift\Form', 'title' => TEXT_FORM, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\AmountView', 'title' => AMOUNT_VIEW, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\MessageView', 'title' => MESSAGE_VIEW, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\CodeView', 'title' => CODE_VIEW, 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'invoice\StoreAddress', 'title' => TEXT_STORE_ADDRESS, 'description' => '', 'type' => 'gift', 'class' => 'store-address'];
        $widgets[] = ['name' => 'invoice\CompanyTaxDetails', 'title' => CATEGORY_COMPANY, 'description' => '', 'type' => 'invoice', 'class' => ''];
        $widgets[] = ['name' => 'invoice\StorePhone', 'title' => TEXT_STORE_PHONE, 'description' => '', 'type' => 'gift', 'class' => 'store-phone'];
        $widgets[] = ['name' => 'invoice\StoreEmail', 'title' => TEXT_STORE_EMAIL, 'description' => '', 'type' => 'gift', 'class' => 'store-email'];
        $widgets[] = ['name' => 'invoice\StoreSite', 'title' => TEXT_STORE_SITE, 'description' => '', 'type' => 'gift', 'class' => 'store-site'];
        $widgets[] = ['name' => 'info\Title', 'title' => TEXT_TITLE_, 'description' => '', 'type' => 'gift', 'class' => 'title'];

        $widgets[] = ['name' => 'gift\AmountViewPdf', 'title' => AMOUNT_VIEW . ' pdf', 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\MessageViewPdf', 'title' => MESSAGE_VIEW . ' pdf', 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'gift\CodeViewPdf', 'title' => CODE_VIEW . ' pdf', 'description' => '', 'type' => 'gift', 'class' => 'contact-form'];

        return $widgets;
    }

    private static function main()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => GENERAL_WIDGETS, 'description' => '', 'type' => 'general'];
        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'general', 'class' => 'block-box'];
        $widgets[] = ['name' => 'Tabs', 'title' => TEXT_TABS, 'description' => '', 'type' => 'general', 'class' => 'tabs'];
        $widgets[] = ['name' => 'Brands', 'title' => TEXT_BRANDS, 'description' => '', 'type' => 'general', 'class' => 'brands'];
        $widgets[] = ['name' => 'Bestsellers', 'title' => TEXT_BESTSELLERS, 'description' => '', 'type' => 'general', 'class' => 'bestsellers'];
        $widgets[] = ['name' => 'Banner', 'title' => TEXT_BANNER, 'description' => '', 'type' => 'general', 'class' => 'banner'];
        $widgets[] = ['name' => 'SpecialsProducts', 'title' => TEXT_SPECIALS_PRODUCTS, 'description' => '', 'type' => 'general', 'class' => 'specials-products'];
        $widgets[] = ['name' => 'FeaturedProducts', 'title' => BOX_CATALOG_FEATURED, 'description' => '', 'type' => 'general', 'class' => 'featured-products'];
        $widgets[] = ['name' => 'NewProducts', 'title' => TEXT_NEW_PRODUCTS, 'description' => '', 'type' => 'general', 'class' => 'new-products'];
        $widgets[] = ['name' => 'NewProductsWithParams', 'title' => TEXT_NEW_PRODUCTS_PARAMS, 'description' => '', 'type' => 'general', 'class' => 'new-products-params'];
        $widgets[] = ['name' => 'ViewedProducts', 'title' => VIEWED_PRODUCTS, 'description' => '', 'type' => 'general', 'class' => 'viewed-products'];
        $widgets[] = ['name' => 'Logo', 'title' => TEXT_LOGO, 'description' => '', 'type' => 'general', 'class' => 'logo'];
        $widgets[] = ['name' => 'Image', 'title' => TEXT_IMAGE_, 'description' => '', 'type' => 'general', 'class' => 'image'];
        $widgets[] = ['name' => 'Video', 'title' => TEXT_VIDEO, 'description' => '', 'type' => 'general', 'class' => 'video'];
        $widgets[] = ['name' => 'Text', 'title' => TEXT_TEXT, 'description' => '', 'type' => 'general', 'class' => 'text'];
        $widgets[] = ['name' => 'Heading', 'title' => TEXT_SEO_HEADING, 'description' => '', 'type' => 'general', 'class' => ''];
        $widgets[] = ['name' => 'InfoPage', 'title' => INFORMATION_PAGES, 'description' => '', 'type' => 'general', 'class' => 'text'];
        $widgets[] = ['name' => 'Reviews', 'title' => TEXT_REVIEWS, 'description' => '', 'type' => 'general', 'class' => 'reviews'];
        $widgets[] = ['name' => 'Menu', 'title' => TEXT_MENU, 'description' => '', 'type' => 'general', 'class' => 'menu'];
        $widgets[] = ['name' => 'Languages', 'title' => TEXT_LANGUAGES_, 'description' => '', 'type' => 'general', 'class' => 'languages'];
        $widgets[] = ['name' => 'Currencies', 'title' => TEXT_CURRENCIES, 'description' => '', 'type' => 'general', 'class' => 'currencies'];
        $widgets[] = ['name' => 'Search', 'title' => TEXT_SEARCH, 'description' => '', 'type' => 'general', 'class' => 'search'];
        $widgets[] = ['name' => 'Cart', 'title' => TEXT_CART, 'description' => '', 'type' => 'general', 'class' => 'cart'];
        $widgets[] = ['name' => 'Breadcrumb', 'title' => TEXT_BREADCRUMB, 'description' => '', 'type' => 'general', 'class' => 'breadcrumb'];
        $widgets[] = ['name' => 'Compare', 'title' => TEXT_COMPARE, 'description' => '', 'type' => 'general', 'class' => 'compare'];
        //$widgets[] = array('name' => 'Address', 'title' => 'Store Address', 'description' => '', 'type' => 'general', 'class' => 'contacts');
        //$widgets[] = array('name' => 'invoice\StoreAddress', 'title' => TEXT_STORE_ADDRESS, 'description' => '', 'type' => 'general', 'class' => 'store-address');
        $widgets[] = ['name' => 'Copyright', 'title' => COPYRIGHT, 'description' => '', 'type' => 'general', 'class' => 'copyright'];
        $widgets[] = ['name' => 'Account', 'title' => TEXT_ACCOUNT, 'description' => '', 'type' => 'general', 'class' => 'account'];
        $widgets[] = ['name' => 'Import', 'title' => IMPORT_BLOCK, 'description' => '', 'type' => 'general', 'class' => 'import'];

        if (\frontend\design\Info::hasBlog()) {
            $widgets[] = ['name' => 'BlogSidebar', 'title' => TEXT_BLOG_SIDEBAR, 'description' => '', 'type' => 'general', 'class' => 'menu'];
            $widgets[] = ['name' => 'BlogContent', 'title' => TEXT_BLOG_CONTENT, 'description' => '', 'type' => 'general', 'class' => 'content'];
        }
        $widgets[] = ['name' => 'Quote', 'title' => TEXT_QUOTE_CART, 'description' => '', 'type' => 'general', 'class' => 'quote'];
        $widgets[] = ['name' => 'StoreName', 'title' => TEXT_STORE_NAME, 'description' => '', 'type' => 'general', 'class' => ''];
        $widgets[] = ['name' => 'WidgetsAria', 'title' => 'Widgets Aria', 'description' => '', 'type' => 'general', 'class' => ''];
        $widgets[] = ['name' => 'GoogleReviews', 'title' => TEXT_GOOGLE_REVIEWS, 'description' => '', 'type' => 'general', 'class' => 'contact-form'];
        $widgets[] = ['name' => 'CustomerData', 'title' => TEXT_CUSTOMER_DATA, 'description' => '', 'type' => 'general', 'class' => ''];
        //$widgets[] = array('name' => 'ProductElement', 'title' => TEXT_PRODUCT_ELEMENT, 'description' => '', 'type' => 'general', 'class' => ''); //this widget has to parent product component

        if (\common\helpers\Acl::checkExtensionAllowed('Trustpilot', 'allowed')) {
            $client = new \common\extensions\Trustpilot\Trustpilot();
            if ($client->anyAPIKeyExists()) {
                $widgets[] = ['name' => 'TrustPilotReviews', 'title' => EXT_TRUSTPILOT_TRUSTPILOT_REVIEWS, 'description' => '', 'type' => 'general', 'class' => 'content'];
            }
        }
        $widgets[] = ['name' => 'SocialLinks', 'title' => TEXT_SOCIAL_LINKS, 'description' => '', 'type' => 'general', 'class' => ''];
        $widgets[] = ['name' => 'FiltersSimple', 'title' => FILTERS_SIMPLE, 'description' => '', 'type' => 'general', 'class' => ''];
        //$widgets[] = array('name' => 'CartPopUp', 'title' => 'CartPopUp', 'description' => '', 'type' => 'general', 'class' => '');

        $widgets[] = ['name' => 'BatchProducts', 'title' => TEXT_WIDGET_BATCH_PRODUCTS, 'description' => '', 'type' => 'general', 'class' => ''];
        $widgets[] = ['name' => 'BatchSelectedProducts', 'title' => TEXT_WIDGET_BATCH_SELECTED_PRODUCTS, 'description' => '', 'type' => 'general', 'class' => ''];
        $widgets[] = ['name' => 'TopText', 'title' => TEXT_TOP_TEXT, 'description' => '', 'type' => 'general', 'class' => 'toptext'];
        //$widgets[] = array('name' => 'UnsupportedBrowser', 'title' => TEXT_UNSUPPORTED_BROWSER, 'description' => '', 'type' => 'general', 'class' => '');

        $widgets[] = ['name' => 'VisitorCountry', 'title' => TEXT_VISITOR_COUNTRY, 'description' => '', 'type' => 'general', 'class' => 'account'];

        //$widgets[] = array('name' => 'Wristband', 'title' => 'wristband', 'description' => '', 'type' => 'general');

        return $widgets;
    }

    private static function pdf()
    {
        $widgets = [];

        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'pdf', 'class' => 'block-box'];
        $widgets[] = ['name' => 'Logo', 'title' => TEXT_LOGO, 'description' => '', 'type' => 'pdf', 'class' => 'logo'];
        $widgets[] = ['name' => 'Image', 'title' => TEXT_IMAGE_, 'description' => '', 'type' => 'pdf', 'class' => 'image'];
        $widgets[] = ['name' => 'Text', 'title' => TEXT_TEXT, 'description' => '', 'type' => 'pdf', 'class' => 'text'];
        $widgets[] = ['name' => 'invoice\StoreAddress', 'title' => TEXT_STORE_ADDRESS, 'description' => '', 'type' => 'pdf', 'class' => 'store-address'];
        $widgets[] = ['name' => 'invoice\CompanyTaxDetails', 'title' => CATEGORY_COMPANY, 'description' => '', 'type' => 'invoice', 'class' => ''];
        //$widgets[] = array('name' => 'Copyright', 'title' => COPYRIGHT, 'description' => '', 'type' => 'pdf', 'class' => 'copyright');
        $widgets[] = ['name' => 'invoice\StorePhone', 'title' => TEXT_STORE_PHONE, 'description' => '', 'type' => 'pdf', 'class' => 'store-phone'];
        $widgets[] = ['name' => 'invoice\StoreEmail', 'title' => TEXT_STORE_EMAIL, 'description' => '', 'type' => 'pdf', 'class' => 'store-email'];
        $widgets[] = ['name' => 'invoice\StoreSite', 'title' => TEXT_STORE_SITE, 'description' => '', 'type' => 'pdf', 'class' => 'store-site'];
        $widgets[] = ['name' => 'invoice\Container', 'title' => TEXT_CONTAINER, 'description' => '', 'type' => 'pdf', 'class' => 'container'];
        $widgets[] = ['name' => 'pdf\ProductElement', 'title' => TEXT_PRODUCT_ELEMENT, 'description' => '', 'type' => 'pdf', 'class' => ''];
        $widgets[] = ['name' => 'pdf\CategoryName', 'title' => CATEGORY_NAME, 'description' => '', 'type' => 'pdf', 'class' => ''];
        $widgets[] = ['name' => 'pdf\CategoryImage', 'title' => TEXT_CATEGORY_IMAGE, 'description' => '', 'type' => 'pdf', 'class' => ''];
        $widgets[] = ['name' => 'pdf\CategoryDescription', 'title' => TEXT_CATEGORY_DESCRIPTION, 'description' => '', 'type' => 'pdf', 'class' => ''];
        $widgets[] = ['name' => 'pdf\PageNumber', 'title' => TEXT_PAGE_NUMBER, 'description' => '', 'type' => 'pdf', 'class' => ''];
        $widgets[] = ['name' => 'Import', 'title' => IMPORT_BLOCK, 'description' => '', 'type' => 'pdf', 'class' => 'import'];

        return $widgets;
    }

    private static function account()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_ACCOUNT, 'description' => '', 'type' => 'account'];
        $widgets[] = ['name' => 'account\AccountLink', 'title' => ACCOUNT_LINK, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\LastOrder', 'title' => DATE_LAST_ORDERED, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderCount', 'title' => ORDER_COUNT, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\TotalOrdered', 'title' => TOTAL_ORDERED, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\CreditAmount', 'title' => CREDIT_AMOUNT, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\CreditAmountHistory', 'title' => CREDIT_AMOUNT_HISTORY, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\ApplyCertificate', 'title' => APPLY_CERTIFICATE_FORM, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\CustomerData', 'title' => TEXT_CUSTOMER_DATA, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\AccountEdit', 'title' => EDIT_MAIN_DETAILS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\ChangePassword', 'title' => TEXT_CHANGE_PASSWORD, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\PrimaryAddress', 'title' => TEXT_PRIMARY_ADDRESS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\AddressBook', 'title' => TEXT_ADDRESS_BOOK, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\EditAddress', 'title' => TEXT_EDIT_ADDRESS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\Tokens', 'title' => TEXT_TOKENS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrdersHistory', 'title' => TEXT_ORDERS_HISTORY, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderData', 'title' => TEXT_ORDER_DATA, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderProducts', 'title' => ORDER_PRODUCTS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderSubTotals', 'title' => ORDER_SUBTOTALS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderHistory', 'title' => ORDER_HISTORY, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderInvoiceButton', 'title' => ORDER_INVOICE_BUTTON, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderNotPaid', 'title' => ORDER_NOT_PAID, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderPayButton', 'title' => ORDER_PAY_BUTTON, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderReorderButton', 'title' => REORDER_BUTTON, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderDownload', 'title' => IMAGE_DOWNLOAD, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderCancelAndReorder', 'title' => CANCEL_AND_REORDER_BUTTON, 'description' => '', 'type' => 'account', 'class' => ''];
        //$widgets[] = array('name' => 'account\Wishlist', 'title' => TEXT_WISHLIST, 'description' => '', 'type' => 'account', 'class' => '');
        $widgets[] = ['name' => 'account\Reviews', 'title' => BOX_CATALOG_REVIEWS, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderHeading', 'title' => TEXT_ORDER_HEADING, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\OrderTracking', 'title' => TEXT_ORDER_TRACKING, 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\Subscription', 'title' => 'Subscription', 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\GiftCards', 'title' => 'Gift cards', 'description' => '', 'type' => 'account', 'class' => ''];
        $widgets[] = ['name' => 'account\SpendBalance', 'title' => TEXT_SPEND_BALANCE, 'description' => '', 'type' => 'account', 'class' => ''];

        return $widgets;
    }

    private static function trade_form()
    {
        $widgets = [];

        $widgets[] = ['name' => 'account\CustomerAdditionalField', 'title' => 'CustomerAdditionalField', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\BackButton', 'title' => 'BackButton', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\SaveButton', 'title' => 'SaveButton', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\PdfButton', 'title' => 'PdfButton', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\AddressesList', 'title' => 'AddressesList', 'description' => '', 'type' => 'trade_form', 'class' => ''];

        return $widgets;
    }

    private static function trade_form_pdf()
    {
        $widgets = [];

        $widgets[] = ['name' => 'account\CustomerAdditionalField', 'title' => 'CustomerAdditionalField', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\BackButton', 'title' => 'BackButton', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\SaveButton', 'title' => 'SaveButton', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\PdfButton', 'title' => 'PdfButton', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\AddressesList', 'title' => 'AddressesList', 'description' => '', 'type' => 'trade_form', 'class' => ''];
        $widgets[] = ['name' => 'account\CombinedField', 'title' => 'CombinedField', 'description' => '', 'type' => 'trade_form', 'class' => ''];

        return $widgets;
    }

    private static function login()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => 'Login', 'description' => '', 'type' => 'login'];
        $widgets[] = ['name' => 'login\Returning', 'title' => 'Returning customer', 'description' => '', 'type' => 'login', 'class' => ''];
        $widgets[] = ['name' => 'login\Register', 'title' => 'Register', 'description' => '', 'type' => 'login', 'class' => ''];
        $widgets[] = ['name' => 'login\Socials', 'title' => 'Socials login', 'description' => '', 'type' => 'login', 'class' => ''];
        //$widgets[] = array('name' => 'login\Guest', 'title' => 'Guest login', 'description' => '', 'type' => 'login', 'class' => '');
        $widgets[] = ['name' => 'quote\FastOrder', 'title' => 'Fast Order', 'description' => '', 'type' => 'login', 'class' => ''];
        $widgets[] = ['name' => 'checkout\GuestBtn', 'title' => 'Guest Button', 'description' => '', 'type' => 'login', 'class' => ''];
        $widgets[] = ['name' => 'checkout\CreateBtn', 'title' => 'Create Button', 'description' => '', 'type' => 'login', 'class' => ''];

        return $widgets;
    }

    private static function password_forgotten()
    {
        $widgets = [];

        $widgets[] = ['name' => 'login\PasswordForgotten', 'title' => 'Password Forgotten', 'description' => '', 'type' => 'login', 'class' => ''];

        return $widgets;
    }

    private static function index()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => HOME_PAGE_WIDGETS, 'description' => '', 'type' => 'index'];
        $widgets[] = ['name' => 'TopCategories', 'title' => TEXT_CATEGORIES, 'description' => '', 'type' => 'index', 'class' => 'categories'];
        $widgets[] = ['name' => 'login\Returning', 'title' => 'Returning customer', 'description' => '', 'type' => 'index', 'class' => 'categories'];
        $widgets[] = ['name' => 'login\Register', 'title' => 'Register', 'description' => '', 'type' => 'index', 'class' => 'categories'];
        //$widgets[] = array('name' => 'login\Enquire', 'title' => 'Enquire', 'description' => '', 'type' => 'index', 'class' => 'categories');

        return $widgets;
    }

    private static function sitemap()
    {
        $widgets = [];

        $widgets[] = ['name' => 'sitemap\Categories', 'title' => 'Categories', 'description' => '', 'type' => 'sitemap', 'class' => ''];
        $widgets[] = ['name' => 'sitemap\InfoPages', 'title' => 'Info Pages', 'description' => '', 'type' => 'sitemap', 'class' => ''];

        return $widgets;
    }

    private static function reviews()
    {
        $widgets = [];

        $widgets[] = ['name' => 'reviews\Heading', 'title' => 'Heading', 'description' => '', 'type' => 'reviews', 'class' => ''];
        $widgets[] = ['name' => 'reviews\Content', 'title' => 'Content', 'description' => '', 'type' => 'reviews', 'class' => ''];

        return $widgets;
    }

    private static function compare()
    {
        $widgets = [];

        $widgets[] = ['name' => 'catalog\Compare', 'title' => TEXT_COMPARE, 'description' => '', 'type' => 'compare', 'class' => ''];

        return $widgets;
    }

    private static function productListing()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => TEXT_LISTING_ITEM, 'description' => '', 'type' => 'productListing'];
        $widgets[] = ['name' => 'productListing\name', 'title' => TEXT_PRODUCT_NAME, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\image', 'title' => TEXT_IMAGE, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\stock', 'title' => BOX_SETTINGS_BOX_STOCK_INDICATION, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\description', 'title' => TEXT_PRODUCTS_DESCRIPTION_SHORT, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\model', 'title' => TEXT_MODEL, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\properties', 'title' => TEXT_PRODUCTS_PROPERTIES, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\rating', 'title' => TEXT_RATING, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\ratingCounts', 'title' => TEXT_RATING_COUNTS, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\price', 'title' => TABLE_HEADING_PRODUCTS_PRICE, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\priceFrom', 'title' => 'Price From', 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\buyButton', 'title' => TEXT_BUY_BUTTON, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\quoteButton', 'title' => REQUEST_FOR_QUOTE_BUTTON, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\qtyInput', 'title' => TEXT_QUANTITY_INPUT, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\viewButton', 'title' => TEXT_VIEW_BUTTON, 'description' => '', 'type' => 'productListing', 'class' => ''];
        //$widgets[] = array('name' => 'productListing\wishlistButton', 'title' => TEXT_WISHLIST_BUTTON, 'description' => '', 'type' => 'productListing', 'class' => '');
        $widgets[] = ['name' => 'productListing\compare', 'title' => TEXT_COMPARE, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\attributes', 'title' => TEXT_ATTRIBUTES, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\paypalButton', 'title' => TEXT_PAYPAL_BUTTON, 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'Import', 'title' => IMPORT_BLOCK, 'description' => '', 'type' => 'productListing', 'class' => 'import'];
        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'productListing', 'class' => 'block-box'];
        $widgets[] = ['name' => 'batchSelect', 'title' => 'batchSelect', 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'batchRemove', 'title' => 'batchRemove', 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\productGroup', 'title' => 'Product Group', 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\BazaarvoiceRatingInline', 'title' => 'Bazaarvoice Rating Inline', 'description' => '', 'type' => 'productListing', 'class' => ''];
        //$widgets[] = array('name' => 'productListing\amazonButton', 'title' => 'amazon button', 'description' => '', 'type' => 'productListing', 'class' => '');
        $widgets[] = ['name' => 'productListing\brand', 'title' => 'Brand', 'description' => '', 'type' => 'productListing', 'class' => ''];
        $widgets[] = ['name' => 'productListing\internalName', 'title' => 'Internal product name', 'description' => '', 'type' => 'productListing', 'class' => ''];

        return $widgets;
    }

    private static function backendOrder()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => 'Backend Order', 'description' => '', 'type' => 'backendOrder'];
        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'backendOrder', 'class' => 'block-box'];
        $widgets[] = ['name' => 'AddressDetailsHolder', 'title' => 'AddressDetails', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Asset', 'title' => 'Asset', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'AssignTransactions', 'title' => 'AssignTransactions', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Attributes', 'title' => 'Attributes', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Buttons', 'title' => 'Buttons', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'CommentTemplate', 'title' => 'CommentTemplate', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'CreditNotes', 'title' => 'CreditNotes', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Customer', 'title' => 'Customer', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'DeleteOrder', 'title' => 'DeleteOrder', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Downloads', 'title' => 'Downloads', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'ExternalOrders', 'title' => 'ExternalOrders', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'ExtraCustomData', 'title' => 'ExtraCustomData', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'FoundTransactionsList', 'title' => 'FoundTransactionsList', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'InvoiceComments', 'title' => 'InvoiceComments', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'MapHolder', 'title' => 'Map', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'MapJS', 'title' => 'MapJS', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Notification', 'title' => 'Notification', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'NSHelper', 'title' => 'NSHelper', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'OrderComments', 'title' => 'OrderComments', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'OrderTotals', 'title' => 'OrderTotals', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Paying', 'title' => 'Paying', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'PaymentActions', 'title' => 'PaymentActions', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'PaymentExtraInfo', 'title' => 'PaymentExtraInfo', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'PrintLabel', 'title' => 'PrintLabel', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Product', 'title' => 'Product', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'ProductAssets', 'title' => 'ProductAssets', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'ProductsHolder', 'title' => 'Products', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'RequestHolder', 'title' => 'Request', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'ShippingExtraInfo', 'title' => 'ShippingExtraInfo', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'SMS', 'title' => 'SMS', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'StatusComments', 'title' => 'StatusComments', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'StatusList', 'title' => 'StatusList', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'StatusTable', 'title' => 'StatusTable', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Toolbar', 'title' => 'Toolbar', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'TotalsItem', 'title' => 'TotalsItem', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Transactions', 'title' => 'Transactions', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Trustpilot', 'title' => 'Trustpilot', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'Unprocessed', 'title' => 'Unprocessed', 'description' => '', 'type' => 'backendOrder', 'class' => ''];
        $widgets[] = ['name' => 'ClosableBox', 'title' => 'ClosableBox', 'description' => '', 'type' => 'backendOrder', 'class' => ''];

        return $widgets;
    }

    private static function backendOrdersList()
    {
        $widgets = [];

        $widgets[] = ['name' => 'title', 'title' => ORDERS_LIST_ITEMS, 'description' => '', 'type' => 'backendOrdersList'];
        $widgets[] = ['name' => 'BlockBox', 'title' => TEXT_BLOCK, 'description' => '', 'type' => 'backendOrdersList', 'class' => 'block-box'];
        $widgets[] = ['name' => 'backendOrdersList\BatchCheckbox', 'title' => BATCH_CHECKBOX_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderMarkersCell', 'title' => ORDER_MARKERS_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\CustomerColumnCell', 'title' => CUSTOMER_COLUMN_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderTotalsCell', 'title' => ORDER_TOTALS_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderDescriptionCell', 'title' => ORDER_DESCRIPTION_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderPurchaseCell', 'title' => ORDER_PURCHASE_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderStatusCell', 'title' => ORDER_STATUS_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\NeighbourCell', 'title' => NEIGHBOUR_CELL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\CustomerGender', 'title' => CUSTOMER_GENDER, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\CustomerName', 'title' => TEXT_CUSTOMER_NAME, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\CustomerEmail', 'title' => TEXT_CUSTOMER_EMAIL, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderLocation', 'title' => ORDER_LOCATION, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\WalkinOrder', 'title' => WALKIN_ORDER, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'Html_box', 'title' => 'html', 'description' => '', 'type' => 'backendOrdersList', 'class' => 'html'];
        $widgets[] = ['name' => 'backendOrdersList\OrderId', 'title' => TEXT_ORDER_ID, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderProducts', 'title' => ORDER_PRODUCTS, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\Platform', 'title' => TABLE_HEADING_PLATFORM, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\PaymentMethod', 'title' => TEXT_INFO_PAYMENT_METHOD, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\ShippingMethod', 'title' => TEXT_CHOOSE_SHIPPING_METHOD, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];
        $widgets[] = ['name' => 'backendOrdersList\OrderPurchase', 'title' => TABLE_HEADING_DATE_PURCHASED, 'description' => '', 'type' => 'backendOrdersList', 'class' => ''];

        return $widgets;
    }
}
