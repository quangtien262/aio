<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogProduct;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\CmsTeamMember;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Dl750DemoContentProvider implements ThemeDemoContentProvider
{
    public function themeKey(): string
    {
        return 'DL750';
    }

    public function defaultPreset(): string
    {
        return 'dl750-complete';
    }

    public function presets(): array
    {
        return [
            ['key' => 'dl750-complete', 'label' => 'Du lịch và cắm trại — Forest Camp', 'description' => 'Trang bị dã ngoại, thuê lều và hoạt động ngoài trời.'],
            ['key' => 'dl750-cafe', 'label' => 'Quán cà phê — Mộc Garden Café', 'description' => 'Cà phê, trà trái cây, bánh ngọt và đặt bàn tại quán.'],
            ['key' => 'dl750-restaurant', 'label' => 'Nhà hàng — Mộc Garden Restaurant', 'description' => 'Thực đơn món ăn, đặt bàn gia đình và tiệc nhóm.'],
        ];
    }

    public function preset(): array
    {
        return $this->presets()[0];
    }

    private function brief(string $key): array
    {
        $briefs = json_decode(file_get_contents(resource_path('demo/dl750-presets.json')), true, 512, JSON_THROW_ON_ERROR);
        $briefs[$this->defaultPreset()] = json_decode(file_get_contents(resource_path('demo/remaining-themes.json')), true, 512, JSON_THROW_ON_ERROR)['DL750'];

        return $briefs[$key] ?? throw new InvalidArgumentException('Bộ dữ liệu DL750 không hợp lệ.');
    }

    private function records(string $model)
    {
        return $model::whereKey(ThemeDemoRecord::where('theme_key', 'DL750')->where('model_type', $model)->pluck('model_id'))->orderBy('id')->get();
    }

    public function delete(): array
    {
        return (new IndustryDemoContentProvider('DL750', $this->brief($this->defaultPreset())))->delete();
    }

    public function generate(string $presetKey): array
    {
        $brief = $this->brief($presetKey);

        return DB::transaction(function () use ($presetKey, $brief): array {
            $result = (new IndustryDemoContentProvider('DL750', $brief))->generate($presetKey);
            if ($presetKey === $this->defaultPreset()) {
                $profile = SiteProfile::firstOrFail();
                $branding = (array) $profile->branding;
                if (str_starts_with((string) ($branding['logo_url'] ?? ''), '/theme-demo/dl750/')) {
                    $profile->update(['branding' => array_merge($branding, ['logo_url' => $brief['logo']])]);
                }

                return $result;
            }
            $intro = $brief['intro'];
            $note = 'Thực đơn và giá là dữ liệu mẫu. Vui lòng liên hệ để xác nhận tình trạng phục vụ, thành phần món và yêu cầu dị ứng trước khi đặt.';
            foreach ($this->records(CatalogProduct::class) as $product) {
                $summary = $product->name.' — một lựa chọn trong thực đơn của '.$brief['brand'].'.';
                $product->update(['price' => $brief['prices'][$product->name], 'short_description' => $summary, 'detail_content' => '<h2>'.e($product->name).'</h2><p>'.e($summary).'</p><p>'.e($note).'</p>']);
            }
            foreach ($this->records(CmsService::class) as $service) {
                $summary = 'Liên hệ '.$brief['brand'].' để chọn thời gian, số khách và yêu cầu cho '.mb_strtolower($service->title).'.';
                $service->update(['summary' => $summary, 'content' => '<h2>'.e($service->title).'</h2><p>'.e($summary).'</p><p>Đội ngũ tại quán sẽ xác nhận khả năng phục vụ, thực đơn và chi phí trước khi nhận đặt chỗ.</p>', 'button_label' => 'Liên hệ đặt bàn']);
            }
            foreach ($this->records(CmsPost::class) as $i => $post) {
                $post->update(['title' => $brief['posts'][$i], 'excerpt' => $intro, 'body' => '<h2>'.e($brief['posts'][$i]).'</h2><p>'.e($intro).'</p><p>Chọn một khung giờ phù hợp, xem trước thực đơn và chia sẻ sở thích với đội ngũ tại quán để chuẩn bị cho buổi gặp gỡ.</p>', 'meta_title' => $brief['posts'][$i], 'meta_description' => $intro]);
            }
            foreach ($this->records(CmsPage::class) as $page) {
                $page->update(['excerpt' => $intro, 'body' => '<h2>'.e($brief['brand']).'</h2><p>'.e($intro).'</p><p>Để đặt bàn, vui lòng cung cấp ngày giờ, số người và yêu cầu riêng. Thay đổi hoặc hủy đặt bàn cần được trao đổi trực tiếp với quán.</p>']);
            }
            foreach ($this->records(CmsProject::class) as $i => $project) {
                $project->update(['title' => ['Góc vườn xanh', 'Không gian gặp gỡ', 'Bàn tiệc ấm cúng'][$i], 'summary' => $intro, 'content' => '<p>'.e($intro).'</p>']);
            }
            foreach ($this->records(CmsTeamMember::class) as $i => $member) {
                $member->update(['role' => ['Quản lý quán', 'Phụ trách thực đơn', 'Chăm sóc khách hàng'][$i], 'summary' => 'Đội ngũ mẫu tại '.$brief['brand'], 'bio' => '<p>Chào đón và hỗ trợ khách trong từng trải nghiệm tại quán.</p>']);
            }
            foreach ($this->records(CmsTestimonial::class) as $testimonial) {
                $testimonial->update(['quote' => 'Không gian thư thái, thực đơn dễ lựa chọn và đội ngũ phục vụ chu đáo. Đây là nhận xét minh họa của bộ dữ liệu mẫu.']);
            }
            foreach ($this->records(SiteBanner::class) as $i => $banner) {
                $banner->update(['title' => [$brief['brand'], 'Hẹn nhau một khoảnh khắc thư thái'][$i], 'subtitle' => $intro, 'metadata' => ['kicker' => $brief['brand'], 'summary' => $intro, 'button_label' => 'Khám phá thực đơn'], 'link_url' => route('site.catalog.search', [], false)]);
            }
            $profile = SiteProfile::firstOrFail();
            $branding = (array) $profile->branding;
            if (str_starts_with((string) ($branding['logo_url'] ?? ''), '/theme-demo/')) {
                $branding['logo_url'] = $brief['logo'];
            }
            $profile->update(['branding' => array_merge($branding, ['company_description' => $intro])]);
            $titles = ['hero_slider' => $brief['brand'], 'dl750_categories' => 'Khám phá thực đơn', 'dl750_about' => 'Về '.$brief['brand'], 'dl750_services' => 'Trải nghiệm tại quán', 'dl750_reasons' => 'Một điểm hẹn dành cho bạn', 'dl750_products' => 'Gợi ý hôm nay', 'dl750_gallery' => 'Khoảnh khắc tại quán', 'dl750_news' => 'Chuyện bên bàn', 'dl750_faq' => 'Thông tin đặt bàn', 'dl750_partners' => 'Đối tác đồng hành'];
            foreach ($this->records(LandingPage::class) as $page) {
                foreach ($page->blocks()->with('data')->get() as $block) {
                    $settings = (array) $block->settings;
                    if ($block->block_type === 'dl750_products') {
                        $settings['feature_image'] = $brief['images'][0];
                    }
                    $block->update(['settings' => $settings]);
                    foreach ($block->data->where('locale', 'vi') as $data) {
                        $content = json_decode($data->content ?? '{}', true) ?: [];
                        $content['heading_brand'] = 'MỘC GARDEN';
                        $content['about_note'] = 'Gặp gỡ, thưởng thức và tận hưởng một khoảng thời gian dành riêng cho bạn.';
                        $content['service_caption'] = 'Chào đón bạn tại quán';
                        $content['promo_title'] = 'THỰC ĐƠN';
                        $content['promo_text'] = 'Một hương vị mới cho ngày hôm nay';
                        if ($block->block_type === 'dl750_gallery') {
                            $content['items'] = array_map(fn ($image, $title) => ['image' => $image, 'title' => $title], $brief['images'], ['Hương vị tại quán', 'Chia sẻ cùng bạn bè', 'Một ngày thư thái']);
                        }
                        if (in_array($block->block_type, ['dl750_about', 'dl750_reasons'], true)) {
                            $content['items'] = array_map(fn ($title) => ['title' => $title, 'summary' => $intro, 'icon' => 'fa-solid fa-leaf'], ['Không gian xanh', 'Thực đơn đa dạng', 'Phục vụ tận tâm', 'Gặp gỡ và sẻ chia']);
                        }
                        if ($block->block_type === 'dl750_faq') {
                            $content['items'] = [['title' => 'Làm thế nào để đặt bàn?', 'summary' => 'Liên hệ quán và cung cấp ngày giờ, số khách để được xác nhận.'], ['title' => 'Có thể thay đổi thực đơn không?', 'summary' => 'Hãy chia sẻ khẩu vị, yêu cầu ăn chay hoặc dị ứng để quán kiểm tra và tư vấn.'], ['title' => 'Có nhận nhóm đông không?', 'summary' => 'Vui lòng đặt trước để xác nhận không gian, thực đơn và chi phí.']];
                        }
                        $data->update(['title' => $titles[$block->block_type] ?? $data->title, 'subtitle' => $brief['brand'], 'description' => $intro, 'button_label' => 'Liên hệ đặt bàn', 'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    }
                }
            }
            $result['preset'] = collect($this->presets())->firstWhere('key', $presetKey);
            $result['counts']['products'] = count($brief['products']);

            return $result;
        });
    }
}
