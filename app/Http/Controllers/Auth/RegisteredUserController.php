<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', ['initialRole' => $request->query('role') === 'guru' ? 'guru' : 'siswa']);
    }
}
