<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    function generateOTP()
    {
        return rand(10000, 99999);
    }

    public static function generateUID()
    {
        $randomNumber = random_int(100, 1000);
        $id = uniqid($randomNumber);
        return $id;
    }

    public function getUserByRequest($request)
    {
        return User::where('remember_token', $request->header('Authorization'))->first();
    }

    public function formatHouseData($house, $building, $estate, $user = null)
    {
        $isFavorite = $user === null ? false : $user->favorites()->where('house_id', $house->id)->exists();
        $averageReview = $house->reviews->avg('ratings');

        return [
            'id' => $house->id,
            'building_id' => $house->building_id,
            'category' => $house->category,
            'rent' => $house->rent,
            'bedrooms' => $house->bedrooms,
            'kitchens' => $house->kitchens,
            'bathrooms' => $house->bathrooms,
            'balconies' => $house->balconies,
            'total_rooms' => $house->total_rooms,
            'vacancies' => $house->vacancies,
            'description' => $house->description,
            'is_favorite' => $isFavorite,
            'created_at' => $house->created_at,
            'updated_at' => $house->updated_at,
            'reviews' => $house->reviews,
            'average_review' => $averageReview == null ? 0 : $averageReview,
            'facilities' => $house->facilities,
            'house_views' => $house->houseViews,
            'gallery' => $house->gallery,
            'building' => [
                'id' => $building->id,
                'estate_id' => $building->estate_id,
                'name' => $building->name,
                'profile_image' => $building->profile_image,
                'occupation_certificate' => $building->occupation_certificate,
                'longitude' => $building->longitude,
                'latitude' => $building->latitude,
                'description' => $building->description,
                'status' => $building->status,
                'created_at' => $building->created_at,
                'updated_at' => $building->updated_at,
                'estate' => [
                    'id' => $estate->id,
                    'manager_id' => $estate->manager_id,
                    'name' => $estate->name,
                    'description' => $estate->description,
                    'created_at' => $estate->created_at,
                    'updated_at' => $estate->updated_at,
                    'manager' => $estate->manager
                ]
            ]
        ];
    }

    function getLocationName($lat, $lon)
    {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon";

        // Initialize CURL:
        $ch = curl_init($url);

        // Set headers to mimic a browser request
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3', // Example Chrome User-Agent
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Get the JSON response:
        $json = curl_exec($ch);
        curl_close($ch);

        // Decode the JSON response
        $data = json_decode($json);


        // Check if the response is valid and return the address part
        if (isset($data->display_name)) {
            return $data->display_name;
        } else {
            return "No Location Name";
        }
    }


}