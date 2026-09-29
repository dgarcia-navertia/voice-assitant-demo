<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Esquema completo de la agenda: punto de partida de todo entorno.
 *
 * Sustituye al par `docker/schema.sql` + `docker/migrations/001..015_*.sql`,
 * que obligaba a escribir cada `ALTER` dos veces (una para las bases nuevas y
 * otra para las ya creadas) sin que nada registrase qué se había aplicado.
 * Aquí los quince `ALTER` históricos ya vienen plegados en el `CREATE TABLE`
 * definitivo de cada tabla, así que esta migración describe el esquema tal
 * como quedó, no cómo se llegó a él.
 *
 * El DDL va en SQL crudo y no con la API de tablas de Phinx porque es un
 * porte fiel de un esquema en producción: hay detalles que la API no expresa
 * (el `CHECK` del mutex, el índice `FULLTEXT` de products) y otros que sería
 * fácil perder al traducir sin que nada fallase de forma visible — sobre todo
 * la colación `utf8mb4_bin` de las claves de idempotencia, que es lo que hace
 * que distingan mayúsculas. Las migraciones NUEVAS sí deben usar la API de
 * Phinx; esta es la única excepción, y lo es por una vez.
 */
final class BaselineSchema extends AbstractMigration
{
    public function up(): void
    {
        foreach ($this->tables() as $ddl) {
            $this->execute($ddl);
        }

        // Fila única del mutex de asignación de transferencias. Es estructura,
        // no dato de negocio: sin ella CommercialTransferAssignmentRepository
        // no tiene qué bloquear y toda transferencia de llamada falla. Por eso
        // vive aquí y no en un seeder.
        $this->execute('INSERT IGNORE INTO commercial_transfer_assignment_mutex (id) VALUES (1)');
    }

