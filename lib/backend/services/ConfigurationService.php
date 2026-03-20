<?php

declare (strict_types=1);
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
namespace backend\services;

use common\models\repositories\Configuration_Repository;
use common\services\Platforms_Configuration_Service;
final class Configuration_Service
{
    /** @var ConfigurationRepository */
    private $configuration_repository;
    /** @var PlatformsConfigurationService */
    private $platforms_configuration_service;
    public function __construct(Configuration_Repository $configuration_repository, Platforms_Configuration_Service $platforms_configuration_service)
    {
        $this->configuration_repository = $configuration_repository;
        $this->platforms_configuration_service = $platforms_configuration_service;
    }
    public function is_default_order_status_id_for_online_payment(int $order_status_id)
    {
        if (defined('DEFAULT_ONLINE_PAYMENT_ORDERS_STATUS_ID')) {
            if ((int) DEFAULT_ONLINE_PAYMENT_ORDERS_STATUS_ID === $order_status_id) {
                return true;
            }
        }
        return false;
    }
    public function is_default_order_status_id_for_online_payment_success(int $order_status_id)
    {
        if (defined('DEFAULT_ONLINE_PAYMENT_SUCCESS_ORDERS_STATUS_ID')) {
            if ((int) DEFAULT_ONLINE_PAYMENT_SUCCESS_ORDERS_STATUS_ID === $order_status_id) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param string $key
     * @param string $value
     * @return array|bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function update_by_key(string $key, string $value)
    {
        return $this->configuration_repository->update_by_key($key, $value);
    }
    /**
     * @param int $orderStatusId
     * @return array|bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function set_default_order_status_id_for_online_payment(int $order_status_id)
    {
        return $this->configuration_repository->update_by_key('DEFAULT_ONLINE_PAYMENT_ORDERS_STATUS_ID', (string) $order_status_id);
    }
    public function set_default_order_status_id_for_online_payment_success(int $order_status_id)
    {
        return $this->configuration_repository->update_by_key('DEFAULT_ONLINE_PAYMENT_SUCCESS_ORDERS_STATUS_ID', (string) $order_status_id);
    }
    /**
     * @param string $key
     * @param bool $asArray
     * @return array|\common\models\Configuration|null
     */
    public function find_by_key(string $key, bool $as_array = false)
    {
        return $this->configuration_repository->find_by_key($key, $as_array);
    }
    /**
     * @param string $key
     * @param int|null $platformId
     * @return string
     */
    public function find_value(string $key, ?int $platform_id = null): string
    {
        $value = '';
        $site_value = $this->find_by_key($key, true);
        if ($site_value) {
            $value = $site_value['configuration_value'];
        }
        if ($platform_id !== null) {
            $platform_value = $this->platforms_configuration_service->find_by_key($key, $platform_id, true);
            if ($platform_value) {
                $value = $platform_value['configuration_value'];
            }
        }
        return $value;
    }
}