<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Review;
use App\Models\User;
use App\Models\House;
use App\Models\HouseView;
use Illuminate\Http\Request;

class HouseController extends Controller
{
    public function getHouses(Request $request)
    {
        // Get user from remember_token
        $user = $this->getUserByRequest($request);
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

    public function updateHouseViews(Request $request)
    {
        $houseId = $request->house_id;

        $houseView = HouseView::firstOrNew(['house_id' => $houseId]);
        $houseView->counts = $houseView->exists ? $houseView->counts + 1 : 1;
        $houseView->save();

        return response()->json([
            'error' => false,
            'message' => 'House views updated successfully.',
        ], 200);
    }

    public function addFavoriteHouse(Request $request)
    {

        $houseId = $request->house_id;

        $user = $this->getUserByRequest($request);

        $favorite = Favorite::firstOrCreate([
            'user_id' => $user->id,
            'house_id' => $houseId
        ]);

        return response()->json([
            'error' => false,
            'message' => 'House added to favorites successfully.',
            'data' => $favorite
        ], 200);
    }

    public function deleteFavorite($id)
    {
        $favorite = Favorite::find($id);

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'error' => false,
                'message' => 'Favorite removed successfully.',
            ], 200);
        }

        return response()->json([
            'error' => true,
            'message' => 'Favorite not found.',
        ], 404);
    }

    public function recordReview(Request $request)
    {
        $user = $this->getUserByRequest($request);
        $houseId = $request->house_id;
        $message = $request->message;
        $rating = $request->rating;

        $review = Review::updateOrCreate(
            ['user_id' => $user->id, 'house_id' => $houseId],
            ['message' => $message, 'rating' => $rating]
        );

        return response()->json([
            'error' => false,
            'message' => 'Review recorded successfully.',
            'data' => $review,
        ]);
    }
}