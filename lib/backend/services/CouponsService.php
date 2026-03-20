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

use common\models\Coupons;
use common\models\repositories\Coupon_Repository;
class Coupons_Service
{
    /** @var CouponsRepository */
    private $coupons_repository;
    public function __construct(Coupon_Repository $coupons_repository)
    {
        $this->coupons_repository = $coupons_repository;
    }
    public function set_active(Coupons $coupon)
    {
        if (!is_object($coupon)) {
            throw new \RuntimeException('Coupon error data.');
        }
        if ($coupon->coupon_active === Coupons::STATUS_ACTIVE) {
            return true;
        }
        return $this->coupons_repository->edit($coupon, ['coupon_active' => Coupons::STATUS_ACTIVE]);
    }
    public function set_disable(Coupons $coupon)
    {
        if (!is_object($coupon)) {
            throw new \RuntimeException('Coupon error data.');
        }
        if ($coupon->coupon_active === Coupons::STATUS_DISABLE) {
            return true;
        }
        return $this->coupons_repository->edit($coupon, ['coupon_active' => Coupons::STATUS_DISABLE]);
    }
    public function get_by_id(int $id)
    {
        return $this->coupons_repository->get_by_id($id);
    }
}