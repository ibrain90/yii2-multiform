<?php

namespace brain90\multiform\tests\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "clinic_contact_phone".
 *
 * @property int $id
 * @property int $clinic_contact_id
 * @property string $phone
 */
class ClinicContactPhone extends ActiveRecord
{
    /**
     * Вызван ли метод сохранения.
     *
     * @var bool
     */
    public $isSave = false;

    /**
     * Вызван ли метод валидации.
     *
     * @var bool
     */
    public $isValidate = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clinic_contact_phone';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clinic_contact_id'], 'integer'],
            [['phone'], 'string'],
        ];
    }

    public function attributes()
    {
        return ['id', 'clinic_contact_id', 'phone'];
    }

    /**
     * {@inheritdoc}
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        $this->isSave = true;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {
        $this->isValidate = true;
        return parent::validate($attributeNames, $clearErrors);
    }
}
