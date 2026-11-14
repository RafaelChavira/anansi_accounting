<?php

use yii\db\Migration;

/**
 * Solicitudes de compra a proveedores (planeación de compras, fase 3).
 *
 * - purchase_request:          encabezado (negocio, quién la hizo, semana planeada).
 * - purchase_request_item:     renglones; guardan una copia (snapshot) de los nombres para que el
 *                              historial no cambie si luego se renombra o borra un insumo o proveedor.
 * - purchase_request_dispatch: cada intento de envío por proveedor y canal (email / whatsapp).
 */
class m261115_110000_create_purchase_request_tables extends Migration
{
    public function safeUp()
    {
        $this->createTable('purchase_request', [
            'id' => $this->primaryKey(),
            'business_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null()->comment('Quien la creó'),
            'week_start' => $this->date()->null()->comment('Lunes de la semana planeada'),
            'notes' => $this->text()->null(),
            'status' => $this->string(20)->notNull()->defaultValue('sent'),
            'total_estimated' => $this->decimal(14, 2)->notNull()->defaultValue(0),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);
        $this->createIndex('idx-purchase_request-business_created', 'purchase_request', ['business_id', 'created_at']);
        $this->addForeignKey('fk-purchase_request-business_id', 'purchase_request', 'business_id', 'business', 'id', 'CASCADE');

        $this->createTable('purchase_request_item', [
            'id' => $this->primaryKey(),
            'request_id' => $this->integer()->notNull(),
            'provider_id' => $this->integer()->null(),
            'provider_name' => $this->string(255)->notNull(),
            'ingredient_stock_id' => $this->integer()->null(),
            'ingredient_name' => $this->string(255)->notNull(),
            'presentation_name' => $this->string(255)->notNull()->comment('Caja, Paquete, o el nombre de la presentación del catálogo'),
            'sku' => $this->string(100)->null(),
            'quantity' => $this->decimal(12, 2)->notNull()->comment('Número de presentaciones a comprar'),
            'units_per_presentation' => $this->decimal(14, 4)->null(),
            'use_unit' => $this->string(100)->null(),
            'unit_price' => $this->decimal(12, 2)->null()->comment('Precio de una presentación'),
            'line_total' => $this->decimal(14, 2)->null(),
        ]);
        $this->createIndex('idx-purchase_request_item-request_provider', 'purchase_request_item', ['request_id', 'provider_id']);
        $this->addForeignKey('fk-purchase_request_item-request_id', 'purchase_request_item', 'request_id', 'purchase_request', 'id', 'CASCADE');
        $this->addForeignKey('fk-purchase_request_item-provider_id', 'purchase_request_item', 'provider_id', 'provider', 'id', 'SET NULL');
        $this->addForeignKey('fk-purchase_request_item-ingredient_stock_id', 'purchase_request_item', 'ingredient_stock_id', 'ingredient_stock', 'id', 'SET NULL');

        $this->createTable('purchase_request_dispatch', [
            'id' => $this->primaryKey(),
            'request_id' => $this->integer()->notNull(),
            'provider_id' => $this->integer()->null(),
            'provider_name' => $this->string(255)->notNull(),
            'channel' => $this->string(20)->notNull()->comment('email | whatsapp'),
            'recipient' => $this->string(255)->null(),
            'status' => $this->string(20)->notNull()->comment('sent | failed | prepared | skipped'),
            'error' => $this->string(500)->null(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);
        $this->createIndex('idx-purchase_request_dispatch-request_provider', 'purchase_request_dispatch', ['request_id', 'provider_id']);
        $this->addForeignKey('fk-purchase_request_dispatch-request_id', 'purchase_request_dispatch', 'request_id', 'purchase_request', 'id', 'CASCADE');
        $this->addForeignKey('fk-purchase_request_dispatch-provider_id', 'purchase_request_dispatch', 'provider_id', 'provider', 'id', 'SET NULL');
    }

    public function safeDown()
    {
        $this->dropTable('purchase_request_dispatch');
        $this->dropTable('purchase_request_item');
        $this->dropTable('purchase_request');
    }
}
