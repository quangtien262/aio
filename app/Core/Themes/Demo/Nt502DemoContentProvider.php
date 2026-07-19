<?php

namespace App\Core\Themes\Demo;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
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

class Nt502DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'NT502';
    private const PRESET_KEY = 'nt502-dola-furniture';

    public function __construct(private readonly LandingPageBuilder $builder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'NT502 Dola Furniture', 'description' => 'Cửa hàng nội thất với danh mục, sản phẩm, đánh giá và tin tức.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) throw new InvalidArgumentException('Preset demo không hợp lệ cho NT502.');
        return DB::transaction(function (): array {
            $purged = $this->delete(); $websiteKey = $this->siteContext->websiteKey();
            $image = fn (string $id, int $width = 1000): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=86";
            $categoryData = [['Ghế','1567538096630-e0c55bd6374c'],['Bàn ăn','1604578762246-41134e37f9cc'],['Giường ngủ','1505693416388-ac5ce068fe85'],['Đèn','1507473885765-e6ed057f782c'],['Kệ và tủ','1600566753086-00f18fb6b3ea']]; $categories=[];
            foreach ($categoryData as $index => [$name,$photo]) { $model=CatalogCategory::query()->create(['name'=>$name,'slug'=>Str::slug('nt502-'.$name),'description'=>'Nội thất '.$name.' hiện đại cho không gian sống.','image_url'=>$image($photo),'sort_order'=>$index,'is_active'=>true]);$this->record($model);$categories[]=$model; }
            $products=[['Ghế Sofa Gỗ Cao Su Tự Nhiên',1990000,2300000,0,'1567538096630-e0c55bd6374c'],['Bàn Sofa - Bàn Cafe Gỗ',1790000,2000000,1,'1532372320572-cda25653a26d'],['Bộ bàn ăn gỗ hiện đại',3490000,3900000,1,'1604578762246-41134e37f9cc'],['Tủ Đầu Giường Gỗ',1190000,1390000,4,'1615874694520-474822394e73'],['Tủ Kệ Tivi Gỗ',2490000,3000000,4,'1600566753086-00f18fb6b3ea'],['Tủ Quần Áo Gỗ',4490000,5000000,4,'1558997519-83ea9252edf8'],['Giường Ngủ Gỗ',4990000,5500000,2,'1505693416388-ac5ce068fe85'],['Đèn bàn phòng ngủ',890000,990000,3,'1507473885765-e6ed057f782c'],['Bàn Trang Điểm Gỗ Đa Năng',3990000,4500000,1,'1616486338812-3dadae4b4ace'],['Kệ Giày Ba Ngăn',2790000,3100000,4,'1600607687920-4e2a09cf159d']];
            foreach($products as $index=>[$name,$price,$old,$category,$photo]){$model=CatalogProduct::query()->create(['catalog_category_id'=>$categories[$category]->id,'name'=>$name,'slug'=>Str::slug('nt502-'.$name),'sku'=>'NT502-'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT),'price'=>$price,'original_price'=>$old,'stock'=>30,'short_description'=>'DOLA FURNITURE','detail_content'=>'<p>Sản phẩm nội thất thiết kế hiện đại, vật liệu bền đẹp và hoàn thiện chỉn chu.</p>','image_url'=>$image($photo),'is_featured'=>$index<5,'is_highlight'=>$index<5,'sort_order'=>$index,'is_active'=>true]);$this->record($model);}
            foreach([['Nội thất phòng khách','Giảm đến 50% khi đặt hàng qua web','1600210492486-724fe5c67fb0'],['Không gian sống hiện đại','Nội thất bền đẹp cho mọi gia đình','1600585154340-be6161a56a0c']] as $index=>[$title,$summary,$photo]){$model=SiteBanner::query()->create(['theme_key'=>self::THEME_KEY,'placement'=>'nt502-hero-slider','title'=>$title,'subtitle'=>$summary,'image_url'=>$image($photo,2200),'link_url'=>'#phong-khach','badge'=>'DOLA FURNITURE','metadata'=>['summary'=>$summary,'button_label'=>'Xem ngay'],'sort_order'=>$index,'is_active'=>true]);$this->record($model);}
            foreach([['Ngọc Tuyến','Đầu bếp','Nội thất đẹp, chắc chắn và tạo cảm giác rất ấm cúng cho gia đình.'],['Minh Anh','Kiến trúc sư','Thiết kế tinh tế, giao hàng nhanh và đội ngũ tư vấn tận tâm.']] as $index=>[$name,$role,$quote]){$model=CmsTestimonial::query()->create(['name'=>$name,'role'=>$role,'company'=>'Dola Furniture customer','quote'=>$quote,'image_url'=>$image($index?'1494790108377-be9c29b29330':'1500648767791-00dcc994a43e',400),'status'=>'published','publish_at'=>now(),'is_featured'=>true,'sort_order'=>$index]);$this->record($model);}
            $postCategory=CmsCategory::query()->create(['name'=>'Cẩm nang nội thất','slug'=>'nt502-cam-nang-noi-that','description'=>'Kinh nghiệm trang trí và chăm sóc nội thất.']);$this->record($postCategory);
            foreach([['Phòng ngủ Mây cổ điển, trầm ấm','Gợi ý thiết kế phòng ngủ mang lại cảm giác thư giãn.'],['Không gian tỏa sáng với hàng trang trí mới','Những món đồ trang trí tạo điểm nhấn cho ngôi nhà.'],['Giường ngủ hiện đại và thoải mái','Cách lựa chọn giường phù hợp với diện tích phòng.'],['Các cách bảo quản sofa da luôn đẹp','Mẹo vệ sinh và bảo quản sofa bền lâu.']] as $index=>[$title,$excerpt]){$model=CmsPost::query()->create(['category_id'=>$postCategory->id,'title'=>$title,'slug'=>Str::slug('nt502-'.$title),'status'=>'published','excerpt'=>$excerpt,'body'=>'<p>'.$excerpt.'</p>','publish_at'=>now()->subDays($index+1),'is_highlight'=>true]);$this->record($model);}
            $home=route('site.home');$menu=CmsMenu::query()->create(['name'=>'NT502 Main Menu','location'=>'primary-navigation','items'=>[['label'=>'Trang chủ','url'=>$home],['label'=>'Giới thiệu','url'=>$home.'#gioi-thieu'],['label'=>'Sản phẩm','url'=>$home.'#phong-khach'],['label'=>'Tin tức','url'=>route('site.blog.index')],['label'=>'Liên hệ','url'=>route('site.contact')]]]);$this->record($menu);
            $page=CmsPage::query()->create(['title'=>'Liên hệ','slug'=>'contact','status'=>'published','excerpt'=>'Liên hệ Dola Furniture.','body'=>'<p>Đội ngũ Dola Furniture luôn sẵn sàng tư vấn cho không gian của bạn.</p>','publish_at'=>now()]);$this->record($page);
            $profile=SiteProfile::query()->firstOrNew();$profile->forceFill(['site_name'=>'Dola Furniture','website_type'=>'ecommerce','active_theme_key'=>self::THEME_KEY,'branding'=>array_merge((array)$profile->branding,['company_name'=>'DOLA FURNITURE','company_description'=>'Nội thất Việt cho không gian sống hiện đại.','support_hotline'=>'1900 6750','support_email'=>'support@sapo.vn','support_location'=>'70 Lữ Gia, Phường 15, Quận 11, TP.HCM'])])->save();
            $existing=LandingPage::query()->where('website_key',$websiteKey)->where('theme_key',self::THEME_KEY)->where('is_home',true)->first();$landing=$this->builder->resolveHome($websiteKey,self::THEME_KEY,true);if($landing&&!$existing)$this->record($landing);
            return ['preset'=>$this->preset(),'counts'=>['categories'=>5,'products'=>10,'banners'=>2,'testimonials'=>2,'post_categories'=>1,'posts'=>4,'pages'=>1,'menus'=>1,'landing_pages'=>!$existing&&$landing?1:0],'purged'=>$purged];
        });
    }

    public function delete(): array
    {
        $records=ThemeDemoRecord::query()->where('theme_key',self::THEME_KEY)->where('preset_key',self::PRESET_KEY)->get();$ids=fn(string $type):array=>$records->where('model_type',$type)->pluck('model_id')->all();$counts=['categories'=>0,'products'=>0,'banners'=>0,'testimonials'=>0,'post_categories'=>0,'posts'=>0,'pages'=>0,'menus'=>0,'landing_pages'=>0];
        if($pageIds=$ids(LandingPage::class)){$blockIds=LandingPageBlock::query()->whereIn('landing_page_id',$pageIds)->pluck('id');LandingPageBlockData::query()->whereIn('landing_page_block_id',$blockIds)->delete();LandingPageBlock::query()->whereIn('landing_page_id',$pageIds)->delete();LandingPageData::query()->whereIn('landing_page_id',$pageIds)->delete();$counts['landing_pages']=LandingPage::query()->whereKey($pageIds)->delete();}
        foreach([[CmsPost::class,'posts'],[CmsCategory::class,'post_categories'],[CmsPage::class,'pages'],[CatalogProduct::class,'products'],[CatalogCategory::class,'categories'],[CmsTestimonial::class,'testimonials'],[CmsMenu::class,'menus'],[SiteBanner::class,'banners']] as [$model,$key])if($modelIds=$ids($model))$counts[$key]=$model::query()->whereKey($modelIds)->delete();ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();return $counts;
    }
    private function record(Model $model):void{ThemeDemoRecord::query()->create(['theme_key'=>self::THEME_KEY,'preset_key'=>self::PRESET_KEY,'model_type'=>$model::class,'model_id'=>$model->getKey()]);}
}
