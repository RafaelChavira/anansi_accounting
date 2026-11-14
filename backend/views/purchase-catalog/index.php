<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

/* @var $this yii\web\View */
/* @var $rows array */
/* @var $q string */
/* @var $filter string */
/* @var $counts array */
/* @var $pagination yii\data\Pagination */
/* @var $totalRows int */

$this->title = 'Catálogo de compras';
$this->params['breadcrumbs'][] = $this->title;

$money = function ($n, $dec = 2) {
    return '$' . number_format((float)$n, $dec, ',', '.');
};
$qty = function ($n) {
    return rtrim(rtrim(number_format((float)$n, 4, ',', '.'), '0'), ',');
};
$qParam = $q !== '' ? $q : null;
$filters = [
    'all' => ['Todos', 'secondary'],
    'pendientes' => ['Con datos pendientes', 'warning'],
    'sin_equivalencia' => ['Sin equivalencia', 'warning'],
    'sin_precio' => ['Sin precio', 'warning'],
    'sin_proveedor' => ['Sin proveedor', 'warning'],
];
$shownFrom = $totalRows > 0 ? $pagination->offset + 1 : 0;
$shownTo = min($pagination->offset + $pagination->limit, $totalRows);
$perPage = (int)$pagination->getPageSize();
?>
<div class="purchase-catalog-index">

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Aquí ves, para cada insumo, <strong>los datos de compra que ya tiene</strong>: su unidad de compra, la equivalencia a
        unidades de uso, su último precio de compra y sus proveedores, además del <strong>último proveedor con el que
        trabajaste</strong>. La planeación de compras usa justo estos datos. Para corregir algo, entra al insumo
        (<em>Editar insumo</em>). Si algún insumo se compra en otra presentación, puedes darla de alta en
        <a href="<?= Url::to(['presentations']) ?>">presentaciones adicionales</a>.
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <?php foreach ($filters as $key => $meta): ?>
            <a class="btn btn-sm <?= $filter === $key ? 'btn-' . $meta[1] : 'btn-outline-' . $meta[1] ?>"
               href="<?= Url::to(['index', 'filter' => $key === 'all' ? null : $key, 'q' => $qParam]) ?>">
                <?= Html::encode($meta[0]) ?> <span class="badge bg-white text-dark ms-1"><?= (int)$counts[$key] ?></span>
            </a>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-link ms-auto" href="<?= Url::to(['presentations']) ?>">
            <i class="fas fa-box"></i> Presentaciones adicionales
        </a>
    </div>

    <?= Html::beginForm(['index'], 'get', ['class' => 'mb-3', 'style' => 'max-width: 520px;']) ?>
    <?= $filter !== 'all' ? Html::hiddenInput('filter', $filter) : '' ?>
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bx bx-search"></i></span>
        <?= Html::textInput('q', $q, ['class' => 'form-control', 'placeholder' => 'Buscar insumo…', 'maxlength' => 100, 'autocomplete' => 'off']) ?>
        <button class="btn btn-primary" type="submit">Buscar</button>
        <?php if ($q !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?= Url::to(['index', 'filter' => $filter === 'all' ? null : $filter]) ?>">Limpiar</a>
        <?php endif; ?>
    </div>
    <?= Html::endForm() ?>

    <div class="card">
        <div class="card-body">
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

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Insumo</th>
                        <th>Unidad de compra</th>
                        <th>Equivalencia</th>
                        <th class="text-end">Último precio de compra</th>
                        <th>Proveedores</th>
                        <th>Último proveedor usado</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <?= $q !== ''
                                    ? 'Ningún insumo coincide con «' . Html::encode($q) . '».'
                                    : 'No hay insumos en esta vista.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $missing = [];
                        if (!$r['tiene_equivalencia']) {
                            $missing[] = 'Sin equivalencia';
                        }
                        if ($r['precio'] === null) {
                            $missing[] = 'Sin precio';
                        }
                        if (empty($r['proveedores'])) {
                            $missing[] = 'Sin proveedor';
                        }
                        $provNames = array_values($r['proveedores']);
                        ?>
                        <tr>
                            <td>
                                <strong><?= Html::encode($r['nombre']) ?></strong>
                                <?php if ($r['marca'] || $r['presentacion']): ?>
                                    <br><small class="text-muted"><?= Html::encode(trim($r['marca'] . ' ' . $r['presentacion'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['um'] ? Html::encode($r['um']) : '<span class="text-muted">—</span>' ?></td>
                            <td>
                                <?php if ($r['tiene_equivalencia']): ?>
                                    1 <?= Html::encode($r['um']) ?> = <strong><?= $qty($r['ppu']) ?></strong> <?= Html::encode($r['use_um']) ?>
                                <?php else: ?>
                                    <span class="text-warning">Falta</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($r['precio'] !== null): ?>
                                    <?= $money($r['precio']) ?><?= $r['um'] ? ' <small class="text-muted">/ ' . Html::encode($r['um']) . '</small>' : '' ?>
                                    <?php if ($r['precio_uso'] !== null && $r['use_um']): ?>
                                        <br><small class="text-muted"><?= $money($r['precio_uso'], 4) ?> / <?= Html::encode($r['use_um']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($provNames)): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <?php foreach (array_slice($provNames, 0, 3) as $pn): ?>
                                        <span class="badge bg-label-secondary"><?= Html::encode($pn) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($provNames) > 3): ?>
                                        <span class="badge bg-label-secondary">+<?= count($provNames) - 3 ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['ultimo'] !== null): ?>
                                    <?= Html::encode($r['ultimo']['nombre']) ?>
                                    <br><small class="text-muted">
                                        <?= $r['ultimo']['source'] === 'solicitud' ? 'solicitud' : 'compra' ?>
                                        del <?= Yii::$app->formatter->asDate($r['ultimo']['at'], 'php:d/m/Y') ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($missing)): ?>
                                    <span class="badge bg-label-success">Completo</span>
                                <?php else: ?>
                                    <?php foreach ($missing as $m): ?>
                                        <span class="badge bg-label-warning"><?= Html::encode($m) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($r['extra'] > 0): ?>
                                    <a class="badge bg-label-info" title="Presentaciones adicionales"
                                       href="<?= Url::to(['presentations', 'q' => $r['nombre']]) ?>">+<?= (int)$r['extra'] ?> presentación(es)</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['/ingredient-stock/update', 'id' => $r['id']]) ?>">
                                    <i class="fas fa-edit"></i> Editar insumo
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pagination->pageCount > 1): ?>
                <nav class="mt-3" aria-label="Paginado de insumos"><?= LinkPager::widget([
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
                    ]) ?></nav>
            <?php endif; ?>
        </div>
    </div>
</div>
