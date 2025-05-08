<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lawyer;
use Illuminate\Support\Facades\DB;

class LawyerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('lawyers')->truncate();
        
        $lawyers = [
            [
                'name' => 'Dr. Ahmad Fauzi, S.H., M.H.',
                'specialization' => ['Contract Law', 'Corporate Law'],
                'location' => 'Jakarta',
                'rating' => 4.8,
                'experience_years' => 15,
                'image_url' => 'https://randomuser.me/api/portraits/men/32.jpg',
                'email' => 'ahmad.fauzi@legalfirm.com',
                'phone' => '+62812345678',
                'available' => true,
            ],
            [
                'name' => 'Siti Nurhaliza, S.H.',
                'specialization' => ['Family Law', 'Civil Law'],
                'location' => 'Bandung',
                'rating' => 4.5,
                'experience_years' => 8,
                'image_url' => 'https://randomuser.me/api/portraits/women/44.jpg',
                'email' => 'siti.nurhaliza@legalfirm.com',
                'phone' => '+62823456789',
                'available' => true,
            ],
            [
                'name' => 'Budi Santoso, S.H., LL.M.',
                'specialization' => ['Criminal Law', 'Cyber Law'],
                'location' => 'Surabaya',
                'rating' => 4.9,
                'experience_years' => 12,
                'image_url' => 'https://randomuser.me/api/portraits/men/67.jpg',
                'email' => 'budi.santoso@legalfirm.com',
                'phone' => '+62834567890',
                'available' => true,
            ],
            [
                'name' => 'Maya Wijaya, S.H., M.Kn.',
                'specialization' => ['Property Law', 'Notary'],
                'location' => 'Jakarta',
                'rating' => 4.7,
                'experience_years' => 10,
                'image_url' => 'https://randomuser.me/api/portraits/women/22.jpg',
                'email' => 'maya.wijaya@legalfirm.com',
                'phone' => '+62845678901',
                'available' => false,
            ],
            [
                'name' => 'Reza Pratama, S.H.',
                'specialization' => ['Labor Law', 'Immigration Law'],
                'location' => 'Medan',
                'rating' => 4.3,
                'experience_years' => 5,
                'image_url' => 'https://randomuser.me/api/portraits/men/45.jpg',
                'email' => 'reza.pratama@legalfirm.com',
                'phone' => '+62856789012',
                'available' => true,
            ],
        ];

        foreach ($lawyers as $lawyer) {
            Lawyer::create($lawyer);
        }
    }
}

