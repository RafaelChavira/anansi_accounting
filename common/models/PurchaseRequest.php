<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Solicitud de compra enviada a uno o varios proveedores.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $user_id
 * @property string|null $week_start
 * @property string|null $notes
 * @property string $status
 * @property float $total_estimated
 * @property int $created_at
 * @property int $updated_at
 *
 * @property PurchaseRequestItem[] $items
 * @property PurchaseRequestDispatch[] $dispatches
 * @property Business $business
 * @property User $user
 */
class PurchaseRequest extends ActiveRecord
{
    public static function tableName()
    {
        return 'purchase_request';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['business_id'], 'required'],
            [['business_id', 'user_id'], 'integer'],
            [['week_start'], 'date', 'format' => 'php:Y-m-d'],
            [['notes'], 'string', 'max' => 2000],
            [['total_estimated'], 'number', 'min' => 0],
            [['status'], 'in', 'range' => ['sent']],
        ];
    }

    public function getItems()
    {
        return $this->hasMany(PurchaseRequestItem::class, ['request_id' => 'id'])->orderBy(['provider_name' => SORT_ASC, 'ingredient_name' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getDispatches()
    {
        return $this->hasMany(PurchaseRequestDispatch::class, ['request_id' => 'id'])->orderBy(['id' => SORT_ASC]);
    }

    public function getBusiness()
    {
        return $this->hasOne(Business::class, ['id' => 'business_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Renglones agrupados por proveedor: [clave => ['provider_id', 'provider_name', 'items' => [...]]].
     * La clave es el provider_id, o 'p0' si el proveedor ya no existe.
     *
     * @return array
     */
    public function groupItemsByProvider()
    {
        $groups = [];
        foreach ($this->items as $item) {
            $key = $item->provider_id ? (string)$item->provider_id : 'p0';
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'provider_id' => $item->provider_id ? (int)$item->provider_id : null,
                    'provider_name' => $item->provider_name,
                    'items' => [],
                ];
            }
            $groups[$key]['items'][] = $item;
        }
        return $groups;
    }
}