    public function down(): void
    {
        // El orden inverso no basta con las FK autorreferenciadas de
        // appointments, así que se desactiva la comprobación durante el drop.
        $this->execute('SET FOREIGN_KEY_CHECKS = 0');

        foreach (array_reverse(array_keys($this->tables())) as $table) {
            $this->execute("DROP TABLE IF EXISTS `{$table}`");
        }

        $this->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Las tablas en orden de creación: una tabla con FOREIGN KEY va siempre
     * después de aquella a la que apunta.
     *
     * @return array<string, string> nombre de tabla => sentencia CREATE
     */
    private function tables(): array
    {
        return [
            'stores' => <<<'SQL'
                CREATE TABLE stores (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    name         VARCHAR(255) NOT NULL UNIQUE,
                    address      TEXT NOT NULL,
                    type         ENUM('cocinas','construccion','sede (ventas, logistica, administracion)') NOT NULL,
                    phone_number VARCHAR(9) NOT NULL,
                    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            'store_schedules' => <<<'SQL'
                CREATE TABLE store_schedules (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    store_id      INT NOT NULL UNIQUE,
                    mon_to_friday VARCHAR(100) NULL,
                    saturday      VARCHAR(100) NULL,
                    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // users: login web y el nombre de la persona, que vive SOLO aquí
            // (ver el comentario de `commercials`). Los datos de negocio de
            // quien atiende citas van en `commercials`.
            //
            // `managed_store_id` se llama así y no `store_id` porque no es la
            // tienda en la que la persona trabaja —eso es
            // `commercials.store_id`— sino la tienda a la que el rol manager
            // le ACOTA EL ACCESO. Compartir nombre con una columna de
            // significado distinto ya provocó una confusión; para un manager
            // reservable las dos coinciden, y para un comercial esta es NULL
            // mientras la otra no.
            'users' => <<<'SQL'
                CREATE TABLE users (
                    id               INT AUTO_INCREMENT PRIMARY KEY,
                    name             VARCHAR(255) NOT NULL,
                    email            VARCHAR(255) NOT NULL UNIQUE,
                    password         VARCHAR(255) NOT NULL,
                    role             ENUM('admin','commercial','manager') NOT NULL,
                    managed_store_id INT NULL COMMENT 'Only for manager role; scopes their ACCESS. Not where they work — that is commercials.store_id',
                    ical_token       VARCHAR(64) NULL UNIQUE COMMENT 'Token for the personal .ics calendar feed; NULL until first generated',
                    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (managed_store_id) REFERENCES stores(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // password_resets: una fila por solicitud de "he olvidado mi
            // contraseña". token_hash guarda el SHA-256, nunca el token: el
            // valor en claro solo existe dentro del email, así que un volcado
            // de la base no es una toma de todas las cuentas. De un solo uso
            // (used_at) y de vida corta (expires_at); created_at no es
            // contabilidad, de ahí salen el cooldown y el límite horario.
            'password_resets' => <<<'SQL'
                CREATE TABLE password_resets (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 of the token sent by email',
                    expires_at DATETIME NOT NULL,
                    used_at    DATETIME NULL COMMENT 'NULL until the link is consumed; a token is valid once',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_password_resets_user (user_id, created_at),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // services: catálogo de tipos de cita reservables. Los comerciales
            // declaran cuáles atienden en commercials.specialties.
            //
            // `public` separa dos cosas que comparten tabla: servicios
            // comerciales reales (Cocina, Baño completo…) y tipos de cita
            // operativos que solo tienen sentido internamente (General, Temas,
            // Recoger mercancía comprada, Confirmación de pedido). TODOS siguen
            // siendo reservables; `public` solo decide cuáles enumera en voz
            // alta el asistente. Por defecto 0: un servicio nuevo se queda
            // callado hasta que alguien lo marque.
            'services' => <<<'SQL'
                CREATE TABLE services (
                    id               INT AUTO_INCREMENT PRIMARY KEY,
                    name             VARCHAR(255) NOT NULL UNIQUE,
                    appointment_time INT NOT NULL DEFAULT 60,
                    `public`         TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = el asistente lo menciona al enumerar servicios; 0 = solo interno'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // commercials: datos de negocio de quien atiende citas. `id` no es
            // un autoincremento sino la clave primaria compartida con
            // users.id: relación 1:1 por PK, que garantiza la unicidad sin
            // índice extra y hace que borrar el usuario cascadee aquí.
            //
            // **No hay columna `name` a propósito.** El nombre de la persona
            // vive solo en `users`. Cuando estuvo en las dos tablas, editar a
            // alguien desde la ventana "Usuarios" actualizaba users.name y
            // dejaba commercials.name obsoleto, así que la agenda y el bot
            // seguían diciendo el nombre viejo. Las consultas de este modelo
            // hacen JOIN y lo exponen como `name`, de modo que quien consume
            // el array no nota la diferencia.
            //
            // El horario semanal vive en commercial_week_patterns;
            // rotation_length es el número de semanas del ciclo (1 = semanal
            // fijo) y rotation_anchor el lunes al que se ancla (irrelevante si
            // rotation_length = 1).
            'commercials' => <<<'SQL'
                CREATE TABLE commercials (
                    id              INT PRIMARY KEY,
                    phone           VARCHAR(20) NULL,
                    store_id        INT NULL COMMENT 'Store this person WORKS at. Not the manager access scope — that is users.managed_store_id',
                    navision_id     VARCHAR(50) NULL,
                    cod_vend        VARCHAR(50) NULL,
                    specialties     JSON NULL COMMENT 'JSON array of services.id; NULL = no specialties',
                    rotation_length TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Number of weeks in the rotation; 1 = no rotation',
                    rotation_anchor DATE NULL COMMENT 'Monday the rotation is anchored to; irrelevant when rotation_length = 1',
                    active                 TINYINT(1) NOT NULL DEFAULT 1,
                    voice_transfer_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    last_voice_assigned_at DATETIME(6) NULL,
                    FOREIGN KEY (id)       REFERENCES users(id)  ON DELETE CASCADE,
                    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Un único mutex global serializa la elección de comercial para
            // transferencia, tanto en las peticiones globales como en las
            // acotadas a una tienda.
            'commercial_transfer_assignment_mutex' => <<<'SQL'
                CREATE TABLE commercial_transfer_assignment_mutex (
                    id TINYINT UNSIGNED PRIMARY KEY,
                    CONSTRAINT chk_transfer_assignment_mutex_singleton CHECK (id = 1)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Foto del resultado de cada petición, para repetirla de forma
            // idempotente durante 24 h. El reparto por turnos en sí vive en
            // commercials.last_voice_assigned_at, así que caducar estas filas
            // no reinicia el orden.
            'commercial_transfer_assignments' => <<<'SQL'
                CREATE TABLE commercial_transfer_assignments (
                    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    request_key   VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                    criteria_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    criteria_json TEXT NOT NULL,
                    state         ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
                    assignment_id VARCHAR(32) NULL,
                    commercial_id INT NULL,
                    assigned_at   DATETIME(6) NULL,
                    http_status   SMALLINT UNSIGNED NULL,
                    error_code    VARCHAR(64) NULL,
                    result_json   LONGTEXT NULL,
                    created_at    DATETIME(6) NOT NULL,
                    updated_at    DATETIME(6) NOT NULL,
                    expires_at    DATETIME(6) NOT NULL,
                    UNIQUE KEY uq_transfer_assignment_request_key (request_key),
                    UNIQUE KEY uq_transfer_assignment_public_id (assignment_id),
                    INDEX idx_transfer_assignment_expiry (expires_at),
                    FOREIGN KEY (commercial_id) REFERENCES commercials(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // El horario semanal de cada semana de rotación (week_index
            // 0..rotation_length-1). Mismo formato de string que
            // store_schedules ("09:30-14:00 y 16:00-21:00"); NULL = no trabaja.
            'commercial_week_patterns' => <<<'SQL'
                CREATE TABLE commercial_week_patterns (
                    commercial_id INT NOT NULL,
                    week_index    TINYINT UNSIGNED NOT NULL,
                    monday        VARCHAR(255) NULL,
                    tuesday       VARCHAR(255) NULL,
                    wednesday     VARCHAR(255) NULL,
                    thursday      VARCHAR(255) NULL,
                    friday        VARCHAR(255) NULL,
                    saturday      VARCHAR(255) NULL,
                    sunday        VARCHAR(255) NULL,
                    PRIMARY KEY (commercial_id, week_index),
                    FOREIGN KEY (commercial_id) REFERENCES commercials(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Excepción por fecha al patrón, consultada ANTES que el patrón
            // (festivo > override > patrón). works = 0 libra ese día; works = 1
            // con `schedule` lo sustituye; works = 1 con `schedule` NULL hereda
            // lo que diga el patrón ese día de la semana (inválido si el patrón
            // no tiene horario: se rechaza al guardar).
            'schedule_overrides' => <<<'SQL'
                CREATE TABLE schedule_overrides (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    commercial_id INT NOT NULL,
                    date          DATE NOT NULL,
                    works         TINYINT(1) NOT NULL,
                    schedule      VARCHAR(255) NULL,
                    UNIQUE (commercial_id, date),
                    INDEX idx_schedule_overrides_date (date),
                    FOREIGN KEY (commercial_id) REFERENCES commercials(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            'api_keys' => <<<'SQL'
                CREATE TABLE api_keys (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(100) NOT NULL,
                    `key`      VARCHAR(64)  NOT NULL UNIQUE,
                    type       ENUM('user','mcp') NOT NULL DEFAULT 'user',
                    user_id    INT NULL,
                    active     TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            'clients' => <<<'SQL'
                CREATE TABLE clients (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    client_name  VARCHAR(255) NOT NULL,
                    client_phone VARCHAR(30) NOT NULL,
                    client_email VARCHAR(255) NOT NULL,
                    client_type  ENUM('particular','empresa') NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // products: lo llena src/scripts/import_catalog.php. `cost` es el
            // coste crudo de catálogo (sin IVA) y `price` el precio de cara al
            // cliente (con IVA), derivado por App\Services\ProductPricing.
            'products' => <<<'SQL'
                CREATE TABLE products (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    magento_id      INT NULL,
                    sku             VARCHAR(50) NOT NULL UNIQUE,
                    ref_fabricante  VARCHAR(50) NULL,
                    name            VARCHAR(255) NOT NULL,
                    manufacturer    VARCHAR(100) NULL,
                    model           VARCHAR(100) NULL,
                    category        VARCHAR(40) NOT NULL DEFAULT 'sin_clasificar',
                    product_type    VARCHAR(60) NULL,
                    is_bundle       TINYINT(1) NOT NULL DEFAULT 0,
                    color_norm      VARCHAR(30) NULL,
                    width_cm        DECIMAL(6,1) NULL,
                    height_cm       DECIMAL(6,1) NULL,
                    finish          VARCHAR(30) NULL,
                    specs           JSON NULL,
                    cost            DECIMAL(10,2) NULL,
                    price           DECIMAL(10,2) NULL,
                    pvp             DECIMAL(10,2) NULL,
                    price_unit      VARCHAR(5) NULL,
                    sales_unit      VARCHAR(5) NULL,
                    sales_multiple  DECIMAL(6,2) NULL,
                    is_in_stock     TINYINT(1) NOT NULL DEFAULT 1,
                    availability    ENUM('en_stock','bajo_pedido','no_disponible') NOT NULL DEFAULT 'bajo_pedido',
                    delivery_days   TINYINT UNSIGNED NULL,
                    image           VARCHAR(255) NULL,
                    url_key         VARCHAR(255) NULL,
                    description     TEXT NULL,
                    created_at      DATETIME NULL,
                    updated_at      DATETIME NULL,
                    INDEX idx_manufacturer (manufacturer),
                    INDEX idx_category (category),
                    INDEX idx_product_type (product_type),
                    INDEX idx_color_norm (color_norm),
                    INDEX idx_price (price),
                    INDEX idx_in_stock (is_in_stock),
                    INDEX idx_availability (availability),
                    FULLTEXT idx_search (name, description)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            'providers' => <<<'SQL'
                CREATE TABLE providers (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    name        VARCHAR(255) NOT NULL UNIQUE,
                    description TEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // reschedule_request_key lleva colación utf8mb4_bin a propósito:
            // es una clave de idempotencia y debe distinguir mayúsculas.
            'appointments' => <<<'SQL'
                CREATE TABLE appointments (
                    id                     INT AUTO_INCREMENT PRIMARY KEY,
                    store_id               INT NOT NULL,
                    commercial_id          INT NOT NULL,
                    client_id              INT NOT NULL,
                    service_id             INT NULL COMMENT 'services.id this appointment is for; NULL = generic',
                    starts_at              DATETIME NOT NULL,
                    duration_minutes       INT NOT NULL DEFAULT 60,
                    status                 ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
                    cancellation_reason    TEXT NULL,
                    cancelled_at           DATETIME NULL,
                    replaced_by_id         INT NULL,
                    reschedule_request_key VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
                    has_conflict           TINYINT(1) NOT NULL DEFAULT 0,
                    created_by             INT NULL,
                    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (store_id)      REFERENCES stores(id),
                    FOREIGN KEY (commercial_id) REFERENCES users(id),
                    FOREIGN KEY (client_id)     REFERENCES clients(id) ON DELETE CASCADE,
                    FOREIGN KEY (service_id)    REFERENCES services(id) ON DELETE SET NULL,
                    FOREIGN KEY (created_by)    REFERENCES users(id) ON DELETE SET NULL,
                    CONSTRAINT fk_appointments_replaced_by FOREIGN KEY (replaced_by_id) REFERENCES appointments(id) ON DELETE RESTRICT,
                    UNIQUE KEY uq_appointments_reschedule_request_key (reschedule_request_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Un intento de llamada de sustitución por fila: una llamada que
            // acaba sin reasignación no puede bloquear los siguientes intentos
            // para siempre, que es lo que pasaba con una única columna en
            // appointments.
            'appointment_reschedule_calls' => <<<'SQL'
                CREATE TABLE appointment_reschedule_calls (
                    id                INT AUTO_INCREMENT PRIMARY KEY,
                    appointment_id    INT NOT NULL,
                    call_sid          VARCHAR(64) NOT NULL,
                    status            VARCHAR(32) NOT NULL DEFAULT 'queued',
                    start_time        DATETIME NULL,
                    status_checked_at DATETIME NULL,
                    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_appointment_reschedule_calls_sid (call_sid),
                    KEY idx_appointment_reschedule_calls_appointment (appointment_id, id),
                    CONSTRAINT fk_appointment_reschedule_calls_appointment
                        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Un SMS aceptado por la pasarela del servicio de voz por fila.
            // Tabla y no columna, por lo mismo que el historial de llamadas: un
            // envío fallido no debe bloquear el reintento, y una cita puede
            // recibir legítimamente los dos eventos a lo largo de su vida. No
            // se guarda ni el texto ni el teléfono: ambos se derivan al auditar.
            'appointment_confirmation_sms' => <<<'SQL'
                CREATE TABLE appointment_confirmation_sms (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    appointment_id INT NOT NULL,
                    event          ENUM('confirmed','cancelled') NOT NULL,
                    message_sid    VARCHAR(64) NOT NULL,
                    status         VARCHAR(32) NOT NULL DEFAULT 'queued',
                    sent_by        INT NULL,
                    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_appointment_confirmation_sms_sid (message_sid),
                    KEY idx_appointment_confirmation_sms_appointment (appointment_id, event, id),
                    CONSTRAINT fk_appointment_confirmation_sms_appointment
                        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
                    CONSTRAINT fk_appointment_confirmation_sms_sent_by
                        FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Franjas que un comercial marca como no disponibles (reuniones,
            // etc.). Se restan de la disponibilidad igual que las citas
            // confirmadas, pero sin cliente, ni ciclo de estados, ni avisos.
            'blocking_events' => <<<'SQL'
                CREATE TABLE blocking_events (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    commercial_id INT NOT NULL,
                    starts_at     DATETIME NOT NULL,
                    ends_at       DATETIME NOT NULL,
                    all_day       TINYINT(1) NOT NULL DEFAULT 0,
                    reason        VARCHAR(255) NULL,
                    created_by    INT NULL,
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (commercial_id) REFERENCES commercials(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by)    REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Días concretos en que una tienda (o todas, store_id NULL) cierra.
            // Bloquea reservas NUEVAS en esa fecha por todos los canales; las
            // citas ya existentes no se tocan. Una fila por día cerrado: un
            // rango elegido en el formulario se expande en HolidayController.
            'holidays' => <<<'SQL'
                CREATE TABLE holidays (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    date       DATE NOT NULL,
                    store_id   INT NULL COMMENT 'NULL = all stores (national/chain)',
                    reason     VARCHAR(255) NULL,
                    created_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_holidays_date (date),
                    FOREIGN KEY (store_id)   REFERENCES stores(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Transcripción turno a turno de una llamada. `sid` es el id
            // externo de la sesión de Pipecat y no tiene FK porque esa sesión
            // vive fuera de esta base. turn_index es un contador monotónico por
            // llamada: para reanudarla se lee MAX(turn_index) y se continúa.
            'transcripts' => <<<'SQL'
                CREATE TABLE transcripts (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    sid             VARCHAR(128) NOT NULL,
                    role            ENUM('user','assistant') NOT NULL,
                    transcript_text TEXT NOT NULL,
                    turn_index      INT NOT NULL,
                    interrupted     TINYINT(1) NOT NULL DEFAULT 0,
                    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sid_turn (sid, turn_index)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            'notifications' => <<<'SQL'
                CREATE TABLE notifications (
                    id             INT AUTO_INCREMENT PRIMARY KEY,
                    user_id        INT NOT NULL,
                    appointment_id INT NOT NULL,
                    event          ENUM('created','modified','cancelled') NOT NULL,
                    read_at        TIMESTAMP NULL,
                    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
                    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            // Textos legales y contenido estático de empresa, editables por el
            // admin desde la ventana "Legal". Una fila fija por tipo; el
            // contenido inicial lo pone LegalDocumentsSeeder.
            'legal_documents' => <<<'SQL'
                CREATE TABLE legal_documents (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    doc_type   ENUM('general_terms','warranty','purchase_conditions','shipping_returns','payment_methods','faq_transactional') NOT NULL UNIQUE,
                    content    MEDIUMTEXT NULL,
                    filename   VARCHAR(255) NULL,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,

            'settings' => <<<'SQL'
                CREATE TABLE settings (
                    `key`   VARCHAR(100) PRIMARY KEY,
                    `value` VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                SQL,
        ];
    }
}
