<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "provider".
 *
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $second_phone
 * @property string|null $whatsapp Número de WhatsApp para solicitudes de compra
 * @property string|null $email
 * @property int $business_id
 * @property string|null $payment_method
 * @property string|null $account
 * @property string|null $credit_days
 * @property string|null $rfc
 * @property string|null $business_name
 * @property string|null $advantages
 * @property string|null $disadvantages
 * @property string|null $observations
 *
 * @property Business $business
 */
class Provider extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'provider';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['business_name', 'business_id'], 'required'],
            [['business_name', 'name'], 'trim'], // Eliminar espacios al inicio y final
            [['business_id'], 'integer'],
            ['payment_method', 'each', 'rule' => ['string']],
            ['payment_method', 'required', 'message' => 'Debe seleccionar al menos un método de pago'],
            [['name', 'address', 'phone', 'second_phone', 'email', 'account', 'credit_days', 'rfc', 'business_name', 'advantages', 'disadvantages', 'observations'], 'string', 'max' => 255],
            [['whatsapp'], 'string', 'max' => 50],
            [['whatsapp'], 'match', 'pattern' => '/^[0-9\s\-+()]*$/', 'message' => 'El WhatsApp solo puede llevar números, espacios, guiones, paréntesis y +.'],
            [['whatsapp'], 'default', 'value' => null],
            [['business_id'], 'exist', 'skipOnError' => true, 'targetClass' => Business::className(), 'targetAttribute' => ['business_id' => 'id']],
        ];
    }
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'name' => Yii::t('app', 'Name'),
            'address' => Yii::t('app', 'Address'),
            'phone' => Yii::t('app', 'Phone'),
            'second_phone' => Yii::t('app', 'Second Phone'),
            'whatsapp' => 'WhatsApp',
            'email' => Yii::t('app', 'Email'),
            'business_id' => Yii::t('app', 'Business ID'),
            'payment_method' => Yii::t('app', 'Payment Method'),
            'account' => Yii::t('app', 'Account'),
            'credit_days' => Yii::t('app', 'Credit Days'),
            'rfc' => Yii::t('app', 'Rfc'),
            'business_name' => Yii::t('app', 'Business Name'),
            'advantages' => Yii::t('app', 'Advantages'),
            'disadvantages' => Yii::t('app', 'Disadvantages'),
            'observations' => Yii::t('app', 'Observations'),
        ];
    }
    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // Convertir el array de métodos de pago a string para la BD
            if (is_array($this->payment_method)) {
                $this->payment_method = implode(',', $this->payment_method);
            }
            return true;
        }
        return false;
    }
     /**
     * {@inheritdoc}
     */
    public function afterFind()
    {
        parent::afterFind();
        // Convertir el string de la BD a array para el formulario
        if (!empty($this->payment_method)) {
            $this->payment_method = explode(',', $this->payment_method);
        }
    }


    /**
     * Gets query for [[Business]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBusiness()
    {
        return $this->hasOne(Business::className(), ['id' => 'business_id']);
    }

    public function getMovements()
    {
        return $this->hasMany(Movement::class, ['provider' => 'name']);
    }
}
