<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Auditoria en la tabla clave/valor `settings` (ya existente en el baseline):
 * quien y cuando cambio cada ajuste. Lo usa `handoff_phone_number`, el numero
 * al que el asistente transfiere las llamadas, editable por el admin.
 */
final class SettingsAudit extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'ALTER TABLE settings
                ADD COLUMN updated_by INT NULL,
                ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL,
                ADD CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE settings DROP FOREIGN KEY fk_settings_updated_by, DROP COLUMN updated_by, DROP COLUMN updated_at');
    }
}
