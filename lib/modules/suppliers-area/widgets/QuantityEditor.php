<?php

declare(strict_types=1);

namespace suppliersarea\widgets;

class QuantityEditor extends \yii\base\Widget
{
    public $product;

    public function init()
    {
        parent::init();
    }

    public function run()
    {

        $uprid = $this->product->uprid;

        return $this->render('quantity-editor', [
            'value' => $this->product->suppliers_quantity,
            'b_uprid' => base64_encode($uprid),
            'uprid' => $uprid,
            'baseUrl' => \suppliersarea\SupplierModule::getInstance()->baseUrl,
        ]);

    }
}
