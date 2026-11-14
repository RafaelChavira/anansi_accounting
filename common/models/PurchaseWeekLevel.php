<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Nivel de demanda (baja, media, alta, muy alta) asignado a una semana del calendario.
 * La semana se identifica por su lunes.
 *
 * @property int $id
 * @property int $business_id
 * @property string $week_start Y-m-d (lunes)
 * @property string $level
 * @property int $created_at
 * @property int $updated_at
 */
class PurchaseWeekLevel extends ActiveRecord
{
    public static function tableName()
    {
        return 'purchase_week_level';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['business_id', 'week_start', 'level'], 'required'],
            [['business_id'], 'integer'],
            [['week_start'], 'date', 'format' => 'php:Y-m-d'],
            [['level'], 'in', 'range' => array_keys(PurchasePlanningConfig::levels())],
            [['business_id'], 'exist', 'skipOnError' => true, 'targetClass' => Business::class, 'targetAttribute' => ['business_id' => 'id']],
        ];
    }

    /**
     * Devuelve el lunes (Y-m-d) de la semana de la fecha dada.
     * Si la fecha es inválida, devuelve el lunes de la próxima semana.
     *
     * @param string|null $date
     * @return string
     */
    public static function normalizeWeek($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$date);
        if ($d === false || $d->format('Y-m-d') !== (string)$date) {
            $d = new \DateTime('+7 days');
        }
        $d->setTime(0, 0, 0);
        $d->modify('monday this week');
        return $d->format('Y-m-d');
    }

    /**
     * Lunes de la semana actual.
     */
    public static function currentWeek()
    {
        return self::normalizeWeek(date('Y-m-d'));
    }

    /**
     * Nivel de una semana; 'medium' si el negocio no la ha definido.
     */
    public static function levelFor($businessId, $weekStart)
    {
        $level = static::find()
            ->select('level')
            ->where(['business_id' => $businessId, 'week_start' => $weekStart])
            ->scalar();
        return $level ?: PurchasePlanningConfig::LEVEL_MEDIUM;
    }

    /**
     * Niveles definidos para un conjunto de semanas: ['Y-m-d' => 'level'].
     */
    public static function levelsFor($businessId, array $weekStarts)
    {
        if (empty($weekStarts)) {
            return [];
        }
        return static::find()
            ->select('level')
            ->indexBy('week_start')
            ->where(['business_id' => $businessId, 'week_start' => $weekStarts])
            ->column();
    }
}
