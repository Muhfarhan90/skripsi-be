<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsiteSetting;
use App\Models\WebsiteSocialLink;
use Illuminate\Support\Collection;

class WebsiteSettingService
{
    public function getSettings(): WebsiteSetting
    {
        return WebsiteSetting::query()->firstOrCreate([], $this->settingsDefaults());
    }

    public function updateSettings(array $payload): WebsiteSetting
    {
        $settings = $this->getSettings();
        $settings->update($payload);

        return $settings->fresh();
    }

    public function getHomePayload(bool $admin = false): array
    {
        $this->ensureDefaultContent();

        $settings = $this->getSettings();
        $sections = WebsiteSection::query()
            ->with('items')
            ->where('page_key', 'home')
            ->when(! $admin, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('section_key');

        $hero = $sections->get('hero');
        $featuredCourses = $sections->get('featured_courses');
        $features = $sections->get('features');
        $learningPaths = $sections->get('learning_paths');
        $faqSection = $sections->get('faq');
        $cta = $sections->get('cta');
        $footerLinks = WebsiteSection::query()
            ->with('items')
            ->where('page_key', 'global')
            ->where('section_key', 'footer_links')
            ->when(! $admin, fn ($query) => $query->where('is_active', true))
            ->first();

        return [
            'id' => $settings->id,
            'site_name' => $settings->site_name,
            'site_tagline' => $settings->site_tagline,
            'logo_url' => $settings->logo_url,
            'footer_text' => $settings->footer_text,
            'contact_email' => $settings->contact_email,
            'contact_phone' => $settings->contact_phone,
            'address' => $settings->address,
            'hero_badge' => $hero?->eyebrow ?? '',
            'hero_title' => $hero?->title ?? '',
            'hero_highlight' => $hero?->subtitle ?? '',
            'hero_description' => $hero?->body ?? '',
            'hero_image_url' => $hero?->image_url,
            'hero_primary_cta_label' => $hero?->cta_label ?? '',
            'hero_primary_cta_url' => $hero?->cta_url ?? '/register',
            'hero_secondary_cta_label' => $hero?->secondary_cta_label ?? '',
            'hero_secondary_cta_url' => $hero?->secondary_cta_url ?? '/courses',
            'featured_courses_badge' => $featuredCourses?->eyebrow ?? '',
            'featured_courses_title' => $featuredCourses?->title ?? '',
            'featured_courses_description' => $featuredCourses?->body ?? '',
            'features_badge' => $features?->eyebrow ?? '',
            'features_title' => $features?->title ?? '',
            'features_description' => $features?->body ?? '',
            'feature_items' => $this->mapSectionItems($features?->items, ['icon', 'title', 'description'])->all(),
            'learning_path_badge' => $learningPaths?->eyebrow ?? '',
            'learning_path_title' => $learningPaths?->title ?? '',
            'learning_path_description' => $learningPaths?->body ?? '',
            'learning_path_items' => $this->mapSectionItems($learningPaths?->items, ['icon', 'title', 'description'])->all(),
            'faq_badge' => $faqSection?->eyebrow ?? '',
            'faq_title' => $faqSection?->title ?? '',
            'faq_description' => $faqSection?->body ?? '',
            'bottom_cta_title' => $cta?->title ?? '',
            'bottom_cta_description' => $cta?->body ?? '',
            'bottom_cta_primary_label' => $cta?->cta_label ?? '',
            'bottom_cta_primary_url' => $cta?->cta_url ?? '/register',
            'bottom_cta_secondary_label' => $cta?->secondary_cta_label ?? '',
            'bottom_cta_secondary_url' => $cta?->secondary_cta_url ?? '/login',
            'bottom_cta_bullets' => $this->mapSectionItems($cta?->items, ['title'])
                ->pluck('title')
                ->filter()
                ->values()
                ->all(),
            'social_links' => WebsiteSocialLink::query()
                ->when(! $admin, fn ($query) => $query->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'platform', 'label', 'url', 'icon', 'sort_order', 'is_active'])
                ->map(fn (WebsiteSocialLink $link) => [
                    'id' => $link->id,
                    'platform' => $link->platform,
                    'label' => $link->label,
                    'url' => $link->url,
                    'icon' => $link->icon,
                    'sort_order' => $link->sort_order,
                    'is_active' => $link->is_active,
                ])
                ->values()
                ->all(),
            'footer_links' => $this->mapFooterLinks($footerLinks?->items),
            'sections' => $sections->values()->all(),
            'faqs' => $this->mapFaqs($admin),
            'created_at' => $settings->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $settings->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function getPublishedPage(string $slug): WebsitePage
    {
        $this->ensureDefaultContent();

        return WebsitePage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();
    }

    public function ensureDefaultContent(): void
    {
        $this->getSettings();
        $this->ensureDefaultHomeSections();
        $this->ensureDefaultFooterLinks();
        $this->ensureDefaultPages();
        $this->ensureDefaultSocialLinks();
        $this->ensureDefaultFaqCategories();
        $this->ensureDefaultFaqs();
        $this->cleanupLegacyDefaultContent();
    }

    private function ensureDefaultHomeSections(): void
    {
        foreach ($this->homeSectionDefaults() as $sectionPayload) {
            $items = $sectionPayload['items'] ?? [];
            unset($sectionPayload['items']);

            $section = WebsiteSection::query()->firstOrCreate(
                [
                    'page_key' => $sectionPayload['page_key'],
                    'section_key' => $sectionPayload['section_key'],
                ],
                $sectionPayload
            );

            if ($section->wasRecentlyCreated) {
                $this->createDefaultSectionItems($section, $items);
            }
        }
    }

    private function ensureDefaultFooterLinks(): void
    {
        $section = WebsiteSection::query()->firstOrCreate(
            [
                'page_key' => 'global',
                'section_key' => 'footer_links',
            ],
            [
                'page_key' => 'global',
                'section_key' => 'footer_links',
                'eyebrow' => 'Navigasi',
                'title' => 'Informasi',
                'body' => 'Link cepat untuk informasi utama platform.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        if ($section->wasRecentlyCreated) {
            $this->createDefaultSectionItems($section, [
                ['title' => 'Tentang Kami', 'url' => '/pages/about-us'],
                ['title' => 'Help Center', 'url' => '/pages/help-center'],
                ['title' => 'Syarat & Ketentuan', 'url' => '/pages/terms'],
                ['title' => 'Kebijakan Privasi', 'url' => '/pages/privacy-policy'],
                ['title' => 'Courses', 'url' => '/courses'],
            ]);
        }
    }

    private function ensureDefaultPages(): void
    {
        foreach ($this->pageDefaults() as $page) {
            WebsitePage::query()->firstOrCreate(['slug' => $page['slug']], $page);
        }
    }

    private function ensureDefaultSocialLinks(): void
    {
        if (WebsiteSocialLink::query()->exists()) {
            return;
        }

        foreach ($this->socialLinkDefaults() as $link) {
            WebsiteSocialLink::query()->create($link);
        }
    }

    private function ensureDefaultFaqCategories(): void
    {
        foreach ($this->faqCategoryDefaults() as $category) {
            FaqCategory::query()->firstOrCreate(['name' => $category['name']], $category);
        }
    }

    private function ensureDefaultFaqs(): void
    {
        if (Faq::query()->exists()) {
            return;
        }

        $categories = FaqCategory::query()
            ->pluck('id', 'name');

        foreach ($this->faqDefaults() as $faq) {
            Faq::query()->create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'faq_category_id' => $categories->get($faq['category_name']),
                'sort_order' => $faq['sort_order'],
                'is_active' => $faq['is_active'],
            ]);
        }
    }

    private function cleanupLegacyDefaultContent(): void
    {
        WebsiteSection::query()
            ->where('page_key', 'home')
            ->where('section_key', 'gallery')
            ->where(function ($query) {
                $query
                    ->where('body', 'like', '%dummy%')
                    ->orWhere('body', 'like', '%Dummy%')
                    ->orWhere('body', 'like', '%Tampilkan dokumentasi kegiatan%');
            })
            ->update(['is_active' => false]);

        WebsiteSection::query()
            ->where('page_key', 'home')
            ->where('section_key', 'stats')
            ->update(['is_active' => false]);

        foreach ($this->pageDefaults() as $page) {
            WebsitePage::query()
                ->where('slug', $page['slug'])
                ->where(function ($query) {
                    $query
                        ->where('content', 'like', '%dummy%')
                        ->orWhere('content', 'like', '%Dummy%');
                })
                ->update([
                    'excerpt' => $page['excerpt'],
                    'content' => $page['content'],
                ]);
        }

        $footerLinks = WebsiteSection::query()
            ->where('page_key', 'global')
            ->where('section_key', 'footer_links')
            ->first();

        $footerLinks?->items()
            ->where('title', 'Katalog Course')
            ->update(['title' => 'Courses']);

        WebsiteSection::query()
            ->where('page_key', 'home')
            ->where('section_key', 'hero')
            ->whereNull('image_url')
            ->update([
                'image_url' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80',
            ]);
    }

    private function createDefaultSectionItems(WebsiteSection $section, array $items): void
    {
        foreach ($items as $index => $item) {
            $section->items()->create([
                ...$item,
                'sort_order' => $item['sort_order'] ?? $index + 1,
                'is_active' => $item['is_active'] ?? true,
            ]);
        }
    }

    private function settingsDefaults(): array
    {
        return [
            'site_name' => 'SkripsiLMS',
            'site_tagline' => 'Platform Belajar Pre-University',
            'logo_url' => null,
            'footer_text' => 'Copyright {year} {site_name} - Platform Belajar Pre-University',
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
        ];
    }

    private function homeSectionDefaults(): array
    {
        return [
            [
                'page_key' => 'home',
                'section_key' => 'hero',
                'eyebrow' => 'Platform Belajar Pre-University',
                'title' => 'Belajar lebih cepat,',
                'subtitle' => 'lebih terstruktur.',
                'body' => 'Akses materi, kuis, diskusi, dan sertifikat dalam satu platform. Dirancang untuk persiapan masuk universitas terbaik.',
                'image_url' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80',
                'cta_label' => 'Mulai Belajar Gratis',
                'cta_url' => '/register',
                'secondary_cta_label' => 'Lihat Course',
                'secondary_cta_url' => '/courses',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'page_key' => 'home',
                'section_key' => 'featured_courses',
                'eyebrow' => 'Course Pilihan',
                'title' => 'Mulai dari sini',
                'body' => 'Jelajahi course terpopuler dan mulai dari materi yang paling relevan untuk targetmu.',
                'cta_label' => 'Lihat semua',
                'cta_url' => '/courses',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'page_key' => 'home',
                'section_key' => 'features',
                'eyebrow' => 'Kenapa Belajar di Sini?',
                'title' => 'Semua yang kamu butuhkan, dalam satu platform',
                'body' => 'Materi, diskusi, kuis, dan sertifikat tersedia dalam alur belajar yang rapi.',
                'sort_order' => 3,
                'is_active' => true,
                'items' => [
                    [
                        'icon' => 'book-open',
                        'title' => 'Materi Terstruktur',
                        'description' => 'Kurikulum disusun sistematis dari dasar hingga mahir, dengan video, teks, dan latihan soal di setiap sesi.',
                    ],
                    [
                        'icon' => 'message-square-text',
                        'title' => 'Forum Diskusi Aktif',
                        'description' => 'Setiap course punya ruang diskusi sendiri. Tanya langsung, jawab bareng, atau berdiskusi dengan instructor.',
                    ],
                    [
                        'icon' => 'award',
                        'title' => 'Sertifikat Resmi',
                        'description' => 'Dapatkan sertifikat digital setelah menyelesaikan course dan semua syarat kelulusan terpenuhi.',
                    ],
                    [
                        'icon' => 'smartphone',
                        'title' => 'Bisa Akses Lewat HP',
                        'description' => 'Platform dioptimalkan sebagai PWA. Install ke homescreen HP kamu dan belajar kapan saja, di mana saja.',
                    ],
                    [
                        'icon' => 'graduation-cap',
                        'title' => 'Progress Terpantau',
                        'description' => 'Lihat progres belajar kamu per lesson. Lanjutkan dari terakhir kamu berhenti dengan mudah.',
                    ],
                    [
                        'icon' => 'monitor',
                        'title' => 'Kuis & Tugas Interaktif',
                        'description' => 'Uji pemahaman kamu lewat kuis pilihan ganda dan tugas submission yang dikoreksi oleh instructor.',
                    ],
                ],
            ],
            [
                'page_key' => 'home',
                'section_key' => 'learning_paths',
                'eyebrow' => 'Alur Belajar',
                'title' => 'Mulai dari jalur yang paling sesuai dengan targetmu',
                'body' => 'Pilih course, ikuti materi bertahap, diskusikan kendala, lalu pantau progress sampai sertifikat diterbitkan.',
                'sort_order' => 4,
                'is_active' => true,
                'items' => [
                    [
                        'icon' => 'book-open',
                        'title' => 'Pilih Course',
                        'description' => 'Temukan course berdasarkan kategori, kebutuhan belajar, dan instructor yang tersedia.',
                    ],
                    [
                        'icon' => 'monitor',
                        'title' => 'Ikuti Materi',
                        'description' => 'Pelajari lesson, kuis, dan tugas secara berurutan agar progress belajar terukur.',
                    ],
                    [
                        'icon' => 'message-square-text',
                        'title' => 'Diskusi Kendala',
                        'description' => 'Gunakan forum course untuk bertanya, berdiskusi, dan mendapatkan arahan belajar.',
                    ],
                    [
                        'icon' => 'award',
                        'title' => 'Selesaikan Course',
                        'description' => 'Penuhi syarat penyelesaian course dan dapatkan sertifikat digital sesuai ketentuan.',
                    ],
                ],
            ],
            [
                'page_key' => 'home',
                'section_key' => 'faq',
                'eyebrow' => 'FAQ',
                'title' => 'Pertanyaan yang sering ditanyakan',
                'body' => 'Temukan jawaban singkat sebelum mulai belajar atau menghubungi admin.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'page_key' => 'home',
                'section_key' => 'cta',
                'title' => 'Siap mulai perjalanan belajarmu?',
                'body' => 'Daftar sekarang gratis dan akses ratusan materi belajar berkualitas.',
                'cta_label' => 'Daftar Gratis Sekarang',
                'cta_url' => '/register',
                'secondary_cta_label' => 'Sudah punya akun? Masuk',
                'secondary_cta_url' => '/login',
                'sort_order' => 6,
                'is_active' => true,
                'items' => [
                    ['title' => 'Gratis untuk pelajar'],
                    ['title' => 'Akses seumur hidup'],
                    ['title' => 'Sertifikat resmi'],
                ],
            ],
        ];
    }

    private function pageDefaults(): array
    {
        return [
            [
                'slug' => 'about-us',
                'title' => 'Tentang Kami',
                'excerpt' => 'Platform belajar pre-university yang membantu siswa belajar lebih terstruktur.',
                'content' => 'SkripsiLMS menyediakan materi, kuis, forum diskusi, dan sertifikat untuk membantu siswa mempersiapkan diri masuk universitas. Halaman ini dapat disesuaikan melalui admin Website CMS agar sesuai dengan profil institusi.',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'help-center',
                'title' => 'Help Center',
                'excerpt' => 'Pusat bantuan untuk akun, course, pembayaran, dan sertifikat.',
                'content' => 'Jika mengalami kendala, cek FAQ di landing page atau hubungi admin melalui kontak yang tersedia. Tim admin dapat memperbarui panduan akun, course, pembayaran, dan sertifikat dari CMS.',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'terms',
                'title' => 'Syarat & Ketentuan',
                'excerpt' => 'Ketentuan umum penggunaan platform belajar.',
                'content' => 'Dengan menggunakan platform ini, pengguna menyetujui aturan penggunaan akun, akses course, diskusi, pembayaran, dan penerbitan sertifikat. Ketentuan dapat diperbarui sesuai kebijakan operasional platform.',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Kebijakan Privasi',
                'excerpt' => 'Informasi pengelolaan data pengguna platform.',
                'content' => 'Kami menggunakan data pengguna untuk autentikasi, pengelolaan course, transaksi, progress belajar, dan sertifikat. Kebijakan ini dapat disesuaikan dengan kebutuhan legal dan operasional platform.',
                'status' => 'published',
                'published_at' => now(),
            ],
        ];
    }

    private function socialLinkDefaults(): array
    {
        return [
            [
                'platform' => 'instagram',
                'label' => 'Instagram',
                'url' => 'https://instagram.com/skripsilms',
                'icon' => 'instagram',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'platform' => 'youtube',
                'label' => 'YouTube',
                'url' => 'https://youtube.com/@skripsilms',
                'icon' => 'youtube',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'platform' => 'linkedin',
                'label' => 'LinkedIn',
                'url' => 'https://linkedin.com/company/skripsilms',
                'icon' => 'linkedin',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];
    }

    private function faqCategoryDefaults(): array
    {
        return [
            [
                'name' => 'General',
                'is_active' => true,
            ],
            [
                'name' => 'Certificate',
                'is_active' => true,
            ],
            [
                'name' => 'Learning',
                'is_active' => true,
            ],
        ];
    }

    private function faqDefaults(): array
    {
        return [
            [
                'question' => 'Apakah course bisa diakses lewat HP?',
                'answer' => 'Bisa. Landing page dan area belajar dibuat responsif sehingga bisa diakses melalui browser mobile.',
                'category_name' => 'General',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'Apakah siswa mendapatkan sertifikat?',
                'answer' => 'Ya. Sertifikat digital dapat diterbitkan setelah siswa menyelesaikan course sesuai syarat kelulusan.',
                'category_name' => 'Certificate',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'Bagaimana cara mulai belajar?',
                'answer' => 'Daftar akun, pilih course dari katalog, lalu mulai belajar dari lesson pertama yang tersedia.',
                'category_name' => 'Learning',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'question' => 'Apakah ada forum diskusi?',
                'answer' => 'Ada. Setiap course dapat memiliki forum diskusi agar siswa bisa bertanya dan berinteraksi dengan instructor.',
                'category_name' => 'Learning',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];
    }

    private function mapSectionItems(?Collection $items, array $fields): Collection
    {
        return ($items ?? collect())
            ->filter(fn ($item) => $item->is_active)
            ->map(function ($item) use ($fields) {
                $payload = [];

                foreach ($fields as $field) {
                    $payload[$field] = $item->{$field};
                }

                if ($item->url) {
                    $payload['url'] = $item->url;
                }

                return $payload;
            })
            ->values();
    }

    private function mapFooterLinks(?Collection $items): array
    {
        return $this->mapSectionItems($items, ['title'])
            ->map(fn (array $item) => [
                'label' => $item['title'] ?? '',
                'url' => $item['url'] ?? '#',
            ])
            ->filter(fn (array $item) => $item['label'] !== '' && $item['url'] !== '')
            ->values()
            ->all();
    }

    private function mapFaqs(bool $admin): array
    {
        return Faq::query()
            ->with('category')
            ->when(! $admin, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'faq_category_id' => $faq->faq_category_id,
                'category_name' => $faq->category?->name,
                'category' => $faq->category ? [
                    'id' => $faq->category->id,
                    'name' => $faq->category->name,
                    'is_active' => $faq->category->is_active,
                ] : null,
                'sort_order' => $faq->sort_order,
                'is_active' => $faq->is_active,
                'created_at' => $faq->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'updated_at' => $faq->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            ])
            ->values()
            ->all();
    }
}
