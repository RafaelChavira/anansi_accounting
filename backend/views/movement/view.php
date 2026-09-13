<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Movement */

$this->title = $model->type === $model::TYPE_REQUISITION 
    ? 'Requisición #' . $model->requisition_number 
    : 'Movimiento #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Movements'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);

// Obtener formatter del business para respecter separadores decimales/miles (igual que en creación de requisición e index)
$businessData = \backend\helpers\RedisKeys::getValue(\backend\helpers\RedisKeys::BUSINESS_KEY);
$business = $businessData ? \common\models\Business::findOne(['id' => $businessData['id']]) : null;

// CSS para mejorar la presentación
$this->registerCss("
    .movement-view .card {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }
    
    .movement-view .card-header {
        border-radius: 8px 8px 0 0;
        font-weight: 600;
    }
    
    .movement-view .table-responsive {
        border-radius: 4px;
        overflow-x: auto;
        overflow-y: visible;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
        position: relative;
    }
    
    .movement-view .table-responsive:hover::after {
        opacity: 0.8;
    }
    
    /* Mejorar el scrollbar horizontal */
    .movement-view .table-responsive::-webkit-scrollbar {
        height: 10px;
    }
    
    .movement-view .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .movement-view .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    
    .movement-view .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* Para Firefox */
    .movement-view .table-responsive {
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
    }
    
    .movement-view .table {
        min-width: 1200px; /* Asegura que la tabla tenga un ancho mínimo para activar scroll */
        margin-bottom: 0;
    }
    
    .movement-view .table thead th {
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px;
        white-space: nowrap; /* Evita que los encabezados se rompan en múltiples líneas */
    }
    
    .movement-view .table tbody td {
        padding: 10px 12px;
        vertical-align: middle;
    }
    
    .movement-view .badge {
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    
    .movement-view .btn-lg {
        padding: 12px 24px;
        font-size: 1rem;
    }
    
    /* Indicador de ayuda para scroll */
    
");
?>
<div class="movement-view">

    <?php if ($model->type == $model::TYPE_ORDER): ?>
        <div class="mb-4">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bx bx-clipboard"></i> Orden Registrada</h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        Esta orden está lista para ser convertida en una entrada cuando recibas los productos del proveedor. 
                        Al convertirla, se creará automáticamente un nuevo movimiento de entrada con todos los datos de esta orden.
                    </p>
                    <div class="d-flex gap-2">
                        <?= Html::a(
                            '<i class="bx bx-transfer"></i> Registrar Entrada', 
                            ['convert-to-entry', 'id' => $model->id], 
                            [
                                'class' => 'btn btn-success btn-lg',
                                'data-confirm' => '¿Confirmas que quieres convertir esta orden en una entrada?\n\nSe creará un nuevo movimiento de entrada con:\n• Mismo proveedor\n• Mismo producto\n• Misma cantidad\n• Mismos datos financieros\n• Fecha actual',
                                'title' => 'Convertir orden a entrada'
                            ]
                        ) ?>
                        <?= Html::a(
                            '<i class="bx bx-edit"></i> Editar Orden', 
                            ['update', 'id' => $model->id], 
                            [
                                'class' => 'btn btn-outline-primary',
                                'title' => 'Editar esta orden antes de convertirla'
                            ]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($model->type == $model::TYPE_REQUISITION): ?>
        <div class="mb-4">
            <div class="card border-primary">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="bx bx-receipt"></i> Requisición #<?= Html::encode($model->requisition_number) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Centro de Consumo:</strong> 
                            <?= $model->consumptionCenter ? Html::encode($model->consumptionCenter->name) : '-' ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Fecha Requerida:</strong> 
                            <?= $model->required_date ? Yii::$app->formatter->asDate($model->required_date, 'php:d/m/Y') : '-' ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Estado:</strong> 
                            <span class="badge bg-<?= $model->status === 'fulfilled' ? 'success' : ($model->status === 'partially_fulfilled' ? 'warning' : 'secondary') ?>">
                                <?= $model->status === 'fulfilled' ? 'Surtida' : ($model->status === 'partially_fulfilled' ? 'Parcialmente Surtida' : 'Pendiente') ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Creada:</strong> 
                            <?php 
                            $dt = new \DateTime($model->created_at);
                            echo $dt->format('d/m/Y H:i');
                            ?>
                        </div>
                    </div>

                    <?php if ($model->observations): ?>
                        <div class="mb-3">
                            <strong>Observaciones:</strong><br>
                            <?= Html::encode($model->observations) ?>
                        </div>
                    <?php endif; ?>

                    <h6 class="mt-4 mb-3">Insumos Solicitados</h6>
                    
                    <div id="view-business-format-config" data-decimal-sep="<?= Html::encode($business ? $business->decimal_separator : ',') ?>" data-thousand-sep="<?= Html::encode($business ? $business->thousands_separator : '.') ?>" style="display:none;"></div>
                    <?php if ($model->status !== 'fulfilled' && !Yii::$app->user->can('consumption_requester')): ?>
                        <?php 
                        $form = \yii\widgets\ActiveForm::begin([
                            'action' => ['convert-to-output', 'id' => $model->id],
                            'method' => 'post',
                            'id' => 'fulfill-form'
                        ]); 
                        ?>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Insumo</th>
                                    <th class="text-center">Cantidad Solicitada</th>
                                    <th class="text-center">Ya Surtido</th>
                                    <th class="text-center">Saldo Pendiente</th>
                                    <?php if ($model->status !== 'fulfilled' && !Yii::$app->user->can('consumption_requester')): ?>
                                        <th class="text-center">Cantidad a Surtir</th>
                                    <?php endif; ?>
                                    <th>Disponibilidad</th>
                                    <?php if (!Yii::$app->user->can('consumption_requester')): ?>
                                        <th class="text-end">Costo Estimado</th>
                                    <?php endif; ?>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $items = $model->requisitionItems;
                                $totalEstimated = 0;
                                $totalPending = 0;
                                $hasPending = false;
                                
                                foreach ($items as $item): 
                                    $totalEstimated += $item->cost_at_request;
                                    $fulfilledQty = $item->quantity_fulfilled ?? 0;
                                    $pendingQty = $item->quantity_requested - $fulfilledQty;
                                    
                                    if ($pendingQty > 0.01) {
                                        $hasPending = true;
                                    }
                                    
                                    $totalPending += $pendingQty;
                                    
                                    // Determinar clase de fila según estado
                                    $rowClass = '';
                                    if ($pendingQty <= 0.01) {
                                        $rowClass = 'table-success';
                                    } elseif ($fulfilledQty > 0) {
                                        $rowClass = 'table-warning';
                                    }
                                    
                                    $availableStock = $item->ingredient ? $item->ingredient->quantity : 0;
                                    $suggestedQty = min($pendingQty, $availableStock);
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td>
                                            <strong><?= $item->ingredient ? Html::encode($item->ingredient->ingredient) : '-' ?></strong>
                                            <?php if ($item->ingredient && $item->ingredient->brand): ?>
                                                <br><small class="text-muted"><?= Html::encode($item->ingredient->brand) ?></small>
                                            <?php endif; ?>
                                            <?php if ($item->ingredient && $item->ingredient->presentation): ?>
                                                <br><small class="text-muted"><?= Html::encode($item->ingredient->presentation) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= formatNumber($item->quantity_requested, 3) ?></strong>
                                            <?= $item->ingredient ? Html::encode($item->ingredient->portion_um ?? $item->ingredient->um) : '' ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($fulfilledQty > 0): ?>
                                                <span class="badge bg-success">
                                                    <?= formatNumber($fulfilledQty, 3) ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    (<?= formatNumber(($fulfilledQty / $item->quantity_requested) * 100, 1) ?>%)
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($pendingQty > 0.01): ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 0.95rem;">
                                                    <?= formatNumber($pendingQty, 3) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">✓ Completo</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($model->status !== 'fulfilled' && !Yii::$app->user->can('consumption_requester')): ?>
                                            <td class="text-center">
                                                <?php if ($pendingQty > 0.01): ?>
                                                    <?php 
                                                    // Permitir hasta 30% más de la cantidad solicitada
                                                    $maxAllowed = $pendingQty * 1.30;
                                                    ?>
                                                    <?= Html::input('text', "fulfill_quantities[{$item->id}]", 
                                                        formatNumber($suggestedQty, 3), 
                                                        [
                                                            'class' => 'form-control form-control-sm text-center fulfill-quantity-input',
                                                            'style' => 'width: 100px; display: inline-block; font-weight: bold;',
                                                            'data-pending' => $pendingQty,
                                                            'data-available' => $availableStock,
                                                            'data-max-allowed' => $maxAllowed,
                                                            'placeholder' => formatNumber(0, 3),
                                                            'pattern' => '[0-9]+([,\.][0-9]+)?'
                                                        ]
                                                    ) ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Stock: <?= formatNumber($availableStock, 2) ?> | 
                                                        Máx: <?= formatNumber($maxAllowed, 3) ?> (+30%)
                                                    </small>
                                                    <?php if ($availableStock < $pendingQty || in_array($item->availability_status, ['insufficient', 'unavailable'])): ?>
                                                        <br><small class="text-danger">
                                                            <i class="bx bx-error-circle"></i> Insuficiente
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php
                                            $statusMap = [
                                                'available' => ['text' => 'Disponible', 'class' => 'success', 'icon' => '🟢'],
                                                'warning' => ['text' => 'Advertencia', 'class' => 'warning', 'icon' => '🟡'],
                                                'low' => ['text' => 'Advertencia', 'class' => 'warning', 'icon' => '🟡'],
                                                'insufficient' => ['text' => 'Insuficiente', 'class' => 'danger', 'icon' => '🔴'],
                                                'unavailable' => ['text' => 'No Disponible', 'class' => 'danger', 'icon' => '🔴'],
                                            ];
                                            $status = $statusMap[$item->availability_status] ?? ['text' => 'Pendiente', 'class' => 'secondary', 'icon' => '⚪'];
                                            ?>
                                            <span class="badge bg-<?= $status['class'] ?>">
                                                <?= $status['icon'] ?> <?= $status['text'] ?>
                                            </span>
                                        </td>
                                        <?php if (!Yii::$app->user->can('consumption_requester')): ?>
                                            <td class="text-end">
                                                <?= formatPrice($item->cost_at_request ?? 0) ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <?php if ($pendingQty <= 0.01): ?>
                                                <span class="badge bg-success">
                                                    <i class="bx bx-check-circle"></i> Surtido
                                                </span>
                                            <?php elseif ($fulfilledQty > 0): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bx bx-time"></i> Parcial
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="bx bx-hourglass"></i> Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if (!Yii::$app->user->can('consumption_requester')): ?>
                                <tfoot class="table-light">
                                    <tr>
                                        <?php 
                                        $colspanBase = $model->status !== 'fulfilled' ? 5 : 4;
                                        ?>
                                        <td colspan="<?= $colspanBase ?>" class="text-end"><strong>Total Estimado:</strong></td>
                                        <td></td>
                                        <td class="text-end"><strong><?= formatPrice($totalEstimated) ?></strong></td>
                                        <td></td>
                                    </tr>
                                    <?php if ($hasPending && $model->status !== 'fulfilled'): ?>
                                        <tr class="table-info">
                                            <td colspan="<?= $colspanBase + 3 ?>" class="text-center">
                                                <i class="bx bx-info-circle"></i>
                                                <strong>Ajuste las cantidades a surtir según disponibilidad y presione "Convertir a Salida"</strong>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>

                    <?php if ($model->status !== 'fulfilled' && !Yii::$app->user->can('consumption_requester')): ?>
                        <div class="d-flex gap-3 mt-4 align-items-center">
                            <?= Html::submitButton(
                                '<i class="bx bx-transfer-alt"></i> Convertir a Salida',
                                [
                                    'class' => 'btn btn-danger btn-lg',
                                    'form' => 'fulfill-form'
                                ]
                            ) ?>
                            
                            <?php if ($model->status === 'partially_fulfilled'): ?>
                                <div class="alert alert-warning mb-0" style="flex: 1;">
                                    <i class="bx bx-info-circle"></i>
                                    <strong>Requisición parcialmente surtida.</strong>
                                    Puede continuar surtiendo los items pendientes.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php \yii\widgets\ActiveForm::end(); ?>
                    <?php elseif ($model->status === 'fulfilled'): ?>
                        <div class="alert alert-success mt-3">
                            <i class="bx bx-check-circle"></i>
                            <strong>Requisición completamente surtida.</strong>
                            Todos los items han sido entregados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($model->type !== $model::TYPE_REQUISITION): ?>
        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
    //            'id',
                [
                    'attribute' => 'type',
                    'value' => function ($model) {
                        return $model->formattedType;
                    },
                ],
                'provider',
                [
                    'attribute' => 'payment_type',
                    'value' => function ($model) {
                        return $model->formattedPaymentType;
                    },
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],
                'ingredient.ingredient',
                [
                    'attribute' => 'invoice',
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],
                'quantity',
                'um',
                [
                    'attribute' => 'amount',
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],
                [
                    'attribute' => 'tax',
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],
                [
                    'attribute' => 'retention',
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],
                [
                    'attribute' => 'unit_price',
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],
                [
                    'attribute' => 'total',
                    'visible' => $model->type == $model::TYPE_INPUT,
                ],

                'created_at',
                'observations',
            ],
        ]) ?>
    <?php endif; ?>

