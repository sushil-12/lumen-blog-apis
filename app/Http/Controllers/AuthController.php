<?php

/**
 * @author Sushil
 * @date 2024-01-09
 */

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Traits\ResponseTrait;

class AuthController extends Controller
{
    use ResponseTrait;

    /**
     * Register a new user and generate JWT token.
     */
    public function register(Request $request)
    {
        try {
            // Validate input data
            $this->validate($request, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6|confirmed',
            ]);

            // Create user with hashed password
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, 
            ]);

            // Generate token for the new user
            $token = JWTAuth::fromUser($user);

            return $this->successResponse([
                'token' => $token,
                'user' => $user,
            ], 'User registered successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error occurred: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Login user and generate JWT token with security measures.
     */
    public function login(Request $request)
    {
        try {
            // Validate input data
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            // Correct authentication attempt (DO NOT HASH PASSWORD)
            if (!$token = JWTAuth::attempt($request->only(['email', 'password']))) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            // Update last login time
            $user = auth()->user();
            $user->last_login_at = Carbon::now();;
            $user->save();

            return $this->successResponse([
                'token' => $token,
                'refresh_token' => JWTAuth::fromUser($user),
                'user' => $user,
                'last_login_at' => $user->last_login_at,
            ], 'User logged in successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Error occurred: ' . $e->getMessage(), 500);
        }
    }


    /**
     * Logout user and invalidate token.
     */
    public function logout(Request $request)
    {
        try {
            $token = JWTAuth::getToken();
            if ($token) {
                JWTAuth::invalidate($token); // Blacklist token
            }

            return $this->successResponse([], 'Successfully logged out');
        } catch (\Exception $e) {
            return $this->errorResponse('Error occurred: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get authenticated user info.
     */
    public function me()
    {
        return $this->successResponse(Auth::user(), 'Authenticated user retrieved');
    }

    /**
     * Refresh token.
     */
    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh();
            return $this->successResponse(['token' => $newToken], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Could not refresh token', 401);
        }
    }
}