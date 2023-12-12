<?php

namespace App\Http\Controllers;

use App\Models\Manager;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function getRankedManagers(Request $request)
    {
        $managers = Manager::with(['estates.buildings.houses' => function ($query) {
            $query->with(['facilities', 'houseViews', 'gallery', 'reviews.user']);
        }])
            ->get()
            ->map(function ($manager) {
                return $this->formatManagerData($manager);
            })
            ->sortByDesc(function ($manager) {
                return $manager['average_ratings'];
            })
            ->values()
            ->take(6);

        return response()->json([
            'error' => false,
            'message' => 'Managers ranked by average ratings',
            'managers' => $managers
        ], 200);
    }

    public function getPaginatedManagers(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('perPage', 10);

        $paginatedManagers = Manager::with(['estates.buildings.houses' => function ($query) {
            $query->with(['facilities', 'houseViews', 'gallery', 'reviews.user']);
        }])->paginate($perPage, ['*'], 'page', $page);

        $managers = collect($paginatedManagers->items())->map(function ($manager) {
            return $this->formatManagerData($manager);
        });

        return response()->json([
            'error' => false,
            'message' => 'Paginated managers list',
            'managers' => $managers,
            'pagination' => [
                'total' => $paginatedManagers->total(),
                'currentPage' => $paginatedManagers->currentPage(),
                'perPage' => $paginatedManagers->perPage(),
                'lastPage' => $paginatedManagers->lastPage(),
                'nextPageUrl' => $paginatedManagers->nextPageUrl(),
                'prevPageUrl' => $paginatedManagers->previousPageUrl(),
            ]
        ], 200);
    }

    private function formatManagerData($manager)
    {
        $housesData = collect();
        $totalRatings = 0;
        $totalReviewsCount = 0;
        $activeHousesCount = 0;

        foreach ($manager->estates as $estate) {
            foreach ($estate->buildings as $building) {
                foreach ($building->houses as $house) {
                    $housesData->push($this->formatHouseData($house, $building, $estate));

                    $houseRatings = $house->reviews->avg('rating'); // Assuming 'rating' column exists in reviews
                    $totalRatings += $houseRatings;
                    $totalReviewsCount += $house->reviews->count();
                    $activeHousesCount += ($house->vacancies > 0) ? 1 : 0;
                }
            }
        }

        $averageRatings = $totalReviewsCount > 0 ? $totalRatings / $totalReviewsCount : 0;

        return [
            'id' => $manager->id,
            'name' => $manager->name,
            'email' => $manager->email,
            'phone' => $manager->phone,
            'county' => $manager->county,
            'profile_image' => $manager->profile_image,
            'average_ratings' => $averageRatings,
            'total_reviews' => $totalReviewsCount,
            'active_houses' => $activeHousesCount,
            'houses' => $housesData
        ];
    }

    private function formatHouseData($house, $building, $estate)
    {
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
            'created_at' => $house->created_at,
            'updated_at' => $house->updated_at,
            'reviews' => $house->reviews->map(function ($review) {
                return [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'profile_picture' => $review->user->profile_image
                ];
            }),
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

}