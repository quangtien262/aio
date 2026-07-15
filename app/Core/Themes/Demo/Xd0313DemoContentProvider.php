<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockContent;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Xd0313DemoContentProvider implements ThemeDemoContentProvider
{
    private const PRESET_KEY = 'xd0313-visa-routex';

    public function themeKey(): string
    {
        return 'XD0313';
    }

    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'RouteX Visa',
            'description' => 'Du lieu mau cho dich vu visa, du hoc va dinh cu voi cac khoi Landing Page Builder da duoc gan nguon du lieu that.',
        ];
    }

    public function generate(): array
    {
        $purged = $this->delete();
        $counts = array_fill_keys(['services', 'posts', 'testimonials', 'menus', 'banners', 'profiles', 'landing_pages'], 0);

        $services = [
            ['Visa du hoc Chau Au', 'Ho tro chon truong, chuan bi ho so va dat lich phong van visa du hoc Chau Au.', 'photo-1529156069898-49953e39b3ac'],
            ['Visa du lich', 'Tu van lo trinh, chung minh tai chinh va hoan thien ho so du lich an tam.', 'photo-1488646953014-85cb44e25828'],
            ['Visa cong tac', 'Giai phap visa cong tac ro rang cho ca nhan va doanh nghiep.', 'photo-1500530855697-b586d89ba3ee'],
            ['Visa tham nguoi than', 'Dong hanh chuan bi giay to moi, chung minh quan he va lich trinh.', 'photo-1518600506278-4e8ef466b810'],
            ['Gia han visa My', 'Kiem tra dieu kien va ho tro gia han visa phu hop voi ho so cua ban.', 'photo-1521737711867-e3b97375f902'],
            ['Visa du hoc Uc', 'Tu van visa du hoc Uc, loi trinh hoc tap va ke hoach nhap canh.', 'photo-1500534314209-a25ddb2bd429'],
        ];

        foreach ($services as $index => [$title, $summary, $photo]) {
            $service = CmsService::query()->create([
                'title' => $title,
                'slug' => Str::slug($title).'-'.($index + 1),
                'status' => 'published',
                'summary' => $summary,
                'content' => $summary,
                'button_label' => 'Xem chi tiet',
                'link_url' => '/dich-vu',
                'publish_at' => now(),
                'is_featured' => $index < 3,
                'is_highlight' => $index < 3,
                'sort_order' => $index + 1,
                'website_key' => 'main',
            ]);

            CmsServiceImage::query()->create([
                'cms_service_id' => $service->id,
                'image_url' => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=1200&q=85",
                'alt' => $title,
                'sort_order' => 1,
            ]);
            $counts['services']++;
        }

        foreach ([
            ['Visa de dang, giac mo thanh hien thuc', 'RouteX Visa dong hanh tu buoc tu van den ngay ban nhan visa.', 'photo-1503220317375-aaad61436b1b'],
            ['Mo rong hanh trinh the gioi', 'Dich vu visa va du hoc duoc ca nhan hoa theo muc tieu cua ban.', 'photo-1526772662000-3f88f10405ff'],
        ] as $index => [$title, $subtitle, $photo]) {
            SiteBanner::query()->create([
                'theme_key' => $this->themeKey(),
                'placement' => 'xd0313-hero-slider',
                'title' => $title,
                'subtitle' => $subtitle,
                'image_url' => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=1800&q=85",
                'link_url' => '#lien-he',
                'badge' => 'RouteX Visa',
                'metadata' => ['button_label' => 'Nhan tu van', 'description' => 'Chung toi giup ban don gian hoa ho so visa de tap trung vao hanh trinh phia truoc.'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
            $counts['banners']++;
        }

        foreach ([
            ['Bi quyet chuan bi ho so visa du hoc', 'Danh sach giay to quan trong de ho so visa du hoc ro rang va thuyet phuc hon.'],
            ['Nhung dieu can biet khi xin visa Chau Au', 'Chuan bi lich trinh, bao hiem va chung minh tai chinh dung theo yeu cau.'],
            ['Visa du lich: nhung luu y de de dang dat ty le thanh cong', 'Mot vai kinh nghiem thuc te giup ban tu tin hon truoc ngay nop ho so.'],
            ['Cap nhat chinh sach visa cho nguoi Viet Nam', 'Theo doi thong tin moi de chu dong ke hoach di lai, hoc tap va lam viec.'],
        ] as $index => [$title, $excerpt]) {
            CmsPost::query()->create([
                'title' => $title,
                'slug' => Str::slug($title).'-'.($index + 1),
                'status' => 'published',
                'excerpt' => $excerpt,
                'body' => $excerpt,
                'publish_at' => now()->subDays($index),
                'is_highlight' => $index < 3,
            ]);
            $counts['posts']++;
        }

        foreach ([
            ['Tran Minh Hoang', 'CEO cong ty ABC', 'Toi la chu doanh nghiep, khong co thoi gian tim hieu thu tuc phuc tap. RouteX da dong hanh tu A den Z, rat tien loi va chuyen nghiep.', 'photo-1534528741775-53994a69daeb'],
            ['Ngoc Anh', 'Hoc vien du hoc Chau Au', 'Doi ngu tu van tan tam, nhac tung moc thoi gian va giai dap ro rang moi thac mac trong suot qua trinh lam ho so.', 'photo-1544005313-94ddf0286df2'],
        ] as $index => [$name, $role, $quote, $photo]) {
            CmsTestimonial::query()->create([
                'name' => $name,
                'role' => $role,
                'quote' => $quote,
                'image_url' => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=320&q=85",
                'status' => 'published',
                'publish_at' => now(),
                'is_featured' => true,
                'sort_order' => $index + 1,
                'website_key' => 'main',
            ]);
            $counts['testimonials']++;
        }

        foreach ([
            ['Trang chu', '/'], ['Gioi thieu', '#gioi-thieu'], ['Visa noi bat', '#visa-noi-bat'], ['Dich vu visa', '#dich-vu'], ['Quy trinh', '#quy-trinh'], ['Tin tuc', '#tin-tuc'], ['Lien he', '#lien-he'],
        ] as $index => [$label, $url]) {
            CmsMenu::query()->create(['name' => $label, 'slug' => Str::slug($label).'-'.($index + 1), 'url' => $url, 'status' => 'published', 'sort_order' => $index + 1]);
            $counts['menus']++;
        }

        SiteProfile::query()->create([
            'key' => 'main', 'company_name' => 'RouteX Visa', 'hotline' => '1900 9477', 'email' => 'hello@routex.local',
            'address' => '344 Huynh Tan Phat, Phuong Binh Thuan, Quan 7, TP.HCM',
            'description' => 'RouteX Visa la don vi tu van visa va du hoc, dong hanh cung ban tren hanh trinh mo rong tuong lai.',
        ]);
        $counts['profiles']++;

        $page = app(LandingPageBuilder::class)->createDefaultPageForTheme($this->themeKey());
        $this->record($page, 'landing_page');
        $counts['landing_pages']++;

        return ['preset' => self::PRESET_KEY, 'created' => $counts, 'deleted' => $purged];
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', $this->themeKey())->where('preset_key', self::PRESET_KEY)->get();
        $counts = array_fill_keys(['services', 'posts', 'testimonials', 'menus', 'banners', 'profiles', 'landing_pages'], 0);

        foreach ($records->groupBy('record_type') as $type => $items) {
            $ids = $items->pluck('record_id')->filter()->values();
            if ($ids->isEmpty()) {
                continue;
            }

            if ($type === 'landing_page') {
                $pages = LandingPage::query()->whereIn('id', $ids)->get();
                foreach ($pages as $page) {
                    $blockIds = LandingPageBlock::query()->where('landing_page_id', $page->id)->pluck('id');
                    LandingPageBlockContent::query()->whereIn('landing_page_block_id', $blockIds)->delete();
                    LandingPageBlock::query()->whereIn('id', $blockIds)->delete();
                    $page->delete();
                    $counts['landing_pages']++;
                }
                continue;
            }

            $model = match ($type) {
                'service' => CmsService::class,
                'post' => CmsPost::class,
                'testimonial' => CmsTestimonial::class,
                'menu' => CmsMenu::class,
                'banner' => SiteBanner::class,
                'profile' => SiteProfile::class,
                default => null,
            };
            if ($model === null) {
                continue;
            }

            if ($type === 'service') {
                CmsServiceImage::query()->whereIn('cms_service_id', $ids)->delete();
            }
            $deleted = $model::query()->whereIn('id', $ids)->delete();
            $key = match ($type) {
                'service' => 'services', 'post' => 'posts', 'testimonial' => 'testimonials', 'menu' => 'menus', 'banner' => 'banners', 'profile' => 'profiles', default => null,
            };
            if ($key !== null) {
                $counts[$key] += $deleted;
            }
        }

        ThemeDemoRecord::query()->whereIn('id', $records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model, string $type): void
    {
        ThemeDemoRecord::query()->create([
            'theme_key' => $this->themeKey(),
            'preset_key' => self::PRESET_KEY,
            'record_type' => $type,
            'record_id' => $model->getKey(),
        ]);
    }
}
