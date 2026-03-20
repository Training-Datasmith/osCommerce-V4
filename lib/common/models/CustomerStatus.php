<?php

declare(strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 */

namespace common\models;

/**
 * Customer account status values, replacing the integer constants on the Customers model.
 *
 * Previously expressed as:
 *   Customers::STATUS_ACTIVE  = 1
 *   Customers::STATUS_DISABLE = 0
 *
 * These are stored as integers in the customers.customers_status column, so
 * the enum is backed by int.
 *
 * Usage:
 *   CustomerStatus::Active->value   // 1
 *   CustomerStatus::Disabled->value // 0
 *
 *   // In a query:
 *   Customers::find()->where(['customers_status' => CustomerStatus::Active->value])
 *
 * @since 2024
 */
enum CustomerStatus: int
{
    /** Account is active and can log in. */
    case Active = 1;

    /** Account has been disabled by an administrator. */
    case Disabled = 0;

    /**
     * Returns the human-readable label for display purposes.
     *
     * @return string  'Active' or 'Disabled'.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active   => 'Active',
            self::Disabled => 'Disabled',
        };
    }

    /**
     * Creates a CustomerStatus from a raw integer value.
     *
     * @param  int  $value  The raw DB value (0 or 1).
     * @return self
     * @throws \ValueError  If $value is not a valid CustomerStatus.
     */
    public static function fromInt(int $value): self
    {
        return self::from($value);
    }
}
