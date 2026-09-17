# CMS post tags

CMS 0.2.7 adds website-scoped tags to posts. `cms_tags` owns a tag's source name and slug; `cms_post_tag` links posts to reusable tags. The unique website/name key removes case-insensitive duplicates, and slug collisions receive a deterministic suffix. Deleting a post removes its links, not shared tags. `meta_keywords` remains independent.

## Editing

The post form uses Ant Design Select in tags mode. Enter creates a pending tag; saving the post creates/reuses tags and synchronizes links in one transaction. At most 20 names, each at most 80 characters, are accepted. Existing clients that omit `tags` preserve existing relationships; `tags: []` clears them. Suggestions and links are scoped to the current website.

Relationships are edited in the source language and shared across translations. When editing an existing post in another language, **Dịch tên tags** opens translation of the attached tags. These translations are shared by every post using that tag. Draft save and publication use the existing CMS permission and translation workflow, with name/slug registered as the `cms_tag` resource. Source tag creation uses the existing model localization observer; translated slug history and canonical paths use `localized_routes`.

## Public pages

`/{locale}/tags/{slug}` (`site.blog.tag`) renders a paginated post archive with 10 posts per page. It filters by website, published status, publish time, and published/current translation before pagination. Unknown tags return 404. Existing empty tags render an empty archive. Old translated slugs redirect to their canonical URL. Canonical URLs retain the page number; first pages expose available locale alternates.

NEWS88 renders tags below the post body and reuses its post cards for archives. Other themes receive the same archive listing data via the existing listing renderer. No tag queries are performed by theme templates.

## Deployment

Deploy the new source and apply this additive migration before serving requests to the new admin form. On an existing CMS installation, apply the single migration without re-seeding content:

```bash
php artisan migrate --path=modules/Cms/database/migrations/2026_09_17_000001_create_cms_tags.php --force
php artisan config:clear
php artisan route:clear
npm run build
```

Fresh CMS installation includes the migration through the normal module lifecycle. The CMS manifest is versioned at 0.2.7. No backfill from meta_keywords is performed, and existing posts start with no tags.

## Verification

```bash
php vendor/bin/phpunit tests/Feature/CmsPostTagsTest.php tests/Feature/News88ThemeTest.php tests/Feature/FreshProductionInstallTest.php
node node_modules/@playwright/test/cli.js test tests/browser/cms-post-tags.spec.js --output=storage/framework/testing/cms-post-tags
```

The browser regression runs the compiled React app with synthetic API responses. PHP feature tests use an isolated test database, covering tag identity, normalization, validation, website isolation, archives, publication workflow and translated URL redirects.
