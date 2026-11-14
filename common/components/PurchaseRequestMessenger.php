<?php

namespace common\components;

use common\models\Provider;
use common\models\PurchaseRequest;
use common\models\PurchaseRequestItem;
use Yii;
use yii\helpers\Html;
use yii\validators\EmailValidator;

/**
 * Arma y envía las solicitudes de compra a los proveedores.
 *
 * - Correo: se envía de verdad desde el sistema con el mailer configurado (en local, a archivo).
 * - WhatsApp: no se envía solo; se genera un enlace wa.me con el mensaje ya redactado y el usuario
 *   lo manda desde su propio WhatsApp.
 */
class PurchaseRequestMessenger
{
    /**
     * Deja solo dígitos de un número de WhatsApp (formato que pide wa.me, sin "+").
     * Devuelve null si no parece un número válido (E.164: de 8 a 15 dígitos).
     *
     * @param string|null $raw
     * @return string|null
     */
    public static function normalizeWhatsapp($raw)
    {
        $digits = preg_replace('/\D+/', '', (string)$raw);
        // Prefijo internacional "00" (equivale a "+").
        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }
        $len = strlen($digits);
        if ($len < 8 || $len > 15) {
            return null;
        }
        return $digits;
    }

    /**
     * Número al que se le mandaría el WhatsApp: WhatsApp del proveedor; si no tiene, el teléfono
     * del contacto; y si tampoco, el del negocio.
     *
     * @param Provider $provider
     * @return array {number: string|null, source: string|null, has_country_code: bool}
     */
    public static function whatsappTarget(Provider $provider)
    {
        foreach (['whatsapp', 'second_phone', 'phone'] as $field) {
            $raw = trim((string)$provider->$field);
            $number = self::normalizeWhatsapp($raw);
            if ($number !== null) {
                return [
                    'number' => $number,
                    'source' => $field,
                    // wa.me necesita el código de país: solo lo sabemos seguro si el número trae "+" o "00".
                    'has_country_code' => (bool)preg_match('/^\s*(\+|00)/', $raw),
                ];
            }
        }
        return ['number' => null, 'source' => null, 'has_country_code' => false];
    }

    /**
     * Enlace que abre WhatsApp con el mensaje ya escrito.
     */
    public static function whatsappUrl($number, $text)
    {
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
    }

    /**
     * Texto plano de la solicitud (cuerpo del WhatsApp y del correo).
     *
     * @param PurchaseRequest $request
     * @param PurchaseRequestItem[] $items Renglones de UN proveedor.
     * @param string $greetingName Nombre del contacto del proveedor (o de la empresa).
     * @param string $businessName Nombre del negocio que compra.
     * @return string
     */
    public static function buildText(PurchaseRequest $request, array $items, $greetingName, $businessName)
    {
        $lines = [];
        $lines[] = 'Hola ' . self::oneLine($greetingName) . ', te comparto la solicitud de compra #' . $request->id
            . ' de ' . self::oneLine($businessName) . self::weekLabel($request) . ':';
        $lines[] = '';
        $total = 0.0;
        foreach ($items as $it) {
            $line = '• ' . $it->getQuantityLabel() . ' × ' . self::oneLine($it->presentation_name)
                . ' — ' . self::oneLine($it->ingredient_name);
            if ($it->units_per_presentation !== null && $it->use_unit) {
                $line .= ' (' . self::number($it->units_per_presentation) . ' ' . self::oneLine($it->use_unit) . ' c/u)';
            }
            if ($it->sku) {
                $line .= ' [código: ' . self::oneLine($it->sku) . ']';
            }
            $lines[] = $line;
            $total += (float)$it->line_total;
        }
        if ($total > 0) {
            $lines[] = '';
            $lines[] = 'Total estimado: $' . number_format($total, 2, '.', ',');
        }
        $lines[] = '';
        $lines[] = 'Por favor confírmame disponibilidad, precio y fecha de entrega. ¡Gracias!';
        $lines[] = self::oneLine($businessName);
        return implode("\n", $lines);
    }

    /**
     * Versión HTML del correo. Todo dato variable va escapado.
     */
    public static function buildHtml(PurchaseRequest $request, array $items, $greetingName, $businessName)
    {
        $rows = '';
        $total = 0.0;
        foreach ($items as $it) {
            $content = ($it->units_per_presentation !== null && $it->use_unit)
                ? Html::encode(self::number($it->units_per_presentation) . ' ' . $it->use_unit) . ' c/u'
                : '';
            $rows .= '<tr>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #ddd">' . Html::encode($it->ingredient_name) . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #ddd;text-align:right"><strong>' . Html::encode($it->getQuantityLabel()) . '</strong> × ' . Html::encode($it->presentation_name) . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #ddd">' . $content . '</td>'
                . '<td style="padding:6px 10px;border-bottom:1px solid #ddd">' . Html::encode((string)$it->sku) . '</td>'
                . '</tr>';
            $total += (float)$it->line_total;
        }
        $totalHtml = $total > 0
            ? '<p>Total estimado: <strong>$' . number_format($total, 2, '.', ',') . '</strong></p>'
            : '';

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333">'
            . '<p>Hola ' . Html::encode(self::oneLine($greetingName)) . ',</p>'
            . '<p>Te comparto la solicitud de compra <strong>#' . (int)$request->id . '</strong> de '
            . Html::encode(self::oneLine($businessName)) . Html::encode(self::weekLabel($request)) . ':</p>'
            . '<table style="border-collapse:collapse;min-width:420px">'
            . '<thead><tr style="background:#f3f3f3">'
            . '<th style="padding:6px 10px;text-align:left">Insumo</th>'
            . '<th style="padding:6px 10px;text-align:right">Cantidad</th>'
            . '<th style="padding:6px 10px;text-align:left">Contenido</th>'
            . '<th style="padding:6px 10px;text-align:left">Código</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . $totalHtml
            . '<p>Por favor confírmame disponibilidad, precio y fecha de entrega. ¡Gracias!</p>'
            . '<p>' . Html::encode(self::oneLine($businessName)) . '</p>'
            . '</div>';
    }

    /**
     * Envía el correo de la solicitud a un proveedor.
     *
     * @param PurchaseRequest $request
     * @param Provider $provider
     * @param PurchaseRequestItem[] $items Renglones de ese proveedor.
     * @param string $businessName
     * @param string|null $replyTo Correo de quien compra, para que el proveedor le responda a él.
     * @return array [bool $enviado, string|null $error]
     */
    public static function sendEmail(PurchaseRequest $request, Provider $provider, array $items, $businessName, $replyTo = null)
    {
        $to = trim((string)$provider->email);
        $validator = new EmailValidator();
        if ($to === '' || !$validator->validate($to)) {
            return [false, 'El proveedor no tiene un correo válido.'];
        }
        if (empty($items)) {
            return [false, 'La solicitud no tiene renglones para este proveedor.'];
        }

        $greeting = trim((string)$provider->name) !== '' ? $provider->name : ($provider->business_name ?: 'proveedor');
        $subject = 'Solicitud de compra #' . $request->id . ' - ' . self::oneLine($businessName);

        try {
            $message = Yii::$app->mailer->compose()
                ->setTo($to)
                ->setFrom([Yii::$app->params['senderEmail'] => self::oneLine($businessName) . ' (Sistema de Costeo)'])
                ->setSubject($subject)
                ->setTextBody(self::buildText($request, $items, $greeting, $businessName))
                ->setHtmlBody(self::buildHtml($request, $items, $greeting, $businessName));

            if ($replyTo !== null && $validator->validate($replyTo)) {
                $message->setReplyTo($replyTo);
            }
            if (!$message->send()) {
                return [false, 'El servidor de correo no aceptó el mensaje.'];
            }
            return [true, null];
        } catch (\Throwable $e) {
            Yii::error('Error al enviar la solicitud de compra #' . $request->id . ': ' . $e->getMessage(), __METHOD__);
            return [false, mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /** Quita saltos de línea y caracteres de control (evita inyección en cabeceras y mensajes). */
    private static function oneLine($value)
    {
        return trim(preg_replace('/[\r\n\t\x00-\x1F\x7F]+/u', ' ', (string)$value));
    }

    private static function number($n)
    {
        return rtrim(rtrim(number_format((float)$n, 4, ',', '.'), '0'), ',');
    }

    private static function weekLabel(PurchaseRequest $request)
    {
        if (!$request->week_start) {
            return '';
        }
        $from = new \DateTime($request->week_start);
        $to = (clone $from)->modify('+6 days');
        return ' (semana del ' . $from->format('d/m/Y') . ' al ' . $to->format('d/m/Y') . ')';
    }
}
