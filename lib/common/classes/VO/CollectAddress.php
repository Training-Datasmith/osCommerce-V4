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
namespace common\classes\VO;

final class Collect_Address
{
    /** @var string */
    private $street_address;
    /** @var string */
    private $city;
    /** @var string */
    private $state;
    /** @var string */
    private $postcode;
    /** @var string */
    private $country_name;
    /** @var string */
    private $country_iso2;
    /** @var string */
    private $country_iso3;
    /** @var string */
    private $warehouse;
    private function __construct()
    {
    }
    public static function create(string $street_address, string $city, string $state, string $postcode, string $country_name, string $country_iso2, string $country_iso3, string $warehouse = ''): self
    {
        $address = new self();
        $address->street_address = $street_address;
        $address->city = $city;
        $address->state = $state;
        $address->postcode = $postcode;
        $address->country_name = $country_name;
        $address->country_iso2 = $country_iso2;
        $address->country_iso3 = $country_iso3;
        $address->warehouse = $warehouse;
        return $address;
    }
    /**
     * @return string
     */
    public function get_street_address(): string
    {
        return $this->street_address;
    }
    /**
     * @return string
     */
    public function get_city(): string
    {
        return $this->city;
    }
    /**
     * @return string
     */
    public function get_state(): string
    {
        return $this->state;
    }
    /**
     * @return string
     */
    public function get_country_name(): string
    {
        return $this->country_name;
    }
    /**
     * @return string
     */
    public function get_country_iso2(): string
    {
        return $this->country_iso2;
    }
    /**
     * @return string
     */
    public function get_country_iso3(): string
    {
        return $this->country_iso3;
    }
    /**
     * @return string
     */
    public function get_postcode(): string
    {
        return $this->postcode;
    }
    /**
     * @return string
     */
    public function get_warehouse(): string
    {
        return $this->warehouse;
    }
}