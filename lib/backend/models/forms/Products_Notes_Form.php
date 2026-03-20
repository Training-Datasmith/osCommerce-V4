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
namespace backend\models\forms;

use yii\base\Model;
/**
 * Class ProductsNotesForm
 * @package backend\models\forms
 */
final class Products_Notes_Form extends Model
{
    /** @var string $note */
    public $note;
    public function rules()
    {
        return [[['note'], 'required'], [['note'], 'string']];
    }
    /**
     * @inheritdoc
     */
    public function attribute_labels()
    {
        return ['note' => 'Product Note'];
    }
    /**
     * @return array
     */
    public function __to_array(): array
    {
        return json_decode(json_encode($this), true);
    }
}