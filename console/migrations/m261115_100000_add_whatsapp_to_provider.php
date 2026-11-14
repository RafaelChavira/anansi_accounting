<?php

use yii\db\Migration;

/**
 * Número de WhatsApp del proveedor para enviar las solicitudes de compra.
 * Si está vacío se usa el teléfono del contacto y, en su defecto, el del negocio.
 */
class m261115_100000_add_whatsapp_to_provider extends Migration
{
    public function safeUp()
    {
        $this->addColumn('provider', 'whatsapp', $this->string(50)->null()->after('second_phone'));
    }

    public function safeDown()
    {
        $this->dropColumn('provider', 'whatsapp');
    }
}
