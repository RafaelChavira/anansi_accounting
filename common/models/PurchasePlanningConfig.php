<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Factores de planeación de compras por negocio.
 *
 * Stock ideal de una semana = stock máximo del insumo × factor del nivel de esa semana.
 *
 * @property int $id
 * @property int $business_id
 * @property float $factor_low
 * @property float $factor_medium
 * @property float $factor_high
 * @property float $factor_very_high
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Business $business
 */
class PurchasePlanningConfig extends ActiveRecord
{
    const LEVEL_LOW = 'low';
    const LEVEL_MEDIUM = 'medium';
    const LEVEL_HIGH = 'high';
    const LEVEL_VERY_HIGH = 'very_high';

    public static function tableName()
    {
        return 'purchase_planning_config';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['business_id'], 'required'],
            [['business_id'], 'integer'],
            [['factor_low', 'factor_medium', 'factor_high', 'factor_very_high'], 'required'],
            [['factor_low', 'factor_medium', 'factor_high', 'factor_very_high'], 'number', 'min' => 0.1, 'max' => 10],
            [['business_id'], 'exist', 'skipOnError' => true, 'targetClass' => Business::class, 'targetAttribute' => ['business_id' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'factor_low' => 'Semana baja',
            'factor_medium' => 'Semana media',
            'factor_high' => 'Semana alta',
            'factor_very_high' => 'Semana muy alta',
        ];
    }

    /**
     * Niveles válidos => etiqueta.
     */
    public static function levels()
    {
        return [
            self::LEVEL_LOW => 'Baja',
            self::LEVEL_MEDIUM => 'Media',
            self::LEVEL_HIGH => 'Alta',
            self::LEVEL_VERY_HIGH => 'Muy alta',
        ];
    }

    public static function levelLabel($level)
    {
        return self::levels()[$level] ?? $level;
    }

    /**
     * Clase de color (badge de Bootstrap) para cada nivel.
     */
    public static function levelBadge($level)
    {
        $map = [
            self::LEVEL_LOW => 'secondary',
            self::LEVEL_MEDIUM => 'info',
            self::LEVEL_HIGH => 'warning',
            self::LEVEL_VERY_HIGH => 'danger',
        ];
        return $map[$level] ?? 'secondary';
    }

    /**
     * Devuelve la configuración del negocio; la crea con los valores por defecto si no existe.
     *
     * @param int $businessId
     * @return static
     */
    public static function forBusiness($businessId)
    {
        $config = static::findOne(['business_id' => $businessId]);
        if ($config === null) {
            $config = new static([
                'business_id' => $businessId,
                'factor_low' => 0.70,
                'factor_medium' => 1.00,
                'factor_high' => 1.30,
                'factor_very_high' => 1.60,
            ]);
            $config->save(false);
        }
        return $config;
    }

    public function getFactor($level)
    {
        switch ($level) {
            case self::LEVEL_LOW:
                return (float)$this->factor_low;
            case self::LEVEL_HIGH:
                return (float)$this->factor_high;
            case self::LEVEL_VERY_HIGH:
                return (float)$this->factor_very_high;
            case self::LEVEL_MEDIUM:
            default:
                return (float)$this->factor_medium;
        }
    }

    public function getBusiness()
    {
        return $this->hasOne(Business::class, ['id' => 'business_id']);
    }
}
