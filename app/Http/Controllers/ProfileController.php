<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'siswa' => $request->user()->siswa,
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $data = $request->validated();
            $user->fill(collect($data)->only(['name', 'email'])->all());
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
                $user->otpVerifications()->whereNull('used_at')->update(['used_at' => now()]);
            }
            $user->save();
            $user->siswa?->update(['nama_lengkap' => $user->name, ...collect($data)->only(['nis', 'nisn'])->all()]);
            $user->guru?->update(['nama_lengkap' => $user->name]);
        });

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->guru()->exists()) {
            throw ValidationException::withMessages([
                'password' => 'Akun guru terkait data akademik. Nonaktifkan akun melalui pengelola sekolah.',
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
