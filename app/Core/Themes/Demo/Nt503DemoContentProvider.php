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

class Nt503DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'NT503';
    private const PRESET_KEY = 'nt503-wolfbed';

    public function __construct(private readonly LandingPageBuilder $builder, private readonly SiteContext $siteContext) {}
    public function themeKey(): string { return self::THEME_KEY; }
    public function defaultPreset(): string { return self::PRESET_KEY; }
    public function preset(): array { return ['key' => self::PRESET_KEY, 'label' => 'NT503 WolfBed', 'description' => 'Cửa hàng chăn ga, gối và nệm với dữ liệu demo WolfBed.']; }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) throw new InvalidArgumentException('Preset demo không hợp lệ cho NT503.');
        return DB::transaction(function (): array {
            $purged = $this->delete(); $websiteKey = $this->siteContext->websiteKey();
            $asset = fn (string $name): string => '/theme-demo/nt503/'.$name.'.png';
            $categoryData = [['Bộ chăn ga','bedding'],['Chăn và vỏ chăn','bedding'],['Ga lẻ','bedding'],['Vỏ gối lẻ','kids-pillow'],['Chăn ga gối trẻ em','hero-wolfbed'],['Bộ ga chun','bedding'],['Bộ ga phủ','bedding'],['Vỏ chăn lẻ','bedding'],['Chăn lẻ','bedding'],['Nệm cao cấp','mattress']]; $categories=[];
            foreach ($categoryData as $index => [$name,$photo]) { $model=CatalogCategory::query()->create(['name'=>$name,'slug'=>Str::slug('nt503-'.$name),'description'=>'Sản phẩm '.$name.' êm dịu cho giấc ngủ sâu.','image_url'=>$asset($photo),'sort_order'=>$index,'is_active'=>true]);$this->record($model);$categories[]=$model; }
            $products=[
                ['Nệm foam Goodnight Eva gấp 3 nâng đỡ dày 10cm',3099000,4599000,9,'mattress'],['Nệm foam tổng hợp Kim Cương Erica Smart Tech',4299000,4999000,9,'mattress'],
                ['Nệm lò xo Amando Faro 5 vùng giảm đau lưng',14490000,23990000,9,'mattress'],['Nệm lò xo đa tầng Sleep Wave Hybrid',4400000,6900000,9,'mattress'],
                ['Nệm Goodnight Latex Hybrid Rena',4900000,null,9,'mattress'],['Gối Cao Su Liên Á Oval đàn hồi cao',810000,null,3,'kids-pillow'],
                ['Gối cao su Kim Cương lượn sóng B',610000,640000,3,'kids-pillow'],['Gối bông trẻ em Goodnight Deepsleep Khủng Long',300000,400000,4,'kids-pillow'],
                ['Gối ôm Cao su Gummi Body thiên nhiên',1200000,1400000,3,'kids-pillow'],['Bộ chăn ga Cotton Thảo Mộc',1290000,1690000,0,'bedding'],
                ['Bộ ga chun Serenity Blooming',1590000,1990000,5,'bedding'],['Chăn hè Cotton Airy',890000,1090000,8,'bedding']
            ];
            foreach($products as $index=>[$name,$price,$old,$category,$photo]){$model=CatalogProduct::query()->create(['catalog_category_id'=>$categories[$category]->id,'name'=>$name,'slug'=>Str::slug('nt503-'.$name),'sku'=>'NT503-'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT),'price'=>$price,'original_price'=>$old,'stock'=>$index===6?0:30,'short_description'=>'WOLFBED','detail_content'=>'<p>Chất liệu an toàn, nâng đỡ tối ưu và hoàn thiện tinh tế cho giấc ngủ trọn vẹn.</p>','image_url'=>$asset($photo),'is_featured'=>$index<8,'is_highlight'=>$index<8,'sort_order'=>$index,'is_active'=>true]);$this->record($model);}
            foreach([['WolfBed CUTIE','Êm dịu và nâng niu giấc mơ của con','hero-wolfbed'],['Serene Blooming','Tận hưởng sự mềm mại từ thiên nhiên','bedding']] as $index=>[$title,$summary,$photo]){$model=SiteBanner::query()->create(['theme_key'=>self::THEME_KEY,'placement'=>'nt503-hero-slider','title'=>$title,'subtitle'=>$summary,'image_url'=>$asset($photo),'link_url'=>'#san-pham','badge'=>'WOLFBED','metadata'=>['summary'=>$summary,'button_label'=>'Xem thêm sản phẩm'],'sort_order'=>$index,'is_active'=>true]);$this->record($model);}
            foreach([['Ngọc Vy','Nhân viên văn phòng','Nệm nâng đỡ tốt, chăn ga mềm và đội ngũ tư vấn rất tận tâm.'],['Minh Anh','Kiến trúc sư','Sản phẩm hoàn thiện đẹp, giao hàng nhanh và giấc ngủ cải thiện rõ rệt.']] as $index=>[$name,$role,$quote]){$model=CmsTestimonial::query()->create(['name'=>$name,'role'=>$role,'company'=>'WolfBed customer','quote'=>$quote,'image_url'=>$asset('hero-wolfbed'),'status'=>'published','publish_at'=>now(),'is_featured'=>true,'sort_order'=>$index]);$this->record($model);}
            $postCategory=CmsCategory::query()->create(['name'=>'Góc tư vấn giấc ngủ','slug'=>'nt503-goc-tu-van','description'=>'Kiến thức chọn nệm, tư thế ngủ và chăm sóc chăn ga.']);$this->record($postCategory);
            foreach([['Review các loại nệm cao su thiên nhiên nào tốt?','So sánh độ đàn hồi, khả năng nâng đỡ và độ bền của các dòng nệm cao su.'],['Hết đau lưng với 3 tư thế nằm đơn giản','Ba tư thế giúp cột sống được thư giãn và ngủ sâu hơn mỗi đêm.'],['5 mối liên hệ giữa giấc ngủ và sức khỏe tinh thần','Giấc ngủ chất lượng giúp cơ thể phục hồi và cân bằng cảm xúc.'],['Cách vệ sinh nệm topper nhanh chóng tại nhà','Quy trình đơn giản giúp topper sạch, thơm và bền lâu.']] as $index=>[$title,$excerpt]){$model=CmsPost::query()->create(['category_id'=>$postCategory->id,'title'=>$title,'slug'=>Str::slug('nt503-'.$title),'status'=>'published','excerpt'=>$excerpt,'body'=>'<p>'.$excerpt.'</p>','publish_at'=>now()->subDays($index+1),'is_highlight'=>true]);$this->record($model);}
            $home=route('site.home', [], false);$menu=CmsMenu::query()->create(['name'=>'NT503 Main Menu','location'=>'primary-navigation','items'=>[['label'=>'Trang chủ','url'=>$home],['label'=>'Flash Sale','url'=>$home.'#flash-sale'],['label'=>'Sản phẩm','url'=>$home.'#san-pham'],['label'=>'Tin tức','url'=>route('site.blog.index', [], false)],['label'=>'Giới thiệu','url'=>$home.'#footer'],['label'=>'Liên hệ','url'=>route('site.contact', [], false)],['label'=>'Hệ thống cửa hàng','url'=>$home.'#footer']]]);$this->record($menu);
            $page=CmsPage::query()->firstOrCreate(['slug'=>'contact'],['title'=>'Liên hệ','status'=>'published','excerpt'=>'Liên hệ WolfBed.','body'=>'<p>Đội ngũ WolfBed luôn sẵn sàng tư vấn để bạn có giấc ngủ trọn vẹn.</p>','publish_at'=>now()]);if($page->wasRecentlyCreated)$this->record($page);
            $profile=SiteProfile::query()->firstOrNew();$profile->forceFill(['site_name'=>'WolfBed','website_type'=>'ecommerce','active_theme_key'=>self::THEME_KEY,'branding'=>array_merge((array)$profile->branding,['company_name'=>'WolfBed','company_description'=>'Nệm, chăn ga gối và phụ kiện chính hãng cho giấc ngủ sâu.','support_hotline'=>'1900 6750','support_email'=>'support@sapo.vn','support_location'=>'70 Lữ Gia, Phường 15, Quận 11, Thành phố Hồ Chí Minh'])])->save();
            $existing=LandingPage::query()->where('website_key',$websiteKey)->where('theme_key',self::THEME_KEY)->where('is_home',true)->first();$landing=$this->builder->resolveHome($websiteKey,self::THEME_KEY,true);if($landing&&!$existing)$this->record($landing);
            return ['preset'=>$this->preset(),'counts'=>['categories'=>10,'products'=>12,'banners'=>2,'testimonials'=>2,'post_categories'=>1,'posts'=>4,'pages'=>$page->wasRecentlyCreated?1:0,'menus'=>1,'landing_pages'=>!$existing&&$landing?1:0],'purged'=>$purged];
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
