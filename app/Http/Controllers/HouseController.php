<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\User;
use App\Models\House;
use App\Models\HouseView;
use Exception;
use Illuminate\Http\Request;

class HouseController extends Controller
{
    public function getHouses(Request $request)
    {
        // Get user from remember_token
        $user = $this->getUserByRequest($request);

        // Fetch user preferences
        $preferences = $user->userPreferences;

        // Query houses based on user preferences and building status
        $houses = House::whereHas('building', function ($query) {
            $query->where('status', 1); // Only include buildings with status == 1
        })
            ->whereHas('building.estate')
            ->where(function ($query) use ($preferences) {
                $query->where('vacancies', '>', 0)
                    ->where(function ($q) use ($preferences) {
                        $q->whereBetween('rent', [$preferences->min_rent, $preferences->max_rent])
                            ->orWhere('category', $preferences->house_category);
                    });
            })
            ->with(['building.estate.manager', 'facilities', 'houseViews', 'gallery', 'reviews.user'])
            ->inRandomOrder()
            ->take(6)
            ->get();

        // Transform the house data
        $houses = $houses->map(function ($house) use ($user) {
            $building = $house->building ?? null;
            $estate = $building ? $building->estate ?? null : null;
            return $this->formatHouseData($house, $building, $estate, $user);
        });

        return response()->json(['error' => false, 'message' => 'Data found', 'houses' => $houses], 200);
    }


    public function getRandomHouses(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('perPage', 10);

        // Get user from remember_token (or use auth middleware to ensure user is authenticated)
        $user = $this->getUserByRequest($request);

        // Query houses randomly and paginate the results
        $houses = House::where('vacancies', '>', 0)
            ->whereHas('building', function ($query) {
                $query->where('status', 1); // Only include buildings with status == 1
            })
            ->with(['building.estate.manager', 'facilities', 'houseViews', 'gallery', 'reviews.user'])
            ->inRandomOrder()
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform the house data
        $housesCollection = $houses->getCollection()->map(function ($house) use ($user) {
            $building = $house->building ?? null;
            $estate = $building ? $building->estate ?? null : null;
            return $this->formatHouseData($house, $building, $estate, $user);
        });

        // Replace the original collection with the transformed collection
        $houses->setCollection($housesCollection);

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


    public function searchByLocation(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'longitude' => 'required|numeric',
                'latitude' => 'required|numeric',
            ]);

            $earthRadius = 6371; // Radius of the earth in kilometers.
            $radius = 5; // Radius of the search in kilometers.

            // Get user from remember_token
            $user = $this->getUserByRequest($request);

            // Fetch all buildings first (consider limiting this query to a reasonable bounding box if possible)
            $buildings = Building::where('status', 1)->get();

            // Filter the buildings using PHP to calculate the distance
            $nearbyBuildings = $buildings->filter(function ($building) use ($validatedData, $earthRadius, $radius) {
                $latFrom = deg2rad($validatedData['latitude']);
                $lonFrom = deg2rad($validatedData['longitude']);
                $latTo = deg2rad($building->latitude);
                $lonTo = deg2rad($building->longitude);

                $latDelta = $latTo - $latFrom;
                $lonDelta = $lonTo - $lonFrom;

                $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
                $distance = $angle * $earthRadius;

                //echo $building->latitude . ' : ' . $building->longitude . ' => ' . $distance . ', ';
                return $distance < $radius;
            });

            // Get the house ids related to the nearby buildings
            $houseIds = $nearbyBuildings->pluck('id');

            // Now get the houses related to these buildings
            $houses = House::whereIn('building_id', $houseIds)
                ->with(['building.estate.manager', 'facilities', 'houseViews', 'gallery', 'reviews.user'])
                ->get();

            $formattedHouses = $houses->map(function ($house) use ($user) {
                // Assuming formatHouseData is defined to format the house data correctly
                return $this->formatHouseData($house, $house->building, $house->building->estate, $user);
            });

            return response()->json([
                'error' => false,
                'message' => 'Houses found near the specified location.',
                'data' => $formattedHouses
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }


    public function searchByKeyword(Request $request)
    {
        try {
            // Validate the request data
            $keyword = $request->validate([
                'keyword' => 'required|string',
            ])['keyword'];

            $user = $this->getUserByRequest($request);

            // Search in houses, estates, and buildings
            $houses = House::whereHas('building', function ($query) use ($keyword) {
                $query->where('status', 1) // Only include buildings with status == 1
                    ->where(function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%")
                            ->orWhere('description', 'LIKE', "%{$keyword}%");
                    });
            })
                ->orWhereHas('building.estate', function ($query) use ($keyword) {
                    $query->whereHas('building', function ($q) {
                        $q->where('status', 1); // Only include estates of buildings with status == 1
                    })->where(function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%")
                            ->orWhere('description', 'LIKE', "%{$keyword}%");
                    });
                })
                ->orWhereHas('facilities', function ($query) use ($keyword) {
                    $query->where('name', 'LIKE', "%{$keyword}%");
                })
                ->with(['building.estate.manager', 'facilities', 'houseViews', 'gallery', 'reviews.user'])
                ->get();

            $formattedHouses = $houses->map(function ($house) use ($user) {
                // Extract building and estate from the house
                $building = $house->building;
                $estate = $building ? $building->estate : null;

                return $this->formatHouseData($house, $building, $estate, $user);
            });

            return response()->json([
                'error' => false,
                'message' => 'Houses found matching the keyword.',
                'data' => $formattedHouses
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
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