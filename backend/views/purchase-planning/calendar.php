<?php

use common\models\PurchasePlanningConfig;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $config common\models\PurchasePlanningConfig */
/* @var $weeks string[] */
/* @var $currentWeek string */
/* @var $levels array */

$this->title = 'Calendario de compras';
$this->params['breadcrumbs'][] = ['label' => 'KPI\'s y Control', 'url' => ['#']];
$this->params['breadcrumbs'][] = ['label' => 'Planeación de compras', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$fmtDate = function ($ymd) {
    return Yii::$app->formatter->asDate($ymd, 'php:d/m/Y');
};
?>
<div class="purchase-planning-calendar">

    <?php foreach (['success', 'error'] as $type): ?>
        <?php if (Yii::$app->session->hasFlash($type)): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?>">
                <?= Html::encode(Yii::$app->session->getFlash($type)) ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-sliders-h"></i> Factores por nivel</h3>
                </div>
                <?= Html::beginForm(['calendar'], 'post') ?>
                <div class="card-body">
                    <p class="text-muted">
                        El stock ideal de una semana es el <strong>stock máximo</strong> de cada insumo
                        multiplicado por el factor de su nivel. Con 1.00 el ideal es el stock máximo.
                    </p>
                    <?php foreach (['factor_low', 'factor_medium', 'factor_high', 'factor_very_high'] as $attr): ?>
                        <div class="mb-3">
                            <label class="form-label" for="f-<?= $attr ?>"><?= Html::encode($config->getAttributeLabel($attr)) ?></label>
                            <?= Html::activeTextInput($config, $attr, [
                                'id' => 'f-' . $attr,
                                'type' => 'number',
                                'step' => '0.05',
                                'min' => '0.1',
                                'max' => '10',
                                'class' => 'form-control' . ($config->hasErrors($attr) ? ' is-invalid' : ''),
                            ]) ?>
                            <?php if ($config->hasErrors($attr)): ?>
                                <div class="invalid-feedback d-block"><?= Html::encode($config->getFirstError($attr)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <button type="submit" name="save_config" value="1" class="btn btn-primary">Guardar factores</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-calendar-alt"></i> Nivel de cada semana</h3>
                </div>
                <?= Html::beginForm(['calendar'], 'post') ?>
                <div class="card-body">
                    <p class="text-muted">
                        Las semanas sin nivel asignado se consideran <strong>medias</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>Semana</th>
                                <th style="width: 200px;">Nivel</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($weeks as $w):
                                $end = (new \DateTime($w))->modify('+6 days')->format('Y-m-d');
                                $current = $levels[$w] ?? PurchasePlanningConfig::LEVEL_MEDIUM;
                                ?>
                                <tr>
                                    <td>
                                        <?= $fmtDate($w) ?> &ndash; <?= $fmtDate($end) ?>
                                        <?php if ($w === $currentWeek): ?>
                                            <span class="badge bg-primary ms-1">Esta semana</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= Html::dropDownList(
                                            'levels[' . $w . ']',
                                            $current,
                                            PurchasePlanningConfig::levels(),
                                            ['class' => 'form-select form-select-sm']
                                        ) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= Url::to(['index', 'week' => $w]) ?>" class="btn btn-sm btn-outline-secondary">
                                            Ver planeación
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" name="save_levels" value="1" class="btn btn-primary">Guardar calendario</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
</div>
