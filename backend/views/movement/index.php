<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

/* @var $this yii\web\View */
/* @var $searchModel common\models\MovementSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$isConsumptionRequester = Yii::$app->user->can('consumption_requester');
$canDelete = Yii::$app->user->can('manage_users');
$this->title = $isConsumptionRequester ? Yii::t('app', 'Historial de mis Requisiciones') : Yii::t('app', 'Movements');
$this->params['breadcrumbs'][] = $this->title;

$this->registerJsFile(Yii::getAlias("@web/js/movement/index.js"), [
    'depends' => \yii\web\YiiAsset::class
]);
$businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
$business = \common\models\Business::findOne(['id' => $businessData['id']]);
?>
<div class="movement-index">

    <?php
    // Obtener y consumir todos los mensajes flash para evitar duplicados en el layout
    $allFlashes = Yii::$app->session->getAllFlashes();
    // Limpiar los flashes para que no aparezcan en el layout
    Yii::$app->session->removeAllFlashes();
    
    // Mostrar mensajes flash encima de los botones
    foreach ($allFlashes as $key => $messages) {
        $alertClass = '';
        $iconClass = '';
        
        switch ($key) {
            case 'error':
                $alertClass = 'alert-danger';
                $iconClass = 'bx bx-error-circle';
                break;
            case 'success':
                $alertClass = 'alert-success';
                $iconClass = 'bx bx-check-circle';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                $iconClass = 'bx bx-info-circle';
                break;
            default:
                $alertClass = 'alert-info';
                $iconClass = 'bx bx-info-circle';
        }
        
        foreach ((array) $messages as $message) {
            echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show mb-3" role="alert">';
            echo '<i class="' . $iconClass . ' me-2"></i>';
            
            // Permitir HTML en mensajes de éxito y warning para mostrar listas de errores
            if ($key === 'success' || $key === 'warning') {
                echo $message;
            } else {
                echo Html::encode($message);
            }
            
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
    }
    ?>

    <style>
    .alert-success {
        background-color: #d1e7dd;
        border-color: #badbcc;
        color: #0f5132;
    }
    
    .alert-success .btn-outline-primary {
        border-color: #0f5132;
        color: #0f5132;
    }
    
    .alert-success .btn-outline-primary:hover {
        background-color: #0f5132;
        border-color: #0f5132;
        color: white;
    }
    
    .alert {
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .alert i {
        font-size: 1.2em;
        vertical-align: middle;
    }
    
    /* Colores para requisiciones según disponibilidad de stock */
    .requisition-insufficient-stock {
        color: #dc3545 !important; /* Rojo */
        font-weight: 500;
    }
    
    .requisition-available-stock {
        color: #0d6efd !important; /* Azul */
        font-weight: 500;
    }
    
    /* Asegurar que el texto de las celdas herede el color de la fila */
    .requisition-insufficient-stock td {
        color: inherit !important;
    }
    
    .requisition-available-stock td {
        color: inherit !important;
    }
    
    /* Contenedor sticky con scroll */
    .sticky-header-container {
        position: relative;
        overflow: auto;
        max-height: calc(100vh - 280px);
        margin-bottom: 15px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    
    /* Tabla con headers fijos */
    .sticky-header-table {
        margin-bottom: 0;
    }
    
    .sticky-header-table thead th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa;
        z-index: 10;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        white-space: normal;
        vertical-align: middle;
    }
    
    /* Scrollbar horizontal siempre visible */
    .sticky-header-container {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Forzar que el scrollbar horizontal siempre sea visible */
    .sticky-header-container::-webkit-scrollbar {
        height: 12px;
        -webkit-appearance: none;
    }
    
    .sticky-header-container::-webkit-scrollbar-track {
        background-color: #f1f1f1;
        border-radius: 10px;
    }
    
    .sticky-header-container::-webkit-scrollbar-thumb {
        background-color: #888;
        border-radius: 10px;
        border: 2px solid #f1f1f1;
    }
    
    .sticky-header-container::-webkit-scrollbar-thumb:hover {
        background-color: #555;
    }
    
    /* Scrollbar vertical también visible */
    .sticky-header-container::-webkit-scrollbar:vertical {
        width: 12px;
    }
    
    /* Para Firefox */
    .sticky-header-container {
        scrollbar-width: auto;
        scrollbar-color: #888 #f1f1f1;
    }
    </style>

    <script>
    // Inicializar tooltips de Bootstrap
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    
    // Re-inicializar tooltips después de que Pjax recargue el grid
    $(document).on('pjax:success', '#movements-pjax', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>

    <div class="d-flex flex-wrap">
        <?php if (Yii::$app->user->can('consumption_requester')): ?>
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Crear requisición'), ['create-requisition'], ['class' => 'btn btn-warning']) ?>
            </div>
        <?php else: ?>
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Create entry'), ['create', 'type' => \common\models\Movement::TYPE_INPUT], ['class' => 'btn btn-warning']) ?>
            </div>
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Create output'), ['create', 'type' => \common\models\Movement::TYPE_OUTPUT], ['class' => 'btn btn-warning']) ?>
            </div>
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Create order'), ['create', 'type' => \common\models\Movement::TYPE_ORDER], ['class' => 'btn btn-warning']) ?>
            </div>
            <!-- <div class="p-2">
                <?= Html::a(Yii::t('app', 'Create requisition'), ['create-requisition'], ['class' => 'btn btn-info']) ?>
            </div> -->
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Download template'), ['movement/download-template'], ['class' => 'btn btn-warning']) ?>
            </div>
            <div class="p-2">
                <?= \yii\bootstrap5\Html::a(Yii::t('app', 'Cargar movimientos', [
                ]), '#', [
                    'class' => 'btn btn-warning',
                    'data-bs-toggle' => 'modal',
                    'data-bs-target' => '#modal-upload-file'
                ]) ?>
            </div>
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Exportar movimientos'), '#', [
                    'class' => 'btn btn-warning',
                    'id' => 'btn-export-movements'
                ]) ?>
            </div>
            <div class="p-2">
                <?= Html::a(Yii::t('app', 'Balance'), "#", ['class' => 'btn btn-warning', 'data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-balance']) ?>
            </div>
            <!-- <?php if ($canDelete): ?>
            <div class="p-2">
                <?= Html::a('<i class="bx bx-trash"></i> ' . Yii::t('app', 'Eliminar seleccionados'), '#', [
                    'class' => 'btn btn-danger',
                    'id' => 'btn-delete-movements'
                ]) ?>
            </div>
            <?php endif; ?> -->
        <?php endif; ?>
    </div>

    <!-- Selector de elementos por página -->
    <div class="row mb-2 align-items-center mt-3">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><?= Yii::t('app', 'Mostrar') ?></span>
                <select id="per-page-selector-movements" class="form-select form-select-sm" style="width: auto; max-width: 78px;">
                    <?php foreach ([10, 25, 50, 100, 250, 500] as $value): ?>
                    <option value="<?= $value ?>" <?= $dataProvider->pagination->pageSize == $value ? 'selected' : '' ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="input-group-text bg-light"><?= Yii::t('app', 'movimientos por página') ?></span>
            </div>
        </div>
    </div>


    <?php Pjax::begin(['id' => 'movements-pjax']); ?>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?php
    // Definir columnas según el rol del usuario
    $columns = [
        [
            'class' => \yii\grid\CheckboxColumn::class,
            'checkboxOptions' => function ($model, $key, $index, $column) {
                return ['value' => $model->id];
            }
        ],
    ];

    // Columna de tipo (solo si NO es consumption_requester)
    if (!$isConsumptionRequester) {
        $columns[] = [
            'attribute' => 'type',
            'value' => function ($model) {
                $type = $model->formattedType;
                if ($model->type === \common\models\Movement::TYPE_ORDER) {
                    return $type . ' <span class="badge bg-info ms-1">Convertible a entrada</span>';
                }
                if ($model->type === \common\models\Movement::TYPE_REQUISITION) {
                    return $type . ' <span class="badge bg-warning ms-1">Convertible a salida</span>';
                }
                return $type;
            },
            'format' => 'raw',
            'filter' => \yii\bootstrap5\Html::activeDropDownList(
                $searchModel,
                'type',
                \common\models\Movement::getFormattedTypes(),
                [
                    'class' => 'form-control form-control-sm',
                    'prompt' => Yii::t('app', "All")
                ]
            ),
            'label' => $searchModel->getAttributeLabel('type')
        ];
    }

    // Columna número de requisición
    $columns[] = [
        'attribute' => 'requisition_number',
        'label' => 'Número de Requisición',
        'filter' => \yii\helpers\Html::activeTextInput($searchModel, 'requisition_number', [
                        'class' => 'form-control form-control-sm',
                        'style' => 'padding-right: 30px; background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgdmlld0JveD0iMCAwIDE2IDE2Ij4KPHBhdGggZD0iTSAxMC41IDEgQyA4LjAyNzI3MjcgMSA2IDMuMDI3MjcyIDYgNS41IEMgNiA2LjU1NDE0NTkgNi40MjI3OTM2IDcuNDg2MTgxIDcuMDM3MTA5NCA4LjI1NTg1OTQgTCAyLjA0Njg3NSAxMy4yNDYwOTQgTCAyLjc1MzkwNjIgMTMuOTUzMTI1IEwgNy43NDQxNDA2IDguOTYyODkwNiBDIDguNTEzODE4NSA5LjU3NzIwNjQgOS40NDU4NTQxIDEwIDEwLjUgMTAgQyAxMi45NzI3MjcgMTAgMTUgNy45NzI3MjcgMTUgNS41IEMgMTUgMy4wMjcyNzMgMTIuOTcyNzMgMSAxMC41IDEgeiBNIDEwLjUgMiBDIDEyLjQyNzI3MyAyIDE0IDMuNTcyNzI3MyAxNCA1LjUgQyAxNCA3LjQyNzI3MyAxMi40MjcyNzMgOSAxMC41IDkgQyA4LjU3MjcyNyA5IDcgNy40MjcyNzMgNyA1LjUgQyA3IDMuNTcyNzI3MyA4LjU3MjcyNyAyIDEwLjUgMiB6Ij48L3BhdGg+Cjwvc3ZnPgo=); background-repeat: no-repeat; background-position: right 10px center;'
                    ]),
        'value' => function ($model) {
            if ($model->type === 'requisition') {
                return $model->requisition_number ?? '-';
            }
            return '-';
        },
    ];

    // Columna fecha requerida (después de número de requisición)
    $columns[] = [
        'attribute' => 'required_date',
        'label' => 'Fecha Requerida',
        'value' => function($model) {
            if ($model->type === \common\models\Movement::TYPE_REQUISITION && $model->required_date) {
                $dt = new \DateTime($model->required_date);
                return $dt->format('d/m/Y');
            }
            return '-';
        },
        'contentOptions' => ['style' => 'text-align: center; white-space: nowrap;'],
        'visible' => true,
    ];

    // Columna urgencia (solo para requisiciones)
    $columns[] = [
        'attribute' => 'urgency',
        'label' => 'Urgencia',
        'format' => 'raw',
        'value' => function ($model) {
            if ($model->type !== 'requisition' || empty($model->urgency)) {
                return '-';
            }
            
            $urgencyLevels = \common\models\Movement::getUrgencyLevels();
            $urgencyText = $urgencyLevels[$model->urgency] ?? $model->urgency;
            
            // Badges con colores según urgencia
            $badgeClass = 'bg-secondary';
            $icon = '';
            if ($model->urgency === \common\models\Movement::URGENCY_VERY_URGENT) {
                $badgeClass = 'bg-danger';
                $icon = '<i class="bx bx-up-arrow-alt"></i> ';
            } elseif ($model->urgency === \common\models\Movement::URGENCY_LOW) {
                $badgeClass = 'bg-info';
                $icon = '<i class="bx bx-down-arrow-alt"></i> ';
            } else {
                $badgeClass = 'bg-secondary';
                $icon = '<i class="bx bx-minus"></i> ';
            }
            
            return '<span class="badge ' . $badgeClass . '">' . $icon . Html::encode($urgencyText) . '</span>';
        },
        'filter' => Html::activeDropDownList(
            $searchModel,
            'urgency',
            \common\models\Movement::getUrgencyLevels(),
            [
                'class' => 'form-control form-control-sm',
                'prompt' => 'Todas las urgencias'
            ]
        ),
        'contentOptions' => ['style' => 'text-align: center; white-space: nowrap;'],
    ];

    // Columna de insumos
    $columns[] = [
        'attribute' => 'ingredient_id',
        'label' => 'Insumo',
        'format' => 'raw',
        'headerOptions' => ['style' => 'min-width: 250px; width: 25%;'],
        'value' => function ($model) {
            // Para requisiciones con un item específico (expanded row)
            if ($model->type === 'requisition' && isset($model->_expandedItem)) {
                $item = $model->_expandedItem;
                $ingredient = $item->ingredient;
                if ($ingredient) {
                    $text = Html::encode($ingredient->ingredient);
                    if (!empty($ingredient->brand)) {
                        $text .= ' <span class="text-muted">(' . Html::encode($ingredient->brand) . ')</span>';
                    }
                    return $text;
                }
                return '-';
            }
            
            // Para otros tipos de movimiento
            $ingredient = $model->ingredient;
            if (!$ingredient) {
                return '-';
            }
            
            $parts = [];
            $parts[] = $ingredient->ingredient;
            
            if (!empty($ingredient->brand)) {
                $parts[] = $ingredient->brand;
            }
            
            if (!empty($ingredient->presentation)) {
                $parts[] = $ingredient->presentation;
            }
            
            return implode('  ', $parts);
        },
        'filter' => \yii\helpers\Html::activeTextInput($searchModel, 'name', [
            'class' => 'form-control form-control-sm',
            'placeholder' => 'Buscar por nombre...',
                        'style' => 'padding-right: 30px; background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgdmlld0JveD0iMCAwIDE2IDE2Ij4KPHBhdGggZD0iTSAxMC41IDEgQyA4LjAyNzI3MjcgMSA2IDMuMDI3MjcyIDYgNS41IEMgNiA2LjU1NDE0NTkgNi40MjI3OTM2IDcuNDg2MTgxIDcuMDM3MTA5NCA4LjI1NTg1OTQgTCAyLjA0Njg3NSAxMy4yNDYwOTQgTCAyLjc1MzkwNjIgMTMuOTUzMTI1IEwgNy43NDQxNDA2IDguOTYyODkwNiBDIDguNTEzODE4NSA5LjU3NzIwNjQgOS40NDU4NTQxIDEwIDEwLjUgMTAgQyAxMi45NzI3MjcgMTAgMTUgNy45NzI3MjcgMTUgNS41IEMgMTUgMy4wMjcyNzMgMTIuOTcyNzMgMSAxMC41IDEgeiBNIDEwLjUgMiBDIDEyLjQyNzI3MyAyIDE0IDMuNTcyNzI3MyAxNCA1LjUgQyAxNCA3LjQyNzI3MyAxMi40MjcyNzMgOSAxMC41IDkgQyA4LjU3MjcyNyA5IDcgNy40MjcyNzMgNyA1LjUgQyA3IDMuNTcyNzI3MyA4LjU3MjcyNyAyIDEwLjUgMiB6Ij48L3BhdGg+Cjwvc3ZnPgo=); background-repeat: no-repeat; background-position: right 10px center;'
            
        ]),
    ];
    // Columna de cantidad
    // Cantidad
    $columns[] = [
        'attribute' => 'quantity',
        'label' => 'Cantidad',
        'format' => 'raw',
        'filter'=> false,
        'value' => function ($model) {
            // Para requisiciones con un item específico (expanded row)
            if ($model->type === 'requisition' && isset($model->_expandedItem)) {
                $item = $model->_expandedItem;
                $val = (float)$item->quantity_requested;
                $quantity = ($val == floor($val)) ? formatNumber($val, 0) : formatNumber($val, 3);
                return '<strong>' . $quantity . '</strong>';
            }

            // Para otros tipos
            // Si quantity es numérico, formatearlo; si no, devolver tal cual
            if ($model->quantity !== null && is_numeric($model->quantity)) {
                $val = (float)$model->quantity;
                $formatted = ($val == floor($val)) ? formatNumber($val, 0) : formatNumber($val, 2);
                return '<strong>' . $formatted . '</strong>';
            }
            return Html::encode($model->quantity ?? '-');
        },
        'contentOptions' => ['style' => 'text-align: right;'],
    ];
         // Columna de unidad de medida
        $columns[] = [
            'attribute' => 'um',
            'filter' => \yii\bootstrap5\Html::activeDropDownList(
                $searchModel,
                'um',
                \yii\helpers\ArrayHelper::map(\common\models\Movement::find()->all(), 'um', 'um'),
                [
                    'class' => 'form-control form-control-sm',
                    'prompt' => '----'
                ]
            )
        ];    // Columna de familia
    $columns[] = [
        'attribute' => 'category_id',
        'label' => 'Familia',
        'format' => 'raw',
        'value' => function ($model) {
            // Para requisiciones con un item específico (expanded row)
            if ($model->type === 'requisition' && isset($model->_expandedItem)) {
                $item = $model->_expandedItem;
                $ingredient = $item->ingredient;
                if ($ingredient && $ingredient->category) {
                    return Html::encode($ingredient->category->name ?? '-');
                }
                return '-';
            }
            
            // Para otros tipos
            if ($model->ingredient && $model->ingredient->category) {
                return Html::encode($model->ingredient->category->name);
            }
            return '-';
        },
        'filter' => \yii\helpers\Html::activeDropDownList(
            $searchModel,
            'category_id',
            (function() use ($business) {
            $catsBusiness = \common\models\Category::find()
                ->where(['business_id' => $business->id])
                ->orderBy('name')
                ->all();
            $catsBuiltin = \common\models\Category::find()
                ->where(['builtin' => true])
                ->orderBy('name')
                ->all();
            $all = array_merge($catsBusiness, $catsBuiltin);
            // Index by id to remove duplicates and preserve model instances
            $unique = \yii\helpers\ArrayHelper::index($all, 'id');
            // Re-index numeric keys
            $unique = array_values($unique);
            return \yii\helpers\ArrayHelper::map($unique, 'id', 'name');
            })(),
            ['class' => 'form-control form-control-sm', 'prompt' => 'Todas las familias']
        ),
    ];

    

    // Unidad de medida
    $columns[] = [
        'attribute' => 'um',
        'label' => 'Unidad de medida',
        'format' => 'raw',
        'value' => function ($model) {
            // Para requisiciones con un item específico (expanded row)
            if ($model->type === 'requisition' && isset($model->_expandedItem)) {
                $item = $model->_expandedItem;
                $ingredient = $item->ingredient;
                return Html::encode($ingredient->um ?? '-');
            }

            // Para otros tipos
            return Html::encode($model->um ?? '-');
        },
        // Mostrar esta columna para requesters (para usuarios que NO verán la columna 'um' más abajo)
        'visible' => $isConsumptionRequester,
        'contentOptions' => ['style' => 'text-align: center; white-space: nowrap;'],
    ];

    // Columnas adicionales solo para usuarios que no son consumption_requester
    if (!$isConsumptionRequester) {
        $columns[] = [
            'attribute' => 'invoice',
            'filter' => false,
        ];
        $columns[] = [
            'attribute' => 'provider',
            'filter' => false,
            'value' => function ($data) {
                if ($data->type === \common\models\Movement::TYPE_INPUT) {
                    $provider = \common\models\Provider::find()
                        ->where(['name' => $data->provider, 'business_id' => $data->business_id])
                        ->one();
                    
                    if ($provider) {
                        return $provider->business_name ?? $provider->getBusiness()->one()->name ?? $provider->name;
                    }
                    
                    return $data->provider;
                }
                
                return '-';
            },
        ];
        
        // Columna de tipo de pago
        $columns[] = [
            'attribute' => 'payment_type',
            'value' => function ($data) {
                return $data->formattedPaymentType;
            },
            'filter' => \yii\bootstrap5\Html::activeDropDownList(
                $searchModel,
                'payment_type',
                \common\models\Movement::getFormattedPaymentTypes(),
                [
                    'class' => 'form-control form-control-sm',
                    'prompt' => '----'
                ]
            )
        ];
        
       

        $columns[] = [
            'attribute' => 'total',
            'label' => Yii::t('app', 'Total'),
            'filter' => false,
            'value' => function($model) {
                if ($model->type === \common\models\Movement::TYPE_OUTPUT) {
                    return formatPrice(-$model->total);
                }
                return formatPrice($model->total);
            },
            'contentOptions' => ['style' => 'text-align: right;'],
        ];
    }

    // Centro de consumo (para requisiciones y salidas)
    $columns[] = [
        'attribute' => 'consumption_center_id',
        'label' => 'Centro de Consumo',
        'value' => function ($data) use ($isConsumptionRequester) {
            // Para requisiciones, siempre mostrar
            if ($data->type === \common\models\Movement::TYPE_REQUISITION && $data->consumptionCenter) {
                return $data->consumptionCenter->name;
            }
            // Para salidas, solo si no es consumption_requester
            if (!$isConsumptionRequester && $data->type === \common\models\Movement::TYPE_OUTPUT && $data->consumptionCenter) {
                return $data->consumptionCenter->name;
            }
            return '-';
        },
        'filter' => \yii\helpers\Html::activeDropDownList($searchModel, 'consumption_center_id', 
            \yii\helpers\ArrayHelper::map(
                \common\models\ConsumptionCenter::find()->where(['business_id' => $business->id])->all(), 
                'id', 
                'name'
            ), 
            ['class' => 'form-control form-control-sm', 'prompt' => 'Todos']
        ),
    ];
    
    // Estado de tiempo (para requisiciones - visible para todos)
    $columns[] = [
        'attribute' => 'requisition_time_status',
        'label' => 'Estado de Tiempo',
        'value' => function($model) {
            if ($model->type === \common\models\Movement::TYPE_REQUISITION) {
                return $model->getTimeStatusLabel();
            }
            return '-';
        },
        'format' => 'raw',
        'contentOptions' => ['style' => 'text-align: center;'],
        'filter' => \yii\helpers\Html::activeDropDownList($searchModel, 'requisition_time_status', [
            \common\models\Movement::TIME_STATUS_ON_TIME => 'En tiempo',
            \common\models\Movement::TIME_STATUS_OUT_OF_TIME => 'Fuera de tiempo',
            \common\models\Movement::TIME_STATUS_EXTEMPORANEOUS => 'Extemporánea',
        ], ['class' => 'form-control form-control-sm', 'prompt' => 'Todos']),
        'visible' => !$isConsumptionRequester, // Solo visible para administradores
    ];

    // Fecha requerida (para todas las requisiciones)
    $columns[] = [
        'attribute' => 'required_date',
        'label' => 'Fecha Requerida',
        'value' => function($model) {
            if ($model->type === \common\models\Movement::TYPE_REQUISITION && $model->required_date) {
                $dt = new \DateTime($model->required_date);
                return $dt->format('d/m/Y');
            }
            return '-';
        },
        'contentOptions' => ['style' => 'text-align: center; white-space: nowrap;'],
        'visible' => true,
    ];

    // Estado (solo para requisiciones/consumption_requester)
    if ($isConsumptionRequester) {
        $columns[] = [
            'attribute' => 'status',
            'label' => 'Estado',
            'value' => function($model) {
                if ($model->status === 'fulfilled') {
                    return '<span class="badge bg-success">Surtida</span>';
                } elseif ($model->status === 'partially_fulfilled') {
                    return '<span class="badge bg-warning">Parcialmente Surtida</span>';
                } else {
                    return '<span class="badge bg-secondary">Pendiente</span>';
                }
            },
            'format' => 'raw',
            'contentOptions' => ['style' => 'text-align: center;'],
        ];
        
        // Estado de tiempo (solo para requisiciones)
        $columns[] = [
            'attribute' => 'requisition_time_status',
            'label' => 'Estado de Tiempo',
            'value' => function($model) {
                if ($model->type === \common\models\Movement::TYPE_REQUISITION) {
                    return $model->getTimeStatusLabel();
                }
                return '-';
            },
            'format' => 'raw',
            'contentOptions' => ['style' => 'text-align: center;'],
            'filter' => \yii\helpers\Html::activeDropDownList($searchModel, 'requisition_time_status', [
                \common\models\Movement::TIME_STATUS_ON_TIME => 'En tiempo',
                \common\models\Movement::TIME_STATUS_OUT_OF_TIME => 'Fuera de tiempo',
                \common\models\Movement::TIME_STATUS_EXTEMPORANEOUS => 'Extemporánea',
            ], ['class' => 'form-control form-control-sm', 'prompt' => 'Todos']),
        ];
    }

    // Fecha de creación (para todos)
    $columns[] = [
        'attribute' => 'created_at',
        'label' => Yii::t('app', 'Fecha de creación'),
        'value' => function($model) {
            $dt = new \DateTime($model->created_at);
            return $dt->format('d/m/Y H:i');
        },
        'contentOptions' => ['style' => 'text-align: center; white-space: nowrap;'],
    ];

    // Columna de acciones
    $deleteTemplate = $canDelete ? ' {delete}' : '';
    $columns[] = [
        'class' => 'yii\grid\ActionColumn',
        'template' => $isConsumptionRequester ? "{view} {update}" . $deleteTemplate : "{view} {update} {convert}" . $deleteTemplate,
        'buttons' => [
            'view' => function ($url, $model, $key) {
                return \yii\bootstrap5\Html::a(
                    '<i class="bx bx-show"></i>',
                    $url,
                    [
                        'class' => 'movement-details text-warning',
                        'title' => 'Ver detalles'
                    ]
                );
            },
            'update' => function ($url, $model, $key) use ($isConsumptionRequester) {
                // Para requisiciones: solo permitir editar si está en estado "Pendiente"
                if ($model->type === \common\models\Movement::TYPE_REQUISITION) {
                    if ($model->status === 'pending') {
                        return \yii\bootstrap5\Html::a(
                            '<i class="bx bx-edit-alt"></i>',
                            ['update-requisition', 'id' => $model->id],
                            [
                                'class' => 'text-warning ms-2',
                                'title' => 'Editar requisición'
                            ]
                        );
                    } else {
                        // Mostrar botón deshabilitado con tooltip explicativo
                        $statusLabels = [
                            'partially_fulfilled' => 'Parcialmente Cumplida',
                            'fulfilled' => 'Cumplida',
                            'cancelled' => 'Cancelada'
                        ];
                        $statusLabel = $statusLabels[$model->status] ?? ucfirst($model->status);
                        
                        return \yii\bootstrap5\Html::tag(
                            'span',
                            '<i class="bx bx-edit-alt"></i>',
                            [
                                'class' => 'text-muted ms-2',
                                'style' => 'opacity: 0.5; cursor: not-allowed;',
                                'title' => 'No se puede editar. Estado: ' . $statusLabel,
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top'
                            ]
                        );
                    }
                }
                
                // Para otros tipos de movimientos: solo administradores (no consumption_requester)
                if (!$isConsumptionRequester && (Yii::$app->user->can('manage_users') || Yii::$app->user->can('admin') || Yii::$app->user->can('administrator'))) {
                    return \yii\bootstrap5\Html::a(
                        '<i class="bx bx-edit-alt"></i>',
                        ['update', 'id' => $model->id],
                        [
                            'class' => 'text-warning ms-2',
                            'title' => 'Editar movimiento'
                        ]
                    );
                }
                return '';
            },
            'convert' => function ($url, $model, $key) {
                // Mostrar botón de convertir para órdenes (a entradas)
                if ($model->type === \common\models\Movement::TYPE_ORDER) {
                    return \yii\bootstrap5\Html::a(
                        '<i class="bx bx-transfer"></i>',
                        ['convert-to-entry', 'id' => $model->id],
                        [
                            'class' => 'text-success ms-2 convert-order',
                            'title' => 'Convertir a entrada',
                            'data-confirm' => '¿Confirmas que quieres convertir esta orden en una entrada?'
                        ]
                    );
                }
                // Mostrar botón de convertir para requisiciones (a salidas)
                if ($model->type === \common\models\Movement::TYPE_REQUISITION) {
                    // Solo mostrar botón si NO está completamente surtida
                    if ($model->status !== 'fulfilled') {
                        return \yii\bootstrap5\Html::a(
                            '<i class="bx bx-transfer-alt"></i>',
                            ['view', 'id' => $model->id],
                            [
                                'class' => 'text-danger ms-2 convert-requisition',
                                'title' => 'Ver y convertir a salida',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top'
                            ]
                        );
                    } else {
                        // Requisición completamente surtida
                        return \yii\bootstrap5\Html::tag(
                            'span',
                            '<i class="bx bx-check-circle"></i>',
                            [
                                'class' => 'text-success ms-2',
                                'title' => 'Requisición completamente surtida',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top',
                                'style' => 'cursor: default;'
                            ]
                        );
                    }
                }
                return '';
            },
            'delete' => function ($url, $model, $key) use ($canDelete) {
                if (!$canDelete) {
                    return '';
                }
                // Usar modal de advertencia - no link directo
                $typeLabel = $model->getFormattedType();
                $ingredientName = '';
                if (isset($model->_expandedItem) && $model->_expandedItem->ingredient) {
                    $ingredientName = $model->_expandedItem->ingredient->ingredient;
                } elseif ($model->ingredient) {
                    $ingredientName = $model->ingredient->ingredient;
                }
                $qty = '';
                if (isset($model->_expandedItem)) {
                    $qty = $model->_expandedItem->quantity_requested;
                } else {
                    $qty = $model->quantity;
                }
                return \yii\bootstrap5\Html::a(
                    '<i class="bx bx-trash"></i>',
                    '#',
                    [
                        'class' => 'text-danger ms-2 btn-delete-single-movement',
                        'title' => 'Eliminar movimiento',
                        'data-bs-toggle' => 'tooltip',
                        'data-bs-placement' => 'top',
                        'data-id' => $model->id,
                        'data-type' => $model->type,
                        'data-typelabel' => $typeLabel,
                        'data-ingredient' => $ingredientName,
                        'data-quantity' => $qty,
                        'data-um' => $model->um ?? ($model->ingredient->portion_um ?? ''),
                    ]
                );
            }
        ]
    ];

    // Renderizar GridView con las columnas definidas
    ?>
    <div class="sticky-header-container">
    <?= GridView::widget([
        'id' => 'movements-grid',
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'formatter' => $business->getFormatter(),
        'tableOptions' => ['class' => 'table table-striped sticky-header-table'],
        'options' => ['class' => 'grid-view'],
        'layout' => "{items}\n<div class='d-flex justify-content-between align-items-center mt-3'><div>{pager}</div><div>{summary}</div></div>",
        'columns' => $columns,
        'rowOptions' => function ($model, $key, $index, $grid) {
            // Solo aplicar colores a requisiciones
            if ($model->type !== \common\models\Movement::TYPE_REQUISITION) {
                return [];
            }
            
            // Si la requisición ya fue surtida (tiene salida), mostrar en negro (sin clase especial)
            if ($model->status === 'fulfilled' || $model->status === 'partially_fulfilled') {
                return [];
            }
            
            // Si es una fila expandida con un item específico
            if (isset($model->_expandedItem)) {
                $item = $model->_expandedItem;
                $ingredient = $item->ingredient;
                
                if ($ingredient) {
                    $availableStock = $ingredient->quantity ?? 0;
                    $requestedQuantity = $item->quantity_requested;
                    
                    // Rojo si no hay suficiente stock
                    if ($availableStock < $requestedQuantity) {
                        return ['class' => 'requisition-insufficient-stock'];
                    }
                    
                    // Azul si hay suficiente stock
                    return ['class' => 'requisition-available-stock'];
                }
            }
            
            return [];
        },
    ]); ?>
    </div>

    <?php Pjax::end(); ?>

