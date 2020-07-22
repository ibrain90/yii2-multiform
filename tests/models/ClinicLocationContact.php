<?php

namespace brain90\multiform\tests\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "clinic_location_contact".
 *
 * @property int $id
 * @property int $clinic_location_id
 * @property string $address
 * @property string $phone
 */
class ClinicLocationContact extends ActiveRecord
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
        return 'clinic_location_contact';
    }


    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clinic_location_id'], 'integer'],
            [['address', 'phone'], 'string'],
        ];
    }

    public function attributes()
    {
        return ['id', 'clinic_location_id', 'address', 'phone'];
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
