<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\Manager;
use Exception;
use Illuminate\Http\Request;

class ManagerController extends Controller
{
    public function getRankedManagers(Request $request)
    {
        $user = $this->getUserByRequest($request);

        // Ensure the user exists and has favorites
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'You need to be logged in to view houses. Please log in and try again.'], 401);
        }

        $managers = Manager::with([
            'estates.buildings' => function ($query) {
                $query->where('status', 1);
            },
            'estates.buildings.houses' => function ($query) {
                $query->with(['facilities', 'houseViews', 'gallery', 'reviews']);
            }
        ])
            ->get()
            ->map(function ($manager) use ($user) {
                return $this->formatManagerData($manager, $user);
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

        $user = $this->getUserByRequest($request);
        // Ensure the user exists and has favorites
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'You need to be logged in to view houses. Please log in and try again.'], 401);
        }

        // Include only buildings with status == 1 in the nested relation
        $paginatedManagers = Manager::with([
            'estates.buildings' => function ($query) {
                $query->where('status', 1);
            },
            'estates.buildings.houses' => function ($query) {
                $query->with(['facilities', 'houseViews', 'gallery', 'reviews']);
            }
        ])
            ->paginate($perPage, ['*'], 'page', $page);

        $managers = collect($paginatedManagers->items())->map(function ($manager) use ($user) {
            return $this->formatManagerData($manager, $user);
        });

        return response()->json([
            'error' => false,
            'message' => 'Paginated managers list',
            'managers' => $managers,
            'pagination' => [
                'total' => $paginatedManagers->total(),
                'currentPage' => $paginatedManagers->currentPage(),
                'perPage' => $paginatedManagers->perPage(),
                'lastPage' => $paginatedManagers->lastPage()
            ]
        ], 200);
    }


    private function formatManagerData($manager, $user)
    {
        $housesData = collect();
        $totalRatings = 0;
        $totalReviewsCount = 0;
        $activeHousesCount = 0;

        foreach ($manager->estates as $estate) {
            foreach ($estate->buildings as $building) {
                foreach ($building->houses as $house) {
                    $housesData->push($this->formatHouseData($house, $building, $estate, $user));

                    $houseRatings = $house->reviews->avg('ratings');
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

    public function storeEnquiry(Request $request)
    {
        try {
            // Validate the request data
            $validated = $request->validate([
                'manager_id' => 'required|exists:managers,id',
                'house_id' => 'required|exists:houses,id',
                'title' => 'required|string|max:255',
                'message' => 'required|string'
            ]);

            // Get user from remember_token
            $user = $this->getUserByRequest($request);

            // Ensure the user exists and has favorites
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized access'], 401);
            }

            $validated['user_id'] = $user->id;

            // Create the enquiry
            $enquiry = new Enquiry($validated);
            $enquiry->save();

            return response()->json([
                'error' => false,
                'message' => 'Enquiry submitted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => "Something went wrong. Please try again."
            ], 500);
        }
    }

    public function storeComplaint(Request $request)
    {
        try {
            // Validate the request data
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'manager_id' => 'required|exists:managers,id',
                'message' => 'required|string',
            ]);

            // Create the complaint
            $complaint = new Complaint($validated);
            $complaint->save();

            return response()->json([
                'error' => false,
                'message' => 'Complaint submitted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => "Something went wrong. Please try again."
            ], 500);
        }
    }

}