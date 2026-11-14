<?php

use kartik\select2\Select2;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\PurchasePresentation */
/* @var $ingredientOptions array */
/* @var $ingredientInfo array */
/* @var $providers array */

$fieldId = Html::getInputId($model, 'ingredient_stock_id');
$infoJson = Json::htmlEncode($ingredientInfo);

$this->registerJs(<<<JS
(function () {
    var info = $infoJson;
    var sel = document.getElementById('$fieldId');
    var hint = document.getElementById('ingredient-unit-hint');
    function refresh() {
        var d = info[sel.value];
        if (!d) { hint.textContent = 'Elige un insumo para ver su unidad de uso.'; return; }
        var txt = 'Unidad de uso de este insumo: ' + d.useUm + '. Unidad de compra del insumo: ' + d.um + '.';
        if (d.perUnit > 0) { txt += ' (1 ' + d.um + ' = ' + d.perUnit + ' ' + d.useUm + ')'; }
        hint.textContent = txt;
    }
    $(sel).on('change', refresh);
    refresh();
})();
JS
);
?>
<div class="purchase-catalog-form">
    <?php $form = ActiveForm::begin(['id' => 'form-purchase-presentation']); ?>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'ingredient_stock_id')->widget(Select2::class, [
                        'data' => $ingredientOptions,
                        'options' => ['placeholder' => 'Elige el insumo...'],
                        'pluginOptions' => ['allowClear' => false],
                    ]) ?>
                    <div id="ingredient-unit-hint" class="form-text"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'provider_id')->widget(Select2::class, [
                        'data' => $providers,
                        'options' => ['placeholder' => 'Sin proveedor (opcional)'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'name')->textInput(['maxlength' => true, 'placeholder' => 'Ej. Caja de 24 piezas']) ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?= $form->field($model, 'sku')->textInput(['maxlength' => true]) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'units_per_presentation')->textInput(['type' => 'number', 'step' => 'any', 'min' => '0'])
                        ->hint('Cuántas unidades de uso trae UNA presentación (por ejemplo 24 piezas).') ?>
                </div>
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'price')->textInput(['type' => 'number', 'step' => '0.01', 'min' => '0'])
                        ->hint('Opcional. Precio de una presentación.') ?>
                </div>
                <div class="col-md-4 mb-3">
                    <?= $form->field($model, 'min_order')->textInput(['type' => 'number', 'step' => '0.01', 'min' => '0.01'])
                        ->hint('Mínimo de presentaciones por pedido.') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'is_default')->checkbox() ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'is_active')->checkbox() ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancelar', ['presentations'], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>
