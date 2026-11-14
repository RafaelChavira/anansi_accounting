<?php

namespace backend\controllers;

use backend\helpers\RedisKeys;
use common\components\PurchasePlanner;
use common\models\IngredientStock;
use common\models\Provider;
use common\models\PurchasePresentation;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Catálogo de compras: presentaciones en las que cada proveedor vende un insumo,
 * ligadas a la unidad de uso del insumo.
 *
 * Los insumos y proveedores se dan de alta primero en sus propios catálogos;
 * aquí solo se enlazan.
 */
class PurchaseCatalogController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['actions' => ['index', 'presentations'], 'allow' => true, 'roles' => ['ingredients_list']],
                    ['actions' => ['create'], 'allow' => true, 'roles' => ['ingredients_create']],
                    ['actions' => ['update'], 'allow' => true, 'roles' => ['ingredients_update']],
                    ['actions' => ['delete'], 'allow' => true, 'roles' => ['ingredients_delete']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST']],
            ],
        ];
    }

    /**
     * Catálogo de compras: cada insumo con sus datos de compra tal como ya están en el insumo
     * (unidad de compra, equivalencia, último precio, proveedores) y el último proveedor usado.
     * Solo lectura: lo que falta se corrige en el propio insumo.
     *
     * @param string|null $q Búsqueda por nombre.
     * @param string $filter all | pendientes | sin_proveedor | sin_precio | sin_equivalencia
     */
    public function actionIndex($q = '', $filter = 'all')
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $q = mb_substr(trim((string)$q), 0, 100);

        $stocks = IngredientStock::find()
            ->where(['business_id' => $businessId])
            ->orderBy('ingredient ASC')
            ->all();
        $ids = array_map(function ($s) {
            return (int)$s->id;
        }, $stocks);

        $prices = PurchasePlanner::lastPurchasePrices($ids);
        $assigned = PurchasePlanner::providersByIngredient($businessId, $ids);
        $lastUsed = PurchasePlanner::lastUsedProviders($businessId, $ids);
        $providerNames = $this->providerOptions($businessId);
        $extra = [];
        foreach ((new \yii\db\Query())->select(['ingredient_stock_id', 'n' => 'COUNT(*)'])->from('purchase_presentation')
            ->where(['business_id' => $businessId, 'is_active' => 1])->groupBy('ingredient_stock_id')->all() as $r) {
            $extra[(int)$r['ingredient_stock_id']] = (int)$r['n'];
        }

        $rows = [];
        foreach ($stocks as $s) {
            $id = (int)$s->id;
            $ppu = (float)$s->portions_per_unit;
            $price = $prices[$id] ?? null;
            $price = ($price !== null && $price > 0) ? $price : null;
            $useUm = $s->portion_um ?: null;
            $hasEquivalence = $ppu > 0 && $s->um && $useUm;
            $lu = $lastUsed[$id] ?? null;

            $rows[] = [
                'id' => $id,
                'nombre' => $s->ingredient,
                'marca' => $s->brand,
                'presentacion' => $s->presentation,
                'um' => $s->um,
                'use_um' => $useUm,
                'ppu' => $ppu,
                'tiene_equivalencia' => $hasEquivalence,
                'precio' => $price,
                'precio_uso' => ($price !== null && $ppu > 0) ? $price / $ppu : null,
                'proveedores' => $assigned[$id] ?? [],
                'ultimo' => $lu !== null && isset($providerNames[$lu['provider_id']])
                    ? ['nombre' => $providerNames[$lu['provider_id']], 'source' => $lu['source'], 'at' => $lu['at']]
                    : null,
                'extra' => $extra[$id] ?? 0,
            ];
        }

        // Conteos para los filtros (sobre todos los insumos, no sobre la búsqueda).
        $counts = [
            'all' => count($rows),
            'sin_equivalencia' => 0,
            'sin_precio' => 0,
            'sin_proveedor' => 0,
            'pendientes' => 0,
        ];
        foreach ($rows as $r) {
            $noEq = !$r['tiene_equivalencia'];
            $noPrice = $r['precio'] === null;
            $noProv = empty($r['proveedores']);
            $counts['sin_equivalencia'] += $noEq ? 1 : 0;
            $counts['sin_precio'] += $noPrice ? 1 : 0;
            $counts['sin_proveedor'] += $noProv ? 1 : 0;
            $counts['pendientes'] += ($noEq || $noPrice || $noProv) ? 1 : 0;
        }

        $filter = in_array($filter, ['all', 'pendientes', 'sin_proveedor', 'sin_precio', 'sin_equivalencia'], true) ? $filter : 'all';
        $rows = array_values(array_filter($rows, function ($r) use ($filter) {
            switch ($filter) {
                case 'sin_equivalencia':
                    return !$r['tiene_equivalencia'];
                case 'sin_precio':
                    return $r['precio'] === null;
                case 'sin_proveedor':
                    return empty($r['proveedores']);
                case 'pendientes':
                    return !$r['tiene_equivalencia'] || $r['precio'] === null || empty($r['proveedores']);
                default:
                    return true;
            }
        }));
        if ($q !== '') {
            $rows = PurchasePlanner::filterByName($rows, $q);
        }

        $pagination = new Pagination(['totalCount' => count($rows), 'defaultPageSize' => 25, 'pageSizeLimit' => [10, 100]]);
        $totalRows = count($rows);
        $rows = array_slice($rows, $pagination->offset, $pagination->limit);

        return $this->render('index', [
            'rows' => $rows,
            'q' => $q,
            'filter' => $filter,
            'counts' => $counts,
            'pagination' => $pagination,
            'totalRows' => $totalRows,
        ]);
    }

    /** Presentaciones adicionales dadas de alta a mano (opcional). */
    public function actionPresentations($q = null, $provider_id = null, $status = 'all')
    {
        $businessId = (int)RedisKeys::getBusiness()->id;

        $query = PurchasePresentation::find()
            ->alias('p')
            ->joinWith(['ingredientStock s', 'provider pr'])
            ->where(['p.business_id' => $businessId]);

        $q = trim((string)$q);
        if ($q !== '') {
            $query->andWhere(['or',
                ['like', 'p.name', $q],
                ['like', 'p.sku', $q],
                ['like', 's.ingredient', $q],
            ]);
        }
        if ($provider_id !== null && $provider_id !== '') {
            $query->andWhere(['p.provider_id' => (int)$provider_id]);
        }
        if ($status === 'active') {
            $query->andWhere(['p.is_active' => 1]);
        } elseif ($status === 'inactive') {
            $query->andWhere(['p.is_active' => 0]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 25],
            'sort' => [
                'defaultOrder' => ['ingrediente' => SORT_ASC],
                'attributes' => [
                    'ingrediente' => ['asc' => ['s.ingredient' => SORT_ASC, 'p.id' => SORT_ASC], 'desc' => ['s.ingredient' => SORT_DESC]],
                    'name' => ['asc' => ['p.name' => SORT_ASC], 'desc' => ['p.name' => SORT_DESC]],
                    'price' => ['asc' => ['p.price' => SORT_ASC], 'desc' => ['p.price' => SORT_DESC]],
                ],
            ],
        ]);

        return $this->render('presentations', [
            'dataProvider' => $dataProvider,
            'q' => $q,
            'providerId' => $provider_id,
            'status' => $status,
            'providers' => $this->providerOptions($businessId),
        ]);
    }

    public function actionCreate($ingredient_stock_id = null)
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $model = new PurchasePresentation(['business_id' => $businessId, 'is_active' => 1, 'min_order' => 1]);

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            // Nunca confiar en el business_id que venga del cliente.
            $model->business_id = $businessId;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Presentación agregada al catálogo de compras.');
                return $this->redirect(['presentations']);
            }
        } elseif ($ingredient_stock_id) {
            // Viene de la planeación: prellenar con lo que ya sabemos del insumo.
            $stock = IngredientStock::findOne(['id' => (int)$ingredient_stock_id, 'business_id' => $businessId]);
            if ($stock) {
                $model->ingredient_stock_id = $stock->id;
                $model->name = $stock->um;
                if ((float)$stock->portions_per_unit > 0) {
                    $model->units_per_presentation = (float)$stock->portions_per_unit;
                }
            }
        }

        return $this->render('create', $this->formData($model, $businessId));
    }

    public function actionUpdate($id)
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $model = $this->findModel($id, $businessId);

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            $model->business_id = $businessId;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Presentación actualizada.');
                return $this->redirect(['presentations']);
            }
        }

        return $this->render('update', $this->formData($model, $businessId));
    }

    public function actionDelete($id)
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $this->findModel($id, $businessId)->delete();
        Yii::$app->session->setFlash('success', 'Presentación eliminada del catálogo.');
        return $this->redirect(['presentations']);
    }

    /**
     * Solo presentaciones del negocio del usuario.
     */
    protected function findModel($id, $businessId)
    {
        $model = PurchasePresentation::findOne(['id' => (int)$id, 'business_id' => $businessId]);
        if ($model === null) {
            throw new NotFoundHttpException('La presentación no existe.');
        }
        return $model;
    }

    protected function providerOptions($businessId)
    {
        $providers = Provider::find()->where(['business_id' => $businessId])->orderBy('business_name ASC, name ASC')->all();
        $out = [];
        foreach ($providers as $p) {
            $out[$p->id] = $p->business_name ?: $p->name;
        }
        return $out;
    }

    protected function formData(PurchasePresentation $model, $businessId)
    {
        $stocks = IngredientStock::find()
            ->select(['id', 'ingredient', 'um', 'portion_um', 'portions_per_unit'])
            ->where(['business_id' => $businessId])
            ->orderBy('ingredient ASC')
            ->asArray()
            ->all();

        $ingredientOptions = [];
        $ingredientInfo = [];
        foreach ($stocks as $s) {
            $ingredientOptions[$s['id']] = $s['ingredient'];
            $ingredientInfo[$s['id']] = [
                'um' => $s['um'],
                'useUm' => $s['portion_um'] ?: $s['um'],
                'perUnit' => (float)$s['portions_per_unit'],
            ];
        }

        return [
            'model' => $model,
            'ingredientOptions' => $ingredientOptions,
            'ingredientInfo' => $ingredientInfo,
            'providers' => $this->providerOptions($businessId),
        ];
    }
}
