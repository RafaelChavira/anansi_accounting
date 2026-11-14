<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Renglón de una solicitud de compra. Guarda copia de los nombres (snapshot) para que el
 * historial no cambie si después se renombra o borra el insumo o el proveedor.
 *
 * @property int $id
 * @property int $request_id
 * @property int|null $provider_id
 * @property string $provider_name
 * @property int|null $ingredient_stock_id
 * @property string $ingredient_name
 * @property string $presentation_name
 * @property string|null $sku
 * @property float $quantity Número de presentaciones
 * @property float|null $units_per_presentation
 * @property string|null $use_unit
 * @property float|null $unit_price
 * @property float|null $line_total
 *
 * @property PurchaseRequest $request
 */
class PurchaseRequestItem extends ActiveRecord
{
    public static function tableName()
    {
        return 'purchase_request_item';
    }

    public function rules()
    {
        return [
            [['request_id', 'provider_name', 'ingredient_name', 'presentation_name', 'quantity'], 'required'],
            [['request_id', 'provider_id', 'ingredient_stock_id'], 'integer'],
            [['provider_name', 'ingredient_name', 'presentation_name'], 'string', 'max' => 255],
            [['sku'], 'string', 'max' => 100],
            [['use_unit'], 'string', 'max' => 100],
            [['quantity'], 'number', 'min' => 0.01, 'max' => 1000000],
            [['units_per_presentation', 'unit_price', 'line_total'], 'number', 'min' => 0],
        ];
    }

    public function getRequest()
    {
        return $this->hasOne(PurchaseRequest::class, ['id' => 'request_id']);
    }

    /**
     * Cantidad sin ceros sobrantes: 3 o 2,5.
     */
    public function getQuantityLabel()
    {
        return rtrim(rtrim(number_format((float)$this->quantity, 2, ',', ''), '0'), ',');
    }
}
