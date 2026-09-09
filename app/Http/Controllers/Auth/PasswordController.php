<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(['password' => Hash::make($validated['password']), 'remember_token' => Str::random(60)]);
            $user->otpVerifications()->whereNull('used_at')->update(['used_at' => now()]);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            }
        });

        return back();
    }
}