</div>

<?php
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-details-movement',
    'size' => \yii\bootstrap5\Modal::SIZE_EXTRA_LARGE, // Modal más grande para ver mejor los detalles
]);
?>
<div id="container-modal-details-movement"></div>
<?php \yii\bootstrap5\Modal::end(); ?>


<?php
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-upload-file',
    'title' => Yii::t('app', "Importar movimientos de entrada")
]);
$url = \yii\helpers\Url::to(['movement/import-movements', 'id' => $business->id]);
\yii\bootstrap5\ActiveForm::begin([
    'action' => $url,
    'method' => 'post',
    'options' => [
        'enctype' => 'multipart/form-data'
    ]
]);

echo \yii\bootstrap5\Html::input('file', 'movement-file', '', [
    'class' => 'form-control'
]);
echo "<br>";
echo \yii\bootstrap5\Html::submitButton(Yii::t('app', "Import"), [
    'class' => 'btn btn-success'
]);

\yii\bootstrap5\ActiveForm::end();

\yii\bootstrap5\Modal::end();

// MODAL BALANCE
\yii\bootstrap5\Modal::begin([
    'title' => Yii::t('app', "Current balance"),
    'id' => 'modal-balance',
    'options' => [
        'data' => [
            'url' => \yii\helpers\Url::to(['movement/balance'])
        ]
    ]
]);

