<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\House;
use Illuminate\Http\Request;

class HouseController extends Controller
{
    public function getHouses(Request $request)
    {
        // Get user from remember_token
        $user = User::where('remember_token', $request->header('Authorization'))->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Fetch user preferences
        $preferences = $user->userPreferences;

        // Query houses based on user preferences (example: filtering by county and rent range)
        $houses = House::whereHas('building.estate')
            ->where(function ($query) use ($preferences) {
                $query->where('vacancies', '>', 0)->whereBetween('rent', [$preferences->min_rent, $preferences->max_rent])
                    ->orWhere('category', $preferences->house_category);
            })
            ->with(['building.estate.manager', 'facilities', 'houseViews', 'gallery', 'reviews.user'])
            ->inRandomOrder()
            ->take(6)
            ->get();

        // Transform the house data
        $houses = $houses->map(function ($house) {
            $house->reviews = $house->reviews->map(function ($review) {
                return [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'profile_picture' => $review->user->profile_image
                ];
            });
            return $house;
        });
        return response()->json(['error' => false, 'message' => 'data found', 'houses' => $houses], 200);
    }

    public function getRandomHouses(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('perPage', 10);

        // Query houses randomly and paginate the results
        $houses = House::where('vacancies', '>', 0)
            ->with(['building.estate.manager', 'facilities', 'houseViews', 'gallery', 'reviews.user'])
            ->inRandomOrder()
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform the house data (optional)
        $housesCollection = $houses->getCollection()->transform(function ($house) {
            $house->reviews = $house->reviews->map(function ($review) {
                return [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'profile_picture' => $review->user->profile_image
                ];
            });
            return $house;
        })->toArray();

        return response()->json(
            [
                'error' => false,
                'message' => 'Houses found',
                'houses' => $housesCollection,
                'pagination' => [
                    'total' => $houses->total(),
                    'currentPage' => $houses->currentPage(),
                    'perPage' => $houses->perPage(),
                    'lastPage' => $houses->lastPage()
                ]
            ],
            200
        );
    }
    //
}