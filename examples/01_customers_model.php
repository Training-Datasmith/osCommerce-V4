<?php

declare(strict_types=1);

/**
 * Example: Working with the Customers ActiveRecord model in osCommerce V4.
 *
 * osCommerce V4 uses Yii2's ActiveRecord. The Customers model provides finders,
 * relations, and lifecycle hooks for the customers table.
 *
 * Prerequisites: Yii2 application bootstrap must have run (index.php or console entry).
 */

use common\models\Customers;

// 1. Find a customer by primary key
$customer = Customers::findIdentity(42);
if ($customer !== null) {
    echo $customer->customers_firstname . ' ' . $customer->customers_lastname . "\n";
}

// 2. Find by email address (checks main email + customersEmails relation)
$byEmail = Customers::findByEmail('user@example.com');
if ($byEmail !== null) {
    echo 'Found: ' . $byEmail->customers_email_address . "\n";
}

// 3. Use the scoped query class for complex finders
$activeCustomers = Customers::find()
    ->where(['customers_status' => Customers::STATUS_ACTIVE])
    ->andWhere(['opc_temp_account' => 0])
    ->limit(10)
    ->all();

foreach ($activeCustomers as $c) {
    echo $c->customers_id . ': ' . $c->customers_email_address . "\n";
}

// 4. Access relations
$customer = Customers::findIdentity(42);
if ($customer !== null) {
    // Eager-load the default address with country
    $address = $customer->defaultAddress;
    if ($address !== null) {
        echo $address->entry_city . ', ' . $address->country->countries_name . "\n";
    }

    // Get all orders
    foreach ($customer->orders as $order) {
        echo 'Order #' . $order->orders_id . "\n";
    }
}

// 5. Update a customer's password
$customer = Customers::findIdentity(42);
if ($customer !== null) {
    $customer->editCustomersPassword(
        \Yii::$app->security->generatePasswordHash('NewP@ssw0rd')
    );
    echo "Password updated.\n";
}

// 6. Check status constants
var_dump(Customers::STATUS_ACTIVE);  // int(1)
var_dump(Customers::STATUS_DISABLE); // int(0)
// These are candidates for a backed enum in PHP 8.1+:
// enum CustomerStatus: int { case Active = 1; case Disabled = 0; }
