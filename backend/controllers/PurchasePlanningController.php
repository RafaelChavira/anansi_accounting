<?php

namespace backend\controllers;

use backend\helpers\RedisKeys;
use common\models\IngredientStock;
use common\models\PurchasePlanningConfig;
use common\models\PurchasePresentation;
use common\models\PurchaseWeekLevel;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * Planeación de compras sugerida.
 *
 * Stock ideal de la semana = stock máximo del insumo × factor del nivel de la semana
 * (baja, media, alta, muy alta). Sugerido a comprar = stock ideal - existencia actual.
 */
class PurchasePlanningController extends Controller
{
    /** Semanas que muestra el calendario a partir de la semana actual. */
    const CALENDAR_WEEKS = 12;

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'calendar'],
                        'allow' => true,
                        'roles' => ['kpi_access'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['get'],
                    'calendar' => ['get', 'post'],
                ],
            ],
        ];
    }

    /**
     * Qué comprar para la semana elegida (por defecto, la que viene).
     *
     * @param string|null $week Cualquier fecha Y-m-d; se normaliza a su lunes.
     * @param int $only_missing 1 = solo insumos con faltante.
     */
    public function actionIndex($week = null, $only_missing = 1)
    {
        $business = RedisKeys::getBusiness();
        $businessId = (int)$business->id;

        $weekStart = PurchaseWeekLevel::normalizeWeek($week ?: date('Y-m-d', strtotime('+7 days')));
        $config = PurchasePlanningConfig::forBusiness($businessId);
        $level = PurchaseWeekLevel::levelFor($businessId, $weekStart);
        $factor = $config->getFactor($level);

        $rows = [];
        $sinStockIdeal = 0;
        $stocks = IngredientStock::find()
            ->where(['business_id' => $businessId])
            ->orderBy('ingredient ASC')
            ->all();

        // Catálogo de compras: mejor presentación activa de cada insumo.
        $catalog = PurchasePresentation::bestForIngredients(
            $businessId,
            array_map(function ($s) {
                return (int)$s->id;
            }, $stocks)
        );

        foreach ($stocks as $stock) {
            $base = (float)$stock->max_stock;
            if ($base <= 0) {
                // Sin stock máximo no hay base para calcular el ideal.
                $sinStockIdeal++;
                continue;
            }
            // La existencia y los stocks mínimo/máximo se llevan en UNIDAD DE USO
            // (las entradas suman cantidad × portions_per_unit; las salidas restan en unidad de uso).
            $actual = (float)$stock->quantity;
            $ideal = $base * $factor;
            $faltante = max(0.0, $ideal - $actual);

            $row = [
                'id' => $stock->id,
                'nombre' => $stock->ingredient,
                'unidad' => $stock->portion_um ?: $stock->um,
                'actual' => $actual,
                'base' => $base,
                'ideal' => $ideal,
                'faltante' => $faltante,
                'compra' => null,
            ];

            if ($faltante > 0) {
                $row['compra'] = $this->purchaseFor($stock, $faltante, $catalog[$stock->id] ?? null);
            }
            $rows[] = $row;
        }

        $totalConFaltante = count(array_filter($rows, function ($r) {
            return $r['faltante'] > 0;
        }));

        if ((int)$only_missing === 1) {
            $rows = array_values(array_filter($rows, function ($r) {
                return $r['faltante'] > 0;
            }));
        }
        // Lo que más falta, primero.
        usort($rows, function ($a, $b) {
            return $b['faltante'] <=> $a['faltante'];
        });

        $costoTotal = 0.0;
        $sinPresentacion = 0;
        foreach ($rows as $r) {
            if ($r['compra'] === null) {
                if ($r['faltante'] > 0) {
                    $sinPresentacion++;
                }
                continue;
            }
            $costoTotal += (float)$r['compra']['costo'];
        }

        $weekDate = new \DateTime($weekStart);
        $prev = (clone $weekDate)->modify('-7 days')->format('Y-m-d');
        $next = (clone $weekDate)->modify('+7 days')->format('Y-m-d');

        return $this->render('index', [
            'rows' => $rows,
            'weekStart' => $weekStart,
            'weekEnd' => (clone $weekDate)->modify('+6 days')->format('Y-m-d'),
            'prevWeek' => $prev,
            'nextWeek' => $next,
            'level' => $level,
            'factor' => $factor,
            'onlyMissing' => (int)$only_missing === 1,
            'totalConFaltante' => $totalConFaltante,
            'totalConfigurados' => count($stocks) - $sinStockIdeal,
            'sinStockIdeal' => $sinStockIdeal,
            'costoTotal' => $costoTotal,
            'sinPresentacion' => $sinPresentacion,
        ]);
    }

    /**
     * Cuánto comprar de un insumo: convierte el faltante (en unidad de uso) a número de
     * presentaciones. Usa la presentación del catálogo de compras; si el insumo no tiene,
     * usa su propia unidad de compra (um) con su equivalencia (portions_per_unit).
     *
     * @param IngredientStock $stock
     * @param float $faltante En unidad de uso.
     * @param PurchasePresentation|null $presentation
     * @return array|null null si no hay forma de convertir (sin catálogo ni equivalencia).
     */
    private function purchaseFor(IngredientStock $stock, $faltante, $presentation)
    {
        if ($presentation !== null) {
            $per = (float)$presentation->units_per_presentation;
            $n = PurchasePresentation::packagesFor($faltante, $per, (float)$presentation->min_order);
            $provider = $presentation->provider;
            return [
                'origen' => 'catalogo',
                'presentacion_id' => $presentation->id,
                'nombre' => $presentation->name,
                'proveedor' => $provider ? ($provider->business_name ?: $provider->name) : null,
                'contenido' => $per,
                'cantidad' => $n,
                'costo' => $presentation->price !== null ? $n * (float)$presentation->price : 0.0,
                'tiene_precio' => $presentation->price !== null,
            ];
        }

        $per = (float)$stock->portions_per_unit;
        if ($per > 0 && $stock->um) {
            return [
                'origen' => 'insumo',
                'presentacion_id' => null,
                'nombre' => $stock->um,
                'proveedor' => null,
                'contenido' => $per,
                'cantidad' => PurchasePresentation::packagesFor($faltante, $per, 1),
                'costo' => 0.0,
                'tiene_precio' => false,
            ];
        }
        return null;
    }

    /**
     * Calendario de semanas (nivel de cada una) y factores del negocio.
     */
    public function actionCalendar()
    {
        $business = RedisKeys::getBusiness();
        $businessId = (int)$business->id;
        $config = PurchasePlanningConfig::forBusiness($businessId);

        $currentWeek = PurchaseWeekLevel::currentWeek();
        $weeks = [];
        $d = new \DateTime($currentWeek);
        for ($i = 0; $i < self::CALENDAR_WEEKS; $i++) {
            $weeks[] = $d->format('Y-m-d');
            $d->modify('+7 days');
        }

        $request = Yii::$app->request;
        if ($request->isPost) {
            if ($request->post('save_config') !== null) {
                $loaded = $config->load($request->post());
                // Nunca confiar en el business_id del POST: siempre el del usuario.
                $config->business_id = $businessId;
                if ($loaded && $config->save()) {
                    Yii::$app->session->setFlash('success', 'Factores actualizados.');
                    return $this->redirect(['calendar']);
                }
                Yii::$app->session->setFlash('error', 'Revisa los factores: deben ser números entre 0.1 y 10.');
            } elseif ($request->post('save_levels') !== null) {
                $this->saveLevels($businessId, $weeks, (array)$request->post('levels', []));
                Yii::$app->session->setFlash('success', 'Calendario actualizado.');
                return $this->redirect(['calendar']);
            }
        }

        return $this->render('calendar', [
            'config' => $config,
            'weeks' => $weeks,
            'currentWeek' => $currentWeek,
            'levels' => PurchaseWeekLevel::levelsFor($businessId, $weeks),
        ]);
    }

    /**
     * Guarda los niveles recibidos, aceptando solo semanas del calendario mostrado
     * y niveles válidos.
     *
     * @param int $businessId
     * @param string[] $allowedWeeks
     * @param array $levels ['Y-m-d' => 'level']
     */
    private function saveLevels($businessId, array $allowedWeeks, array $levels)
    {
        $valid = PurchasePlanningConfig::levels();
        foreach ($allowedWeeks as $week) {
            if (!isset($levels[$week]) || !isset($valid[$levels[$week]])) {
                continue;
            }
            $model = PurchaseWeekLevel::findOne(['business_id' => $businessId, 'week_start' => $week]);
            if ($model === null) {
                $model = new PurchaseWeekLevel(['business_id' => $businessId, 'week_start' => $week]);
            }
            $model->level = $levels[$week];
            $model->save();
        }
    }
}
