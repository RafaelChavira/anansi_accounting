<?php

namespace backend\controllers;

use backend\helpers\RedisKeys;
use common\models\IngredientStock;
use common\models\Provider;
use common\models\PurchasePresentation;
use Yii;
use yii\data\ActiveDataProvider;
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
                    ['actions' => ['index'], 'allow' => true, 'roles' => ['ingredients_list']],
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

    public function actionIndex($q = null, $provider_id = null, $status = 'all')
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

        return $this->render('index', [
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
                return $this->redirect(['index']);
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
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', $this->formData($model, $businessId));
    }

    public function actionDelete($id)
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $this->findModel($id, $businessId)->delete();
        Yii::$app->session->setFlash('success', 'Presentación eliminada del catálogo.');
        return $this->redirect(['index']);
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
