<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\PurchasePresentation */
/* @var $ingredientOptions array */
/* @var $ingredientInfo array */
/* @var $providers array */

$this->title = 'Nueva presentación de compra';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de compras', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="purchase-catalog-create">
    <?= $this->render('_form', [
        'model' => $model,
        'ingredientOptions' => $ingredientOptions,
        'ingredientInfo' => $ingredientInfo,
        'providers' => $providers,
    ]) ?>
</div>
