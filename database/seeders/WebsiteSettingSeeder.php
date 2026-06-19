<?php

namespace Database\Seeders;

use App\Services\WebsiteSettingService;
use Illuminate\Database\Seeder;

class WebsiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $websiteSettingService = app(WebsiteSettingService::class);

        $websiteSettingService->ensureDefaultContent();
        $websiteSettingService->updateSettings([
            'site_name' => 'UPNVJT Pre-University',
            'site_tagline' => 'Mempersiapkan transisi akademik Anda dari sekolah menengah ke perguruan tinggi. Akses materi belajar terstruktur, uji pemahaman lewat kuis, dan raih sertifikat kesiapan kuliah.',
            'footer_text' => 'Copyright 2026 UPNVJT - Platform Belajar Pre-University',
            'contact_email' => 'customersupport@upnvjt-preuniversity.com',
            'contact_phone' => '(031) 8706369',
            'address' => 'Jl. Rungkut Madya, Gn. Anyar, Kec. Gn. Anyar, Surabaya, Jawa Timur 60294',
        ]);
    }
}
