<?php

use common\models\PurchasePlanningConfig;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

/* @var $this yii\web\View */
/* @var $pagination yii\data\Pagination */
/* @var $totalRows int */
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

// Paginado (se imprime arriba y abajo de la tabla).
$pagerHtml = $pagination->pageCount > 1 ? LinkPager::widget([
    'pagination' => $pagination,
    'options' => ['class' => 'pagination pagination-sm m-0 flex-wrap'],
    'linkContainerOptions' => ['class' => 'page-item'],
    'linkOptions' => ['class' => 'page-link'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'firstPageLabel' => '«',
    'lastPageLabel' => '»',
    'prevPageLabel' => '‹',
    'nextPageLabel' => '›',
    'maxButtonCount' => 7,
]) : '';
$shownFrom = $totalRows > 0 ? $pagination->offset + 1 : 0;
$shownTo = min($pagination->offset + $pagination->limit, $totalRows);
$perPage = (int)$pagination->getPageSize();
$qParam = $q !== '' ? $q : null; // para conservar la búsqueda en los enlaces
?>
<div class="purchase-planning-index">
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h3 class="card-title mb-0">
                <i class="fas fa-shopping-basket"></i> <?= Html::encode($this->title) ?>
            </h3>
            <div class="btn-group">
                <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['index', 'week' => $prevWeek, 'only_missing' => (int)$onlyMissing, 'q' => $qParam]) ?>">
                    <i class="fas fa-chevron-left"></i> Semana anterior
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['index', 'only_missing' => (int)$onlyMissing, 'q' => $qParam]) ?>">
                    Semana que viene
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= Url::to(['index', 'week' => $nextWeek, 'only_missing' => (int)$onlyMissing, 'q' => $qParam]) ?>">
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

            <?php
            // Tarjetas de resumen con el estilo del tema (Sneat): icono boxicons dentro de un cuadro de color.
            $summaryCards = [
                [
                    'color' => 'danger',
                    'icon' => 'bx-cart',
                    'title' => 'Insumos por comprar',
                    'value' => (string)(int)$totalConFaltante,
                    'note' => 'de ' . (int)$totalConfigurados . ' con stock máximo definido',
                ],
                [
                    'color' => 'success',
                    'icon' => 'bx-dollar-circle',
                    'title' => 'Costo estimado de la compra',
                    'value' => '$' . number_format((float)$costoTotal, 2, ',', '.'),
                    'note' => 'según el último precio de compra de cada insumo',
                ],
                [
                    'color' => 'warning',
                    'icon' => 'bx-error-circle',
                    'title' => 'Sin equivalencia de compra',
                    'value' => (string)(int)$sinPresentacion,
                    'note' => 'completa la equivalencia en el insumo o define una presentación',
                ],
            ];
            ?>
            <div class="row g-3 mb-3">
                <?php foreach ($summaryCards as $card): ?>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-none border">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="avatar avatar-md flex-shrink-0">
                                    <span class="avatar-initial rounded bg-label-<?= $card['color'] ?>">
                                        <i class="bx <?= $card['icon'] ?> bx-sm"></i>
                                    </span>
                                </div>
                                <div>
                                    <div class="text-muted small"><?= Html::encode($card['title']) ?></div>
                                    <h4 class="mb-0"><?= Html::encode($card['value']) ?></h4>
                                    <small class="text-muted"><?= Html::encode($card['note']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <?= Html::beginForm(['index'], 'get', ['class' => 'flex-grow-1', 'style' => 'max-width: 520px;']) ?>
                <?= Html::hiddenInput('week', $weekStart) ?>
                <?= Html::hiddenInput('only_missing', (int)$onlyMissing) ?>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                    <?= Html::textInput('q', $q, [
                        'class' => 'form-control',
                        'placeholder' => 'Buscar insumo…',
                        'maxlength' => 100,
                        'autocomplete' => 'off',
                        'id' => 'plan-search',
                    ]) ?>
                    <button class="btn btn-primary" type="submit">Buscar</button>
                    <?php if ($q !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?= Url::to(['index', 'week' => $weekStart, 'only_missing' => (int)$onlyMissing]) ?>">Limpiar</a>
                    <?php endif; ?>
                </div>
                <?= Html::endForm() ?>

                <?php if ($onlyMissing): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['index', 'week' => $weekStart, 'only_missing' => 0, 'q' => $qParam]) ?>">
                        Ver todos los insumos
                    </a>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['index', 'week' => $weekStart, 'only_missing' => 1, 'q' => $qParam]) ?>">
                        Ver solo los que hay que comprar
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($q !== ''): ?>
                <div class="text-muted small mb-2">
                    Búsqueda: «<strong><?= Html::encode($q) ?></strong>» &middot; <?= (int)$totalRows ?> insumo(s) encontrado(s).
                    Las tarjetas de arriba son de toda la semana.
                </div>
            <?php endif; ?>

            <?php if ($totalRows > 0): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div class="text-muted small">
                        Mostrando <strong><?= $shownFrom ?></strong>&ndash;<strong><?= $shownTo ?></strong>
                        de <strong><?= $totalRows ?></strong> insumos
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Por página:</span>
                        <div class="btn-group btn-group-sm">
                            <?php foreach ([25, 50, 100] as $n): ?>
                                <a class="btn btn-outline-secondary <?= $perPage === $n ? 'active' : '' ?>"
                                   href="<?= Url::current(['per-page' => $n, 'page' => null]) ?>"><?= $n ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?= Html::beginForm(['/purchase-request/review'], 'post', ['id' => 'plan-form']) ?>
            <?= Html::hiddenInput('week', $weekStart) ?>
            <?= $q !== '' ? Html::hiddenInput('q', $q) : '' ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <button type="submit" name="mode" value="selected" id="btn-selected" class="btn btn-primary btn-sm" disabled>
                    <i class="fas fa-paper-plane"></i> Preparar solicitud con los seleccionados (<span id="sel-count">0</span>)
                </button>
                <?php if ($q !== '' && $totalSolicitablesBusqueda > 0): ?>
                    <button type="submit" name="mode" value="all" class="btn btn-outline-primary btn-sm">
                        Preparar solicitud con los insumos de la búsqueda (<?= (int)$totalSolicitablesBusqueda ?>)
                    </button>
                <?php elseif ($q === '' && $totalSolicitables > 0): ?>
                    <button type="submit" name="mode" value="all" class="btn btn-outline-primary btn-sm">
                        Preparar solicitud con todos los insumos por comprar (<?= (int)$totalSolicitables ?>)
                    </button>
                <?php endif; ?>
                <a class="btn btn-link btn-sm ms-auto" href="<?= Url::to(['/purchase-request/index']) ?>">
                    <i class="fas fa-history"></i> Ver solicitudes enviadas
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <th style="width:40px">
                            <input type="checkbox" id="select-page" class="form-check-input" title="Seleccionar los de esta página">
                        </th>
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
                            <td colspan="9" class="text-center text-muted py-4">
                                <?php if ($q !== ''): ?>
                                    Ningún insumo coincide con «<?= Html::encode($q) ?>»<?= $onlyMissing ? ' entre los que hay que comprar. Prueba con «Ver todos los insumos».' : '.' ?>
                                <?php else: ?>
                                    <?= $onlyMissing
                                        ? 'No hay insumos por comprar para esta semana con el nivel y los stocks actuales.'
                                        : 'No hay insumos con stock máximo configurado.' ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <?php if ($r['faltante'] > 0 && $r['compra'] !== null): ?>
                                    <input type="checkbox" name="ingredient_ids[]" value="<?= (int)$r['id'] ?>" class="form-check-input row-pick">
                                <?php endif; ?>
                            </td>
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
            <?= Html::endForm() ?>

            <?php if ($pagerHtml): ?>
                <nav class="mt-3" aria-label="Paginado de insumos"><?= $pagerHtml ?></nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
// Casillas: contador de seleccionados y "seleccionar los de esta página".
$this->registerJs(<<<'JS'
(function () {
    var picks = document.querySelectorAll('.row-pick');
    var btn = document.getElementById('btn-selected');
    var counter = document.getElementById('sel-count');
    var all = document.getElementById('select-page');
    function refresh() {
        var n = 0;
        picks.forEach(function (c) { if (c.checked) n++; });
        if (counter) counter.textContent = n;
        if (btn) btn.disabled = n === 0;
        if (all) { all.checked = n > 0 && n === picks.length; all.indeterminate = n > 0 && n < picks.length; }
    }
    picks.forEach(function (c) { c.addEventListener('change', refresh); });
    if (all) all.addEventListener('change', function () {
        picks.forEach(function (c) { c.checked = all.checked; });
        refresh();
    });
    refresh();
})();
JS
);
?>
