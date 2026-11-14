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

    <?php
    // Ejemplo de la leyenda, calculado con los factores guardados del negocio.
    $exMax = 50;      // stock máximo del insumo de ejemplo
    $exActual = 22;   // existencia actual del insumo de ejemplo
    ?>
    <div class="card border-info mb-3">
        <div class="card-header bg-label-info">
            <h3 class="card-title mb-0"><i class="fas fa-lightbulb"></i> ¿Para qué sirven los factores?</h3>
        </div>
        <div class="card-body">
            <p class="mb-2">
                Cada insumo tiene <strong>un solo stock máximo</strong>: lo que quieres tener en una semana
                <strong>normal</strong>. El factor lo ajusta según qué tan fuerte será la semana, para que el sistema
                te sugiera comprar más cuando viene una semana alta y menos cuando viene una baja:
            </p>
            <p class="text-center fs-5 mb-3">
                <strong>Stock ideal de la semana = stock máximo &times; factor del nivel</strong>
            </p>
            <ol class="mb-3">
                <li>Marca abajo el <strong>nivel de cada semana</strong> (baja, media, alta o muy alta).</li>
                <li>La planeación de compras toma el <strong>factor de ese nivel</strong> y lo aplica a todos tus insumos
                    que tengan stock máximo.</li>
                <li>Lo que <strong>falta</strong> es el stock ideal menos tu existencia actual, y eso es lo que el sistema
                    te sugiere comprar.</li>
            </ol>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-2" style="max-width: 760px;">
                    <caption class="caption-top">
                        Ejemplo con los factores guardados: un insumo con stock máximo de <strong><?= $exMax ?></strong>
                        y existencia actual de <strong><?= $exActual ?></strong>.
                    </caption>
                    <thead class="table-light">
                    <tr>
                        <th>Nivel de la semana</th>
                        <th class="text-end">Factor</th>
                        <th class="text-end">Stock ideal</th>
                        <th class="text-end">Falta por comprar</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (PurchasePlanningConfig::levels() as $levelKey => $levelLabel):
                        $f = $config->getFactor($levelKey);
                        $ideal = $exMax * $f;
                        $falta = max(0, $ideal - $exActual);
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?= PurchasePlanningConfig::levelBadge($levelKey) ?>">
                                    <?= Html::encode($levelLabel) ?>
                                </span>
                            </td>
                            <td class="text-end">&times;<?= number_format($f, 2, '.', '') ?></td>
                            <td class="text-end"><?= rtrim(rtrim(number_format($ideal, 2, ',', '.'), '0'), ',') ?></td>
                            <td class="text-end"><strong><?= rtrim(rtrim(number_format($falta, 2, ',', '.'), '0'), ',') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="small text-muted mb-0">
                <li>Con factor <strong>1.00</strong> el stock ideal es igual al stock máximo; menor a 1 compras menos, mayor a 1 compras más.</li>
                <li>Los valores por defecto (0.70, 1.00, 1.30 y 1.60) son solo una propuesta: ajústalos a lo que realmente
                    pasa en tu negocio (si en una semana muy alta vendes el doble, pon 2.00).</li>
                <li>El factor se aplica por igual a <strong>todos</strong> los insumos y se guarda por negocio.</li>
                <li>Las semanas sin nivel asignado se consideran <strong>medias</strong>.</li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-sliders-h"></i> Factores por nivel</h3>
                </div>
                <?= Html::beginForm(['calendar'], 'post') ?>
                <div class="card-body">
                    <p class="text-muted">
                        Escribe cuánto multiplica el stock máximo cada nivel (entre 0.1 y 10). El ejemplo de arriba
                        se actualiza al guardar.
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
