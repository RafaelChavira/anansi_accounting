<?php

namespace common\models;

use backend\helpers\RedisKeys;
use Yii;

/**
 * This is the model class for table "unit_of_measurement".
 *
 * @property int $id
 * @property string $name
 * @property int|null $business_id
 * @property int $custom
 * @property string $type
 * @property int $is_purchase
 * @property int $is_kitchen
 * @property int $is_subrecipe_yield
 * @property int $is_subrecipe_um
 * @property int $is_recipe_yield
 * @property int $is_recipe_final_um
 *
 * @property Business $business
 */
class UnitOfMeasurement extends \yii\db\ActiveRecord
{
    const TYPE_KITCHEN = 'kitchen';
    const TYPE_PURCHASE = 'purchase';
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'unit_of_measurement';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'trim'], // Eliminar espacios al inicio y final
            [['name'], 'validateUniqueByFlags'],
            [['is_purchase', 'is_kitchen', 'is_subrecipe_yield', 'is_subrecipe_um', 'is_recipe_yield', 'is_recipe_final_um'], 'validateFlagNotInUse'],
            [['business_id', 'custom', 'is_purchase', 'is_kitchen', 'is_subrecipe_yield', 'is_subrecipe_um', 'is_recipe_yield', 'is_recipe_final_um'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['type'], 'string', 'max' => 20],
            [['custom'], 'default', 'value' => 0],
            [['is_purchase', 'is_kitchen', 'is_subrecipe_yield', 'is_subrecipe_um', 'is_recipe_yield', 'is_recipe_final_um'], 'default', 'value' => 0],
            [['type'], 'default', 'value' => self::TYPE_KITCHEN],
            [['type'], 'in', 'range' => [self::TYPE_KITCHEN, self::TYPE_PURCHASE]],
            [['business_id'], 'exist', 'skipOnError' => true, 'targetClass' => Business::className(), 'targetAttribute' => ['business_id' => 'id']],
        ];
    }

    /**
     * Valida que el nombre sea único por negocio (insensible a mayúsculas y espacios).
     * Las unidades se guardan por nombre en insumos y recetas, así que un nombre es una sola unidad
     * y sus usos (compra, uso, recetas...) se definen con las casillas, no con registros repetidos.
     */
    public function validateUniqueByFlags($attribute)
    {
        $businessId = $this->business_id;
        if (empty($businessId)) {
            $business = RedisKeys::getBusinessData();
            $businessId = $business['id'] ?? null;
        }

        $query = self::find()
            ->where(['business_id' => $businessId])
            ->andWhere(new \yii\db\Expression('LOWER(TRIM(name)) = :name', [':name' => mb_strtolower(trim($this->name))]));
        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }
        $existing = $query->one();
        if (!$existing) {
            return;
        }

        $labels = [
            'is_purchase' => 'compra',
            'is_kitchen' => 'uso',
            'is_subrecipe_yield' => 'rendimiento subreceta',
            'is_subrecipe_um' => 'unidad subreceta',
            'is_recipe_yield' => 'rendimiento receta',
            'is_recipe_final_um' => 'unidad final receta',
        ];
        $roles = [];
        foreach ($labels as $flag => $label) {
            if (!empty($existing->$flag)) {
                $roles[] = $label;
            }
        }
        $types = $roles ? ' para el tipo (' . implode(', ', $roles) . ')' : '';
        $this->addError($attribute, "Ya existe una unidad llamada '{$this->name}'{$types}. Edítala para cambiar sus usos.");
    }

    /**
     * Impide desmarcar un uso de la unidad si hay insumos o recetas del negocio que la usan en ese rol.
     */
    public function validateFlagNotInUse($attribute)
    {
        if ($this->isNewRecord || !empty($this->$attribute) || !$this->getOldAttribute($attribute)) {
            return;
        }

        $usages = $this->findUsages($attribute);
        if (empty($usages)) {
            return;
        }

        $this->addError(
            $attribute,
            "No se puede desmarcar: la unidad '{$this->name}' está en uso como " . self::describeUsage($usages) . '.'
        );
    }

    /**
     * Todos los usos de la unidad en insumos y recetas del negocio (uno por rol en el que esté en uso).
     *
     * @return array[] ver findUsages()
     */
    public function getAllUsages()
    {
        $result = [];
        foreach (['is_purchase', 'is_kitchen', 'is_subrecipe_yield', 'is_subrecipe_um', 'is_recipe_yield', 'is_recipe_final_um'] as $flag) {
            if ($usage = $this->findUsages($flag)) {
                $result[] = $usage;
            }
        }
        return $result;
    }

    /**
     * Texto "rol en N entidades: a, b, c y X más".
     */
    public static function describeUsage(array $usage)
    {
        $names = array_slice($usage['items'], 0, 10);
        $more = $usage['total'] - count($names);
        $list = implode(', ', $names) . ($more > 0 ? " y {$more} más" : '');
        return "{$usage['role']} en {$usage['total']} {$usage['entity']}: {$list}";
    }

    /**
     * Busca los registros del negocio que usan esta unidad en el rol indicado por el flag.
     * Las tablas guardan la unidad por nombre (no por id).
     *
     * @return array{role:string,entity:string,total:int,items:string[]}|null
     */
    protected function findUsages($flag)
    {
        $businessId = $this->business_id;
        if (empty($businessId)) {
            return null;
        }

        $name = $this->getOldAttribute('name');
        $recipe = function ($column, $type) use ($businessId, $name) {
            return StandardRecipe::find()
                ->where(['business_id' => $businessId, 'type' => $type, $column => $name]);
        };

        switch ($flag) {
            case 'is_purchase':
                $query = IngredientStock::find()->where(['business_id' => $businessId, 'um' => $name]);
                $role = 'unidad de compra'; $entity = 'insumos'; $col = 'ingredient';
                break;
            case 'is_kitchen':
                $query = IngredientStock::find()->where(['business_id' => $businessId, 'portion_um' => $name]);
                $role = 'unidad de uso'; $entity = 'insumos'; $col = 'ingredient';
                break;
            case 'is_subrecipe_yield':
                $query = $recipe('yield_um', StandardRecipe::STANDARD_RECIPE_TYPE_SUB);
                $role = 'rendimiento de subreceta'; $entity = 'subrecetas'; $col = 'title';
                break;
            case 'is_subrecipe_um':
                $query = $recipe('um', StandardRecipe::STANDARD_RECIPE_TYPE_SUB);
                $role = 'unidad de subreceta'; $entity = 'subrecetas'; $col = 'title';
                break;
            case 'is_recipe_yield':
                $query = $recipe('yield_um', StandardRecipe::STANDARD_RECIPE_TYPE_MAIN);
                $role = 'rendimiento de receta'; $entity = 'recetas'; $col = 'title';
                break;
            case 'is_recipe_final_um':
                $query = $recipe('um', StandardRecipe::STANDARD_RECIPE_TYPE_MAIN);
                $role = 'unidad final de receta'; $entity = 'recetas'; $col = 'title';
                break;
            default:
                return null;
        }

        $total = (int)$query->count();
        if ($total === 0) {
            return null;
        }

        return [
            'role' => $role,
            'entity' => $entity,
            'total' => $total,
            'items' => $query->select($col)->orderBy($col)->limit(10)->column(),
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
            'business_id' => Yii::t('app', 'Business ID'),
            'custom' => Yii::t('app', 'Unidad personalizada'),
            'type' => Yii::t('app', 'Tipo de unidad'),
            'is_purchase' => Yii::t('app', '¿Es unidad de compra (insumos)?'),
            'is_kitchen' => Yii::t('app', '¿Es unidad de uso (insumos)?'),
            'is_subrecipe_yield' => Yii::t('app', '¿Es unidad de rendimiento (subreceta)?'),
            'is_subrecipe_um' => Yii::t('app', '¿Es unidad de insumo (subreceta)?'),
            'is_recipe_yield' => Yii::t('app', '¿Es unidad de rendimiento (receta)?'),
            'is_recipe_final_um' => Yii::t('app', '¿Es unidad final de (receta)?'),
        ];
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

    public static function getOwn(): \yii\db\ActiveQuery
    {
        $business = RedisKeys::getBusinessData();
        return self::find()->where(['business_id' => $business['id']]);
    }
    
    /**
     * Obtiene unidades de cocina del negocio actual
     */
    public static function getKitchenUnits(): \yii\db\ActiveQuery
    {
        $business = RedisKeys::getBusinessData();
        return self::find()
            ->where(['business_id' => $business['id']])
            ->andWhere(['type' => self::TYPE_KITCHEN]);
    }
    
    /**
     * Obtiene unidades de compra del negocio actual
     */
    public static function getPurchaseUnits(): \yii\db\ActiveQuery
    {
        $business = RedisKeys::getBusinessData();
        return self::find()
            ->where(['business_id' => $business['id']])
            ->andWhere(['type' => self::TYPE_PURCHASE]);
    }
    
    /**
     * Obtiene el texto amigable del tipo
     */
    public function getTypeText()
    {
        return $this->type === self::TYPE_KITCHEN ? 'Cocina' : 'Compra';
    }
    
    /**
     * Obtiene las opciones de tipo para dropdowns
     */
    public static function getTypeOptions()
    {
        return [
            self::TYPE_KITCHEN => 'Unidad de cocina',
            self::TYPE_PURCHASE => 'Unidad de compra',
        ];
    }
}
