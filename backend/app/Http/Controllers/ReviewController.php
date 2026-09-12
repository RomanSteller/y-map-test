<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    /**
     * Paginated reviews for one organisation — 50 per page as required.
     *
     * Reviews are served from our own DB (the parse result is cached there),
     * not re-scraped on every page turn. So paging is a cheap indexed query and
     * the front-end can switch pages instantly without hammering Yandex. The
     * reasoning behind caching-then-paginating is in the README.
     */
    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $perPage = (int) config('services.yandex.page_size', 50);

        $reviews = $organization->reviews()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ReviewResource::collection($reviews);
    }
}
