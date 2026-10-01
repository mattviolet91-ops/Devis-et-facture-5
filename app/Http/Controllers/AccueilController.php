<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccueilController extends Controller
{
    public function index(Request $request): View
    {
        return view('accueil', [
            'alertes' => $request->user()->unreadNotifications()->latest()->limit(10)->get(),
        ]);
    }

    public function lireAlerte(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return redirect()->route('accueil');
    }
}
