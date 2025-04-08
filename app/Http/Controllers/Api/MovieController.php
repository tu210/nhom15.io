<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\Movie;
use App\Models\Rating;
use App\Models\Category;
use App\Models\Episode;
use App\Models\MovieCategory;
use App\Models\Package;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class MovieController extends Controller
{



    // ví dụ request: http://localhost:8000/api/movies?category=[slug]&sort_by=[sort_by]&limit=5&per_page=10&sort_dir=[asc|desc]
    // ví dụ request: http://localhost:8000/api/movies/1 // hoặc http://localhost:8000/api/movies/slug-movie

    public function index(Request $request)
    {
        $query = Movie::with('category')->withCount(['episodes', 'ratings']);

        if ($request->has('category')) {
            $slug = $request->input('category');
            $query->whereHas('category', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }
        if ($request->has('genre')) {
            $genreSlug = $request->input('genre');
            $query->whereRaw("REPLACE(LOWER(CONVERT(genres USING utf8mb4)), ' ', '-') LIKE ?", ['%' . $genreSlug . '%']);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowedSorts = ['created_at', 'view', 'name', 'rating'];

        if (in_array($sortBy, $allowedSorts) && in_array($sortDir, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }


        if ($request->has('limit') && is_numeric($request->input('limit'))) {
            $limit = (int) $request->input('limit');
            $movies = $query->limit($limit)->get();
        } else {
            $perPage = $request->input('per_page', 15);
            if (!is_numeric($perPage) || $perPage <= 0) {
                $perPage = 15;
            }
            $movies = $query->paginate((int)$perPage);
        }
        $movies->load(['packages' => function ($query) {
            $query->select('packages.id', 'packages.name', 'packages.price')->orderBy('price', 'asc')->limit(1);
        }]);

        return response()->json($movies);
    }

    public function show(Request $request, $identifier)
    {
        $query = Movie::with('category')->withCount('episodes')->withCount('ratings');
        $query->with(['episodes' => function ($q) {
            $q->orderBy('episode_number', 'asc');
        }]);
        if (is_numeric($identifier)) {
            $movie = $query->find($identifier);
            if ($movie) {
                $movie->increment('view');
            }
        } else {
            $movie = $query->where('slug', $identifier)->first();
        }

        if (!$movie) {
            return response()->json(['message' => 'Movie not found'], 404);
        }
        $movie->view = $movie->view + 1;
        $movie->save();

        return response()->json($movie);
    }

    public function getEpisodes(Request $request, $movieId)
    {
        $movie = Movie::with('episodes')->find($movieId);

        if (!$movie) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json($movie->episodes);
    }

    public function getMovieWithEpisodes(Request $request, $movieId)
    {
        $movie = Movie::with('episodes')->find($movieId);

        if (!$movie) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json($movie);
    }

    public function Rating(Request $request, $movieId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['message' => 'User authentication failed'], 401);
            }

            $movie = Movie::find($movieId);
            if (!$movie) {
                return response()->json(['message' => 'Movie not found'], 404);
            }

            $request->validate([
                'rating' => 'required|numeric|min:1|max:5',
            ]);

            $rating = $request->input('rating');
            Rating::updateOrInsert(
                ['user_id' => $user->id, 'movie_id' => $movieId],
                ['rating_value' => $rating]
            );


            $averageRating = Rating::where('movie_id', $movieId)->avg('rating_value');
            $movie->rating = $averageRating;
            $movie->save();

            return response()->json([
                'message' => 'Cảm ơn bạn đã đánh giá',
                'rating' => $movie->rating,
                'total_rating' => Rating::where('movie_id', $movieId)->count()
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    public function setHistory(Request $request, $episodeId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['message' => 'Xác thực user thất bại'], 401);
            }
            $episode = Episode::find($episodeId);
            if (!$episode) {
                return response()->json(['message' => 'Không thấy movie'], 404);
            }
            $progress = $request->has('progress') ? $request->input('progress') : 0;
            if ($progress < 0 || $progress > 100) {
                return response()->json(['message' => 'Progress không hợp lệ'], 400);
            }
            $episode->history()->updateOrCreate(
                ['user_id' => $user->id],
                ['progress' => $progress]
            );
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage(), 'message' => 'Có lỗi xảy ra'], 500);
        }
    }

    // Dung để test
    public function genRandomViews(Request $request, $type = 'create')
    {
        if ($type == 'create') {
            $movies = Movie::all();
            foreach ($movies as $movie) {
                $movie->view = rand(0, 10000);
                $movie->save();
            }
        } elseif ($type == 'reset') {
            $movies = Movie::all();
            foreach ($movies as $movie) {
                $movie->view = 0;
                $movie->save();
            }
        } else {
            return response()->json(['message' => 'Invalid type'], 400);
        }
    }
}
