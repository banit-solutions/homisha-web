<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use App\Models\User;
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
            return response()->json(['error' => true, 'message' => "Login Failed! Incorrect OTP. Check and try again."], 200);
        }

        $user->remember_token = md5($this->generateUID());
        $user->save();

        return response()->json(['error' => false, 'message' => "Login Successful", 'user' => $user], 200);
    }
}