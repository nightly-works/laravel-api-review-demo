<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Mobile app sign-in: returns a token with full access.
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required', 'device' => 'required|string|max:100']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return ['token' => $user->createToken($data['device'])->plainTextToken];
    }

    // Read-only tokens for integrations (calendar sync, assistants), created from the settings screen.
    public function createReadOnlyToken(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);

        return ['token' => $request->user()->createToken($data['name'], ['read'])->plainTextToken];
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => 'required|min:12|confirmed']);
        $user = $request->user();
        $user->update(['password' => Hash::make($data['password'])]);

        // A changed password is often a reaction to a leak: every other token stops working.
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }
}
