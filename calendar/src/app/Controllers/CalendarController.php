<?php

namespace App\Controllers;

/**
 * The weekly calendar is now a view mode of the Agenda page. This route is kept
 * so old links/bookmarks still work.
 */
class CalendarController extends Controller
{
    public function index(array $params = []): void
    {
        $week = $this->input('week');
        $query = is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)
            ? '&week=' . $week
            : '';
        $this->redirect('/dashboard?view=calendar' . $query);
    }
}
