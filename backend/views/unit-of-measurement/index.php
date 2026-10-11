<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $searchModel common\models\UnitOfMeasurementSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = Yii::t('app', 'Unit Of Measurements');
$this->params['breadcrumbs'][] = $this->title;

$this->registerJsFile(Yii::getAlias("@web/js/um/index.js"), [
    'depends' => \yii\web\YiiAsset::class
]);
$this->registerCss('
    .grid-view th a {
        color: #333;
        text-decoration: none;
        position: relative;
        display: block;
    }
    .grid-view th a.asc:after {
        content: " ▲";
        font-size: 12px;
    }
    .grid-view th a.desc:after {
        content: " ▼";
        font-size: 12px;
    }
    .grid-view th a:hover {
        color: #fca311;
        text-decoration: none;
    }
        /* Estilos para encabezados fijos */
    .sticky-header-container {
        position: relative;
        overflow: auto;
        max-height: calc(90vh - 80px); /* Ajusta según tu diseño */
        margin-bottom: 10px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    
    .sticky-header-table {
        margin-bottom: 0;
    }
    
    .sticky-header-table thead th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 10;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    /* Columnas sticky para scroll horizontal */
    .sticky-header-table th:first-child,
    .sticky-header-table td:first-child,
    .sticky-header-table th:nth-child(2),
    .sticky-header-table td:nth-child(2) {
        position: sticky;
        left: 0;
        background-color: #f8f9fa;
        z-index: 5;
    }
    
    .sticky-header-table th:nth-child(2),
    .sticky-header-table td:nth-child(2) {
        left: 20px; /* Ancho aproximado de la primera columna */
        box-shadow: 2px 0 2px rgba(0,0,0,0.1);
    }
    
    .sticky-header-table thead th:first-child,
    .sticky-header-table thead th:nth-child(2) {
        z-index: 15; /* Mayor que el header normal para que se superponga correctamente */
    }
    
    /* Ajustar el ancho mínimo de las primeras columnas */
    .sticky-header-table th:first-child,
    .sticky-header-table td:first-child {
        min-width: 40px;
        max-width: 80px;
    }
    
    .sticky-header-table th:nth-child(2),
    .sticky-header-table td:nth-child(2) {
        min-width: 120px; /* Ancho para el nombre */
    }
    
    /* Mejorar la apariencia de las columnas ordenables */
    .sortable-column {
        background-color: rgba(0,0,0,0.01);
    }
    
    /* Asegurar que el texto de los encabezados no se corte */
    .sticky-header-table th {
        white-space: normal;
        vertical-align: middle;
    }
');
?>
    <div class="unit-of-measurement-index">

        <p>
            <?php if (Yii::$app->user->can('unit_measurement_create')): ?>
                <?= Html::a(Yii::t('app', 'Add Unit Of Measurement'), ['create'], [
                        'class' => 'btn btn-success',
                    'id' => 'create-um'
                ]) ?>
            <?php endif; ?>
        </p>
<!-- Selector de elementos por página y filtros mejorados -->
<div class="row mb-2 align-items-center">
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light"><?= Yii::t('app', 'Mostrar') ?></span>
            <select id="per-page-selector" class="form-select form-select-sm" style="width: auto; max-width: 78px;">
                <?php foreach ([10, 25, 50, 100] as $value): ?>
                <option value="<?= $value ?>" <?= $dataProvider->pagination->pageSize == $value ? 'selected' : '' ?>><?= $value ?></option>
                <?php endforeach; ?>
            </select>
            <span class="input-group-text bg-light"><?= Yii::t('app', 'unidades por página') ?></span>
        </div>
    </div>
</div>
        <?php Pjax::begin(['id' => 'unit-of-measurement-pjax']); ?>
        <?php // echo $this->render('_search', ['model' => $searchModel]); ?>
        <div class="table-responsive sticky-header-container">
        <div class="row"></div>
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => ['class' => 'table sticky-header-table'],
            'options' => ['class' => 'grid-view sticky-header-grid'],
            'layout' => "{items}\n<div class='d-flex justify-content-between align-items-center mt-3'><div>{pager}</div><div>{summary}</div></div>",
                'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                [
                    'attribute' => 'name',
                    'filter' => \yii\helpers\Html::activeTextInput($searchModel, 'name', [
                        'class' => 'form-control form-control-sm',
                        'style' => 'padding-right: 30px; background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgdmlld0JveD0iMCAwIDE2IDE2Ij4KPHBhdGggZD0iTSAxMC41IDEgQyA4LjAyNzI3MjcgMSA2IDMuMDI3MjcyIDYgNS41IEMgNiA2LjU1NDE0NTkgNi40MjI3OTM2IDcuNDg2MTgxIDcuMDM3MTA5NCA4LjI1NTg1OTQgTCAyLjA0Njg3NSAxMy4yNDYwOTQgTCAyLjc1MzkwNjIgMTMuOTUzMTI1IEwgNy43NDQxNDA2IDguOTYyODkwNiBDIDguNTEzODE4NSA5LjU3NzIwNjQgOS40NDU4NTQxIDEwIDEwLjUgMTAgQyAxMi45NzI3MjcgMTAgMTUgNy45NzI3MjcgMTUgNS41IEMgMTUgMy4wMjcyNzMgMTIuOTcyNzMgMSAxMC41IDEgeiBNIDEwLjUgMiBDIDEyLjQyNzI3MyAyIDE0IDMuNTcyNzI3MyAxNCA1LjUgQyAxNCA3LjQyNzI3MyAxMi40MjcyNzMgOSAxMC41IDkgQyA4LjU3MjcyNyA5IDcgNy40MjcyNzMgNyA1LjUgQyA3IDMuNTcyNzI3MyA4LjU3MjcyNyAyIDEwLjUgMiB6Ij48L3BhdGg+Cjwvc3ZnPgo=); background-repeat: no-repeat; background-position: right 10px center;'
                    ]),
                ],
                // [
                //     'attribute' => 'type',
                //     'format' => 'raw',
                //     'value' => function ($model) {
                //         if ($model->type === \common\models\UnitOfMeasurement::TYPE_PURCHASE) {
                //             return '<span class="badge bg-primary"><i class="fas fa-shopping-cart"></i> Compra</span>';
                //         } else {
                //             return '<span class="badge bg-info"><i class="fas fa-utensils"></i> Cocina</span>';
                //         }
                //     },
                //     'filter' => [
                //         \common\models\UnitOfMeasurement::TYPE_KITCHEN => 'Cocina',
                //         \common\models\UnitOfMeasurement::TYPE_PURCHASE => 'Compra'
                //     ],
                //     'headerOptions' => ['style' => 'width: 120px;']
                // ],
                [
                    'attribute' => 'custom',
                    'format' => 'raw',
                    'value' => function ($model) {
                        if ($model->custom == 1) {
                            return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle"></i> Personalizada</span>';
                        } else {
                            return '<span class="badge bg-success"><i class="fas fa-check"></i> Estándar</span>';
                        }
                    },
                    'filter' => [
                        0 => 'Estándar',
                        1 => 'Personalizada'
                    ],
                    'headerOptions' => ['style' => 'width: 150px;'],
                ],

                [
                    'attribute' => 'is_purchase',
                    'label' => 'Insumos<br>(Compras)',
                    'encodeLabel' => false,
                    'format' => 'raw',
                    'value' => function($model) {
                        return $model->is_purchase
                            ? '<span class="badge bg-success">Sí</span>'
                            : '<span class="badge bg-danger">No</span>';
                    },
                    'headerOptions' => ['style' => 'width: 80px;'],
                ],
                [
                    'attribute' => 'is_kitchen',
                    'label' => 'Insumos<br>(Uso)',
                    'encodeLabel' => false,
                    'format' => 'raw',
                    'value' => function($model) {
                        return $model->is_kitchen
                            ? '<span class="badge bg-success">Sí</span>'
                            : '<span class="badge bg-danger">No</span>';
                    },
                    'headerOptions' => ['style' => 'width: 80px;'],
                ],
                [
                    'attribute' => 'is_subrecipe_yield',
                    'label' => 'Subrecetas<br>(Rendimiento)',
                    'encodeLabel' => false,
                    'format' => 'raw',
                    'value' => function($model) {
                        return $model->is_subrecipe_yield
                            ? '<span class="badge bg-success">Sí</span>'
                            : '<span class="badge bg-danger">No</span>';
                    },
                    'headerOptions' => ['style' => 'width: 100px;'],
                ],
                [
                    'attribute' => 'is_subrecipe_um',
                    'label' => 'Subrecetas<br>(Unidad de medida)',
                    'encodeLabel' => false,
                    'format' => 'raw',
                    'value' => function($model) {
                        return $model->is_subrecipe_um
                            ? '<span class="badge bg-success">Sí</span>'
                            : '<span class="badge bg-danger">No</span>';
                    },
                    'headerOptions' => ['style' => 'width: 120px;'],
                ],
                [
                    'attribute' => 'is_recipe_yield',
                    'label' => 'Recetas<br>(rendimiento)',
                    'encodeLabel' => false,
                    'format' => 'raw',
                    'value' => function($model) {
                        return $model->is_recipe_yield
                            ? '<span class="badge bg-success">Sí</span>'
                            : '<span class="badge bg-danger">No</span>';
                    },
                    'headerOptions' => ['style' => 'width: 100px;'],
                ],
                [
                    'attribute' => 'is_recipe_final_um',
                    'label' => 'Recetas<br>(unidad final)',
                    'encodeLabel' => false,
                    'format' => 'raw',
                    'value' => function($model) {
                        return $model->is_recipe_final_um
                            ? '<span class="badge bg-success">Sí</span>'
                            : '<span class="badge bg-danger">No</span>';
                    },
                    'headerOptions' => ['style' => 'width: 100px;'],
                ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'template' => "{update} {delete}",
                    'visibleButtons' => [
                        'update' => function ($model, $key, $index) {
                            return Yii::$app->user->can('unit_measurement_update');
                        },
                        'delete' => function ($model, $key, $index) {
                            // Solo mostrar botón delete para unidades personalizadas Y con permiso
                            return $model->custom == 1 && Yii::$app->user->can('unit_measurement_delete');
                        }
                    ],
                    'buttons' => [
                        'update' => function ($url, $model, $key) {
                            return \yii\bootstrap5\Html::a(
                                "<i class='bx bx-edit'></i>",
                                $url,
                                [
                                    'class' => 'update-um text-warning',
                                    'data-bs-toggle' => 'tooltip',
                                    'title' => 'Editar unidad de medida'
                                ]
                            );
                        },
                        'delete' => function ($url, $model, $key) {
                            return \yii\bootstrap5\Html::a(
                                "<i class='bx bx-trash'></i>",
                                $url,
                                [
                                    'class' => 'text-danger',
                                    'data-confirm' => '¿Está seguro de que desea eliminar la unidad de medida "' . $model->name . '"?',
                                    'data-method' => 'post',
                                ]
                            );
                        },
                    ]
                ],
            ],
        ]); ?>

        <?php Pjax::end(); ?>
        </div>
    </div>
