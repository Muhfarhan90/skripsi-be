<?php

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsiteSocialLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createWebsiteAdminUser(): User
{
    $role = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => 'website-admin@example.com',
    ]);
}

it('returns composed default website home content for public visitors', function () {
    $response = $this->getJson('/api/website/home');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.site_name', 'SkripsiLMS')
        ->assertJsonPath('data.hero_title', 'Belajar lebih cepat,')
        ->assertJsonPath('data.feature_items.0.icon', 'book-open')
        ->assertJsonPath('data.hero_image_url', 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80')
        ->assertJsonPath('data.learning_path_items.0.title', 'Pilih Course')
        ->assertJsonPath('data.footer_links.0.label', 'Tentang Kami')
        ->assertJsonPath('data.faqs.0.question', 'Apakah course bisa diakses lewat HP?')
        ->assertJsonPath('data.faqs.0.category.name', 'General')
        ->assertJsonPath('data.social_links.0.platform', 'instagram')
        ->assertJsonPath('data.bottom_cta_bullets.0', 'Gratis untuk pelajar');

    $this->assertDatabaseHas('faq_categories', [
        'name' => 'General',
    ]);

    $this->assertDatabaseHas('website_pages', [
        'slug' => 'about-us',
        'status' => 'published',
    ]);

    $this->assertDatabaseHas('website_pages', [
        'slug' => 'help-center',
        'status' => 'published',
    ]);
});

it('allows admins to update global website settings only', function () {
    $admin = createWebsiteAdminUser();
    Sanctum::actingAs($admin);

    $this->putJson('/api/admin/website-settings', [
        'site_name' => 'Skripsi Akademi',
        'site_tagline' => 'Belajar terarah untuk masa depan akademik',
        'logo_url' => 'https://example.com/logo.png',
        'footer_text' => 'Copyright {year} {site_name} - Semua hak dilindungi.',
        'contact_email' => 'hello@example.com',
        'contact_phone' => '+628123456789',
        'address' => 'Jakarta, Indonesia',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.site_name', 'Skripsi Akademi')
        ->assertJsonPath('data.logo_url', 'https://example.com/logo.png');

    $this->assertDatabaseHas('website_settings', [
        'site_name' => 'Skripsi Akademi',
        'contact_email' => 'hello@example.com',
    ]);
});

it('handles faq category CRUD and exposes active categories publicly', function () {
    $admin = createWebsiteAdminUser();
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/admin/faq-categories', [
        'name' => 'Payment',
        'is_active' => true,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('data.name', 'Payment');

    $categoryId = $createResponse->json('data.id');

    $this->putJson("/api/admin/faq-categories/{$categoryId}", [
        'name' => 'Payment & Billing',
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Payment & Billing');

    $this->getJson('/api/website/faq-categories')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Payment & Billing']);

    $this->deleteJson("/api/admin/faq-categories/{$categoryId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(FaqCategory::query()->whereKey($categoryId)->exists())->toBeFalse();
});

it('handles social link CRUD and exposes active links publicly', function () {
    $admin = createWebsiteAdminUser();
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/admin/website-social-links', [
        'platform' => 'instagram',
        'label' => 'Instagram',
        'url' => 'https://instagram.com/skripsiakademi',
        'icon' => 'instagram',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('data.platform', 'instagram')
        ->assertJsonPath('data.label', 'Instagram');

    $linkId = $createResponse->json('data.id');

    $this->putJson("/api/admin/website-social-links/{$linkId}", [
        'platform' => 'youtube',
        'label' => 'YouTube',
        'url' => 'https://youtube.com/@skripsiakademi',
        'icon' => 'youtube',
        'sort_order' => 1,
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.label', 'YouTube');

    $this->getJson('/api/website/home')
        ->assertOk()
        ->assertJsonPath('data.social_links.0.label', 'YouTube');

    $this->deleteJson("/api/admin/website-social-links/{$linkId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(WebsiteSocialLink::query()->count())->toBe(0);
});

it('handles FAQ CRUD and hides inactive FAQs from public response', function () {
    $admin = createWebsiteAdminUser();
    Sanctum::actingAs($admin);

    $category = FaqCategory::query()->create([
        'name' => 'Account',
        'is_active' => true,
    ]);

    $createResponse = $this->postJson('/api/admin/faqs', [
        'question' => 'Apakah akun bisa diganti emailnya?',
        'answer' => 'Bisa melalui bantuan admin.',
        'faq_category_id' => $category->id,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('data.question', 'Apakah akun bisa diganti emailnya?')
        ->assertJsonPath('data.category.name', 'Account');

    $faqId = $createResponse->json('data.id');

    $this->getJson('/api/website/faqs')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $faqId,
            'question' => 'Apakah akun bisa diganti emailnya?',
        ]);

    $this->putJson("/api/admin/faqs/{$faqId}", [
        'question' => 'Apakah akun bisa diganti emailnya?',
        'answer' => 'Bisa, dan prosesnya dibantu admin.',
        'faq_category_id' => $category->id,
        'sort_order' => 1,
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->getJson('/api/website/faqs')
        ->assertOk()
        ->assertJsonMissing(['id' => $faqId]);

    expect(Faq::query()->whereKey($faqId)->exists())->toBeTrue();
});

it('handles CMS page publish state for about-us style pages', function () {
    $admin = createWebsiteAdminUser();
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/admin/website-pages', [
        'slug' => 'about-us',
        'title' => 'About Us',
        'excerpt' => 'Tentang platform.',
        'content' => 'Konten about us dari CMS.',
        'status' => 'published',
        'published_at' => null,
    ]);

    $createResponse->assertOk()
        ->assertJsonPath('data.slug', 'about-us')
        ->assertJsonPath('data.status', 'published');

    $pageId = $createResponse->json('data.id');

    $this->getJson('/api/website/pages/about-us')
        ->assertOk()
        ->assertJsonPath('data.content', 'Konten about us dari CMS.');

    $this->putJson("/api/admin/website-pages/{$pageId}", [
        'slug' => 'about-us',
        'title' => 'About Us',
        'excerpt' => 'Tentang platform.',
        'content' => 'Konten draft.',
        'status' => 'draft',
        'published_at' => null,
    ])->assertOk()
        ->assertJsonPath('data.status', 'draft');

    $this->getJson('/api/website/pages/about-us')
        ->assertNotFound();

    expect(WebsitePage::query()->where('slug', 'about-us')->exists())->toBeTrue();
});

it('updates landing sections with modular section items and explicit secondary ctas', function () {
    $admin = createWebsiteAdminUser();
    Sanctum::actingAs($admin);

    $this->getJson('/api/website/home')->assertOk();

    $section = WebsiteSection::query()->where('section_key', 'cta')->firstOrFail();

    $this->putJson("/api/admin/website-sections/{$section->id}", [
        'page_key' => 'home',
        'section_key' => 'cta',
        'eyebrow' => null,
        'title' => 'Mulai belajar hari ini',
        'subtitle' => null,
        'body' => 'Semua CTA kini dikelola dengan field eksplisit.',
        'image_url' => null,
        'cta_label' => 'Daftar sekarang',
        'cta_url' => '/register',
        'secondary_cta_label' => 'Masuk',
        'secondary_cta_url' => '/login',
        'sort_order' => 6,
        'is_active' => true,
        'items' => [
            [
                'title' => 'Gratis untuk pelajar',
                'description' => 'Pendaftaran awal tanpa biaya.',
                'icon' => 'book-open',
                'sort_order' => 1,
                'is_active' => true,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.title', 'Mulai belajar hari ini')
        ->assertJsonPath('data.secondary_cta_label', 'Masuk')
        ->assertJsonPath('data.items.0.title', 'Gratis untuk pelajar');

    $this->getJson('/api/website/home')
        ->assertOk()
        ->assertJsonPath('data.bottom_cta_title', 'Mulai belajar hari ini')
        ->assertJsonPath('data.bottom_cta_secondary_label', 'Masuk')
        ->assertJsonPath('data.bottom_cta_bullets.0', 'Gratis untuk pelajar');
});
