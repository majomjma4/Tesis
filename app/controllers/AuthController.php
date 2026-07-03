<?php

declare(strict_types=1);

final class AuthController
{
    public function login(): void
    {
        $auth = new AuthModel();

        View::render('auth/login', [
            'title' => 'Iniciar sesion | Gestion Documental Academica',
            'bodyClass' => 'login-page',
            'pageScript' => asset('js/login.js'),
            'roles' => $auth->getAllowedRoles(),
        ], 'auth');
    }
}
