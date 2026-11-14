<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Un intento de envío de una solicitud a un proveedor por un canal.
 *
 * - email:    status = sent | failed | skipped (el proveedor no tiene correo).
 * - whatsapp: status = prepared (el enlace wa.me queda listo; el usuario lo abre y manda el mensaje
 *             desde su propio WhatsApp) | skipped (el proveedor no tiene número válido).
 *
 * @property int $id
 * @property int $request_id
 * @property int|null $provider_id
 * @property string $provider_name
 * @property string $channel
 * @property string|null $recipient
 * @property string $status
 * @property string|null $error
 * @property int $created_at
 * @property int $updated_at
 */
class PurchaseRequestDispatch extends ActiveRecord
{
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_WHATSAPP = 'whatsapp';

    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_PREPARED = 'prepared';
    const STATUS_SKIPPED = 'skipped';

    public static function tableName()
    {
        return 'purchase_request_dispatch';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['request_id', 'provider_name', 'channel', 'status'], 'required'],
            [['request_id', 'provider_id'], 'integer'],
            [['channel'], 'in', 'range' => [self::CHANNEL_EMAIL, self::CHANNEL_WHATSAPP]],
            [['status'], 'in', 'range' => [self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_PREPARED, self::STATUS_SKIPPED]],
            [['provider_name', 'recipient'], 'string', 'max' => 255],
            [['error'], 'string', 'max' => 500],
        ];
    }

    public function getRequest()
    {
        return $this->hasOne(PurchaseRequest::class, ['id' => 'request_id']);
    }
}
