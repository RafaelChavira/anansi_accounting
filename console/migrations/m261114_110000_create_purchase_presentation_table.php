<?php

use yii\db\Migration;

/**
 * Catálogo de compras: presentaciones de compra ligadas a un insumo.
 *
 * Cada fila es un producto tal como lo vende un proveedor (p. ej. "Caja 24 pzas").
 * units_per_presentation se expresa en la UNIDAD DE USO del insumo
 * (ingredient_stock.portion_um), que es la unidad en la que se guarda la existencia.
 */
class m261114_110000_create_purchase_presentation_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('purchase_presentation', [
            'id' => $this->primaryKey(),
            'business_id' => $this->integer()->notNull(),
            'ingredient_stock_id' => $this->integer()->notNull(),
            'provider_id' => $this->integer()->null(),
            'name' => $this->string(255)->notNull()->comment('Como lo vende el proveedor'),
            'sku' => $this->string(100)->null()->comment('Codigo del proveedor'),
            'units_per_presentation' => $this->decimal(14, 4)->notNull()->comment('En unidad de uso del insumo'),
            'price' => $this->decimal(12, 2)->null()->comment('Precio por presentacion'),
            'min_order' => $this->decimal(12, 2)->notNull()->defaultValue(1)->comment('Minimo de presentaciones por pedido'),
            'is_default' => $this->boolean()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(1),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->createIndex(
            'idx-purchase_presentation-business_ingredient',
            'purchase_presentation',
            ['business_id', 'ingredient_stock_id']
        );
        $this->createIndex('idx-purchase_presentation-provider_id', 'purchase_presentation', 'provider_id');

        $this->addForeignKey(
            'fk-purchase_presentation-business_id',
            'purchase_presentation', 'business_id',
            'business', 'id', 'CASCADE'
        );
        $this->addForeignKey(
            'fk-purchase_presentation-ingredient_stock_id',
            'purchase_presentation', 'ingredient_stock_id',
            'ingredient_stock', 'id', 'CASCADE'
        );
        $this->addForeignKey(
            'fk-purchase_presentation-provider_id',
            'purchase_presentation', 'provider_id',
            'provider', 'id', 'SET NULL'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-purchase_presentation-provider_id', 'purchase_presentation');
        $this->dropForeignKey('fk-purchase_presentation-ingredient_stock_id', 'purchase_presentation');
        $this->dropForeignKey('fk-purchase_presentation-business_id', 'purchase_presentation');
        $this->dropTable('purchase_presentation');
    }
}