echo "<div id='balance-container'></div>";

\yii\bootstrap5\Modal::end();

// MODALES PARA ELIMINACIÓN MÚLTIPLE
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-bulk-remove-movements',
    'title' => Yii::t('app', "Eliminar movimientos seleccionados"),
]);
?>
<div class="alert alert-danger d-flex align-items-start gap-2 mb-3">
    <i class="bx bx-error-circle fs-4"></i>
    <div>
        <strong>¡Advertencia! Esta acción es irreversible.</strong><br>
        <span id="bulk-delete-warning-text"></span>
        <ul class="mb-0 mt-1 small">
            <li>Si es <strong>Entrada</strong>: se <strong>restará</strong> la cantidad al stock del insumo y se eliminará su precio asociado sin afectar otros precios.</li>
            <li>Si es <strong>Salida</strong>: se <strong>sumará</strong> la cantidad al stock del insumo.</li>
        </ul>
    </div>
</div>
<p>¿Deseas eliminar todos los movimientos seleccionados o solo los de la página actual?</p>
<div class="d-flex justify-content-end gap-3">
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Cancelar'), [
        'class' => 'btn btn-secondary',
        'data-bs-dismiss' => 'modal'
    ]) ?>
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Eliminar las seleccionadas'), [
        'class' => 'btn btn-danger',
        'id' => 'delete-current-page-movements'
    ]) ?>
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Eliminar todas'), [
        'class' => 'btn btn-danger',
        'id' => 'delete-all-movements'
    ]) ?>
