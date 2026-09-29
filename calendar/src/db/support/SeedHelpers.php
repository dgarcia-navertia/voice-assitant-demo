<?php

declare(strict_types=1);

/**
 * Utilidades compartidas por los seeders.
 *
 * Vive fuera de `db/seeds/` a propósito: Phinx carga como seeder todo archivo
 * que encuentre en esa carpeta, y un trait ahí dentro rompería el escaneo. Se
 * incluye con `require_once` desde cada seeder en vez de por autoload, para no
 * tener que registrar un namespace más en composer.json solo para esto.
 */
trait SeedHelpers
{
    /**
     * Inserta o actualiza filas por su clave única.
     *
     * Un seeder tiene que poder ejecutarse sobre una base ya poblada sin
     * duplicar nada: `make db-seed` se lanza tanto en una base recién creada
     * como sobre una que ya lleva datos. De ahí el ON DUPLICATE KEY UPDATE en
     * lugar de un INSERT a secas.
     *
     * @param array<int, array<string, mixed>> $rows          todas con las mismas columnas
     * @param array<int, string>               $updateColumns columnas a refrescar si la fila ya existe
     */
    protected function upsert(string $table, array $rows, array $updateColumns): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', array_map(fn (string $c) => "`{$c}`", $columns)),
            implode(', ', array_map(fn (string $c) => ":{$c}", $columns)),
            implode(', ', array_map(fn (string $c) => "`{$c}` = VALUES(`{$c}`)", $updateColumns))
        );

        $statement = $this->getAdapter()->getConnection()->prepare($sql);

        foreach ($rows as $row) {
            $statement->execute($row);
        }
    }

    /**
     * Deja el AUTO_INCREMENT por encima de los ids fijos que siembra el seeder,
     * para que la primera fila creada desde la aplicación no choque con ellos.
     *
     * MariaDB ignora en silencio un valor por debajo del máximo existente, así
     * que esto nunca puede reutilizar un id ya ocupado.
     */
    protected function resetAutoIncrement(string $table, int $nextId): void
    {
        $this->execute("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
    }
}
