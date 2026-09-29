<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Setting;
use App\Services\PhoneNumber;

/** Ajustes globales (solo admin). Hoy: numero al que se transfieren las llamadas. */
class SettingsController extends Controller
{
    public function index(array $params = []): void
    {
        $this->renderPage(['saved' => $this->input('saved') === '1']);
    }

    public function updateHandoff(array $params = []): void
    {
        if (!$this->csrfValid()) {
            http_response_code(403);
            $this->renderPage(['error' => 'Sesión caducada. Recarga la página e inténtalo de nuevo.']);
            return;
        }

        [$number, $error] = PhoneNumber::validateHandoff((string) $this->input('handoff_phone_number', ''));
        if ($error !== null) {
            http_response_code(422);
            $this->renderPage(['error' => $error, 'attempted' => (string) $this->input('handoff_phone_number', '')]);
            return;
        }

        Setting::setAudited(Setting::HANDOFF_KEY, $number, Auth::id());
        $this->redirect('/settings?saved=1');
    }

    private function renderPage(array $extra = []): void
    {
        [$number, $source] = Setting::handoffNumber();
        $this->render('settings/index.php', $extra + [
            'csrf'   => $this->csrfToken(),
            'number' => $number,
            'source' => $source,
            'row'    => Setting::row(Setting::HANDOFF_KEY),
        ]);
    }
}
