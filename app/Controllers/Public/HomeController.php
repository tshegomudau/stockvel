<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    public function landing(Request $request): Response
    {
        // Keep the marketing home visible even when signed in; Sign in → dashboard.
        return $this->render('public/home', [
            'title' => 'stockvel — Stokvel management',
            'activeNav' => 'home',
            'user' => $this->user(),
        ], 'layouts/guest');
    }
}
