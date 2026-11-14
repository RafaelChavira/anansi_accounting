<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $q string */
/* @var $providerId string|null */
/* @var $status string */
/* @var $providers array */

$this->title = 'Presentaciones adicionales';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de compras', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$money = function ($n) {
    return $n === null ? '—' : '$' . number_format((float)$n, 2, ',', '.');
};
$qty = function ($n) {
    return rtrim(rtrim(number_format((float)$n, 4, ',', '.'), '0'), ',');
};
?>
<div class="purchase-catalog-index">

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success"><?= Html::encode(Yii::$app->session->getFlash('success')) ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <strong>Esto es opcional.</strong> Cada insumo ya define su <em>unidad de compra</em>, su
        <em>equivalencia a unidades de uso</em> y su <em>precio de compra</em> (los ves en el
        <a href="<?= Url::to(['index']) ?>">catálogo de compras</a>), y la planeación usa esos datos por defecto.
        Define aquí una presentación <strong>solo si necesitas otra</strong> para un insumo (por ejemplo "Caja de 24
        piezas" de un proveedor y "Caja de 12" de otro), con su código y su mínimo por pedido. El contenido se expresa
        en la <strong>unidad de uso</strong> del insumo. Si defines una presentación aquí, la planeación la usa en lugar
        de la del insumo.
    </div>

    <form method="get" action="<?= Url::to(['presentations']) ?>" class="row g-2 mb-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" value="<?= Html::encode($q) ?>" class="form-control"
                   placeholder="Insumo, presentación o código del proveedor">
        </div>
        <div class="col-md-3">
            <label class="form-label">Proveedor</label>
            <?= Html::dropDownList('provider_id', $providerId, $providers, ['class' => 'form-select', 'prompt' => 'Todos']) ?>
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <?= Html::dropDownList('status', $status, ['all' => 'Todas', 'active' => 'Activas', 'inactive' => 'Inactivas'], ['class' => 'form-select']) ?>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i> Filtrar</button>
            <a class="btn btn-primary ms-auto" href="<?= Url::to(['create']) ?>"><i class="fas fa-plus"></i> Nueva presentación</a>
        </div>
    </form>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            [
                'label' => 'Insumo',
                'attribute' => 'ingrediente',
                'value' => function ($m) {
                    return $m->ingredientStock ? $m->ingredientStock->ingredient : '—';
                },
            ],
            [
                'label' => 'Proveedor',
                'value' => function ($m) {
                    return $m->provider ? ($m->provider->business_name ?: $m->provider->name) : 'Sin proveedor';
                },
            ],
            [
                'attribute' => 'name',
                'label' => 'Presentación',
                'format' => 'raw',
                'value' => function ($m) {
                    $h = Html::encode($m->name);
                    if ($m->sku) {
                        $h .= ' <small class="text-muted">(' . Html::encode($m->sku) . ')</small>';
                    }
                    if ($m->is_default) {
                        $h .= ' <span class="badge bg-primary">Predeterminada</span>';
                    }
                    return $h;
                },
            ],
            [
                'label' => 'Contenido',
                'value' => function ($m) use ($qty) {
                    $use = $m->ingredientStock ? ($m->ingredientStock->portion_um ?: $m->ingredientStock->um) : '';
                    return $qty($m->units_per_presentation) . ' ' . $use;
                },
            ],
            [
                'attribute' => 'price',
                'label' => 'Precio',
                'value' => function ($m) use ($money) {
                    return $money($m->price);
                },
            ],
            [
                'label' => 'Precio por unidad de uso',
                'value' => function ($m) use ($money) {
                    $u = $m->getUnitPrice();
                    return $u === null ? '—' : '$' . number_format($u, 4, ',', '.');
                },
            ],
            [
                'label' => 'Estado',
                'format' => 'raw',
                'value' => function ($m) {
                    return $m->is_active
                        ? '<span class="badge bg-success">Activa</span>'
                        : '<span class="badge bg-secondary">Inactiva</span>';
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'urlCreator' => function ($action, $m) {
                    return Url::to([$action, 'id' => $m->id]);
                },
                'buttons' => [
                    'update' => function ($url) {
                        return Html::a('<i class="fas fa-edit"></i>', $url, ['title' => 'Editar', 'class' => 'text-warning me-2']);
                    },
                    'delete' => function ($url) {
                        return Html::a('<i class="fas fa-trash"></i>', $url, [
                            'title' => 'Eliminar',
                            'class' => 'text-danger',
                            'data-method' => 'post',
                            'data-confirm' => '¿Eliminar esta presentación del catálogo?',
                        ]);
                    },
                ],
            ],
        ],
    ]) ?>
</div>