</div>
<?php
\yii\bootstrap5\Modal::end();
?>

<?php
// Modal para mostrar error cuando no hay elementos seleccionados
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-no-selection-movements',
    'title' => Yii::t('app', "Selección vacía"),
]);
?>
<p>No has seleccionado ningún movimiento para eliminar. Por favor, selecciona al menos un movimiento.</p>
<div class="d-flex justify-content-end">
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Entendido'), [
        'class' => 'btn btn-primary',
        'data-bs-dismiss' => 'modal'
    ]) ?>
</div>
<?php
\yii\bootstrap5\Modal::end();
?>

<?php
// Modal para confirmar la eliminación de elementos específicos
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-confirm-selected-remove-movements',
    'title' => Yii::t('app', "Confirmar eliminación"),
]);
?>
<div class="alert alert-danger d-flex align-items-start gap-2 mb-3">
    <i class="bx bx-error-circle fs-4"></i>
    <div>
        <strong>¡Advertencia! Esta acción es irreversible.</strong><br>
        Se revertirá el stock del insumo. Entradas restan stock y eliminan su precio sin afectar otros precios. Salidas suman stock.
    </div>
</div>
<p>¿Estás seguro de que deseas eliminar <span id="selected-count-message-movements"></span> movimientos?</p>
<div class="d-flex justify-content-end gap-3">
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Cancelar'), [
        'class' => 'btn btn-secondary',
        'data-bs-dismiss' => 'modal'
    ]) ?>
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Eliminar'), [
        'class' => 'btn btn-danger',
        'id' => 'confirm-delete-selected-movements'
    ]) ?>
