<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Tablas propias de la demo de voz de Navertia (sobre el baseline copiado).
 *
 *  - calls: una fila por llamada (entrante, saliente o web). `call_sid` es el
 *    CallSid de Twilio y tambien el `sid` de `transcripts`, asi la UI enlaza
 *    llamada -> transcripcion sin FK (la sesion vive fuera de esta base).
 *  - leads: contactos que el asistente deriva a un comercial (handoff).
 *  - stores: se ensancha `phone_number` (E.164 no cabe en VARCHAR(9)) y el
 *    ENUM `type` gana valores propios de Navertia.
 */
final class VoiceDemoTables extends AbstractMigration
{
    public function up(): void
    {
        // Las tablas del baseline se crearon con la colacion por defecto del
        // charset utf8mb4 (varia segun la version de MariaDB). Las nuevas usan la misma,
        // o los JOIN/comparaciones entre ellas fallan con "Illegal mix of collations".
        $collation = (string) $this->fetchRow(
            "SELECT TABLE_COLLATION AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transcripts'"
        )['c'];

        $this->execute(
            "ALTER TABLE stores
                MODIFY phone_number VARCHAR(20) NOT NULL,
                MODIFY type ENUM('cocinas','construccion','sede (ventas, logistica, administracion)','showroom','oficina') NOT NULL"
        );

        $this->table('calls', ['id' => false, 'primary_key' => 'id', 'collation' => $collation])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('call_sid', 'string', ['limit' => 64])
            ->addColumn('direction', 'enum', ['values' => ['outbound', 'inbound', 'web'], 'default' => 'outbound'])
            ->addColumn('to_number', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('from_number', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'queued'])
            ->addColumn('duration_seconds', 'integer', ['null' => true])
            ->addColumn('error', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_by', 'integer', ['null' => true, 'signed' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['call_sid'], ['unique' => true, 'name' => 'uq_calls_call_sid'])
            ->addIndex(['created_at'], ['name' => 'idx_calls_created_at'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'SET_NULL', 'constraint' => 'fk_calls_created_by'])
            ->create();

        $this->table('leads', ['id' => false, 'primary_key' => 'id', 'collation' => $collation])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('call_sid', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 32])
            ->addColumn('store_id', 'integer', ['null' => true, 'signed' => true])
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('source', 'string', ['limit' => 30, 'default' => 'handoff'])
            ->addColumn('transferred', 'boolean', ['default' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['call_sid'], ['name' => 'idx_leads_call_sid'])
            ->addForeignKey('store_id', 'stores', 'id', ['delete' => 'SET_NULL', 'constraint' => 'fk_leads_store'])
            ->create();
    }

    public function down(): void
    {
        $this->table('leads')->drop()->save();
        $this->table('calls')->drop()->save();
        // El ensanchado de `stores` no se revierte: reducir la columna podria truncar datos.
    }
}
