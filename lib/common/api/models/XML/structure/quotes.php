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

return [
    'Header' => 'site/quotes',
    'dependsOn' => ['site/languages', 'site/order_statuses', 'site/currencies', 'site/platforms', 'site/customers', 'site/products', 'site/countries'],
    'Data' => [
        'common\\models\\QuoteOrders' => [
            'xmlCollection' => 'QuoteOrders>QuoteOrder',
            'properties' => [
                'orders_status' => ['class' => 'IOOrderStatus'],
                'language_id' => ['class' => 'IOLanguageMap'],
            ],
            'withRelated' => [
                'quoteOrdersProducts' => [
                    'xmlCollection' => 'QuoteOrderProducts>QuoteOrderProduct',
                    'withRelated' => [
                        'quoteOrderProductAttributes' => [
                            'xmlCollection' => 'Attributes>Attribute',
                        ],
                    ],
                ],
                'quoteOrdersTotals' => [
                    'xmlCollection' => 'QuoteOrderTotals>QuoteOrderTotal',
                ],
                'quoteOrdersStatusHistory' => [
                    'xmlCollection' => 'QuoteOrderStatusHistory>QuoteOrderStatus',
                    'properties' => [
                        'orders_status_id' => ['class' => 'IOOrderStatus'],
                    ],
                ],
            ],
        ],
    ],
    'covered_tables' => ['quote_orders', 'quote_orders_products', 'quote_orders_products_attributes', 'quote_orders_total', 'quote_orders_status_history'],
];
