<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Holiday;
use App\Models\Store;
use App\Models\Appointment;

class HolidayController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('holidays/index.php', [
            'holidays' => Holiday::listUpcoming(date('Y-m-d')),
            'stores'   => Store::all('name'),
            'success'  => $this->input('success'),
        ]);
    }

    public function store(array $params = []): void
    {
        if (!Auth::isAdmin()) {
            $this->forbidden();
            return;
        }

        $dateFrom   = trim((string) $this->input('date_from', ''));
        $dateTo     = trim((string) $this->input('date_to', '')) ?: $dateFrom;
        $storeIdRaw = trim((string) $this->input('store_id', ''));
        $reason     = trim((string) $this->input('reason', ''));

        $errors = [];
        if ($dateFrom === '') {
            $errors['date_from'] = 'La fecha de inicio es obligatoria.';
        } elseif ($dateTo < $dateFrom) {
            $errors['date_to'] = 'La fecha de fin debe ser igual o posterior a la de inicio.';
        } elseif ((strtotime($dateTo) - strtotime($dateFrom)) / 86400 > 366) {
            $errors['date_to'] = 'El rango no puede superar un año.';
        }
        if (!$errors && $storeIdRaw !== '' && !Store::find((int) $storeIdRaw)) {
            $errors['store_id'] = 'La tienda seleccionada no existe.';
        }

        if ($errors) {
            $this->render('holidays/index.php', [
                'holidays' => Holiday::listUpcoming(date('Y-m-d')),
                'stores'   => Store::all('name'),
                'errors'   => $errors,
            ]);
            return;
        }

        $storeId = $storeIdRaw === '' ? null : (int) $storeIdRaw;

        $dates = [];
        for ($day = new \DateTime($dateFrom); $day->format('Y-m-d') <= $dateTo; $day->modify('+1 day')) {
            $dates[] = $day->format('Y-m-d');
        }

        $duplicateDates = [];
        foreach ($dates as $date) {
            $exists = $storeId === null
                ? Holiday::existsGlobal($date)
                : Holiday::existsForStore($date, $storeId);
            if ($exists) {
                $duplicateDates[] = $date;
            }
        }
        if ($duplicateDates) {
            $errors['duplicate'] = 'Ya existe un festivo registrado para: '
                . implode(', ', array_map(fn($d) => date('d/m/Y', strtotime($d)), $duplicateDates))
                . '.';
            $this->render('holidays/index.php', [
                'holidays' => Holiday::listUpcoming(date('Y-m-d')),
                'stores'   => Store::all('name'),
                'errors'   => $errors,
            ]);
            return;
        }

        $minDate     = min($dates);
        $maxDate     = max($dates);
        $conditions  = $storeId === null ? [] : ['store_id' => $storeId];
        $overlapping = array_values(array_filter(
            Appointment::listWithDetails($conditions, $minDate, $maxDate),
            fn($a) => $a['status'] !== 'cancelled'
        ));

        if ($overlapping && $this->input('force') !== '1') {
            $this->render('holidays/index.php', [
                'holidays'    => Holiday::listUpcoming(date('Y-m-d')),
                'stores'      => Store::all('name'),
                'overlapping' => $overlapping,
            ]);
            return;
        }

        foreach ($dates as $date) {
            Holiday::create([
                'date'       => $date,
                'store_id'   => $storeId,
                'reason'     => $reason !== '' ? $reason : null,
                'created_by' => Auth::id(),
            ]);
        }

        $this->redirect('/holidays?success=1');
    }

    public function destroy(array $params = []): void
    {
        if (!Auth::isAdmin()) {
            $this->forbidden();
            return;
        }

        $holiday = Holiday::find((int) $params['id']);
        if (!$holiday) {
            $this->notFound();
            return;
        }

        Holiday::delete((int) $params['id']);
        $this->redirect('/holidays?success=1');
    }
}
