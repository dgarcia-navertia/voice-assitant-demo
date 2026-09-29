<?php

namespace App\Services;

use App\Database;
use App\Models\Appointment;
use App\Models\Service;
use Throwable;

/**
 * Creates appointments. The single path for both the voice agent
 * (POST /mcp/appointments) and the web form (POST /appointments).
 *
 * "Who is free at this time?" and "book them" are two statements. Run apart,
 * two simultaneous requests both see the same person free and both insert,
 * double-booking them. So both run in one transaction that first locks the
 * store row: bookings of the same store queue up behind each other, and each
 * one re-checks availability after the previous one has committed. Stores are
 * independent, so they do not wait for each other.
 *
 * The lock order is store first, always. the reschedule flow (not in this demo) would take the
 * same store lock before touching any appointment, so a booking and a
 * reschedule in the same store serialise too, and cannot deadlock.
 */
class AppointmentBookingService
{
    /**
     * Books the slot and returns the new appointment id, or null when no
     * eligible person is free at $startsAt (or the store does not exist).
     *
     * $commercialId names the person (explicit choice); null picks one by
     * priority, filtered by $serviceId specialty. $durationMinutes null means
     * the service's own duration.
     */
    public static function book(
        int     $storeId,
        int     $clientId,
        string  $startsAt,
        ?int    $serviceId,
        ?int    $commercialId,
        ?int    $durationMinutes,
        ?int    $createdBy
    ): ?int {
        $db = Database::connection();
        // Each read after the lock must see what the previous booking just
        // committed; REPEATABLE READ could hand back an older snapshot.
        $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->beginTransaction();

        try {
            $store = $db->prepare('SELECT id FROM stores WHERE id = ? FOR UPDATE');
            $store->execute([$storeId]);
            if (!$store->fetch()) {
                $db->rollBack();
                return null;
            }

            $chosen = AvailabilityService::resolveCommercial(
                $storeId,
                $startsAt,
                $serviceId,
                $commercialId,
                $durationMinutes
            );
            if ($chosen === null) {
                $db->rollBack();
                return null;
            }

            $id = Appointment::create([
                'store_id'         => $storeId,
                'commercial_id'    => $chosen['id'],
                'client_id'        => $clientId,
                'service_id'       => $serviceId ?? Service::defaultId(),
                'starts_at'        => $startsAt,
                'duration_minutes' => $chosen['duration'],
                'status'           => 'pending',
                'created_by'       => $createdBy,
            ]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return $id;
    }
}
