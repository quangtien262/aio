<?php

namespace App\Http\Controllers\Site;

use App\Core\Cms\CmsMenuResolver;
use App\Core\Themes\ThemeRegistry;
use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\SiteContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RealEstateController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ThemeRegistry $themeRegistry,
        private readonly CmsMenuResolver $menuResolver,
        private readonly LocalizedContentRepository $localizedContent,
    ) {}

    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $websiteKey = $this->siteContext->websiteKey();
        $query = RealEstateListing::query()
            ->with(['propertyType', 'media'])
            ->where('publication_status', 'published')
            ->where('availability_status', 'available');

        $this->applyFilters($query, $request, $locale, $websiteKey);
        $listings = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('is_hot')
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();
        $listings->setCollection($listings->getCollection()->map(
            fn (RealEstateListing $listing): RealEstateListing => $this->localizeListing(
                $listing,
                $locale,
                $websiteKey,
            ),
        ));
        $propertyTypes = RealEstatePropertyType::query()
            ->where('is_active', true)
            ->withCount(['listings' => fn (Builder $builder) => $builder->where('publication_status', 'published')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (RealEstatePropertyType $type): RealEstatePropertyType => $this->localizedContent->localize(
                $type,
                'real_estate_property_type',
                $locale,
                $websiteKey,
            ));

        return $this->view('listings', [
            'listings' => $listings,
            'propertyTypes' => $propertyTypes,
            'filters' => $request->only([
                'q', 'transaction_type', 'property_type', 'province', 'district',
                'min_price', 'max_price', 'bedrooms', 'bathrooms',
            ]),
        ]);
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $websiteKey = $this->siteContext->websiteKey();
        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'real_estate_listing',
            $websiteKey,
            $locale,
            $slug,
        );
        abort_if($resolution === null, 404);
        /** @var RealEstateListing $listing */
        $listing = $resolution['model'];
        $listing->loadMissing(['propertyType', 'project', 'media']);
        $listing = $this->localizeListing($listing, $resolution['resolved_locale'], $websiteKey);

        if ($resolution['used_fallback'] || $resolution['redirect_to'] !== null) {
            return redirect()->to(
                FrontendRouteUrl::realEstateListing(
                    $listing->slug,
                    $resolution['resolved_locale'],
                ),
                $resolution['redirect_to'] !== null ? 301 : 302,
            );
        }

        $relatedListings = RealEstateListing::query()
            ->with(['propertyType', 'media'])
            ->where('publication_status', 'published')
            ->whereKeyNot($listing->id)
            ->when($listing->property_type_id, fn (Builder $query) => $query->where('property_type_id', $listing->property_type_id))
            ->orderByDesc('is_featured')
            ->take(4)
            ->get()
            ->map(fn (RealEstateListing $related): RealEstateListing => $this->localizeListing(
                $related,
                $locale,
                $websiteKey,
            ));

        return $this->view('listing', [
            'listing' => $listing,
            'relatedListings' => $relatedListings,
        ]);
    }

    private function applyFilters(Builder $query, Request $request, string $locale, string $websiteKey): void
    {
        $search = trim((string) $request->query('q', ''));
        $propertyTypeValue = $request->query('property_type');
        $localizedPropertyType = filled($propertyTypeValue) && ! is_numeric($propertyTypeValue)
            ? $this->localizedContent->findPublishedBySlug(
                'real_estate_property_type',
                $websiteKey,
                $locale,
                (string) $propertyTypeValue,
            )
            : null;
        $query
            ->when($search !== '', fn (Builder $builder) => $builder->where(function (Builder $nested) use ($search): void {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('province', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%");
            }))
            ->when(in_array($request->query('transaction_type'), ['sale', 'rent'], true), fn (Builder $builder) => $builder->where('transaction_type', $request->query('transaction_type')))
            ->when($request->filled('property_type'), function (Builder $builder) use ($localizedPropertyType, $propertyTypeValue): void {
                $builder->whereHas('propertyType', function (Builder $typeQuery) use ($localizedPropertyType, $propertyTypeValue): void {
                    if ($localizedPropertyType !== null) {
                        $typeQuery->whereKey($localizedPropertyType->getKey());
                    } elseif (is_numeric($propertyTypeValue)) {
                        $typeQuery->whereKey((int) $propertyTypeValue);
                    } else {
                        $typeQuery->where('slug', $propertyTypeValue);
                    }
                });
            })
            ->when($request->filled('province'), fn (Builder $builder) => $builder->where('province', 'like', '%'.$request->query('province').'%'))
            ->when($request->filled('district'), fn (Builder $builder) => $builder->where('district', 'like', '%'.$request->query('district').'%'))
            ->when($request->filled('min_price'), fn (Builder $builder) => $builder->where('price', '>=', (float) $request->query('min_price')))
            ->when($request->filled('max_price'), fn (Builder $builder) => $builder->where('price', '<=', (float) $request->query('max_price')))
            ->when($request->filled('bedrooms'), fn (Builder $builder) => $builder->where('bedrooms', '>=', (int) $request->query('bedrooms')))
            ->when($request->filled('bathrooms'), fn (Builder $builder) => $builder->where('bathrooms', '>=', (int) $request->query('bathrooms')));
    }

    private function localizeListing(RealEstateListing $listing, string $locale, string $websiteKey): RealEstateListing
    {
        /** @var RealEstateListing $localized */
        $localized = $this->localizedContent->localize(
            $listing,
            'real_estate_listing',
            $locale,
            $websiteKey,
        );

        if ($localized->propertyType !== null) {
            $localized->setRelation('propertyType', $this->localizedContent->localize(
                $localized->propertyType,
                'real_estate_property_type',
                $locale,
                $websiteKey,
            ));
        }

        return $localized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function view(string $view, array $data): View
    {
        $themeKey = strtolower((string) ($this->siteContext->themeKey() ?: 'BDS701'));
        $viewName = "theme-{$themeKey}::{$view}";
        abort_unless(view()->exists($viewName), 404);

        $profile = $this->siteContext->profile();
        $menus = $this->menuResolver->all(
            $this->siteContext->websiteKey(),
            app()->getLocale(),
        );
        $activeTheme = $this->themeRegistry->all()->firstWhere('key', strtoupper($themeKey));

        return view($viewName, array_merge([
            'siteProfile' => $profile,
            'menus' => $menus,
            'activeTheme' => $activeTheme,
        ], $data));
    }
}
