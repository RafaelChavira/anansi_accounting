<?php

use common\models\PurchasePlanningConfig;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $rows array */
/* @var $weekStart string */
/* @var $weekEnd string */
/* @var $prevWeek string */
/* @var $nextWeek string */
/* @var $level string */
/* @var $factor float */
/* @var $onlyMissing bool */
/* @var $totalConFaltante int */
/* @var $totalConfigurados int */
/* @var $sinStockIdeal int */

$this->title = 'Planeación de compras';
$this->params['breadcrumbs'][] = ['label' => 'KPI\'s y Control', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;

$fmtDate = function ($ymd) {
    return Yii::$app->formatter->asDate($ymd, 'php:d/m/Y');
};
$fmtQty = function ($n) {
    return number_format((float)$n, 2, ',', '.');
};
?>
<div class="purchase-planning-index">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h3 class="card-title mb-0">
                <i class="fas fa-shopping-basket"></i> <?= Html::encode($this->title) ?>
            </h3>
            <div class="btn-group">
                <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['index', 'week' => $prevWeek, 'only_missing' => (int)$onlyMissing]) ?>">
                    <i class="fas fa-chevron-left"></i> Semana anterior
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['index', 'only_missing' => (int)$onlyMissing]) ?>">
                    Semana que viene
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['index', 'week' => $nextWeek, 'only_missing' => (int)$onlyMissing]) ?>">
                    Semana siguiente <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Semana del <?= $fmtDate($weekStart) ?> al <?= $fmtDate($weekEnd) ?></strong>
                &middot; Nivel:
                <span class="badge bg-<?= PurchasePlanningConfig::levelBadge($level) ?>">
                    <?= Html::encode(PurchasePlanningConfig::levelLabel($level)) ?>
                </span>
                &middot; Factor: <strong>&times;<?= number_format($factor, 2, '.', '') ?></strong>
                &middot; <a href="<?= Url::to(['calendar']) ?>">Cambiar nivel de la semana o los factores</a>
                <br>
                <small>
                    Stock ideal = stock máximo del insumo &times; factor del nivel.
                    Falta = stock ideal &minus; existencia actual. Existencia y stocks se expresan en la
                    <strong>unidad de uso</strong> del insumo. La columna "Comprar" lo convierte a su
                    <strong>unidad de compra</strong> usando la equivalencia y el último precio de compra que ya
                    tiene el insumo (se redondea hacia arriba). Si necesitas otra presentación o proveedor para un
                    insumo, puedes definirla en el <a href="<?= Url::to(['/purchase-catalog/index']) ?>">catálogo de compras</a>
                    (opcional).
                </small>
            </div>

            <?php if ($sinStockIdeal > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong><?= (int)$sinStockIdeal ?></strong> insumo(s) no tienen <em>stock máximo</em> configurado y no
                    aparecen aquí. Defínelo en el catálogo de insumos para incluirlos en la planeación.
                </div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-cart-plus"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Insumos por comprar</span>
                            <span class="info-box-number"><?= (int)$totalConFaltante ?></span>
                            <small>de <?= (int)$totalConfigurados ?> con stock máximo definido</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Costo estimado de la compra</span>
                            <span class="info-box-number">$<?= number_format((float)$costoTotal, 2, ',', '.') ?></span>
                            <small>según el último precio de compra de cada insumo</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-box-open"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Sin equivalencia de compra</span>
                            <span class="info-box-number"><?= (int)$sinPresentacion ?></span>
                            <small>completa la equivalencia en el insumo o define una presentación</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-2">
                <?php if ($onlyMissing): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['index', 'week' => $weekStart, 'only_missing' => 0]) ?>">
                        Ver todos los insumos
                    </a>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['index', 'week' => $weekStart, 'only_missing' => 1]) ?>">
                        Ver solo los que hay que comprar
                    </a>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Unidad de uso</th>
                        <th class="text-end">Existencia actual</th>
                        <th class="text-end">Stock máximo (base)</th>
                        <th class="text-end">Stock ideal de la semana</th>
                        <th class="text-end">Falta</th>
                        <th>Comprar</th>
                        <th class="text-end">Costo est.</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <?= $onlyMissing
                                    ? 'No hay insumos por comprar para esta semana con el nivel y los stocks actuales.'
                                    : 'No hay insumos con stock máximo configurado.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= Html::encode($r['nombre']) ?></td>
                            <td><?= Html::encode($r['unidad']) ?></td>
                            <td class="text-end"><?= $fmtQty($r['actual']) ?></td>
                            <td class="text-end"><?= $fmtQty($r['base']) ?></td>
                            <td class="text-end"><?= $fmtQty($r['ideal']) ?></td>
                            <td class="text-end">
                                <?php if ($r['faltante'] > 0): ?>
                                    <strong class="text-danger"><?= $fmtQty($r['faltante']) ?></strong>
                                <?php else: ?>
                                    <span class="text-success">Cubierto</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $c = $r['compra']; ?>
                                <?php if ($r['faltante'] <= 0): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($c === null): ?>
                                    <a class="btn btn-sm btn-outline-warning"
                                       href="<?= Url::to(['/ingredient-stock/update', 'id' => $r['id']]) ?>">
                                        <i class="fas fa-edit"></i> Completar equivalencia en el insumo
                                    </a>
                                    <br>
                                    <small>
                                        <a href="<?= Url::to(['/purchase-catalog/create', 'ingredient_stock_id' => $r['id']]) ?>">
                                            o definir una presentación
                                        </a>
                                    </small>
                                <?php else: ?>
                                    <strong><?= (int)$c['cantidad'] ?> &times; <?= Html::encode($c['nombre']) ?></strong>
                                    <small class="text-muted">
                                        (<?= rtrim(rtrim(number_format($c['contenido'], 4, ',', '.'), '0'), ',') ?> <?= Html::encode($r['unidad']) ?> c/u)
                                    </small>
                                    <?php if ($c['proveedor']): ?>
                                        <br><small class="text-muted"><i class="fas fa-truck"></i> <?= Html::encode($c['proveedor']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($c['origen'] === 'insumo'): ?>
                                        <br><small class="text-muted">según la unidad de compra del insumo</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($r['compra'] !== null && $r['compra']['tiene_precio']): ?>
                                    $<?= number_format((float)$r['compra']['costo'], 2, ',', '.') ?>
                                    <?php if ($r['compra']['precio_origen'] === 'insumo'): ?>
                                        <br><small class="text-muted">último precio del insumo</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
