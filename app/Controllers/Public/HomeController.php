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
        return $this->render('public/home', [
            'title' => 'stockvel — Stokvel management',
            'activeNav' => 'home',
        ], 'layouts/guest');
    }
}