</div>
<?php
\yii\bootstrap5\Modal::end();
?>

<?php
// Modal para exportar movimientos seleccionados
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-export-movements',
    'title' => Yii::t('app', "Exportar movimientos seleccionados"),
]);
?>
<p>¿Deseas exportar todos los movimientos seleccionados o solo los de la página actual?</p>
<div class="d-flex justify-content-end gap-3">
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Cancelar'), [
        'class' => 'btn btn-secondary',
        'data-bs-dismiss' => 'modal'
    ]) ?>
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Exportar los seleccionados'), [
        'class' => 'btn btn-success',
        'id' => 'export-current-page-movements'
    ]) ?>
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Exportar todos'), [
        'class' => 'btn btn-success',
        'id' => 'export-all-movements'
    ]) ?>
</div>
<?php
\yii\bootstrap5\Modal::end();
?>

<?php
// Modal para mostrar error cuando no hay elementos seleccionados para exportar
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-no-export-selection-movements',
    'title' => Yii::t('app', "Selección vacía"),
]);
?>
<p>No has seleccionado ningún movimiento para exportar. Por favor, selecciona al menos un movimiento.</p>
<div class="d-flex justify-content-end">
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Entendido'), [
        'class' => 'btn btn-primary',
        'data-bs-dismiss' => 'modal'
    ]) ?>
