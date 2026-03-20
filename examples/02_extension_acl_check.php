<?php

declare(strict_types=1);

/**
 * Example: Checking for optional extensions using the ACL helper in osCommerce V4.
 *
 * Extensions are optional feature packages under lib/common/extensions/. The
 * standard way to test whether an extension is installed and allowed is via
 * \common\helpers\Acl::checkExtensionAllowed(). This returns the extension
 * class or false.
 *
 * This pattern is used extensively in model methods, controllers, and views
 * to degrade gracefully when optional features are not present.
 */

// Example 1: Conditional logic for the Subscribers extension
/** @var \common\extensions\Subscribers\Subscribers|false $subscr */
$subscr = \common\helpers\Acl::checkExtensionAllowed('Subscribers', 'allowed');

if ($subscr) {
    // Extension is active — use its API
    echo "Subscribers extension is enabled.\n";
    // $subscr::onSaveCustomer($customer);
} else {
    echo "Subscribers extension is not installed.\n";
}

// Example 2: Multi-customer dealers extension
if ($dealers = \common\helpers\Acl::checkExtensionAllowed('DealersMultiCustomers', 'allowed')) {
    $customer = $dealers::findByMultiEmail('agent@example.com');
} else {
    $customer = \common\models\Customers::findByEmail('agent@example.com');
}

// Example 3: Split shipping address
if (\common\helpers\Acl::checkExtensionAllowed('SplitCustomerAddresses', 'allowed')) {
    // Use the split shipping address
    $shippingAddress = $customer?->defaultShippingAddress;
} else {
    // Fall back to the default (billing) address
    $shippingAddress = $customer?->defaultAddress;
}
