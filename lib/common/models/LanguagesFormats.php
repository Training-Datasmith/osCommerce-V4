<?php

declare(strict_types=1);

namespace common\models;

class LanguagesFormats extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'languages_formats';
    }

}
