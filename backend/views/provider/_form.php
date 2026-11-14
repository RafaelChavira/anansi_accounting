<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;

/* @var $this yii\web\View */
/* @var $model common\models\Provider */
/* @var $form yii\widgets\ActiveForm */

// Registrar JavaScript para validación de campos
?>

<div class="provider-form">

    <?php $form = ActiveForm::begin([
        'id' => 'form-provider',
        'enableAjaxValidation' => true
    ]); ?>

    <div class="card">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0"><?= Yii::t('app', 'Información del Proveedor') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Nombre del negocio primero -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'business_name')
                        ->textInput(['maxlength' => true, 'class' => 'form-control'])
                        ->label(Yii::t('app', 'Nombre del Negocio *')) ?>
                </div>
                
                <!-- RFC en la misma fila -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'rfc')
                        ->textInput(['maxlength' => true, 'class' => 'form-control'])
                        ->label('RFC') ?>
                </div>
                
                <!-- Cambiar el label de "name" a "nombre del contacto" -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'name')
                        ->textInput(['maxlength' => true])
                        ->label(Yii::t('app', 'Nombre del Contacto')) ?>
                </div>
                
                <!-- Email con validación -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'email')
                        ->input('email', ['maxlength' => true,'placeholder' => 'provider@example.com'])
                        ->label(Yii::t('app', 'Correo Electrónico')) ?>
                </div>
                
                <!-- Teléfono del negocio -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'phone')
                        ->textInput(['maxlength' => true, 'placeholder' => '+52 55 1234 5678'])
                        ->label(Yii::t('app', 'Teléfono del Negocio *')) ?>
                </div>
                
                <!-- Teléfono del contacto -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'second_phone')
                        ->textInput(['maxlength' => true, 'placeholder' => '+52 55 1234 5678'])
                        ->label(Yii::t('app', 'Teléfono del Contacto')) ?>
                </div>

                <!-- WhatsApp para solicitudes de compra -->
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'whatsapp')
                        ->textInput(['maxlength' => true, 'placeholder' => '+52 55 1234 5678'])
                        ->label('WhatsApp (para solicitudes de compra)')
                        ->hint('Con código de país. Si lo dejas vacío se usa el teléfono del contacto o, si tampoco hay, el del negocio.') ?>
                </div>

                <!-- Dirección usando todo el ancho -->
                <div class="col-12 mb-3">
                    <?= $form->field($model, 'address')
                        ->textarea(['rows' => 2])
                        ->label(Yii::t('app', 'Dirección')) ?>
                </div>
                
                <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                    <?= $form->field($model, 'payment_method')->widget(Select2::class, [
                        'data' => [
                            'cash' => Yii::t('app', 'Efectivo'),
                            'transfer' => Yii::t('app', 'Transferencia Bancaria'),
                            'check' => Yii::t('app', 'Cheque'),
                            'credit_card' => Yii::t('app', 'Tarjeta de Crédito'),
                            'debit_card' => Yii::t('app', 'Tarjeta de Débito'),
                            'other' => Yii::t('app', 'Otro Método'),
                        ],
                        'options' => [
                            'placeholder' => Yii::t('app', 'Selecciona los métodos de pago aceptados...'),
                            'multiple' => true
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'tags' => true, // Permite agregar métodos personalizados
                            'maximumInputLength' => 50,
                        ],
                        'pluginEvents' => [
                            'change' => 'function() { 
                                console.log($(this).val()); 
                                // Aquí puedes agregar lógica adicional al cambiar la selección
                            }',
                        ],
                    ])->label(Yii::t('app', 'Métodos de Pago Aceptados *')) ?>
                </div>
                
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'account')
                        ->textInput(['maxlength' => true])
                        ->label(Yii::t('app', 'Cuenta/Referencia')) ?>
                </div>
                
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'credit_days')
                        ->textInput(['type' => 'number', 'min' => '0'])
                        ->label(Yii::t('app', 'Días de Crédito')) ?>
                </div>
                
                <div class="col-md-12">
                    <hr>
                    <h6><?= Yii::t('app', 'Valoración del Proveedor') ?></h6>
                </div>
                
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'advantages')
                        ->textarea(['rows' => 3])
                        ->label(Yii::t('app', 'Ventajas')) ?>
                </div>
                
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'disadvantages')
                        ->textarea(['rows' => 3])
                        ->label(Yii::t('app', 'Desventajas')) ?>
                </div>
                
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'observations')
                        ->textarea(['rows' => 3])
                        ->label(Yii::t('app', 'Observaciones')) ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="form-group">
                <?= Html::submitButton(Yii::t('app', 'Guardar'), ['class' => 'btn btn-success']) ?>
                <?= Html::a(Yii::t('app', 'Cancelar'), ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>
<script>
// Validación para campos de teléfono
document.querySelectorAll('#provider-phone, #provider-second_phone, #provider-whatsapp').forEach(function(input) {
    input.addEventListener('input', function() {
        // Permitir solo números, espacios, guiones y signo +
        this.value = this.value.replace(/[^0-9\s\-+]/g, '');
    });
});

// Validación para días de crédito (solo números)
document.getElementById('provider-credit_days').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Validación de email en tiempo real
const emailInput = document.getElementById('provider-email');
if (emailInput) {
    // Crear el contenedor del mensaje de error fuera del campo (para mejor posicionamiento)
    const errorContainer = document.createElement('div');
    errorContainer.className = 'email-error-container mt-1';
    errorContainer.style.display = 'none';
    emailInput.parentNode.appendChild(errorContainer);
    
    emailInput.addEventListener('input', function() {
        validateEmail(this);
    });
    
    // También validar cuando el campo pierde el foco
    emailInput.addEventListener('blur', function() {
        validateEmail(this, true);
    });
}

/**
 * Valida el formato de un email y muestra feedback visual
 * @param {HTMLInputElement} input - El elemento input de email
 * @param {boolean} isBlur - Si la validación ocurre al perder el foco
 */
function validateEmail(input, isBlur = false) {
    // Expresión regular mejorada para validar emails con dominio válido
    const emailRegex = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/i;
    
    // Validación adicional: verificar que el dominio tenga al menos un punto y extensión válida
    const hasDotInDomain = input.value.split('@')[1] && input.value.split('@')[1].includes('.');
    const hasValidTLD = input.value.split('@')[1] && /\.[a-z]{2,}$/i.test(input.value.split('@')[1]);
    
    // Conseguir el contenedor de error
    const errorContainer = input.parentNode.querySelector('.email-error-container');
    
    // No validar si está vacío y no ha perdido el foco
    if (!input.value) {
        resetValidation(input);
        return;
    }
    
    // Validar el formato completo
    const isValid = emailRegex.test(input.value) && hasDotInDomain && hasValidTLD;
    
    // Mostrar retroalimentación visual
    if (isValid) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        
        // Ocultar mensaje de error
        if (errorContainer) {
            errorContainer.style.display = 'none';
        }
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        
        // Mostrar mensaje de error
        if (errorContainer) {
            errorContainer.style.display = 'block';
            errorContainer.innerHTML = `
                <div class="alert alert-danger py-1 px-2 mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Por favor ingresa un correo electrónico válido
                </div>
            `;
        }
    }
}

function resetValidation(input) {
    input.classList.remove('is-valid', 'is-invalid');
    const errorContainer = input.parentNode.querySelector('.email-error-container');
    if (errorContainer) {
        errorContainer.style.display = 'none';
    }
}
</script>