<?php

use common\models\PurchasePlanningConfig;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $week string */
/* @var $level string */
/* @var $factor float */
/* @var $items array */
/* @var $errors string[] */
/* @var $sinConversion int */
/* @var $notes string */

$this->title = 'Revisar solicitud de compra';
$this->params['breadcrumbs'][] = ['label' => 'Planeación de compras', 'url' => ['/purchase-planning/index', 'week' => $week]];
$this->params['breadcrumbs'][] = $this->title;

$from = new \DateTime($week);
$to = (clone $from)->modify('+6 days');
$qty = function ($n) {
    return rtrim(rtrim(number_format((float)$n, 4, ',', '.'), '0'), ',');
};
?>
<div class="purchase-request-review">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>No se envió la solicitud:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= Html::encode($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        Semana del <strong><?= $from->format('d/m/Y') ?></strong> al <strong><?= $to->format('d/m/Y') ?></strong>
        &middot; Nivel
        <span class="badge bg-<?= PurchasePlanningConfig::levelBadge($level) ?>"><?= Html::encode(PurchasePlanningConfig::levelLabel($level)) ?></span>.
        Ajusta las cantidades, quita lo que no quieras pedir y elige el proveedor de cada insumo. Al enviar, a cada proveedor
        le llegará <strong>un correo</strong> con solo sus insumos, y tendrás el mensaje de <strong>WhatsApp</strong> listo para abrirlo.
        <?php if ($sinConversion > 0): ?>
            <br><small class="text-muted"><?= (int)$sinConversion ?> insumo(s) no se incluyeron porque no tienen equivalencia de compra.</small>
        <?php endif; ?>
    </div>

    <?= Html::beginForm(['send'], 'post', ['id' => 'review-form']) ?>
    <?= Html::hiddenInput('week', $week) ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="review-table">
                    <thead>
                    <tr>
                        <th style="width:40px"><input type="checkbox" id="check-all" class="form-check-input" checked title="Marcar o quitar todos"></th>
                        <th>Insumo</th>
                        <th>Presentación</th>
                        <th class="text-end">Falta</th>
                        <th style="width:140px">Cantidad a pedir</th>
                        <th style="min-width:220px">Proveedor</th>
                        <th class="text-end">Costo est.</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it):
                        $r = $it['row'];
                        $c = $r['compra'];
                        $base = 'items[' . (int)$r['id'] . ']';
                        $unitPrice = $c['precio_unitario'];
                        ?>
                        <tr class="review-row" data-unit-price="<?= $unitPrice !== null ? Html::encode((string)$unitPrice) : '' ?>">
                            <td>
                                <?= Html::checkbox($base . '[include]', (bool)$it['include'], ['class' => 'form-check-input row-include', 'value' => 1]) ?>
                            </td>
                            <td><strong><?= Html::encode($r['nombre']) ?></strong></td>
                            <td>
                                <?= Html::encode($c['nombre']) ?>
                                <br><small class="text-muted"><?= $qty($c['contenido']) ?> <?= Html::encode($r['unidad']) ?> c/u</small>
                            </td>
                            <td class="text-end"><?= $qty($r['faltante']) ?> <small class="text-muted"><?= Html::encode($r['unidad']) ?></small></td>
                            <td>
                                <?= Html::textInput($base . '[quantity]', $it['quantity'], [
                                    'type' => 'number', 'step' => '0.01', 'min' => '0.01',
                                    'class' => 'form-control form-control-sm row-qty',
                                ]) ?>
                            </td>
                            <td>
                                <?php
                                $selectOptions = ['class' => 'form-select form-select-sm'];
                                if (!$it['assigned']) {
                                    // Sin proveedor asignado: obliga a elegir uno (la clave 'prompt' no debe existir si no se usa).
                                    $selectOptions['prompt'] = 'Elige un proveedor…';
                                }
                                ?>
                                <?= Html::dropDownList($base . '[provider_id]', $it['provider_id'], $it['options'], $selectOptions) ?>
                                <?php if (!$it['assigned']): ?>
                                    <small class="text-warning">Sin proveedor asignado al insumo</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end row-total" data-has-price="<?= $unitPrice !== null ? 1 : 0 ?>">
                                <?= $unitPrice !== null ? '$' . number_format((float)$it['quantity'] * (float)$unitPrice, 2, ',', '.') : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="6" class="text-end">Total estimado de lo marcado</th>
                        <th class="text-end" id="grand-total">—</th>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-3">
                <label class="form-label" for="notes">Nota para los proveedores (opcional)</label>
                <?= Html::textarea('notes', $notes, ['id' => 'notes', 'rows' => 2, 'maxlength' => 2000, 'class' => 'form-control']) ?>
                <div class="form-text">Se guarda en la solicitud, para tu historial.</div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Se enviará un correo a cada proveedor con los insumos marcados. ¿Continuar?');">
                <i class="fas fa-paper-plane"></i> Enviar solicitud
            </button>
            <a class="btn btn-outline-secondary" href="<?= Url::to(['/purchase-planning/index', 'week' => $week]) ?>">Cancelar</a>
        </div>
    </div>
    <?= Html::endForm() ?>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
    var table = document.getElementById('review-table');
    function money2(n) {
        var parts = n.toFixed(2).split('.');
        return '$' + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + parts[1];
    }
    function recalc() {
        var grand = 0, any = false;
        table.querySelectorAll('tr.review-row').forEach(function (tr) {
            var unit = tr.getAttribute('data-unit-price');
            var cell = tr.querySelector('.row-total');
            var qty = parseFloat((tr.querySelector('.row-qty').value || '0').replace(',', '.'));
            var included = tr.querySelector('.row-include').checked;
            tr.style.opacity = included ? '1' : '0.45';
            if (unit === '' || isNaN(qty)) { cell.textContent = '—'; return; }
            var line = qty * parseFloat(unit);
            cell.textContent = money2(line);
            if (included) { grand += line; any = true; }
        });
        document.getElementById('grand-total').textContent = any ? money2(grand) : '—';
    }
    table.addEventListener('input', recalc);
    table.addEventListener('change', recalc);
    document.getElementById('check-all').addEventListener('change', function () {
        var on = this.checked;
        table.querySelectorAll('.row-include').forEach(function (c) { c.checked = on; });
        recalc();
    });
    recalc();
})();
JS
);
