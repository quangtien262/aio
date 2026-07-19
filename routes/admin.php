<?php

use App\Http\Controllers\Admin\AdminShellController;
use App\Http\Controllers\Admin\Api\AccessControlIndexController;
use App\Http\Controllers\Admin\Api\AdminAccountController;
use App\Http\Controllers\Admin\Api\AdminCurrentProfileController;
use App\Http\Controllers\Admin\Api\AdminRoleAssignmentController;
use App\Http\Controllers\Admin\Api\Catalog\ProductManagementController;
use App\Http\Controllers\Admin\Api\Catalog\ProductIndexController;
use App\Http\Controllers\Admin\Api\Catalog\CategoryIndexController as CatalogCategoryIndexController;
use App\Http\Controllers\Admin\Api\Catalog\CategoryManagementController as CatalogCategoryManagementController;
use App\Http\Controllers\Admin\Api\Cms\CategoryIndexController;
use App\Http\Controllers\Admin\Api\Cms\CategoryManagementController;
use App\Http\Controllers\Admin\Api\Cms\FeaturedCategoryIndexController;
use App\Http\Controllers\Admin\Api\Cms\FeaturedCategoryManagementController;
use App\Http\Controllers\Admin\Api\Cms\MediaIndexController;
use App\Http\Controllers\Admin\Api\Cms\MediaManagementController;
use App\Http\Controllers\Admin\Api\Cms\MenuIndexController;
use App\Http\Controllers\Admin\Api\Cms\MenuLocationController;
use App\Http\Controllers\Admin\Api\Cms\MenuManagementController;
use App\Http\Controllers\Admin\Api\Cms\PageManagementController;
use App\Http\Controllers\Admin\Api\Cms\PageIndexController;
use App\Http\Controllers\Admin\Api\Cms\PartnerIndexController;
use App\Http\Controllers\Admin\Api\Cms\PartnerManagementController;
use App\Http\Controllers\Admin\Api\Cms\PostIndexController;
use App\Http\Controllers\Admin\Api\Cms\PostManagementController;
use App\Http\Controllers\Admin\Api\Cms\ProjectIndexController as CmsProjectIndexController;
use App\Http\Controllers\Admin\Api\Cms\ProjectCategoryIndexController;
use App\Http\Controllers\Admin\Api\Cms\ProjectCategoryManagementController;
use App\Http\Controllers\Admin\Api\Cms\ProjectManagementController as CmsProjectManagementController;
use App\Http\Controllers\Admin\Api\Cms\ServiceIndexController;
use App\Http\Controllers\Admin\Api\Cms\ServiceCategoryIndexController;
use App\Http\Controllers\Admin\Api\Cms\ServiceCategoryManagementController;
use App\Http\Controllers\Admin\Api\Cms\ServiceManagementController;
use App\Http\Controllers\Admin\Api\Cms\SidePromoIndexController;
use App\Http\Controllers\Admin\Api\Cms\SidePromoManagementController;
use App\Http\Controllers\Admin\Api\Cms\TeamMemberIndexController;
use App\Http\Controllers\Admin\Api\Cms\TeamMemberManagementController;
use App\Http\Controllers\Admin\Api\Cms\TestimonialIndexController;
use App\Http\Controllers\Admin\Api\Cms\TestimonialManagementController;
use App\Http\Controllers\Admin\Api\DashboardController;
use App\Http\Controllers\Admin\Api\Inventory\BarcodeLookupController as InventoryBarcodeLookupController;
use App\Http\Controllers\Admin\Api\Inventory\BatchIndexController as InventoryBatchIndexController;
use App\Http\Controllers\Admin\Api\Inventory\InventoryDashboardController;
use App\Http\Controllers\Admin\Api\Inventory\ItemIndexController as InventoryItemIndexController;
use App\Http\Controllers\Admin\Api\Inventory\ItemManagementController as InventoryItemManagementController;
use App\Http\Controllers\Admin\Api\Inventory\LocationIndexController as InventoryLocationIndexController;
use App\Http\Controllers\Admin\Api\Inventory\LocationManagementController as InventoryLocationManagementController;
use App\Http\Controllers\Admin\Api\Inventory\ProductSyncController as InventoryProductSyncController;
use App\Http\Controllers\Admin\Api\Inventory\ReplenishmentIndexController as InventoryReplenishmentIndexController;
use App\Http\Controllers\Admin\Api\Inventory\SerialNumberIndexController as InventorySerialNumberIndexController;
use App\Http\Controllers\Admin\Api\Inventory\StockBalanceIndexController as InventoryStockBalanceIndexController;
use App\Http\Controllers\Admin\Api\Inventory\StockDocumentIndexController as InventoryStockDocumentIndexController;
use App\Http\Controllers\Admin\Api\Inventory\StockDocumentManagementController as InventoryStockDocumentManagementController;
use App\Http\Controllers\Admin\Api\Inventory\StockMovementIndexController as InventoryStockMovementIndexController;
use App\Http\Controllers\Admin\Api\Inventory\WarehouseIndexController as InventoryWarehouseIndexController;
use App\Http\Controllers\Admin\Api\Inventory\WarehouseManagementController as InventoryWarehouseManagementController;
use App\Http\Controllers\Admin\Api\LandingPageBlockController;
use App\Http\Controllers\Admin\Api\LandingPageController;
use App\Http\Controllers\Admin\Api\ModuleLifecycleController;
use App\Http\Controllers\Admin\Api\ModuleRegistryController;
use App\Http\Controllers\Admin\Api\NewsletterSubscriberIndexController;
use App\Http\Controllers\Admin\Api\NewsletterSubscriberManagementController;
use App\Http\Controllers\Admin\Api\OrderIndexController;
use App\Http\Controllers\Admin\Api\OrderManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectChecklistManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectDetailController;
use App\Http\Controllers\Admin\Api\Project\ProjectFileManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectIndexController;
use App\Http\Controllers\Admin\Api\Project\ProjectManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectMemberManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectReportIndexController;
use App\Http\Controllers\Admin\Api\Project\ProjectReportManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectTaskChecklistManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectTaskCommentManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectTaskIndexController;
use App\Http\Controllers\Admin\Api\Project\ProjectTaskManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectTaskStatusManagementController;
use App\Http\Controllers\Admin\Api\Project\ProjectTaskTimeEntryManagementController;
use App\Http\Controllers\Admin\Api\RoleManagementController;
use App\Http\Controllers\Admin\Api\SetupProfileController;
use App\Http\Controllers\Admin\Api\SetupStepController;
use App\Http\Controllers\Admin\Api\SetupWizardStateController;
use App\Http\Controllers\Admin\Api\SiteBannerIndexController;
use App\Http\Controllers\Admin\Api\SiteBannerManagementController;
use App\Http\Controllers\Admin\Api\SiteMappingController;
use App\Http\Controllers\Admin\Api\ThemeActivationController;
use App\Http\Controllers\Admin\Api\ThemeDemoDataController;
use App\Http\Controllers\Admin\Api\ThemePaletteController;
use App\Http\Controllers\Admin\Api\ThemeRegistryController;
use App\Http\Controllers\Admin\Api\ThemeLocaleController;
use App\Http\Controllers\Admin\Api\ThemeTranslationIndexController;
use App\Http\Controllers\Admin\Api\ThemeTranslationManagementController;
use App\Http\Controllers\Admin\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::middleware('auth:admin')->group(function (): void {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');

            Route::prefix('api')->name('api.')->group(function (): void {
                Route::get('/me', AdminCurrentProfileController::class)
                    ->name('me');
                Route::get('/dashboard', DashboardController::class)
                    ->middleware('admin.permission:platform.dashboard.view')
                    ->name('dashboard');
                Route::get('/orders', OrderIndexController::class)
                    ->middleware('admin.permission:platform.dashboard.view')
                    ->name('orders.index');
                Route::get('/newsletter-subscribers', NewsletterSubscriberIndexController::class)
                    ->middleware('admin.permission:platform.dashboard.view')
                    ->name('newsletter-subscribers.index');
                Route::put('/newsletter-subscribers/{subscriber}', [NewsletterSubscriberManagementController::class, 'update'])
                    ->middleware('admin.permission:platform.dashboard.view')
                    ->name('newsletter-subscribers.update');
                Route::delete('/newsletter-subscribers/{subscriber}', [NewsletterSubscriberManagementController::class, 'destroy'])
                    ->middleware('admin.permission:platform.dashboard.view')
                    ->name('newsletter-subscribers.destroy');
                Route::get('/access', AccessControlIndexController::class)
                    ->middleware('admin.permission:rbac.role.view')
                    ->name('access.index');
                Route::post('/roles', [RoleManagementController::class, 'store'])
                    ->middleware('admin.permission:rbac.role.manage')
                    ->name('roles.store');
                Route::put('/roles/{role}', [RoleManagementController::class, 'update'])
                    ->middleware('admin.permission:rbac.role.manage')
                    ->name('roles.update');
                Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])
                    ->middleware('admin.permission:rbac.role.manage')
                    ->name('roles.destroy');
                Route::put('/admins/{admin}/roles', AdminRoleAssignmentController::class)
                    ->middleware('admin.permission:rbac.permission.assign')
                    ->name('admins.roles.update');
                Route::get('/admins', [AdminAccountController::class, 'index'])
                    ->middleware('admin.permission:admin.account.view')
                    ->name('admins.index');
                Route::post('/admins', [AdminAccountController::class, 'store'])
                    ->middleware('admin.permission:admin.account.manage')
                    ->name('admins.store');
                Route::put('/admins/{admin}', [AdminAccountController::class, 'update'])
                    ->middleware('admin.permission:admin.account.manage')
                    ->name('admins.update');
                Route::put('/admins/{admin}/password', [AdminAccountController::class, 'resetPassword'])
                    ->middleware('admin.permission:admin.account.reset_password')
                    ->name('admins.password.reset');
                Route::post('/admins/{admin}/lock', [AdminAccountController::class, 'lock'])
                    ->middleware('admin.permission:admin.account.lock')
                    ->name('admins.lock');
                Route::post('/admins/{admin}/unlock', [AdminAccountController::class, 'unlock'])
                    ->middleware('admin.permission:admin.account.lock')
                    ->name('admins.unlock');
                Route::get('/modules', ModuleRegistryController::class)
                    ->middleware('admin.permission:store.module.view')
                    ->name('modules');
                Route::get('/inventory/dashboard', InventoryDashboardController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.dashboard');
                Route::get('/inventory/warehouses', InventoryWarehouseIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.warehouses.index');
                Route::post('/inventory/warehouses', [InventoryWarehouseManagementController::class, 'store'])
                    ->middleware('admin.permission:inventory.warehouse.manage')
                    ->name('inventory.warehouses.store');
                Route::put('/inventory/warehouses/{warehouse}', [InventoryWarehouseManagementController::class, 'update'])
                    ->middleware('admin.permission:inventory.warehouse.manage')
                    ->name('inventory.warehouses.update');
                Route::delete('/inventory/warehouses/{warehouse}', [InventoryWarehouseManagementController::class, 'destroy'])
                    ->middleware('admin.permission:inventory.warehouse.manage')
                    ->name('inventory.warehouses.destroy');
                Route::get('/inventory/locations', InventoryLocationIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.locations.index');
                Route::post('/inventory/locations', [InventoryLocationManagementController::class, 'store'])
                    ->middleware('admin.permission:inventory.location.manage')
                    ->name('inventory.locations.store');
                Route::put('/inventory/locations/{location}', [InventoryLocationManagementController::class, 'update'])
                    ->middleware('admin.permission:inventory.location.manage')
                    ->name('inventory.locations.update');
                Route::delete('/inventory/locations/{location}', [InventoryLocationManagementController::class, 'destroy'])
                    ->middleware('admin.permission:inventory.location.manage')
                    ->name('inventory.locations.destroy');
                Route::get('/inventory/items', InventoryItemIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.items.index');
                Route::put('/inventory/items/{item}', [InventoryItemManagementController::class, 'update'])
                    ->middleware('admin.permission:inventory.item.manage')
                    ->name('inventory.items.update');
                Route::post('/inventory/items/sync-products', InventoryProductSyncController::class)
                    ->middleware('admin.permission:inventory.item.sync')
                    ->name('inventory.items.sync-products');
                Route::get('/inventory/batches', InventoryBatchIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.batches.index');
                Route::get('/inventory/serial-numbers', InventorySerialNumberIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.serial-numbers.index');
                Route::get('/inventory/replenishment', InventoryReplenishmentIndexController::class)
                    ->middleware('admin.permission:inventory.replenishment.view')
                    ->name('inventory.replenishment.index');
                Route::get('/inventory/barcode-lookup', InventoryBarcodeLookupController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.barcode-lookup');
                Route::get('/inventory/balances', InventoryStockBalanceIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.balances.index');
                Route::get('/inventory/documents', InventoryStockDocumentIndexController::class)
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.documents.index');
                Route::post('/inventory/documents', [InventoryStockDocumentManagementController::class, 'store'])
                    ->middleware('admin.permission:inventory.view')
                    ->name('inventory.documents.store');
                Route::get('/inventory/movements', InventoryStockMovementIndexController::class)
                    ->middleware('admin.permission:inventory.report.view')
                    ->name('inventory.movements.index');
                Route::get('/themes/{key}/translations', ThemeTranslationIndexController::class)
                    ->middleware('admin.permission:theme.view')
                    ->name('themes.translations.index');
                Route::put('/themes/{key}/palette', ThemePaletteController::class)
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.palette.update');
                Route::put('/themes/{key}/translations/{locale}', [ThemeTranslationManagementController::class, 'update'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.translations.update');
                Route::get('/themes/locales', [ThemeLocaleController::class, 'index'])
                    ->middleware('admin.permission:theme.view')
                    ->name('themes.locales.index');
                Route::post('/themes/locales', [ThemeLocaleController::class, 'store'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.locales.store');
                Route::put('/themes/locales/{code}', [ThemeLocaleController::class, 'update'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.locales.update');
                Route::post('/modules/{key}/install', [ModuleLifecycleController::class, 'install'])
                    ->middleware('admin.permission:store.module.install')
                    ->name('modules.install');
                Route::post('/modules/{key}/enable', [ModuleLifecycleController::class, 'enable'])
                    ->middleware('admin.permission:store.module.enable')
                    ->name('modules.enable');
                Route::post('/modules/{key}/disable', [ModuleLifecycleController::class, 'disable'])
                    ->middleware('admin.permission:store.module.disable')
                    ->name('modules.disable');
                Route::post('/modules/{key}/upgrade', [ModuleLifecycleController::class, 'upgrade'])
                    ->middleware('admin.permission:store.module.upgrade')
                    ->name('modules.upgrade');
                Route::post('/modules/{key}/demo-data', [ModuleLifecycleController::class, 'demoData'])
                    ->middleware('admin.permission:store.module.upgrade')
                    ->name('modules.demo-data');
                Route::delete('/modules/{key}', [ModuleLifecycleController::class, 'uninstall'])
                    ->middleware('admin.permission:store.module.uninstall')
                    ->name('modules.uninstall');
                Route::get('/project/projects', ProjectIndexController::class)
                    ->middleware('admin.permission:project.view')
                    ->name('project.projects.index');
                Route::post('/project/projects', [ProjectManagementController::class, 'store'])
                    ->middleware('admin.permission:project.create')
                    ->name('project.projects.store');
                Route::get('/project/projects/{project}', ProjectDetailController::class)
                    ->middleware('admin.permission:project.view')
                    ->name('project.projects.show');
                Route::put('/project/projects/{project}', [ProjectManagementController::class, 'update'])
                    ->middleware('admin.permission:project.update')
                    ->name('project.projects.update');
                Route::delete('/project/projects/{project}', [ProjectManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.delete')
                    ->name('project.projects.destroy');
                Route::post('/project/projects/{project}/task-statuses', [ProjectTaskStatusManagementController::class, 'store'])
                    ->middleware('admin.permission:project.update')
                    ->name('project.task-statuses.store');
                Route::put('/project/task-statuses/{status}', [ProjectTaskStatusManagementController::class, 'update'])
                    ->middleware('admin.permission:project.update')
                    ->name('project.task-statuses.update');
                Route::delete('/project/task-statuses/{status}', [ProjectTaskStatusManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.update')
                    ->name('project.task-statuses.destroy');
                Route::put('/project/projects/{project}/task-statuses/reorder', [ProjectTaskStatusManagementController::class, 'reorder'])
                    ->middleware('admin.permission:project.update')
                    ->name('project.task-statuses.reorder');
                Route::get('/project/tasks', ProjectTaskIndexController::class)
                    ->middleware('admin.permission:project.task.view')
                    ->name('project.tasks.index');
                Route::post('/project/projects/{project}/tasks', [ProjectTaskManagementController::class, 'store'])
                    ->middleware('admin.permission:project.task.create')
                    ->name('project.tasks.store');
                Route::put('/project/tasks/{task}', [ProjectTaskManagementController::class, 'update'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.tasks.update');
                Route::delete('/project/tasks/{task}', [ProjectTaskManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.task.delete')
                    ->name('project.tasks.destroy');
                Route::post('/project/tasks/{task}/checklists', [ProjectTaskChecklistManagementController::class, 'store'])
                    ->middleware('admin.permission:project.checklist.manage')
                    ->name('project.task-checklists.store');
                Route::put('/project/task-checklists/{checklist}', [ProjectTaskChecklistManagementController::class, 'update'])
                    ->middleware('admin.permission:project.checklist.manage')
                    ->name('project.task-checklists.update');
                Route::delete('/project/task-checklists/{checklist}', [ProjectTaskChecklistManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.checklist.manage')
                    ->name('project.task-checklists.destroy');
                Route::post('/project/tasks/{task}/comments', [ProjectTaskCommentManagementController::class, 'store'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.task-comments.store');
                Route::put('/project/task-comments/{comment}', [ProjectTaskCommentManagementController::class, 'update'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.task-comments.update');
                Route::delete('/project/task-comments/{comment}', [ProjectTaskCommentManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.task-comments.destroy');
                Route::post('/project/tasks/{task}/time-entries', [ProjectTaskTimeEntryManagementController::class, 'store'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.task-time-entries.store');
                Route::put('/project/task-time-entries/{entry}', [ProjectTaskTimeEntryManagementController::class, 'update'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.task-time-entries.update');
                Route::delete('/project/task-time-entries/{entry}', [ProjectTaskTimeEntryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.task.update')
                    ->name('project.task-time-entries.destroy');
                Route::post('/project/projects/{project}/checklists', [ProjectChecklistManagementController::class, 'store'])
                    ->middleware('admin.permission:project.checklist.manage')
                    ->name('project.checklists.store');
                Route::put('/project/checklists/{checklist}', [ProjectChecklistManagementController::class, 'update'])
                    ->middleware('admin.permission:project.checklist.manage')
                    ->name('project.checklists.update');
                Route::delete('/project/checklists/{checklist}', [ProjectChecklistManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.checklist.manage')
                    ->name('project.checklists.destroy');
                Route::post('/project/projects/{project}/members', [ProjectMemberManagementController::class, 'store'])
                    ->middleware('admin.permission:project.member.manage')
                    ->name('project.members.store');
                Route::delete('/project/members/{member}', [ProjectMemberManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.member.manage')
                    ->name('project.members.destroy');
                Route::post('/project/projects/{project}/files', [ProjectFileManagementController::class, 'store'])
                    ->middleware('admin.permission:project.file.manage')
                    ->name('project.files.store');
                Route::get('/project/files/{file}/download', [ProjectFileManagementController::class, 'download'])
                    ->middleware('admin.permission:project.view')
                    ->name('project.files.download');
                Route::delete('/project/files/{file}', [ProjectFileManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.file.manage')
                    ->name('project.files.destroy');
                Route::get('/project/reports', ProjectReportIndexController::class)
                    ->middleware('admin.permission:project.report.view')
                    ->name('project.reports.index');
                Route::post('/project/projects/{project}/reports', [ProjectReportManagementController::class, 'store'])
                    ->middleware('admin.permission:project.report.create')
                    ->name('project.reports.store');
                Route::put('/project/reports/{report}', [ProjectReportManagementController::class, 'update'])
                    ->middleware('admin.permission:project.report.update')
                    ->name('project.reports.update');
                Route::delete('/project/reports/{report}', [ProjectReportManagementController::class, 'destroy'])
                    ->middleware('admin.permission:project.report.delete')
                    ->name('project.reports.destroy');
                Route::get('/cms/pages', PageIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.pages.index');
                Route::get('/landing/pages', [LandingPageController::class, 'index'])
                    ->middleware('admin.permission:cms.view')
                    ->name('landing.pages.index');
                Route::post('/landing/pages', [LandingPageController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('landing.pages.store');
                Route::put('/landing/pages/{landingPage}', [LandingPageController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('landing.pages.update');
                Route::delete('/landing/pages/{landingPage}', [LandingPageController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('landing.pages.destroy');
                Route::get('/landing/pages/{landingPage}/blocks', [LandingPageBlockController::class, 'index'])
                    ->middleware('admin.permission:cms.view')
                    ->name('landing.pages.blocks.index');
                Route::post('/landing/pages/{landingPage}/blocks', [LandingPageBlockController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('landing.pages.blocks.store');
                Route::put('/landing/pages/{landingPage}/blocks/reorder', [LandingPageBlockController::class, 'reorder'])
                    ->middleware('admin.permission:cms.update')
                    ->name('landing.pages.blocks.reorder');
                Route::put('/landing/blocks/{block}', [LandingPageBlockController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('landing.blocks.update');
                Route::get('/landing/blocks/{block}/source-preview', [LandingPageBlockController::class, 'sourcePreview'])
                    ->middleware('admin.permission:cms.update')
                    ->name('landing.blocks.source-preview');
                Route::delete('/landing/blocks/{block}', [LandingPageBlockController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('landing.blocks.destroy');
                Route::post('/cms/pages', [PageManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.pages.store');
                Route::put('/cms/pages/{page}', [PageManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.pages.update');
                Route::delete('/cms/pages/{page}', [PageManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.pages.destroy');
                Route::get('/cms/posts', PostIndexController::class)
                    ->middleware('admin.permission:cms.post.view')
                    ->name('cms.posts.index');
                Route::post('/cms/posts', [PostManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.post.create')
                    ->name('cms.posts.store');
                Route::put('/cms/posts/bulk', [PostManagementController::class, 'bulkUpdate'])
                    ->middleware('admin.permission:cms.post.update')
                    ->name('cms.posts.bulk-update');
                Route::delete('/cms/posts/bulk', [PostManagementController::class, 'bulkDestroy'])
                    ->middleware('admin.permission:cms.post.delete')
                    ->name('cms.posts.bulk-destroy');
                Route::put('/cms/posts/{post}', [PostManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.post.update')
                    ->name('cms.posts.update');
                Route::delete('/cms/posts/{post}', [PostManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.post.delete')
                    ->name('cms.posts.destroy');
                Route::get('/cms/services', ServiceIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.services.index');
                Route::get('/cms/service-categories', ServiceCategoryIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.service-categories.index');
                Route::post('/cms/service-categories', [ServiceCategoryManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.service-categories.store');
                Route::put('/cms/service-categories/{category}', [ServiceCategoryManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.service-categories.update');
                Route::delete('/cms/service-categories/{category}', [ServiceCategoryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.service-categories.destroy');
                Route::post('/cms/services', [ServiceManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.services.store');
                Route::put('/cms/services/{service}', [ServiceManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.services.update');
                Route::delete('/cms/services/{service}', [ServiceManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.services.destroy');
                Route::get('/cms/projects', CmsProjectIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.projects.index');
                Route::get('/cms/project-categories', ProjectCategoryIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.project-categories.index');
                Route::post('/cms/project-categories', [ProjectCategoryManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.project-categories.store');
                Route::put('/cms/project-categories/{category}', [ProjectCategoryManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.project-categories.update');
                Route::delete('/cms/project-categories/{category}', [ProjectCategoryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.project-categories.destroy');
                Route::post('/cms/projects', [CmsProjectManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.projects.store');
                Route::put('/cms/projects/bulk', [CmsProjectManagementController::class, 'bulkUpdate'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.projects.bulk-update');
                Route::delete('/cms/projects/bulk', [CmsProjectManagementController::class, 'bulkDestroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.projects.bulk-destroy');
                Route::put('/cms/projects/{project}', [CmsProjectManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.projects.update');
                Route::delete('/cms/projects/{project}', [CmsProjectManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.projects.destroy');
                Route::get('/cms/testimonials', TestimonialIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.testimonials.index');
                Route::post('/cms/testimonials', [TestimonialManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.testimonials.store');
                Route::put('/cms/testimonials/bulk', [TestimonialManagementController::class, 'bulkUpdate'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.testimonials.bulk-update');
                Route::delete('/cms/testimonials/bulk', [TestimonialManagementController::class, 'bulkDestroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.testimonials.bulk-destroy');
                Route::put('/cms/testimonials/{testimonial}', [TestimonialManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.testimonials.update');
                Route::delete('/cms/testimonials/{testimonial}', [TestimonialManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.testimonials.destroy');
                Route::get('/cms/team-members', TeamMemberIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.team-members.index');
                Route::post('/cms/team-members', [TeamMemberManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.team-members.store');
                Route::put('/cms/team-members/bulk', [TeamMemberManagementController::class, 'bulkUpdate'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.team-members.bulk-update');
                Route::delete('/cms/team-members/bulk', [TeamMemberManagementController::class, 'bulkDestroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.team-members.bulk-destroy');
                Route::put('/cms/team-members/{member}', [TeamMemberManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.team-members.update');
                Route::delete('/cms/team-members/{member}', [TeamMemberManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.team-members.destroy');
                Route::get('/cms/partners', PartnerIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.partners.index');
                Route::post('/cms/partners', [PartnerManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.create')
                    ->name('cms.partners.store');
                Route::put('/cms/partners/bulk', [PartnerManagementController::class, 'bulkUpdate'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.partners.bulk-update');
                Route::delete('/cms/partners/bulk', [PartnerManagementController::class, 'bulkDestroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.partners.bulk-destroy');
                Route::put('/cms/partners/{partner}', [PartnerManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.update')
                    ->name('cms.partners.update');
                Route::delete('/cms/partners/{partner}', [PartnerManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.delete')
                    ->name('cms.partners.destroy');
                Route::get('/cms/categories', CategoryIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.categories.index');
                Route::post('/cms/categories', [CategoryManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.category.manage')
                    ->name('cms.categories.store');
                Route::put('/cms/categories/{category}', [CategoryManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.category.manage')
                    ->name('cms.categories.update');
                Route::delete('/cms/categories/{category}', [CategoryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.category.manage')
                    ->name('cms.categories.destroy');
                Route::get('/cms/menus', MenuIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.menus.index');
                Route::get('/cms/featured-categories', FeaturedCategoryIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.featured-categories.index');
                Route::get('/cms/menu-locations', [MenuLocationController::class, 'index'])
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.menu-locations.index');
                Route::post('/cms/menu-locations', [MenuLocationController::class, 'store'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.menu-locations.store');
                Route::put('/cms/menu-locations/{location}', [MenuLocationController::class, 'update'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.menu-locations.update');
                Route::delete('/cms/menu-locations/{location}', [MenuLocationController::class, 'destroy'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.menu-locations.destroy');
                Route::post('/cms/menus', [MenuManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.menus.store');
                Route::put('/cms/menus/{menu}', [MenuManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.menus.update');
                Route::delete('/cms/menus/{menu}', [MenuManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.menus.destroy');
                Route::post('/cms/featured-categories', [FeaturedCategoryManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.featured-categories.store');
                Route::put('/cms/featured-categories/{featuredCategory}', [FeaturedCategoryManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.featured-categories.update');
                Route::delete('/cms/featured-categories/{featuredCategory}', [FeaturedCategoryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.featured-categories.destroy');
                Route::get('/cms/side-promos', SidePromoIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.side-promos.index');
                Route::post('/cms/side-promos', [SidePromoManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.side-promos.store');
                Route::put('/cms/side-promos/{sidePromo}', [SidePromoManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.side-promos.update');
                Route::delete('/cms/side-promos/{sidePromo}', [SidePromoManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.menu.manage')
                    ->name('cms.side-promos.destroy');
                Route::get('/cms/media', MediaIndexController::class)
                    ->middleware('admin.permission:cms.view')
                    ->name('cms.media.index');
                Route::post('/cms/media', [MediaManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.media.manage')
                    ->name('cms.media.store');
                Route::post('/cms/media/folders', [MediaManagementController::class, 'storeFolder'])
                    ->middleware('admin.permission:cms.media.manage')
                    ->name('cms.media.folders.store');
                Route::put('/cms/media/{media}', [MediaManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.media.manage')
                    ->name('cms.media.update');
                Route::delete('/cms/media/{media}', [MediaManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.media.manage')
                    ->name('cms.media.destroy');
                Route::get('/cms/products', ProductIndexController::class)
                    ->middleware('admin.permission:cms.product.view')
                    ->name('cms.products.index');
                Route::get('/cms/product-categories', CatalogCategoryIndexController::class)
                    ->middleware('admin.permission:cms.product.view')
                    ->name('cms.product-categories.index');
                Route::post('/cms/product-categories', [CatalogCategoryManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.product.create')
                    ->name('cms.product-categories.store');
                Route::put('/cms/product-categories/{category}', [CatalogCategoryManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.product.update')
                    ->name('cms.product-categories.update');
                Route::delete('/cms/product-categories/{category}', [CatalogCategoryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.product.delete')
                    ->name('cms.product-categories.destroy');
                Route::post('/cms/products', [ProductManagementController::class, 'store'])
                    ->middleware('admin.permission:cms.product.create')
                    ->name('cms.products.store');
                Route::put('/cms/products/{product}', [ProductManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.product.update')
                    ->name('cms.products.update');
                Route::delete('/cms/products/{product}', [ProductManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.product.delete')
                    ->name('cms.products.destroy');
                Route::get('/cms/orders', OrderIndexController::class)
                    ->middleware('admin.permission:cms.order.view')
                    ->name('cms.orders.index');
                Route::put('/cms/orders/{order}', [OrderManagementController::class, 'update'])
                    ->middleware('admin.permission:cms.order.view')
                    ->name('cms.orders.update');
                Route::put('/cms/orders/{order}/read', [OrderManagementController::class, 'markRead'])
                    ->middleware('admin.permission:cms.order.view')
                    ->name('cms.orders.read');
                Route::delete('/cms/orders/{order}', [OrderManagementController::class, 'destroy'])
                    ->middleware('admin.permission:cms.order.view')
                    ->name('cms.orders.destroy');
                Route::get('/catalog/products', ProductIndexController::class)
                    ->middleware('admin.permission:catalog.view')
                    ->name('catalog.products.index');
                Route::get('/catalog/categories', CatalogCategoryIndexController::class)
                    ->middleware('admin.permission:catalog.view')
                    ->name('catalog.categories.index');
                Route::post('/catalog/products', [ProductManagementController::class, 'store'])
                    ->middleware('admin.permission:catalog.create')
                    ->name('catalog.products.store');
                Route::post('/catalog/categories', [CatalogCategoryManagementController::class, 'store'])
                    ->middleware('admin.permission:catalog.create')
                    ->name('catalog.categories.store');
                Route::put('/catalog/products/{product}', [ProductManagementController::class, 'update'])
                    ->middleware('admin.permission:catalog.update')
                    ->name('catalog.products.update');
                Route::put('/catalog/categories/{category}', [CatalogCategoryManagementController::class, 'update'])
                    ->middleware('admin.permission:catalog.update')
                    ->name('catalog.categories.update');
                Route::delete('/catalog/products/{product}', [ProductManagementController::class, 'destroy'])
                    ->middleware('admin.permission:catalog.delete')
                    ->name('catalog.products.destroy');
                Route::delete('/catalog/categories/{category}', [CatalogCategoryManagementController::class, 'destroy'])
                    ->middleware('admin.permission:catalog.delete')
                    ->name('catalog.categories.destroy');
                Route::get('/site-banners', SiteBannerIndexController::class)
                    ->middleware('admin.permission:catalog.view')
                    ->name('site-banners.index');
                Route::post('/site-banners', [SiteBannerManagementController::class, 'store'])
                    ->middleware('admin.permission:catalog.create')
                    ->name('site-banners.store');
                Route::put('/site-banners/{banner}', [SiteBannerManagementController::class, 'update'])
                    ->middleware('admin.permission:catalog.update')
                    ->name('site-banners.update');
                Route::delete('/site-banners/{banner}', [SiteBannerManagementController::class, 'destroy'])
                    ->middleware('admin.permission:catalog.delete')
                    ->name('site-banners.destroy');
                Route::get('/themes', ThemeRegistryController::class)
                    ->middleware('admin.permission:theme.view')
                    ->name('themes');
                Route::get('/site-mappings', [SiteMappingController::class, 'index'])
                    ->middleware('admin.permission:theme.view')
                    ->name('site-mappings.index');
                Route::post('/site-mappings', [SiteMappingController::class, 'store'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('site-mappings.store');
                Route::post('/site-mappings/bulk', [SiteMappingController::class, 'bulkStore'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('site-mappings.bulk-store');
                Route::put('/site-mappings/{site}', [SiteMappingController::class, 'update'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('site-mappings.update');
                Route::delete('/site-mappings/{site}', [SiteMappingController::class, 'destroy'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('site-mappings.destroy');
                Route::post('/themes/{key}/avatar', \App\Http\Controllers\Admin\Api\ThemeAvatarController::class)
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.avatar');
                Route::post('/themes/{key}/activate', ThemeActivationController::class)
                    ->middleware('admin.permission:theme.activate')
                    ->name('themes.activate');
                Route::post('/themes/{key}/demo-data', [ThemeDemoDataController::class, 'store'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.demo-data');
                Route::delete('/themes/{key}/demo-data', [ThemeDemoDataController::class, 'destroy'])
                    ->middleware('admin.permission:theme.customize')
                    ->name('themes.demo-data.destroy');
                Route::get('/setup', SetupWizardStateController::class)
                    ->middleware('admin.permission:setup.view')
                    ->name('setup');
                Route::put('/setup', SetupProfileController::class)
                    ->middleware('admin.permission:setup.complete')
                    ->name('setup.update');
                Route::post('/setup/steps/{step}', SetupStepController::class)
                    ->middleware('admin.permission:setup.complete')
                    ->name('setup.steps.complete');
            });

            Route::get('/{any?}', AdminShellController::class)
                ->where('any', '^(?!api(?:/|$)).*')
                ->name('index');
        });
    });
