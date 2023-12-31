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
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

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

            // Commit transaction
            DB::commit();
            // Return as a JSON response
            return response()->json($response, 200);


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
            $locationName = $this->getLocationName($request->latitude, $request->longitude);
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
            $user->location_name = $locationName;
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
            return response()->json(['error' => false, 'message' => 'User registered successfully'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            // Handle the exception
            return response()->json(['error' => true, 'message' => 'Registration failed. Please check your details and try again. ' . $e->getMessage()], 200);
        }
    }

    public function updateUser(Request $request)
    {
        $user = $this->getUserByRequest($request);

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string',
            'residential_county' => 'sometimes|string',
            'actively_searching' => 'sometimes|boolean'
        ]);

        $user->update($validatedData);

        return response()->json([
            'error' => false,
            'message' => 'User updated successfully.',
        ]);
    }

    public function updateUserProfileImage(Request $request)
    {
        // Authenticate and retrieve user
        $user = $this->getUserByRequest($request);
        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User not found.',
            ], 404);
        }

        // Validate the incoming request
        $request->validate([
            'profile_image' => 'required|image|max:2048', // 2MB Max
        ]);

        try {
            // Handle and delete the old image if it exists
            if ($user->profile_image) {
                $this->deleteImageFromStorage($user->profile_image);
            }

            // Get the image file from the request
            $imageFile = $request->file('profile_image');

            // Define the path and filename
            $filename = 'profile_image-' . $user->id . '-' . sha1(time()) . '.' . $imageFile->getClientOriginalExtension();
            $path = 'profile_images/' . $filename;

            // Store the new image
            $imageFile->storeAs('public', $path);

            // Generate the URL for the stored image
            $url = asset('storage/' . $path);

            // Update user's profile image with the new path
            $user->profile_image = $url;
            $user->save();

            return response()->json([
                'error' => false,
                'message' => 'Profile image updated successfully.',
                'user' => $user
            ]);
        } catch (Exception $e) {
            // Handle any exceptions during the process
            return response()->json([
                'error' => true,
                'message' => 'Failed to update profile image. ' . $e->getMessage(),
            ], 200);
        }
    }



    public function updateUserLocation(Request $request)
    {
        $user = $this->getUserByRequest($request);

        $validatedData = $request->validate([
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric'
        ]);

        $user->update($validatedData);

        return response()->json([
            'error' => false,
            'message' => 'Location updated successfully.',
        ]);
    }

    public function updateUserPreferences(Request $request)
    {
        $user = $this->getUserByRequest($request);

        $validatedData = $request->validate([
            'id' => 'required|exists:user_preferences,id',
            'user_id' => 'required|exists:users,id',
            'county' => 'required|string',
            'min_rent' => 'required|numeric',
            'max_rent' => 'required|numeric',
            'house_category' => 'required|string'
        ]);

        $preferences = UserPreference::findOrFail($validatedData['id']);

        if ($preferences->user_id != $user->id) {
            return response()->json([
                'error' => true,
                'message' => 'You do not have permission to update these preferences.',
            ], 403); // Forbidden
        }

        $preferences->update($validatedData);

        return response()->json([
            'error' => false,
            'message' => 'User preferences updated successfully.',
        ]);
    }


    function deleteImageFromStorage($url)
    {
        // Parse the URL to get the path after '/storage/'
        $path = parse_url($url, PHP_URL_PATH); // gets the path part of the URL
        $storagePath = 'public/' . explode('/storage/', $path)[1]; // adjust the path to match the storage directory

        // Check if the file exists and delete it
        if (Storage::exists($storagePath)) {
            if (!Storage::delete($storagePath)) {
                // If the deletion is unsuccessful, throw an exception
                throw new Exception("Failed to delete the image.");
            }
            // If everything goes well, return nothing
        } else {
            // If the file doesn't exist, throw an exception
            throw new Exception("File does not exist." . $storagePath);
        }
    }


}