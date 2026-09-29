<?php

namespace App\Controllers;

use App\Models\Commercial;
use App\Models\Store;

class StoreController extends Controller
{
    public function index(array $params = []): void
    {
        $stores = Store::withCommercialCount();
        $this->render('stores/index.php', compact('stores'));
    }

    public function create(array $params = []): void
    {
        $this->render('stores/form.php', ['store' => null, 'action' => '/stores', 'method' => 'POST']);
    }

    public function store(array $params = []): void
    {
        $errors = $this->validateFields(['name', 'address', 'type', 'phone_number']);
        if ($errors) {
            $this->render('stores/form.php', ['store' => null, 'errors' => $errors, 'action' => '/stores', 'method' => 'POST']);
            return;
        }

        $id = Store::create([
            'name'         => $this->input('name'),
            'address'      => $this->input('address'),
            'type'         => $this->input('type'),
            'phone_number' => $this->input('phone_number'),
        ]);
        Store::saveSchedule($id, $this->scheduleInput('mon_to_friday'), $this->scheduleInput('saturday'));

        $this->redirect('/stores');
    }

    public function edit(array $params = []): void
    {
        $store = Store::find((int) $params['id']);
        if (!$store) { $this->notFound(); return; }

        $schedule = Store::schedules((int) $store['id']);
        $store['mon_to_friday'] = $schedule['mon_to_friday'] ?? null;
        $store['saturday']      = $schedule['saturday'] ?? null;

        $this->render('stores/form.php', [
            'store'       => $store,
            'action'      => '/stores/' . $store['id'],
            'method'      => 'PUT',
            'commercials' => Commercial::withStore((int) $store['id']),
        ]);
    }

    public function update(array $params = []): void
    {
        $store = Store::find((int) $params['id']);
        if (!$store) { $this->notFound(); return; }

        Store::update((int) $params['id'], [
            'name'         => $this->input('name'),
            'address'      => $this->input('address'),
            'type'         => $this->input('type'),
            'phone_number' => $this->input('phone_number'),
        ]);
        Store::saveSchedule((int) $params['id'], $this->scheduleInput('mon_to_friday'), $this->scheduleInput('saturday'));

        $this->redirect('/stores');
    }

    /** Schedule fields store NULL (closed) instead of an empty string. */
    private function scheduleInput(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));
        return $value === '' ? null : $value;
    }

    public function destroy(array $params = []): void
    {
        Store::delete((int) $params['id']);
        $this->redirect('/stores');
    }
}