</div>
<?php
\yii\bootstrap5\Modal::end();
?>

<?php if ($canDelete): ?>
<?php
\yii\bootstrap5\Modal::begin([
    'id' => 'modal-delete-single-movement',
    'title' => Yii::t('app', "Confirmar eliminación"),
]);
?>
<div class="alert alert-danger d-flex align-items-start gap-2 mb-3">
    <i class="bx bx-error-circle fs-4"></i>
    <div>
        <strong>¡Advertencia! Esta acción es irreversible.</strong><br>
        <span id="single-delete-warning-detail"></span>
    </div>
</div>
<div class="bg-light p-3 rounded mb-3 small">
    <div><strong>Tipo:</strong> <span id="single-delete-type"></span></div>
    <div><strong>Insumo:</strong> <span id="single-delete-ingredient"></span></div>
    <div><strong>Cantidad:</strong> <span id="single-delete-quantity"></span> <span id="single-delete-um"></span></div>
</div>
<div class="d-flex justify-content-end gap-3">
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Cancelar'), [
        'class' => 'btn btn-secondary',
        'data-bs-dismiss' => 'modal'
    ]) ?>
    <?= \yii\bootstrap5\Html::button(Yii::t('app', 'Sí, eliminar'), [
        'class' => 'btn btn-danger',
        'id' => 'confirm-delete-single-movement'
    ]) ?>
