<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

/* @var $this yii\web\View */
/* @var $requests common\models\PurchaseRequest[] */
/* @var $summary array */
/* @var $pagination yii\data\Pagination */

$this->title = 'Solicitudes de compra';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="purchase-request-index">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-paper-plane"></i> <?= Html::encode($this->title) ?></h3>
            <a class="btn btn-primary btn-sm" href="<?= Url::to(['/purchase-planning/index']) ?>">
                <i class="fas fa-plus"></i> Nueva desde la planeación
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Creada por</th>
                        <th>Semana</th>
                        <th class="text-end">Proveedores</th>
                        <th class="text-end">Renglones</th>
                        <th class="text-end">Total estimado</th>
                        <th>Correos</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Todavía no has enviado solicitudes de compra.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $r):
                        $s = $summary[$r->id] ?? ['providers' => 0, 'lines' => 0, 'sent' => 0, 'failed' => 0];
                        ?>
                        <tr>
                            <td><strong>#<?= (int)$r->id ?></strong></td>
                            <td><?= Yii::$app->formatter->asDatetime($r->created_at, 'php:d/m/Y H:i') ?></td>
                            <td><?= Html::encode($r->user ? $r->user->email : '—') ?></td>
                            <td><?= $r->week_start ? Yii::$app->formatter->asDate($r->week_start, 'php:d/m/Y') : '—' ?></td>
                            <td class="text-end"><?= (int)$s['providers'] ?></td>
                            <td class="text-end"><?= (int)$s['lines'] ?></td>
                            <td class="text-end"><?= (float)$r->total_estimated > 0 ? '$' . number_format((float)$r->total_estimated, 2, ',', '.') : '—' ?></td>
                            <td>
                                <?php if ($s['sent']): ?><span class="badge bg-success"><?= (int)$s['sent'] ?> enviado(s)</span><?php endif; ?>
                                <?php if ($s['failed']): ?><span class="badge bg-danger"><?= (int)$s['failed'] ?> con error</span><?php endif; ?>
                                <?php if (!$s['sent'] && !$s['failed']): ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= Url::to(['view', 'id' => $r->id]) ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination->pageCount > 1): ?>
                <nav class="mt-3"><?= LinkPager::widget([
                        'pagination' => $pagination,
                        'options' => ['class' => 'pagination pagination-sm m-0 flex-wrap'],
                        'linkContainerOptions' => ['class' => 'page-item'],
                        'linkOptions' => ['class' => 'page-link'],
                        'disabledListItemSubTagOptions' => ['class' => 'page-link'],
                    ]) ?></nav>
            <?php endif; ?>
        </div>
    </div>
</div>
