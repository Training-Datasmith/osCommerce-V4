<?php

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
declare (strict_types=1);
namespace common\classes\events\frontend\attributes\Product_Attributes_Info;

class Product_Attributes_Info_Event
{
    /** @var array */
    private $product_attributes;
    private $customer;
    public function __construct(array $product_attributes, $customer)
    {
        $this->product_attributes = $product_attributes;
        $this->customer = $customer;
    }
    /**
     * @return array
     */
    public function get_product_attributes(): array
    {
        return $this->product_attributes;
    }
    /**
     * @param string $name
     * @param mixed|null $value
     * @return $this
     */
    public function set_product_attributes_property(string $name, $value = null): self
    {
        $this->product_attributes[$name] = $value;
        return $this;
    }
    /**
     * @param string $name
     * @return mixed|null
     */
    public function get_product_attributes_property(string $name)
    {
        return $this->product_attributes[$name] ?? null;
    }
    /**
     * @return mixed
     */
    public function get_customer()
    {
        return $this->customer;
    }
}