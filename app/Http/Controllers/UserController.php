<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use App\Models\User;
use App\Models\UserPreference;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function sendOTP(Request $request)
    {
        try {
            // Begin transaction
            DB::beginTransaction();

            $email = $request->email;

            $user = User::where('email', $email)->first();

            if ($user == null) {
                $response = [
                    'error' => true,
                    'message' => "There's no account registered to this email."
                ];
            } else {
                $otp = $this->generateOTP();
                $encryptedOTP = Hash::make($otp);
                $user->otp = $encryptedOTP;
                $user->save();

                Mail::to($user->email)->send(new OTPMail($otp));

                $response = [
                    'error' => false,
                    'message' => "We've sent your new security code to your email. Please check your inbox."
                ];
            }

            DB::commit();
            // Return as a JSON response
            return response()->json($response, 200);
            // Commit transaction

        } catch (Exception $e) {
            DB::rollback();
            // Prepare error response
            $response = [
                'error' => true,
                'message' => $e->getMessage()
            ];

            // Return as a JSON response
            return response()->json($response, 200);
        }
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)
            ->with('userPreferences')
            ->first();

        if (!Hash::check($request->otp, $user->otp)) {
            return response()->json(['error' => true, 'message' => 'Login Failed! Incorrect OTP. Check and try again.'], 200);
        }

        $authKey = md5($this->generateUID());
        $user->remember_token = $authKey;

        $otp = $this->generateOTP();
        $encryptedOTP = Hash::make($otp);
        $user->otp = $encryptedOTP;

        $user->save();

        return response()->json(['error' => false, 'message' => 'Login Successful', 'user' => $user], 200);
    }

    public function register(Request $request)
    {
        try {
            DB::beginTransaction();

            // Create the user
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->residential_county = $request->residential_county;
            $user->longitude = $request->longitude;
            $user->latitude = $request->latitude;
            $user->actively_searching = $request->actively_searching;
            $user->save();

            // Create the user preferences
            $userPreference = new UserPreference([
                'user_id' => $user->id,
                'county' => $request->county,
                'min_rent' => $request->min_rent,
                'max_rent' => $request->max_rent,
                'house_category' => $request->house_category
            ]);
            $userPreference->save();

            DB::commit();

            // Return a response
            return response()->json(['error' => false, 'message' => 'User registered successfully'], 201);
        } catch (Exception $e) {
            DB::rollBack();
            // Handle the exception
            return response()->json(['error' => true, 'message' => 'Registration failed. Please check your details and try again. ' . $e->getMessage()], 500);
        }
    }

}