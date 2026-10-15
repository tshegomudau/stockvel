<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RegisterStokvelController extends Controller
{
    public function start(Request $request): Response
    {
        if ($this->userId() === null) {
            Session::flash('intended_url', '/register-stokvel');
            Session::flash('info', 'Sign in or create an account to register your stokvel.');
            return $this->redirect('/register');
        }

        Session::forget('onboarding_step');
        return $this->redirect('/app/onboarding');
    }
}
