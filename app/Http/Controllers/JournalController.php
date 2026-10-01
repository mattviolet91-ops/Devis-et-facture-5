<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(): View
    {
        return view('journal', [
            'activites' => Activite::with('user')->latest('created_at')->latest('id')->paginate(30),
        ]);
    }
}
