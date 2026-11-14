<?php

namespace backend\controllers;

use backend\helpers\RedisKeys;
use common\components\PurchasePlanner;
use common\models\PurchasePlanningConfig;
use common\models\PurchaseWeekLevel;
use Yii;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * Planeación de compras sugerida.
 *
 * Stock ideal de la semana = stock máximo del insumo × factor del nivel de la semana
 * (baja, media, alta, muy alta). Sugerido a comprar = stock ideal - existencia actual.
 * El cálculo vive en {@see PurchasePlanner}, compartido con la solicitud de compra.
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

        $plan = PurchasePlanner::plan($businessId, $weekStart);
        $rows = $plan['rows'];
        $level = $plan['level'];
        $factor = $plan['factor'];
        $sinStockIdeal = $plan['sinStockIdeal'];

        $totalConFaltante = count(array_filter($rows, function ($r) {
            return $r['faltante'] > 0;
        }));
        // Insumos que se pueden pedir a un proveedor: con faltante y con forma de convertirlo a presentaciones.
        $totalSolicitables = count(array_filter($rows, function ($r) {
            return $r['faltante'] > 0 && $r['compra'] !== null;
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

        // Paginado: los totales de arriba ya se calcularon sobre TODAS las filas; aquí solo se corta lo que se muestra.
        // Pagination conserva en los enlaces el resto de parámetros (week, only_missing, per-page).
        $pagination = new Pagination([
            'totalCount' => count($rows),
            'defaultPageSize' => 25,
            'pageSizeLimit' => [10, 100],
        ]);
        $totalRows = count($rows);
        $rows = array_slice($rows, $pagination->offset, $pagination->limit);

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
            'totalSolicitables' => $totalSolicitables,
            'totalConfigurados' => $plan['totalStocks'] - $sinStockIdeal,
            'sinStockIdeal' => $sinStockIdeal,
            'costoTotal' => $costoTotal,
            'sinPresentacion' => $sinPresentacion,
            'pagination' => $pagination,
            'totalRows' => $totalRows,
        ]);
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
