<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Ec907DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'EC907';
    private const PRESET_KEY = 'ec907-ega-gear';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'EC907 EGA Gear', 'description' => 'Cửa hàng laptop, gaming gear và thiết bị công nghệ với Landing Page Builder.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) throw new InvalidArgumentException('Preset demo không hợp lệ cho EC907.');

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = $this->siteContext->websiteKey();
            $categoryDefinitions = [
                ['Laptop','laptop.webp'],['Máy tính bảng','laptop.webp'],['Điện thoại','phone-front.webp'],['Tai nghe','headset-black.png'],
                ['Bàn phím','keyboard-white.png'],['Sạc dự phòng','smartwatch.webp'],['Chuột và lót chuột','keyboard-white.png'],['Củ sạc','earbuds.webp'],
                ['Máy tính bàn PC','television.webp'],['Màn hình','television.webp'],['Thiết bị âm thanh','speaker.webp'],['Máy chơi game','game-console.webp'],
                ['Ghế gaming','headset.webp'],['Balo laptop','laptop.webp'],['Cáp sạc','earbuds.webp'],['Phụ kiện','smartwatch.webp'],
            ];
            $categories = [];
            foreach ($categoryDefinitions as $index => [$name, $image]) {
                $category = CatalogCategory::query()->create(['name' => $name, 'slug' => Str::slug('ec907-'.$name), 'description' => 'Thiết bị công nghệ chính hãng, bảo hành minh bạch.', 'image_url' => '/theme-demo/ec907/'.$image, 'sort_order' => $index, 'is_active' => true]);
                $this->record($category); $categories[] = $category;
            }
            $definitions = [
                ['KEYBOARD','Bàn phím cơ không dây Dot Foundation',4700000,4800000,4,'keyboard-white.png'],
                ['KEYBOARD','Bàn phím cơ không dây Flow 75',4400000,6800000,4,'keyboard-white.png'],
                ['KEYBOARD','Bàn phím cơ Chocolate Retro',3800000,4200000,4,'keyboard-white.png'],
                ['KEYBOARD','Bàn phím cơ B-Duck Wireless',3800000,4200000,4,'keyboard-white.png'],
                ['AUDIO','Tai nghe Studio W830BT',4350000,5220000,3,'headset-black.png'],
                ['AUDIO','Tai nghe Gaming Barracuda X',4350000,5220000,3,'headset-black.png'],
                ['AUDIO','Tai nghe SoundForm Play',2220000,3500000,3,'earbuds.webp'],
                ['AUDIO','Tai nghe Virtuoso Pro',4220000,4800000,3,'headset-black.png'],
                ['AUDIO','Tai nghe Fnatic React+ 7.1',4520000,4800000,3,'headset-black.png'],
                ['AUDIO','Loa không dây di động MiniBeat',1850000,2200000,10,'speaker.webp'],
                ['AUDIO','Tai nghe Momentum 4 Wireless',6520000,7000000,3,'headset-black.png'],
                ['AUDIO','Tai nghe SoundForm Mini Kids',3220000,3500000,3,'earbuds.webp'],
                ['AUDIO','Tai nghe Lightspeed G733',4520000,4800000,3,'headset-black.png'],
                ['GAMING','Máy chơi game thế hệ mới',15900000,17900000,11,'game-console.webp'],
                ['GAMING','Kính thực tế ảo Meta Quest',16690000,19800000,11,'headset.webp'],
                ['GAMING','Tay cầm DualSense Edge',6690000,9800000,11,'game-console.webp'],
                ['GAMING','Tay cầm chơi game không dây',2690000,3800000,11,'game-console.webp'],
            ];
            foreach ($definitions as $index => [$group,$name,$price,$original,$categoryIndex,$image]) {
                $product = CatalogProduct::query()->create([
                    'catalog_category_id' => $categories[$categoryIndex]->id, 'name' => $name, 'slug' => Str::slug('ec907-'.$name),
                    'sku' => 'EC907-'.$group.'-'.str_pad((string)($index+1),3,'0',STR_PAD_LEFT), 'price' => $price, 'original_price' => $original,
                    'stock' => 50, 'short_description' => 'Thiết bị công nghệ chính hãng, giao nhanh và bảo hành rõ ràng.',
                    'detail_content' => '<p>Sản phẩm EGA Gear được chọn lọc theo hiệu năng, độ bền và trải nghiệm sử dụng. Chính sách bảo hành và đổi trả được công bố minh bạch.</p>',
                    'image_url' => '/theme-demo/ec907/'.$image, 'is_featured' => $group === 'KEYBOARD', 'is_highlight' => true, 'sort_order' => $index, 'is_active' => true,
                ]);
                $this->record($product);
            }
            foreach ([['Gear giá sốc','Giao siêu tốc · Giảm đến 45%'],['Gaming mùa hè','Nâng cấp góc máy với ưu đãi đến 60%']] as $index => [$title,$summary]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'ec907-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => '/theme-demo/ec907/hero-gear.png', 'link_url' => '#san-pham-ban-chay', 'badge' => 'EGA GEAR', 'metadata' => ['summary' => $summary, 'button_label' => 'Xem ngay'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }
            $postCategory = CmsCategory::query()->create(['name' => 'Bản tin công nghệ EGA', 'slug' => 'ec907-ban-tin-cong-nghe', 'description' => 'Tin laptop, gaming gear và xu hướng công nghệ.']);
            $this->record($postCategory);
            $posts = [
                ['Trình làng trợ lý AI thế hệ mới cho người dùng công nghệ','Mô hình thông minh ngày càng hữu ích trong công việc và sáng tạo.','news-foldable.webp'],
                ['Laptop thế hệ mới được trang bị chip hiệu năng cao','Thiết kế mỏng nhẹ đi cùng thời lượng pin và sức mạnh xử lý tốt hơn.','laptop.webp'],
                ['Cách xuất màn hình laptop ra màn hình ngoài cực đơn giản','Hướng dẫn kết nối và tối ưu không gian làm việc đa màn hình.','news-tv.webp'],
                ['Laptop AI mới: hiệu năng mạnh và siêu mỏng nhẹ','Những thay đổi đáng chú ý của thế hệ máy tính cá nhân mới.','news-wearables.webp'],
            ];
            foreach ($posts as $index => [$title,$excerpt,$image]) {
                $media = CmsMedia::query()->create(['title' => $title, 'file_path' => '', 'file_url' => '/theme-demo/ec907/'.$image, 'mime_type' => str_ends_with($image,'.png')?'image/png':'image/webp', 'size' => 0, 'alt_text' => $title]);
                $this->record($media);
                $post = CmsPost::query()->create(['category_id' => $postCategory->id, 'title' => $title, 'slug' => Str::slug('ec907-'.$title), 'status' => 'published', 'excerpt' => $excerpt, 'body' => '<p>'.$excerpt.'</p><p>EGA Gear tổng hợp thông tin hữu ích giúp bạn chọn và sử dụng thiết bị hiệu quả hơn.</p>', 'featured_media_id' => $media->id, 'publish_at' => now()->subDays($index+1), 'is_highlight' => $index === 0]);
                $this->record($post);
            }
            $home = route('site.home');
            $menu = CmsMenu::query()->create(['name' => 'EC907 Main Menu', 'location' => 'primary-navigation', 'items' => [
                ['label' => 'Khuyến mãi', 'url' => $home.'#khuyen-mai'], ['label' => 'Dịch vụ', 'url' => $home.'#dich-vu'],
                ['label' => 'Tin tức', 'url' => $home.'#tin-tuc'], ['label' => 'Liên hệ', 'url' => route('site.contact')], ['label' => 'Kiểm tra đơn hàng', 'url' => route('site.catalog.search')],
            ]]); $this->record($menu);
            $contact = CmsPage::query()->firstOrCreate(['slug' => 'contact'], ['title' => 'Liên hệ EGA Gear', 'status' => 'published', 'excerpt' => 'Tư vấn thiết bị và hỗ trợ đơn hàng.', 'body' => '<p>Đội ngũ EGA Gear luôn sẵn sàng hỗ trợ bạn.</p>', 'publish_at' => now()]);
            if ($contact->wasRecentlyCreated) $this->record($contact);
            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'EGA Gear', 'website_type' => 'ecommerce', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array)$profile->branding, ['company_name' => 'EGA Gear', 'company_description' => 'Laptop, gaming gear và thiết bị công nghệ chính hãng.', 'support_hotline' => '0399162342', 'support_email' => 'support@egagear.vn', 'support_location' => '70 Lữ Gia, Quận 11, TP. Hồ Chí Minh'])])->save();
            $existing = LandingPage::query()->where('website_key',$websiteKey)->where('theme_key',self::THEME_KEY)->where('is_home',true)->first();
            $landing = $this->landingPageBuilder->resolveHome($websiteKey,self::THEME_KEY,true);
            if ($landing && !$existing) $this->record($landing);
            return ['preset' => $this->preset(), 'counts' => ['categories' => count($categoryDefinitions), 'products' => count($definitions), 'banners' => 2, 'post_categories' => 1, 'posts' => count($posts), 'media' => count($posts), 'pages' => $contact->wasRecentlyCreated ? 1 : 0, 'menus' => 1, 'landing_pages' => !$existing && $landing ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records=ThemeDemoRecord::query()->where('theme_key',self::THEME_KEY)->where('preset_key',self::PRESET_KEY)->get();$ids=fn(string $type):array=>$records->where('model_type',$type)->pluck('model_id')->all();
        $counts=['categories'=>0,'products'=>0,'banners'=>0,'post_categories'=>0,'posts'=>0,'media'=>0,'pages'=>0,'menus'=>0,'landing_pages'=>0];
        if($pageIds=$ids(LandingPage::class)){$blockIds=LandingPageBlock::query()->whereIn('landing_page_id',$pageIds)->pluck('id');LandingPageBlockData::query()->whereIn('landing_page_block_id',$blockIds)->delete();LandingPageBlock::query()->whereIn('landing_page_id',$pageIds)->delete();LandingPageData::query()->whereIn('landing_page_id',$pageIds)->delete();$counts['landing_pages']=LandingPage::query()->whereKey($pageIds)->delete();}
        foreach([[CmsPost::class,'posts'],[CmsMedia::class,'media'],[CmsCategory::class,'post_categories'],[CmsPage::class,'pages'],[CatalogProduct::class,'products'],[CatalogCategory::class,'categories'],[CmsMenu::class,'menus'],[SiteBanner::class,'banners']] as [$model,$key]) if($modelIds=$ids($model)) $counts[$key]=$model::query()->whereKey($modelIds)->delete();
        ThemeDemoRecord::query()->where('theme_key',self::THEME_KEY)->where('preset_key',self::PRESET_KEY)->delete();return $counts;
    }
    private function record(Model $model): void { ThemeDemoRecord::query()->create(['theme_key'=>self::THEME_KEY,'preset_key'=>self::PRESET_KEY,'model_type'=>$model::class,'model_id'=>$model->getKey()]); }
}