<?php
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-form-um',
]);

echo '<div id="form-um-container"></div>';

\yii\bootstrap5\Modal::end();
?>
<script>
    // Función para guardar elementos por página en localStorage
    function savePerPageToStorage(pageSize) {
        localStorage.setItem('unit-measurement-per-page', pageSize);
    }
    
    // Función para obtener elementos por página del localStorage
    function getPerPageFromStorage() {
        const saved = localStorage.getItem('unit-measurement-per-page');
        return saved || '10'; // Default 10 si no hay valor guardado
    }
    
    // Aplicar configuración guardada al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        const perPageSelector = document.getElementById('per-page-selector');
        const savedPerPage = getPerPageFromStorage();
        
        // Establecer el valor guardado en el selector
        perPageSelector.value = savedPerPage;
        
        // Si el valor actual es diferente al guardado, aplicar el guardado
        const currentPageSize = '<?= $dataProvider->pagination->pageSize ?>';
        if (currentPageSize != savedPerPage) {
            // Crear URL con el valor guardado y recargar
            let url = new URL(window.location);
            url.searchParams.set('per-page', savedPerPage);
            
            $.pjax.reload({
                container: '#unit-of-measurement-pjax',
                url: url.toString(),
                timeout: 10000
            });
        }
    });
    
    // Detector de cambio en elementos por página
    document.getElementById('per-page-selector').addEventListener('change', function() {
        const pageSize = this.value;
        
        // Guardar en localStorage
        savePerPageToStorage(pageSize);
        
        // Crear URL con nuevo tamaño de página
        let url = new URL(window.location);
        url.searchParams.set('per-page', pageSize);
        
        // Recargar con el nuevo tamaño de página
        $.pjax.reload({
            container: '#unit-of-measurement-pjax',
            url: url.toString(),
            timeout: 10000
        });
    });
</script>