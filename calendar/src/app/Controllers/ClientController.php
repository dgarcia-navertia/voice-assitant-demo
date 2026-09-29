<?php

namespace App\Controllers;

use App\Models\Client;
use App\Services\PhoneNumber;

class ClientController extends Controller
{
    public function index(array $params = []): void
    {
        $q = trim((string) $this->input('q', ''));
        $clients = $q === ''
            ? Client::query('SELECT * FROM clients ORDER BY id DESC LIMIT 200')
            : Client::query(
                'SELECT * FROM clients WHERE client_name LIKE ? OR client_phone LIKE ? OR client_email LIKE ? ORDER BY id DESC LIMIT 200',
                ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']
            );
        $this->render('clients/index.php', ['clients' => $clients, 'q' => $q, 'errors' => [], 'old' => []]);
    }

    public function store(array $params = []): void
    {
        $name  = trim((string) $this->input('client_name', ''));
        $phone = PhoneNumber::clean((string) $this->input('client_phone', ''));
        $email = trim((string) $this->input('client_email', ''));
        $type  = (string) $this->input('client_type', 'particular');

        $errors = [];
        if ($name === '') { $errors['client_name'] = 'El nombre es obligatorio.'; }
        if (!PhoneNumber::isE164($phone)) { $errors['client_phone'] = 'Teléfono en formato internacional (+34612345678).'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['client_email'] = 'Correo no válido.'; }
        if (!in_array($type, Client::CLIENT_TYPES, true)) { $type = 'particular'; }

        if ($errors) {
            http_response_code(422);
            $this->render('clients/index.php', [
                'clients' => Client::query('SELECT * FROM clients ORDER BY id DESC LIMIT 200'),
                'q' => '', 'errors' => $errors,
                'old' => ['client_name' => $name, 'client_phone' => $phone, 'client_email' => $email, 'client_type' => $type],
            ]);
            return;
        }

        Client::createClient($name, $phone, $type, $email === '' ? null : $email);
        $this->redirect('/clients');
    }
}
