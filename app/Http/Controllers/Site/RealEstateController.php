<?php

namespace App\Http\Controllers\Site;

use App\Core\Themes\ThemeRegistry;
use App\Models\CmsMenu;
use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Support\SiteContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RealEstateController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ThemeRegistry $themeRegistry,
    ) {
    }

    public function index(Request $request): View
    {
        $query = RealEstateListing::query()
            ->with(['propertyType', 'media'])
            ->where('publication_status', 'published')
            ->where('availability_status', 'available');

        $this->applyFilters($query, $request);

        return $this->view('listings', [
            'listings' => $query
                ->orderByDesc('is_featured')
                ->orderByDesc('is_hot')
                ->orderBy('sort_order')
                ->latest('published_at')
                ->paginate(12)
                ->withQueryString(),
            'propertyTypes' => RealEstatePropertyType::query()
                ->where('is_active', true)
                ->withCount(['listings' => fn (Builder $builder) => $builder->where('publication_status', 'published')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => $request->only([
                'q', 'transaction_type', 'property_type', 'province', 'district',
                'min_price', 'max_price', 'bedrooms', 'bathrooms',
            ]),
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $listing = RealEstateListing::query()
            ->with(['propertyType', 'project', 'media'])
            ->where('slug', $slug)
            ->where('publication_status', 'published')
            ->firstOrFail();

        return $this->view('listing', [
            'listing' => $listing,
            'relatedListings' => RealEstateListing::query()
                ->with(['propertyType', 'media'])
                ->where('publication_status', 'published')
                ->whereKeyNot($listing->id)
                ->when($listing->property_type_id, fn (Builder $query) => $query->where('property_type_id', $listing->property_type_id))
                ->orderByDesc('is_featured')
                ->take(4)
                ->get(),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        $query
            ->when($search !== '', fn (Builder $builder) => $builder->where(function (Builder $nested) use ($search): void {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%");
            }))
            ->when(in_array($request->query('transaction_type'), ['sale', 'rent'], true), fn (Builder $builder) => $builder->where('transaction_type', $request->query('transaction_type')))
            ->when($request->filled('property_type'), function (Builder $builder) use ($request): void {
                $value = $request->query('property_type');
                $builder->whereHas('propertyType', fn (Builder $typeQuery) => is_numeric($value)
                    ? $typeQuery->whereKey((int) $value)
                    : $typeQuery->where('slug', $value));
            })
            ->when($request->filled('province'), fn (Builder $builder) => $builder->where('province', 'like', '%'.$request->query('province').'%'))
            ->when($request->filled('district'), fn (Builder $builder) => $builder->where('district', 'like', '%'.$request->query('district').'%'))
            ->when($request->filled('min_price'), fn (Builder $builder) => $builder->where('price', '>=', (float) $request->query('min_price')))
            ->when($request->filled('max_price'), fn (Builder $builder) => $builder->where('price', '<=', (float) $request->query('max_price')))
            ->when($request->filled('bedrooms'), fn (Builder $builder) => $builder->where('bedrooms', '>=', (int) $request->query('bedrooms')))
            ->when($request->filled('bathrooms'), fn (Builder $builder) => $builder->where('bathrooms', '>=', (int) $request->query('bathrooms')));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function view(string $view, array $data): View
    {
        $themeKey = strtolower((string) ($this->siteContext->themeKey() ?: 'BDS701'));
        $viewName = "theme-{$themeKey}::{$view}";
        abort_unless(view()->exists($viewName), 404);

        $profile = $this->siteContext->profile();
        $menus = CmsMenu::query()->orderBy('name')->get();
        $activeTheme = $this->themeRegistry->all()->firstWhere('key', strtoupper($themeKey));

        return view($viewName, array_merge([
            'siteProfile' => $profile,
            'menus' => $menus,
            'activeTheme' => $activeTheme,
        ], $data));
    }
}
