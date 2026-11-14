<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Presentación de compra (catálogo de compras): un producto como lo vende un proveedor,
 * ligado a un insumo.
 *
 * units_per_presentation está en la UNIDAD DE USO del insumo (ingredient_stock.portion_um),
 * que es la unidad en la que se guardan la existencia y los stocks mínimo/máximo.
 *
 * @property int $id
 * @property int $business_id
 * @property int $ingredient_stock_id
 * @property int|null $provider_id
 * @property string $name
 * @property string|null $sku
 * @property float $units_per_presentation
 * @property float|null $price
 * @property float $min_order
 * @property int $is_default
 * @property int $is_active
 * @property int $created_at
 * @property int $updated_at
 *
 * @property IngredientStock $ingredientStock
 * @property Provider|null $provider
 */
class PurchasePresentation extends ActiveRecord
{
    public static function tableName()
    {
        return 'purchase_presentation';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        $businessFilter = function ($query) {
            // El insumo y el proveedor deben ser del mismo negocio que la presentación.
            $query->andWhere(['business_id' => $this->business_id]);
        };

        return [
            [['business_id', 'ingredient_stock_id', 'name', 'units_per_presentation'], 'required'],
            [['business_id', 'ingredient_stock_id', 'provider_id'], 'integer'],
            [['name', 'sku'], 'filter', 'filter' => function ($v) {
                return is_string($v) ? trim($v) : $v;
            }],
            [['name'], 'string', 'max' => 255],
            [['sku'], 'string', 'max' => 100],
            [['sku', 'provider_id', 'price'], 'default', 'value' => null],
            [['units_per_presentation'], 'number', 'min' => 0.0001, 'max' => 9999999],
            [['price'], 'number', 'min' => 0],
            [['min_order'], 'number', 'min' => 0.01],
            [['min_order'], 'default', 'value' => 1],
            [['is_default', 'is_active'], 'boolean'],
            [['business_id'], 'exist', 'targetClass' => Business::class, 'targetAttribute' => ['business_id' => 'id']],
            [['ingredient_stock_id'], 'exist', 'targetClass' => IngredientStock::class,
                'targetAttribute' => ['ingredient_stock_id' => 'id'], 'filter' => $businessFilter,
                'message' => 'El insumo no existe en tu negocio.'],
            [['provider_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => Provider::class,
                'targetAttribute' => ['provider_id' => 'id'], 'filter' => $businessFilter,
                'message' => 'El proveedor no existe en tu negocio.'],
            [['name'], 'unique',
                'targetAttribute' => ['business_id', 'ingredient_stock_id', 'provider_id', 'name'],
                'message' => 'Ya existe una presentación con ese nombre para este insumo y proveedor.'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'ingredient_stock_id' => 'Insumo',
            'provider_id' => 'Proveedor',
            'name' => 'Presentación (como la vende el proveedor)',
            'sku' => 'Código del proveedor',
            'units_per_presentation' => 'Contenido (en unidad de uso del insumo)',
            'price' => 'Precio por presentación',
            'min_order' => 'Mínimo por pedido',
            'is_default' => 'Presentación predeterminada del insumo',
            'is_active' => 'Activa',
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        // Una presentación inactiva no puede ser la predeterminada.
        if (!$this->is_active) {
            $this->is_default = 0;
        }
        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // Solo una predeterminada por insumo.
        if ($this->is_default) {
            static::updateAll(
                ['is_default' => 0],
                ['and',
                    ['business_id' => $this->business_id, 'ingredient_stock_id' => $this->ingredient_stock_id],
                    ['<>', 'id', $this->id],
                ]
            );
        }
    }

    /**
     * Precio por unidad de uso, si hay precio.
     * @return float|null
     */
    public function getUnitPrice()
    {
        if ($this->price === null || (float)$this->units_per_presentation <= 0) {
            return null;
        }
        return (float)$this->price / (float)$this->units_per_presentation;
    }

    /**
     * Mejor presentación activa de cada insumo: la predeterminada; si no hay,
     * la de menor precio por unidad de uso (las que no tienen precio, al final).
     *
     * @param int $businessId
     * @param int[] $ingredientIds
     * @return static[] indexado por ingredient_stock_id
     */
    public static function bestForIngredients($businessId, array $ingredientIds)
    {
        if (empty($ingredientIds)) {
            return [];
        }
        $rows = static::find()
            ->with('provider')
            ->where(['business_id' => $businessId, 'is_active' => 1, 'ingredient_stock_id' => $ingredientIds])
            ->orderBy([
                'is_default' => SORT_DESC,
                new \yii\db\Expression('(price IS NULL) ASC'),
                new \yii\db\Expression('price / units_per_presentation ASC'),
                'id' => SORT_ASC,
            ])
            ->all();

        $best = [];
        foreach ($rows as $row) {
            if (!isset($best[$row->ingredient_stock_id])) {
                $best[$row->ingredient_stock_id] = $row;
            }
        }
        return $best;
    }

    /**
     * Cuántas presentaciones hay que comprar para cubrir un faltante.
     * Siempre se redondea hacia arriba (no se compra media caja) y se respeta el mínimo por pedido.
     *
     * @param float $needed Faltante en unidad de uso.
     * @param float $unitsPerPresentation Contenido de una presentación, en unidad de uso.
     * @param float $minOrder Mínimo de presentaciones por pedido.
     * @return int
     */
    public static function packagesFor($needed, $unitsPerPresentation, $minOrder = 1)
    {
        if ($needed <= 0 || $unitsPerPresentation <= 0) {
            return 0;
        }
        // round(..., 6) evita que 24.0000000001 / 12 se convierta en 3 paquetes por error de flotante.
        $n = (int)ceil(round($needed / $unitsPerPresentation, 6));
        return max($n, (int)ceil($minOrder));
    }

    public function getIngredientStock()
    {
        return $this->hasOne(IngredientStock::class, ['id' => 'ingredient_stock_id']);
    }

    public function getProvider()
    {
        return $this->hasOne(Provider::class, ['id' => 'provider_id']);
    }
}
