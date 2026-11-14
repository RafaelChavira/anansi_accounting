<?php

namespace backend\controllers;

use backend\helpers\RedisKeys;
use common\components\PurchasePlanner;
use common\components\PurchaseRequestMessenger;
use common\models\Provider;
use common\models\PurchaseRequest;
use common\models\PurchaseRequestDispatch;
use common\models\PurchaseRequestItem;
use common\models\PurchaseWeekLevel;
use Yii;
use yii\data\Pagination;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Solicitud de compra a proveedores, a partir de la planeación de compras.
 *
 * Flujo: planeación (elegir insumos) -> review (ajustar cantidades y proveedor) -> send
 * (guarda la solicitud, manda el correo y deja listo el enlace de WhatsApp) -> view / index (historial).
 *
 * Todo se acota al negocio del usuario; las cantidades y presentaciones se recalculan en el servidor
 * con {@see PurchasePlanner}, y del cliente solo se aceptan la cantidad ajustada y el proveedor elegido.
 */
class PurchaseRequestController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'review', 'send', 'resend'],
                        'allow' => true,
                        'roles' => ['kpi_access'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'review' => ['post'],
                    'send' => ['post'],
                    'resend' => ['post'],
                ],
            ],
        ];
    }

    /** Historial de solicitudes del negocio. */
    public function actionIndex()
    {
        $businessId = (int)RedisKeys::getBusiness()->id;

        $query = PurchaseRequest::find()->where(['business_id' => $businessId]);
        $pagination = new Pagination(['totalCount' => (clone $query)->count(), 'defaultPageSize' => 20, 'pageSizeLimit' => [10, 100]]);
        $requests = $query->with(['user'])->orderBy(['id' => SORT_DESC])
            ->offset($pagination->offset)->limit($pagination->limit)->all();

        // Resumen por solicitud en dos consultas (no una por fila).
        $ids = array_map(function ($r) {
            return (int)$r->id;
        }, $requests);
        $summary = [];
        if ($ids) {
            $items = (new \yii\db\Query())->select(['request_id', 'providers' => 'COUNT(DISTINCT COALESCE(provider_id, 0))', 'lines' => 'COUNT(*)'])
                ->from('purchase_request_item')->where(['request_id' => $ids])->groupBy('request_id')->all();
            foreach ($items as $i) {
                $summary[(int)$i['request_id']] = ['providers' => (int)$i['providers'], 'lines' => (int)$i['lines'], 'sent' => 0, 'failed' => 0];
            }
            $disp = (new \yii\db\Query())->select(['request_id', 'status', 'n' => 'COUNT(*)'])
                ->from('purchase_request_dispatch')->where(['request_id' => $ids, 'channel' => 'email'])
                ->groupBy(['request_id', 'status'])->all();
            foreach ($disp as $d) {
                $rid = (int)$d['request_id'];
                if ($d['status'] === PurchaseRequestDispatch::STATUS_SENT) {
                    $summary[$rid]['sent'] = (int)$d['n'];
                } elseif ($d['status'] === PurchaseRequestDispatch::STATUS_FAILED) {
                    $summary[$rid]['failed'] = (int)$d['n'];
                }
            }
        }

        return $this->render('index', ['requests' => $requests, 'summary' => $summary, 'pagination' => $pagination]);
    }

    /**
     * Pantalla de revisión: el usuario ajusta cantidades, quita renglones y elige proveedor.
     * Recibe por POST desde la planeación: week, mode (all|selected) e ingredient_ids[].
     */
    public function actionReview()
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $post = Yii::$app->request->post();

        $week = PurchaseWeekLevel::normalizeWeek($post['week'] ?? null);
        $mode = (($post['mode'] ?? 'selected') === 'all') ? 'all' : 'selected';
        $ids = array_map('intval', (array)($post['ingredient_ids'] ?? []));

        $plan = PurchasePlanner::plan($businessId, $week);
        $rows = [];
        $sinConversion = 0;
        foreach ($plan['rows'] as $row) {
            if ($row['faltante'] <= 0) {
                continue;
            }
            if ($mode === 'selected' && !in_array((int)$row['id'], $ids, true)) {
                continue;
            }
            if ($row['compra'] === null) {
                $sinConversion++;
                continue;
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            Yii::$app->session->setFlash('error', $mode === 'selected'
                ? 'Selecciona al menos un insumo con presentación de compra.'
                : 'No hay insumos por comprar esta semana.');
            return $this->redirect(['/purchase-planning/index', 'week' => $week]);
        }

        return $this->render('review', $this->reviewData($businessId, $week, $plan, $rows, [], [], $sinConversion));
    }

    /**
     * Guarda la solicitud, manda el correo a cada proveedor y deja listo el enlace de WhatsApp.
     */
    public function actionSend()
    {
        $business = RedisKeys::getBusiness();
        $businessId = (int)$business->id;
        $post = Yii::$app->request->post();

        $week = PurchaseWeekLevel::normalizeWeek($post['week'] ?? null);
        $postedItems = (array)($post['items'] ?? []);
        $notes = mb_substr(trim((string)($post['notes'] ?? '')), 0, 2000);

        // Verdad del servidor: solo se pueden pedir insumos de este negocio con faltante y presentación.
        $plan = PurchasePlanner::plan($businessId, $week);
        $rowsById = [];
        foreach ($plan['rows'] as $row) {
            if ($row['faltante'] > 0 && $row['compra'] !== null) {
                $rowsById[(int)$row['id']] = $row;
            }
        }
        $providers = Provider::find()->where(['business_id' => $businessId])->indexBy('id')->all();

        $errors = [];
        $chosen = [];
        $rowsForView = [];
        foreach ($postedItems as $ingredientId => $data) {
            $ingredientId = (int)$ingredientId;
            if (!isset($rowsById[$ingredientId]) || !is_array($data)) {
                $errors[] = 'Un insumo de la solicitud ya no está pendiente de compra; vuelve a la planeación.';
                continue;
            }
            $row = $rowsById[$ingredientId];
            $rowsForView[] = $row;
            if (empty($data['include'])) {
                continue;
            }
            $providerId = (int)($data['provider_id'] ?? 0);
            if (!isset($providers[$providerId])) {
                $errors[] = 'Elige un proveedor para «' . $row['nombre'] . '».';
                continue;
            }
            $quantity = str_replace(',', '.', trim((string)($data['quantity'] ?? '')));
            if (!is_numeric($quantity) || (float)$quantity < 0.01 || (float)$quantity > 1000000) {
                $errors[] = 'La cantidad de «' . $row['nombre'] . '» debe ser un número mayor a 0.';
                continue;
            }
            $chosen[] = ['row' => $row, 'provider' => $providers[$providerId], 'quantity' => round((float)$quantity, 2)];
        }
        if (empty($chosen) && empty($errors)) {
            $errors[] = 'No marcaste ningún insumo para enviar.';
        }

        if ($errors) {
            $rowsForView = $rowsForView ?: array_values($rowsById);
            return $this->render('review', $this->reviewData(
                $businessId, $week, $plan, $rowsForView, $postedItems, array_values(array_unique($errors)), 0, $notes
            ));
        }

        $request = $this->saveRequest($businessId, $week, $notes, $chosen);
        if ($request === null) {
            return $this->render('review', $this->reviewData(
                $businessId, $week, $plan, $rowsForView, $postedItems, ['No se pudo guardar la solicitud. Intenta de nuevo.'], 0, $notes
            ));
        }

        // Envío fuera de la transacción: un correo lento o caído no debe deshacer la solicitud.
        $replyTo = Yii::$app->user->identity ? Yii::$app->user->identity->email : null;
        $sent = $failed = $noEmail = 0;
        foreach ($request->groupItemsByProvider() as $group) {
            $provider = $group['provider_id'] ? ($providers[$group['provider_id']] ?? null) : null;
            if ($provider === null) {
                continue;
            }
            $result = $this->dispatchToProvider($request, $provider, $group['items'], $business->name, $replyTo);
            $sent += $result === 'sent' ? 1 : 0;
            $failed += $result === 'failed' ? 1 : 0;
            $noEmail += $result === 'skipped' ? 1 : 0;
        }

        $msg = "Solicitud #{$request->id} creada.";
        $msg .= $sent ? " Correo enviado a $sent proveedor(es)." : '';
        $msg .= $failed ? " No se pudo enviar el correo a $failed proveedor(es): puedes reintentar desde aquí." : '';
        $msg .= $noEmail ? " $noEmail proveedor(es) no tienen correo." : '';
        $msg .= ' El mensaje de WhatsApp queda listo para abrirlo desde cada proveedor.';
        Yii::$app->session->setFlash($failed ? 'warning' : 'success', $msg);

        return $this->redirect(['view', 'id' => $request->id]);
    }

    /** Detalle de una solicitud: renglones por proveedor, estado de envío y botones de WhatsApp. */
    public function actionView($id)
    {
        $businessId = (int)RedisKeys::getBusiness()->id;
        $request = $this->findRequest($id, $businessId);
        $business = RedisKeys::getBusiness();

        $providers = Provider::find()->where(['business_id' => $businessId])->indexBy('id')->all();
        $groups = [];
        foreach ($request->groupItemsByProvider() as $key => $group) {
            $provider = $group['provider_id'] ? ($providers[$group['provider_id']] ?? null) : null;
            $wa = null;
            if ($provider !== null) {
                $target = PurchaseRequestMessenger::whatsappTarget($provider);
                if ($target['number'] !== null) {
                    $text = PurchaseRequestMessenger::buildText(
                        $request, $group['items'],
                        trim((string)$provider->name) !== '' ? $provider->name : ($provider->business_name ?: 'proveedor'),
                        $business->name
                    );
                    $wa = $target + ['url' => PurchaseRequestMessenger::whatsappUrl($target['number'], $text)];
                }
            }
            $group['provider'] = $provider;
            $group['whatsapp'] = $wa;
            $group['dispatches'] = array_values(array_filter($request->dispatches, function ($d) use ($group) {
                return (int)$d->provider_id === (int)$group['provider_id'];
            }));
            $groups[$key] = $group;
        }

        return $this->render('view', ['request' => $request, 'groups' => $groups]);
    }

    /** Reintenta el correo a un proveedor de una solicitud ya creada. */
    public function actionResend()
    {
        $business = RedisKeys::getBusiness();
        $businessId = (int)$business->id;
        $post = Yii::$app->request->post();

        $request = $this->findRequest($post['id'] ?? 0, $businessId);
        $provider = Provider::findOne(['id' => (int)($post['provider_id'] ?? 0), 'business_id' => $businessId]);
        $groups = $request->groupItemsByProvider();
        $key = $provider ? (string)$provider->id : null;

        if ($provider === null || !isset($groups[$key])) {
            throw new NotFoundHttpException('El proveedor no pertenece a esta solicitud.');
        }

        $replyTo = Yii::$app->user->identity ? Yii::$app->user->identity->email : null;
        $result = $this->dispatchToProvider($request, $provider, $groups[$key]['items'], $business->name, $replyTo, false);
        Yii::$app->session->setFlash(
            $result === 'sent' ? 'success' : 'warning',
            $result === 'sent' ? 'Correo reenviado.' : 'No se pudo reenviar el correo: revisa el correo del proveedor.'
        );
        return $this->redirect(['view', 'id' => $request->id]);
    }

    // ------------------------------------------------------------------------------------------

    /** Solo solicitudes del negocio del usuario. */
    protected function findRequest($id, $businessId)
    {
        $request = PurchaseRequest::findOne(['id' => (int)$id, 'business_id' => $businessId]);
        if ($request === null) {
            throw new NotFoundHttpException('La solicitud no existe.');
        }
        return $request;
    }

    /**
     * Datos de la pantalla de revisión. $posted trae lo que el usuario ya había escrito
     * (para no perderlo si hay errores): items[ingredient_id] => [include, provider_id, quantity].
     */
    protected function reviewData($businessId, $week, array $plan, array $rows, array $posted, array $errors, $sinConversion = 0, $notes = '')
    {
        $ids = array_map(function ($r) {
            return (int)$r['id'];
        }, $rows);
        $candidates = PurchasePlanner::providersByIngredient($businessId, $ids);
        $allProviders = [];
        foreach (Provider::find()->where(['business_id' => $businessId])->orderBy('business_name ASC, name ASC')->all() as $p) {
            $allProviders[$p->id] = $p->business_name ?: $p->name;
        }

        $items = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $opts = $candidates[$id] ?? [];
            $catalogProvider = $row['compra']['proveedor_id'] ?? null;
            if ($catalogProvider && isset($allProviders[$catalogProvider]) && !isset($opts[$catalogProvider])) {
                $opts = [$catalogProvider => $allProviders[$catalogProvider]] + $opts;
            }
            // Si el insumo no tiene proveedor asignado, se puede elegir cualquiera del negocio.
            $assigned = !empty($opts);
            if (!$assigned) {
                $opts = $allProviders;
            }
            $default = null;
            if ($catalogProvider && isset($opts[$catalogProvider])) {
                $default = $catalogProvider;
            } elseif ($assigned) {
                $default = (int)array_key_first($opts);
            }
            $p = $posted[$id] ?? null;
            $items[] = [
                'row' => $row,
                'options' => $opts,
                'assigned' => $assigned,
                'include' => $p === null ? true : !empty($p['include']),
                'provider_id' => $p !== null && isset($p['provider_id']) && $p['provider_id'] !== '' ? (int)$p['provider_id'] : $default,
                'quantity' => $p !== null && isset($p['quantity']) ? (string)$p['quantity'] : (string)$row['compra']['cantidad'],
            ];
        }

        return [
            'week' => $week,
            'level' => $plan['level'],
            'factor' => $plan['factor'],
            'items' => $items,
            'errors' => $errors,
            'sinConversion' => $sinConversion,
            'notes' => $notes,
        ];
    }

    /**
     * Guarda encabezado y renglones en una transacción.
     *
     * @param array $chosen [['row' => planner row, 'provider' => Provider, 'quantity' => float], ...]
     * @return PurchaseRequest|null null si algo falló (no se guarda nada).
     */
    protected function saveRequest($businessId, $week, $notes, array $chosen)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $request = new PurchaseRequest([
                'business_id' => $businessId,
                'user_id' => Yii::$app->user->id,
                'week_start' => $week,
                'notes' => $notes !== '' ? $notes : null,
                'status' => 'sent',
                'total_estimated' => 0,
            ]);
            if (!$request->save()) {
                throw new \RuntimeException('encabezado: ' . json_encode($request->errors));
            }

            $total = 0.0;
            foreach ($chosen as $c) {
                $compra = $c['row']['compra'];
                $unitPrice = $compra['precio_unitario'] ?? null;
                $lineTotal = $unitPrice !== null ? round($c['quantity'] * (float)$unitPrice, 2) : null;
                $total += (float)$lineTotal;

                $item = new PurchaseRequestItem([
                    'request_id' => $request->id,
                    'provider_id' => $c['provider']->id,
                    'provider_name' => $c['provider']->business_name ?: $c['provider']->name,
                    'ingredient_stock_id' => $c['row']['id'],
                    'ingredient_name' => $c['row']['nombre'],
                    'presentation_name' => $compra['nombre'],
                    'sku' => $compra['sku'] ?? null,
                    'quantity' => $c['quantity'],
                    'units_per_presentation' => $compra['contenido'],
                    'use_unit' => $c['row']['unidad'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
                if (!$item->save()) {
                    throw new \RuntimeException('renglón: ' . json_encode($item->errors));
                }
            }
            $request->total_estimated = round($total, 2);
            $request->save(false, ['total_estimated', 'updated_at']);

            $transaction->commit();
            return $request;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('No se pudo guardar la solicitud de compra: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Manda el correo a un proveedor y deja registrado el intento; si $withWhatsapp, también registra
     * que el enlace de WhatsApp quedó listo (o que no hay número).
     *
     * @return string Estado del correo: sent | failed | skipped.
     */
    protected function dispatchToProvider(PurchaseRequest $request, Provider $provider, array $items, $businessName, $replyTo, $withWhatsapp = true)
    {
        $name = $provider->business_name ?: $provider->name;
        $email = trim((string)$provider->email);

        if ($email === '') {
            $status = PurchaseRequestDispatch::STATUS_SKIPPED;
            $error = 'El proveedor no tiene correo.';
        } else {
            list($ok, $err) = PurchaseRequestMessenger::sendEmail($request, $provider, $items, $businessName, $replyTo);
            $status = $ok ? PurchaseRequestDispatch::STATUS_SENT : PurchaseRequestDispatch::STATUS_FAILED;
            $error = $err;
        }
        $this->logDispatch($request, $provider, $name, PurchaseRequestDispatch::CHANNEL_EMAIL, $email ?: null, $status, $error);

        if ($withWhatsapp) {
            $target = PurchaseRequestMessenger::whatsappTarget($provider);
            $this->logDispatch(
                $request, $provider, $name, PurchaseRequestDispatch::CHANNEL_WHATSAPP,
                $target['number'] !== null ? '+' . $target['number'] : null,
                $target['number'] !== null ? PurchaseRequestDispatch::STATUS_PREPARED : PurchaseRequestDispatch::STATUS_SKIPPED,
                $target['number'] !== null ? null : 'El proveedor no tiene un número de WhatsApp o teléfono válido.'
            );
        }
        return $status;
    }

    protected function logDispatch(PurchaseRequest $request, Provider $provider, $name, $channel, $recipient, $status, $error)
    {
        $d = new PurchaseRequestDispatch([
            'request_id' => $request->id,
            'provider_id' => $provider->id,
            'provider_name' => $name,
            'channel' => $channel,
            'recipient' => $recipient !== null ? mb_substr($recipient, 0, 255) : null,
            'status' => $status,
            'error' => $error !== null ? mb_substr($error, 0, 500) : null,
        ]);
        $d->save();
        return $d;
    }
}