</div>

<?php
// Script para manejar el envío del formulario desde el modal
if ($model->type === \common\models\Movement::TYPE_REQUISITION && $model->status !== 'fulfilled' && !Yii::$app->user->can('consumption_requester')) {
    $this->registerJs(<<<'JS'
        var viewCfg = document.getElementById('view-business-format-config');
        var businessDecimalSep = viewCfg ? viewCfg.getAttribute('data-decimal-sep') : ',';
        var businessThousandSep = viewCfg ? viewCfg.getAttribute('data-thousand-sep') : '.';
        function normalizeBusinessNumber(str) {
            if (!str) return '';
            var val = str.trim();
            if (businessThousandSep) {
                val = val.split(businessThousandSep).join('');
            }
            if (businessDecimalSep !== '.') {
                val = val.split(businessDecimalSep).join('.');
            }
            if (isNaN(val) && str.indexOf('.') !== -1 && businessDecimalSep === ',') {
                var alt = str.split(',').join('').replace('.', '.');
                if (!isNaN(alt) && alt !== '') val = alt;
            }
            return val;
        }

        // Permitir entrada con separador decimal del business en los inputs de cantidad con validación en tiempo real
        $(document).on('input', '.fulfill-quantity-input', function() {
            var input = $(this);
            var inputValue = input.val();
            
            // Permitir vacío, números, coma y punto
            if (inputValue === '') {
                input.removeClass('is-invalid is-valid');
                return;
            }
            
            // Normalizar usando separadores del business
            var normalizedValue = normalizeBusinessNumber(inputValue);
            
            // Validar que sea un número válido
            if (!isNaN(normalizedValue) && normalizedValue !== '') {
                var value = parseFloat(normalizedValue);
                var maxAllowed = parseFloat(input.attr('data-max-allowed'));
                
                // Validar rango
                if (value > 0 && value <= maxAllowed) {
                    input.removeClass('is-invalid').addClass('is-valid');
                } else if (value > maxAllowed) {
                    input.removeClass('is-valid').addClass('is-invalid');
                } else {
                    input.removeClass('is-invalid is-valid');
                }
                
                // Guardar valor normalizado
                input.attr('data-normalized-value', normalizedValue);
            } else {
                input.removeClass('is-valid').addClass('is-invalid');
            }
        });
        
        $('#fulfill-form').on('beforeSubmit', function(e) {
            e.preventDefault();
            var form = $(this);
            
            // Normalizar todos los valores antes de validar y enviar
            form.find('input[name^="fulfill_quantities"]').each(function() {
                var inputValue = $(this).val();
                if (inputValue) {
                    var normalizedValue = normalizeBusinessNumber(inputValue);
                    $(this).val(normalizedValue);
                }
            });
            
            // Validar que haya al menos una cantidad mayor a 0
            var hasQuantities = false;
            var validationErrors = [];
            
            form.find('input[name^="fulfill_quantities"]').each(function() {
                var inputValue = $(this).val();
                if (!inputValue) return;
                
                // Convertir a número (ya está con punto decimal)
                var value = parseFloat(inputValue);
                var maxAllowed = parseFloat($(this).attr('data-max-allowed'));
                var pending = parseFloat($(this).attr('data-pending'));
                
                if (isNaN(value)) {
                    validationErrors.push('Una de las cantidades ingresadas no es un número válido.');
                    return;
                }
                
                if (value > 0) {
                    hasQuantities = true;
                    
                    // Validar que no exceda el máximo permitido (130%)
                    if (value > maxAllowed) {
                        validationErrors.push('Una cantidad excede el límite permitido (+30% de la cantidad pendiente).');
                    }
                }
            });
            
            if (!hasQuantities) {
                alert('Debe especificar al menos una cantidad a surtir.');
                return false;
            }
            
            if (validationErrors.length > 0) {
                alert(validationErrors.join('\\n'));
                return false;
            }
            
            // Confirmar antes de enviar
            if (!confirm('¿Confirma que desea convertir esta requisición a salida con las cantidades especificadas?')) {
                return false;
            }
            
            // Deshabilitar el botón de submit y mostrar indicador de carga
            var submitBtn = form.find('button[type="submit"]');
            var originalBtnText = submitBtn.html();
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...');
            
            // Agregar overlay de carga al modal
            var modalBody = form.closest('.card-body');
            if (modalBody.length === 0) {
                modalBody = form.closest('.modal-body');
            }
            var loadingOverlay = $('<div class="loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">' +
                '<div class="text-center">' +
                '<div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>' +
                '<div class="mt-3"><strong>Convirtiendo requisición a salida...</strong></div>' +
                '<div class="text-muted">Por favor espere</div>' +
                '</div>' +
                '</div>');
            modalBody.css('position', 'relative').append(loadingOverlay);
            
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success !== false) {
                        // Cambiar mensaje del overlay a éxito
                        loadingOverlay.html(
                            '<div class="text-center">' +
                            '<i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>' +
                            '<div class="mt-3"><strong class="text-success">¡Conversión exitosa!</strong></div>' +
                            '<div class="text-muted">Recargando página...</div>' +
                            '</div>'
                        );
                        
                        // Cerrar el modal si existe (Bootstrap 5)
                        setTimeout(function() {
                            var modalElement = document.getElementById('modal-details-movement');
                            if (modalElement) {
                                var modalInstance = bootstrap.Modal.getInstance(modalElement);
                                if (modalInstance) {
                                    modalInstance.hide();
                                }
                            }
                            
                            // Recargar la página para mostrar los mensajes flash
                            location.reload();
                        }, 1000);
                    } else {
                        // Remover overlay y restaurar botón
                        loadingOverlay.remove();
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalBtnText);
                        
                        alert(response.message || 'Error al procesar la requisición.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    
                    // Remover overlay y restaurar botón
                    loadingOverlay.remove();
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalBtnText);
                    
                    var errorMessage = 'Error al procesar la requisición.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            // Si no es JSON, usar mensaje por defecto
                        }
                    }
                    
                    alert(errorMessage);
                }
            });
            
            return false;
        });
JS
    );
}
?>
