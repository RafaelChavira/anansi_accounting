<?php

use common\models\PurchaseRequestDispatch;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $request common\models\PurchaseRequest */
/* @var $groups array */

$this->title = 'Solicitud de compra #' . $request->id;
$this->params['breadcrumbs'][] = ['label' => 'Solicitudes de compra', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$fmtMoney = function ($n) {
    return $n === null ? '—' : '$' . number_format((float)$n, 2, ',', '.');
};
$fmtQty = function ($n) {
    return rtrim(rtrim(number_format((float)$n, 4, ',', '.'), '0'), ',');
};
$statusBadge = function ($d) {
    $map = [
        PurchaseRequestDispatch::STATUS_SENT => ['success', 'Enviado'],
        PurchaseRequestDispatch::STATUS_FAILED => ['danger', 'Falló'],
        PurchaseRequestDispatch::STATUS_PREPARED => ['info', 'Listo para abrir'],
        PurchaseRequestDispatch::STATUS_SKIPPED => ['secondary', 'No enviado'],
    ];
    list($color, $label) = $map[$d->status] ?? ['secondary', $d->status];
    return '<span class="badge bg-' . $color . '">' . Html::encode($label) . '</span>';
};
$weekLabel = '';
if ($request->week_start) {
    $from = new \DateTime($request->week_start);
    $weekLabel = $from->format('d/m/Y') . ' al ' . (clone $from)->modify('+6 days')->format('d/m/Y');
}
?>
<div class="purchase-request-view">

    <?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $type => $css): ?>
        <?php if (Yii::$app->session->hasFlash($type)): ?>
            <div class="alert alert-<?= $css ?>"><?= Html::encode(Yii::$app->session->getFlash($type)) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-4">
            <div><div class="text-muted small">Fecha</div><strong><?= Yii::$app->formatter->asDatetime($request->created_at, 'php:d/m/Y H:i') ?></strong></div>
            <div><div class="text-muted small">Creada por</div><strong><?= Html::encode($request->user ? $request->user->email : '—') ?></strong></div>
            <?php if ($weekLabel): ?>
                <div><div class="text-muted small">Semana planeada</div><strong><?= $weekLabel ?></strong></div>
            <?php endif; ?>
            <div><div class="text-muted small">Proveedores</div><strong><?= count($groups) ?></strong></div>
            <div><div class="text-muted small">Total estimado</div><strong><?= $fmtMoney((float)$request->total_estimated > 0 ? $request->total_estimated : null) ?></strong></div>
        </div>
        <?php if ($request->notes): ?>
            <div class="card-footer"><span class="text-muted small">Nota:</span> <?= nl2br(Html::encode($request->notes)) ?></div>
        <?php endif; ?>
    </div>

    <?php foreach ($groups as $g):
        $provider = $g['provider'];
        $emails = array_values(array_filter($g['dispatches'], function ($d) {
            return $d->channel === PurchaseRequestDispatch::CHANNEL_EMAIL;
        }));
        $lastEmail = $emails ? end($emails) : null;
        $total = 0.0;
        foreach ($g['items'] as $it) {
            $total += (float)$it->line_total;
        }
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0"><i class="fas fa-truck"></i> <?= Html::encode($g['provider_name']) ?></h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($lastEmail): ?>
                        <span class="small text-muted">Correo<?= $lastEmail->recipient ? ' a ' . Html::encode($lastEmail->recipient) : '' ?>:</span>
                        <?= $statusBadge($lastEmail) ?>
                        <?php if ($lastEmail->error): ?>
                            <small class="text-muted">(<?= Html::encode($lastEmail->error) ?>)</small>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($provider !== null && trim((string)$provider->email) !== ''): ?>
                        <?= Html::beginForm(['resend'], 'post', ['class' => 'd-inline']) ?>
                        <?= Html::hiddenInput('id', $request->id) ?>
                        <?= Html::hiddenInput('provider_id', $provider->id) ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                onclick="return confirm('¿Reenviar el correo a este proveedor?');">
                            <i class="fas fa-redo"></i> Reenviar correo
                        </button>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                    <?php if ($g['whatsapp'] !== null): ?>
                        <a class="btn btn-sm btn-success" target="_blank" rel="noopener noreferrer"
                           href="<?= Html::encode($g['whatsapp']['url']) ?>">
                            <i class="fab fa-whatsapp"></i> Abrir WhatsApp (+<?= Html::encode($g['whatsapp']['number']) ?>)
                        </a>
                    <?php else: ?>
                        <span class="badge bg-secondary">Sin WhatsApp</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($g['whatsapp'] !== null && !$g['whatsapp']['has_country_code']): ?>
                <div class="alert alert-warning mb-0 rounded-0 py-2">
                    <small>El número +<?= Html::encode($g['whatsapp']['number']) ?> no trae código de país (+52, +34…). Si WhatsApp no lo
                        encuentra, corrígelo en el proveedor.</small>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Insumo</th>
                        <th class="text-end">Cantidad</th>
                        <th>Presentación</th>
                        <th>Contenido</th>
                        <th class="text-end">Costo est.</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($g['items'] as $it): ?>
                        <tr>
                            <td><?= Html::encode($it->ingredient_name) ?></td>
                            <td class="text-end"><strong><?= Html::encode($it->getQuantityLabel()) ?></strong></td>
                            <td><?= Html::encode($it->presentation_name) ?><?= $it->sku ? ' <small class="text-muted">(' . Html::encode($it->sku) . ')</small>' : '' ?></td>
                            <td><?= $it->units_per_presentation !== null ? $fmtQty($it->units_per_presentation) . ' ' . Html::encode($it->use_unit) : '—' ?></td>
                            <td class="text-end"><?= $fmtMoney($it->line_total) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php if ($total > 0): ?>
                        <tfoot><tr><th colspan="4" class="text-end">Total estimado</th><th class="text-end"><?= $fmtMoney($total) ?></th></tr></tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <?php if (count($g['dispatches']) > 0): ?>
                <div class="card-footer">
                    <small class="text-muted">Historial de envíos:</small>
                    <ul class="mb-0 small">
                        <?php foreach ($g['dispatches'] as $d): ?>
                            <li>
                                <?= Yii::$app->formatter->asDatetime($d->created_at, 'php:d/m/Y H:i') ?> &middot;
                                <?= $d->channel === 'email' ? 'Correo' : 'WhatsApp' ?> <?= $statusBadge($d) ?>
                                <?= $d->recipient ? '&middot; ' . Html::encode($d->recipient) : '' ?>
                                <?= $d->error ? '&middot; <span class="text-muted">' . Html::encode($d->error) . '</span>' : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <a class="btn btn-outline-secondary" href="<?= Url::to(['index']) ?>"><i class="fas fa-arrow-left"></i> Historial</a>
    <a class="btn btn-outline-primary" href="<?= Url::to(['/purchase-planning/index']) ?>">Volver a la planeación</a>
</div>
