<?php

use yii\db\Migration;

/**
 * Planeación de compras sugerida (fase 1).
 *
 * - purchase_planning_config: factores por nivel de semana, uno por negocio.
 *   Stock ideal de la semana = stock máximo del insumo × factor del nivel.
 * - purchase_week_level: nivel asignado a cada semana (lunes) del calendario.
 */
class m261114_100000_create_purchase_planning_tables extends Migration
{
    public function safeUp()
    {
        $this->createTable('purchase_planning_config', [
            'id' => $this->primaryKey(),
            'business_id' => $this->integer()->notNull(),
            'factor_low' => $this->decimal(5, 2)->notNull()->defaultValue(0.70),
            'factor_medium' => $this->decimal(5, 2)->notNull()->defaultValue(1.00),
            'factor_high' => $this->decimal(5, 2)->notNull()->defaultValue(1.30),
            'factor_very_high' => $this->decimal(5, 2)->notNull()->defaultValue(1.60),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);
        $this->createIndex('uq-purchase_planning_config-business_id', 'purchase_planning_config', 'business_id', true);
        $this->addForeignKey(
            'fk-purchase_planning_config-business_id',
            'purchase_planning_config', 'business_id',
            'business', 'id', 'CASCADE'
        );

        $this->createTable('purchase_week_level', [
            'id' => $this->primaryKey(),
            'business_id' => $this->integer()->notNull(),
            'week_start' => $this->date()->notNull()->comment('Lunes de la semana'),
            'level' => $this->string(20)->notNull()->comment('low|medium|high|very_high'),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);
        $this->createIndex(
            'uq-purchase_week_level-business_week',
            'purchase_week_level', ['business_id', 'week_start'], true
        );
        $this->addForeignKey(
            'fk-purchase_week_level-business_id',
            'purchase_week_level', 'business_id',
            'business', 'id', 'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-purchase_week_level-business_id', 'purchase_week_level');
        $this->dropTable('purchase_week_level');
        $this->dropForeignKey('fk-purchase_planning_config-business_id', 'purchase_planning_config');
        $this->dropTable('purchase_planning_config');
    }
}
