<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use App\Models\Feedback;
use App\Models\Notification;
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

            if (!$user) {
                $response = [
                    'error' => true,
                    'message' => "There's no account registered to this email."
                ];
            } else {
                if ($user->status == 2) {
                    return response()->json(['error' => true, 'message' => 'This account was deleted. To recover your account contact our support team at info@banit.co.ke'], 200);
                }

                if ($user->status == 1) {
                    return response()->json(['error' => true, 'message' => 'This account was suspended due to breaching of our policies. To recover your account contact our support team at info@banit.co.ke'], 200);
                }

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
                'message' => 'Something went wrong. Please try again.'
            ];

            // Return as a JSON response
            return response()->json($response, 500);
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
        try {
            $user = $this->getUserByRequest($request);
            if (!$user) {
                return response()->json(
                    [
                        'error' => true,
                        'message' => 'Not authorized to perform this action.',
                    ],
                    401
                );
            }

            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'phone' => 'sometimes|string',
                'residential_county' => 'sometimes|string',
                'actively_searching' => 'sometimes|integer'
            ]);

            $user->update($validatedData);

            return response()->json([
                'error' => false,
                'message' => 'User updated successfully.',
                'user' => $user
            ]);
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => true,
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function updateUserProfileImage(Request $request)
    {
        // Authenticate and retrieve user
        $user = $this->getUserByRequest($request);
        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'Not authorized to perform this action.',
            ], 401);
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
        try {
            $user = $this->getUserByRequest($request);

            if (!$user) {
                return response()->json(
                    [
                        'error' => true,
                        'message' => 'Not authorized to perform this action.',
                    ],
                    401
                );
            }

            $validatedData = $request->validate(
                [
                    'longitude' => 'required|numeric',
                    'latitude' => 'required|numeric'
                ]
            );

            $locationName = $this->getLocationName($validatedData['latitude'], $validatedData['longitude']);

            $user->latitude = $validatedData['latitude'];
            $user->longitude = $validatedData['longitude'];
            $user->location_name = $locationName;

            $user->save();

            return response()->json(
                [
                    'error' => false,
                    'message' => 'Location updated successfully.',
                    'user' => $user
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => true,
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    public function updateUserPreferences(Request $request)
    {
        try {

            $user = $this->getUserByRequest($request);
            if (!$user) {
                return response()->json(
                    [
                        'error' => true,
                        'message' => 'Not authorized to perform this action.',
                    ],
                    401
                );
            }


            $validatedData = $request->validate(
                [
                    'id' => 'required|exists:user_preferences,id',
                    'county' => 'required|string',
                    'min_rent' => 'required|numeric',
                    'max_rent' => 'required|numeric',
                    'house_category' => 'required|string'
                ]
            );

            $preferences = UserPreference::findOrFail($validatedData['id']);

            if ($preferences->user_id != $user->id) {
                return response()->json(
                    [
                        'error' => true,
                        'message' => 'Not authorized to perform this action.',
                    ],
                    401
                );
            }

            $preferences->update($validatedData);

            $user = User::find($user->id);

            return response()->json(
                [
                    'error' => false,
                    'message' => 'User preferences updated successfully.',
                    'user' => $user,
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => true,
                    'message' => $e->getMessage(),
                ]
            );
        }
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


    public function getUserNotifications(Request $request)
    {
        // Get user from remember_token
        $user = $this->getUserByRequest($request);

        // Ensure the user exists and has favorites
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
        }
        // Set a default value for perPage, but allow it to be overridden by the request
        $page = $request->get('page', 1);
        $perPage = $request->get('perPage', 10);

        // Get the distinct dates with notifications for the user or for all users, and paginate them
        $distinctDates = Notification::whereIn('recipient', [$user->id, 'all'])
            ->select(DB::raw('DATE(created_at) as notification_date'))
            ->distinct()
            ->orderBy('notification_date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // For each paginated date, fetch the notifications
        $notificationsGroupedByDate = [];
        foreach ($distinctDates as $date) {
            $notificationsForDate = Notification::whereIn('recipient', [$user->id, 'all'])
                ->whereDate('created_at', $date->notification_date)
                ->get();

            $notificationsGroupedByDate[] = [
                'date' => $date->notification_date,
                'notifications' => $notificationsForDate
            ];
        }

        // Return the appropriate response
        if (count($notificationsGroupedByDate) > 0) {
            return response()->json(
                [
                    'error' => false,
                    'message' => "Notifications found",
                    'notification_group' => $notificationsGroupedByDate,
                    'pagination' => [
                        'total' => $distinctDates->total(),
                        'currentPage' => $distinctDates->currentPage(),
                        'perPage' => $distinctDates->perPage(),
                        'lastPage' => $distinctDates->lastPage()
                    ]
                ],
                200
            );
        } else {
            return response()->json(
                [
                    'error' => true,
                    'message' => "No notifications found"
                ],
                200
            );
        }
    }


    public function sendFeedback(Request $request)
    {
        try {
            // Get user from remember_token
            $user = $this->getUserByRequest($request);

            // Ensure the user exists and has favorites
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
            }

            $customerID = $user->id;
            $feedback = $request->feedback;
            $category = $request->category;

            Feedback::create(
                [
                    'user_id' => $customerID,
                    'feedback' => $feedback,
                    'category' => $category
                ]
            );

            // Prepare error response
            $response = [
                'error' => false,
                'message' => "Thank you. We have received your feedback. We value your feedback."
            ];

            // Return as a JSON response
            return response()->json($response, 200);

        } catch (Exception $e) {
            // Prepare error response
            $response = [
                'error' => true,
                'message' => "Failed to send feedback. Please try again."
            ];

            // Return as a JSON response
            return response()->json($response, 200);
        }
    }

    public function deleteUser($userId)
    {
        // Get user from remember_token
        $user = User::find($userId);

        // Ensure the user exists and has favorites
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
        }

        $user->status = 2;
        $user->save();

        // Prepare error response
        $response = [
            'error' => false,
            'message' => "Your account is deleted. You can only recover your account and data within 3 months from now."
        ];

        // Return as a JSON response
        return response()->json($response, 200);
    }
}