</div>
<?php
\yii\bootstrap5\Modal::end();
?>
<?php endif; ?>

<?php if ($canDelete): ?>
<?php
$this->registerJs(<<<'JS'
let pendingDeleteId = null;
$(document).on('click', '.btn-delete-single-movement', function(e){
    e.preventDefault();
    const $btn = $(this);
    pendingDeleteId = $btn.data('id');
    const type = $btn.data('type');
    const typeLabel = $btn.data('typelabel');
    const ingredient = $btn.data('ingredient') || '-';
    const qty = $btn.data('quantity') || '-';
    const um = $btn.data('um') || '';
    $('#single-delete-id').text(pendingDeleteId);
    $('#single-delete-type').text(typeLabel);
    $('#single-delete-ingredient').text(ingredient);
    $('#single-delete-quantity').text(qty);
    $('#single-delete-um').text(um);
    let detail = '';
    if(type === 'input'){
        detail = 'Se eliminará una ENTRADA. Se restará '+qty+' '+um+' al stock del insumo \"'+ingredient+'\" y se eliminará solo el precio asociado a esta entrada, sin afectar otros precios.';
    } else if(type === 'output'){
        detail = 'Se eliminará una SALIDA. Se sumará '+qty+' '+um+' al stock del insumo \"'+ingredient+'\".';
    } else {
        detail = 'Se eliminará el movimiento. Requisiciones y órdenes no afectan stock (según configuración actual).';
    }
    $('#single-delete-warning-detail').text(detail);
    const modalEl = document.getElementById('modal-delete-single-movement');
    if(modalEl){ new bootstrap.Modal(modalEl).show(); }
});
$(document).on('click', '#confirm-delete-single-movement', function(){
    if(!pendingDeleteId) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Eliminando...');
    $.ajax({
        url: '/movement/delete',
        type: 'POST',
        data: { keys: [pendingDeleteId], _csrf: yii.getCsrfToken() },
        success: function(data){
            const modalEl = document.getElementById('modal-delete-single-movement');
            if(modalEl){ bootstrap.Modal.getInstance(modalEl)?.hide(); }
            if(data.success){
                $.pjax.reload({container:'#movements-pjax', timeout:5000});
            } else {
                alert(data.message || 'Error al eliminar');
            }
        },
        error: function(xhr){
            alert('Error al eliminar: ' + (xhr.responseJSON?.message || xhr.responseText || 'Error desconocido'));
        },
        complete: function(){
            $btn.prop('disabled', false).html('Sí, eliminar');
            pendingDeleteId = null;
        }
    });
});
// Mejorar texto de advertencia bulk según selección
$(document).on('click', '#btn-delete-movements', function(){
    const keys = $('#movements-grid').yiiGridView('getSelectedRows');
    if(keys.length>0){
        $('#bulk-delete-warning-text').text('Has seleccionado '+keys.length+' movimiento(s). Esta acción revertirá el stock de cada insumo afectado.');
    }
});
JS
, \yii\web\View::POS_END);
?>
<?php endif; ?>

