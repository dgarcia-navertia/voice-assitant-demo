<?php

namespace App\Controllers;

use App\Models\Call;
use App\Models\Transcript;

class CallController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('calls/index.php', ['calls' => Call::recent(100)]);
    }

    public function show(array $params = []): void
    {
        $sid  = (string) ($params['sid'] ?? '');
        $call = Call::findBySid($sid);
        $turns = Transcript::forSid($sid);
        if (!$call && !$turns) {
            $this->notFound();
            return;
        }
        $this->render('calls/show.php', ['call' => $call, 'sid' => $sid, 'turns' => $turns]);
    }
}