<?php
$this->registerJs("
document.getElementById('per-page-selector-movements').addEventListener('change', function() {
    const pageSize = this.value;
    
    // Guardar en localStorage
    localStorage.setItem('movements-per-page', pageSize);
    
    // Crear URL con nuevo tamaño de página
    let url = new URL(window.location);
    url.searchParams.set('per-page', pageSize);
    
    // Recargar con el nuevo tamaño de página
    $.pjax.reload({
        container: '#movements-pjax',
        url: url.toString(),
        timeout: 10000
    });
});

// Cargar selección guardada al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const savedPerPage = localStorage.getItem('movements-per-page');
    if (savedPerPage) {
        const selector = document.getElementById('per-page-selector-movements');
        if (selector) {
            selector.value = savedPerPage;
            
            // Si el valor actual es diferente al guardado, aplicar el guardado
            const currentPageSize = '" . $dataProvider->pagination->pageSize . "';
            if (currentPageSize != savedPerPage) {
                // Crear URL con el valor guardado y recargar
                let url = new URL(window.location);
                url.searchParams.set('per-page', savedPerPage);
                
                $.pjax.reload({
                    container: '#movements-pjax',
                    url: url.toString(),
                    timeout: 10000
                });
            }
        }
    }
});
", \yii\web\View::POS_END);
?>
