<?php

namespace Database\Seeders;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = [
            [
                'application' => [
                    'company' => 'GoTo (Gojek Tokopedia)',
                    'position' => 'Senior Backend Engineer (Go)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'hybrid',
                    'source' => 'LinkedIn',
                    'source_url' => 'https://www.linkedin.com/jobs/view/3920182741',
                    'applied_at' => '2026-09-10',
                    'salary_note' => 'Rp 28.000.000 - Rp 35.000.000',
                    'contact_name' => 'Rina Dewi',
                    'contact_info' => 'rina.dewi@goto.com',
                    'notes' => 'Tahap system design interview via Google Meet. Fokus ke microservices dan message broker Kafka.',
                    'status' => 'interview',
                    'last_status_change_at' => '2026-09-22 14:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Saved job posting from LinkedIn',
                        'created_at' => '2026-09-08 10:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Submitted resume and portfolio via LinkedIn Easy Apply',
                        'created_at' => '2026-09-10 09:30:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'HR screening call with recruiter (Rina Dewi)',
                        'created_at' => '2026-09-16 11:00:00',
                    ],
                    [
                        'from_status' => 'screening',
                        'to_status' => 'interview',
                        'note' => 'Passed technical take-home test, scheduled system design interview',
                        'created_at' => '2026-09-22 14:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Traveloka',
                    'position' => 'Fullstack Software Engineer (PHP & React)',
                    'location' => 'Tangerang (BSD)',
                    'work_type' => 'hybrid',
                    'source' => 'JobStreet',
                    'source_url' => 'https://www.jobstreet.co.id/job/78921345',
                    'applied_at' => '2026-09-18',
                    'salary_note' => 'Rp 20.000.000 - Rp 25.000.000',
                    'contact_name' => 'Budi Santoso',
                    'contact_info' => 'budi.santoso@traveloka.com',
                    'notes' => 'Menunggu review take-home assignment dari engineering team.',
                    'status' => 'screening',
                    'last_status_change_at' => '2026-09-24 16:30:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Found listing on JobStreet',
                        'created_at' => '2026-09-17 15:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Submitted CV via JobStreet application portal',
                        'created_at' => '2026-09-18 10:15:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'Initial HR phone screening and assignment sent',
                        'created_at' => '2026-09-24 16:30:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'DANA Indonesia',
                    'position' => 'Backend Engineer (Java / Spring Boot)',
                    'location' => 'Jakarta Pusat',
                    'work_type' => 'onsite',
                    'source' => 'Glints',
                    'source_url' => 'https://glints.com/id/opportunities/jobs/92847192',
                    'applied_at' => '2026-08-25',
                    'salary_note' => 'Rp 24.000.000 - Rp 28.000.000',
                    'contact_name' => 'Dimas Pratama',
                    'contact_info' => 'recruitment@dana.id',
                    'notes' => 'Offering letter sudah masuk email. Dalam proses negosiasi benefit kesehatan dan tanggal mulai kerja.',
                    'status' => 'offer',
                    'last_status_change_at' => '2026-09-28 15:45:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Bookmarked opening from Glints',
                        'created_at' => '2026-08-23 20:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Applied through Glints profile',
                        'created_at' => '2026-08-25 11:00:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'HR phone verification',
                        'created_at' => '2026-09-02 14:00:00',
                    ],
                    [
                        'from_status' => 'screening',
                        'to_status' => 'interview',
                        'note' => 'User interview with Lead Engineer and VP of Engineering',
                        'created_at' => '2026-09-12 10:00:00',
                    ],
                    [
                        'from_status' => 'interview',
                        'to_status' => 'offer',
                        'note' => 'Received formal job offer package via email',
                        'created_at' => '2026-09-28 15:45:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Midtrans',
                    'position' => 'Lead Backend Developer (PHP & Go)',
                    'location' => 'Jakarta',
                    'work_type' => 'remote',
                    'source' => 'Career Portal',
                    'source_url' => 'https://midtrans.com/careers/lead-backend',
                    'applied_at' => '2026-09-26',
                    'salary_note' => 'Rp 35.000.000 - Rp 42.000.000',
                    'contact_name' => 'Siti Rahma',
                    'contact_info' => 'siti.rahma@midtrans.com',
                    'notes' => 'Melamar langsung via portal karir resmi. Portfolio GitHub sudah disertakan.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-26 09:30:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Added position to target watchlist',
                        'created_at' => '2026-09-25 19:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Direct submission on Midtrans career page',
                        'created_at' => '2026-09-26 09:30:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Kredivo Group',
                    'position' => 'Fullstack Developer (Laravel & Vue.js)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'hybrid',
                    'source' => 'LinkedIn',
                    'source_url' => 'https://www.linkedin.com/jobs/view/4019284721',
                    'applied_at' => '2026-08-15',
                    'salary_note' => 'Rp 22.000.000',
                    'contact_name' => 'Agus Wicaksono',
                    'contact_info' => 'talent@kredivo.com',
                    'notes' => 'Proses rekrutmen ditutup karena prioritas headcount internal dialihkan ke divisi lain.',
                    'status' => 'rejected',
                    'last_status_change_at' => '2026-09-05 13:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Targeted on LinkedIn job board',
                        'created_at' => '2026-08-14 14:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Applied via LinkedIn',
                        'created_at' => '2026-08-15 08:45:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'Initial HR screening interview',
                        'created_at' => '2026-08-22 10:00:00',
                    ],
                    [
                        'from_status' => 'screening',
                        'to_status' => 'interview',
                        'note' => 'Pair-programming technical assessment with senior dev',
                        'created_at' => '2026-08-29 15:00:00',
                    ],
                    [
                        'from_status' => 'interview',
                        'to_status' => 'rejected',
                        'note' => 'Notified position on freeze due to internal restructuring',
                        'created_at' => '2026-09-05 13:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Shopee Indonesia',
                    'position' => 'Senior Frontend Engineer (React & TypeScript)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'hybrid',
                    'source' => 'Kalibrr',
                    'source_url' => 'https://www.kalibrr.com/id-ID/c/shopee-indonesia/jobs/283471',
                    'applied_at' => '2026-09-05',
                    'salary_note' => 'Rp 26.000.000 - Rp 32.000.000',
                    'contact_name' => 'Michelle Tan',
                    'contact_info' => 'michelle.tan@shopee.com',
                    'notes' => 'Interview panel dengan Tech Lead dan Product Manager. Diskusi arsitektur micro-frontend dan state management.',
                    'status' => 'interview',
                    'last_status_change_at' => '2026-09-25 10:30:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'applied',
                        'note' => 'Direct apply via Kalibrr',
                        'created_at' => '2026-09-05 14:00:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'HR screening and technical assessment online',
                        'created_at' => '2026-09-12 16:00:00',
                    ],
                    [
                        'from_status' => 'screening',
                        'to_status' => 'interview',
                        'note' => 'Technical interview scheduled with engineering team',
                        'created_at' => '2026-09-25 10:30:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Blibli.com',
                    'position' => 'Backend Developer (Golang)',
                    'location' => 'Jakarta Barat',
                    'work_type' => 'onsite',
                    'source' => 'JobStreet',
                    'source_url' => 'https://www.jobstreet.co.id/job/79284531',
                    'applied_at' => '2026-09-03',
                    'salary_note' => 'Rp 18.000.000 - Rp 23.000.000',
                    'contact_name' => 'Andri Wijaya',
                    'contact_info' => 'recruitment@blibli.com',
                    'notes' => 'Ditolak setelah technical interview. Feedback: kurang pengalaman dengan distributed tracing dan observability tooling.',
                    'status' => 'rejected',
                    'last_status_change_at' => '2026-09-20 11:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'applied',
                        'note' => 'Applied via JobStreet portal',
                        'created_at' => '2026-09-03 09:00:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'Initial screening call',
                        'created_at' => '2026-09-10 13:30:00',
                    ],
                    [
                        'from_status' => 'screening',
                        'to_status' => 'interview',
                        'note' => 'Technical interview with senior engineer',
                        'created_at' => '2026-09-17 14:00:00',
                    ],
                    [
                        'from_status' => 'interview',
                        'to_status' => 'rejected',
                        'note' => 'Not moving forward, feedback provided via email',
                        'created_at' => '2026-09-20 11:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Tokopedia',
                    'position' => 'Platform Engineer (Kubernetes & Infrastructure)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'remote',
                    'source' => 'LinkedIn',
                    'source_url' => 'https://www.linkedin.com/jobs/view/3947281234',
                    'applied_at' => '2026-09-15',
                    'salary_note' => 'Rp 30.000.000 - Rp 38.000.000',
                    'contact_name' => 'Rendra Kusuma',
                    'contact_info' => 'rendra.kusuma@tokopedia.com',
                    'notes' => 'Belum ada kabar setelah submit. Mungkin perlu follow-up minggu depan.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-15 11:45:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Saved from LinkedIn jobs feed',
                        'created_at' => '2026-09-14 19:30:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Submitted application with CV and cover letter',
                        'created_at' => '2026-09-15 11:45:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Xendit',
                    'position' => 'Software Engineer (Payments Infrastructure)',
                    'location' => 'Jakarta Pusat',
                    'work_type' => 'hybrid',
                    'source' => 'Glints',
                    'source_url' => 'https://glints.com/id/opportunities/jobs/98327461',
                    'applied_at' => '2026-09-20',
                    'salary_note' => 'Rp 25.000.000 - Rp 32.000.000',
                    'contact_name' => 'Sarah Lim',
                    'contact_info' => 'talent@xendit.co',
                    'notes' => 'Applied via Glints. Posisi fokus payment gateway integration dan reconciliation system.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-20 10:20:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'applied',
                        'note' => 'Direct application through Glints',
                        'created_at' => '2026-09-20 10:20:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Bukalapak',
                    'position' => 'Mobile Engineer (Android - Kotlin)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'hybrid',
                    'source' => 'LinkedIn',
                    'source_url' => 'https://www.linkedin.com/jobs/view/3951728394',
                    'applied_at' => '2026-09-18',
                    'salary_note' => 'Rp 20.000.000 - Rp 26.000.000',
                    'contact_name' => 'Fitri Handayani',
                    'contact_info' => 'fitri.handayani@bukalapak.com',
                    'notes' => 'Melamar posisi Android. Belum ada update sejak apply.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-18 15:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Bookmarked from LinkedIn',
                        'created_at' => '2026-09-17 20:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Submitted via LinkedIn Easy Apply',
                        'created_at' => '2026-09-18 15:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Ruangguru',
                    'position' => 'Fullstack Engineer (Ruby on Rails & React)',
                    'location' => 'Jakarta Pusat',
                    'work_type' => 'hybrid',
                    'source' => 'Kalibrr',
                    'source_url' => 'https://www.kalibrr.com/id-ID/c/ruangguru/jobs/294718',
                    'applied_at' => '2026-09-22',
                    'salary_note' => 'Rp 19.000.000 - Rp 24.000.000',
                    'contact_name' => 'Dian Purnama',
                    'contact_info' => 'dian.purnama@ruangguru.com',
                    'notes' => 'Screening call dijadwalkan minggu depan. Posisi fokus edtech platform dan learning management system.',
                    'status' => 'screening',
                    'last_status_change_at' => '2026-09-27 14:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'applied',
                        'note' => 'Applied through Kalibrr',
                        'created_at' => '2026-09-22 16:30:00',
                    ],
                    [
                        'from_status' => 'applied',
                        'to_status' => 'screening',
                        'note' => 'HR screening scheduled',
                        'created_at' => '2026-09-27 14:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Grab Indonesia',
                    'position' => 'Data Engineer (Spark & Airflow)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'hybrid',
                    'source' => 'LinkedIn',
                    'source_url' => 'https://www.linkedin.com/jobs/view/4024718293',
                    'applied_at' => '2026-09-12',
                    'salary_note' => 'Rp 28.000.000 - Rp 36.000.000',
                    'contact_name' => 'David Tan',
                    'contact_info' => 'recruitment@grab.com',
                    'notes' => 'Applied via LinkedIn. Posisi di team data infrastructure. Belum ada kabar setelah >2 minggu.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-12 16:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Saved for future application',
                        'created_at' => '2026-09-10 18:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Submitted application via LinkedIn',
                        'created_at' => '2026-09-12 16:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'OVO (PT Visionet Internasional)',
                    'position' => 'DevOps Engineer (Terraform & AWS)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'onsite',
                    'source' => 'Glints',
                    'source_url' => 'https://glints.com/id/opportunities/jobs/99182374',
                    'applied_at' => '2026-09-08',
                    'salary_note' => 'Rp 24.000.000 - Rp 30.000.000',
                    'contact_name' => 'Irfan Hakim',
                    'contact_info' => 'irfan.hakim@ovo.id',
                    'notes' => 'Applied via Glints. Requirements cukup tinggi untuk AWS expertise. Belum ada feedback sejak apply.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-08 14:30:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Added to target list',
                        'created_at' => '2026-09-07 21:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Submitted via Glints portal',
                        'created_at' => '2026-09-08 14:30:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Tiket.com',
                    'position' => 'Backend Engineer (PHP & MySQL)',
                    'location' => 'Jakarta Pusat',
                    'work_type' => 'remote',
                    'source' => 'JobStreet',
                    'source_url' => 'https://www.jobstreet.co.id/job/81294657',
                    'applied_at' => '2026-09-16',
                    'salary_note' => 'Rp 21.000.000 - Rp 27.000.000',
                    'contact_name' => 'Putri Lestari',
                    'contact_info' => 'recruitment@tiket.com',
                    'notes' => 'Posisi remote full. Stack familiar dengan Laravel & MySQL. Sudah apply, menunggu kabar.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-16 09:00:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'wishlist',
                        'note' => 'Found on JobStreet',
                        'created_at' => '2026-09-15 08:00:00',
                    ],
                    [
                        'from_status' => 'wishlist',
                        'to_status' => 'applied',
                        'note' => 'Applied via JobStreet portal',
                        'created_at' => '2026-09-16 09:00:00',
                    ],
                ],
            ],
            [
                'application' => [
                    'company' => 'Koinworks',
                    'position' => 'Senior Software Engineer (Microservices)',
                    'location' => 'Jakarta Selatan',
                    'work_type' => 'hybrid',
                    'source' => 'Referral',
                    'source_url' => null,
                    'applied_at' => '2026-09-28',
                    'salary_note' => 'Rp 32.000.000 - Rp 40.000.000',
                    'contact_name' => 'Kevin Aluwi',
                    'contact_info' => 'kevin.aluwi@koinworks.com',
                    'notes' => 'Referral dari teman kuliah yang kerja di sini. Direct submission ke hiring manager.',
                    'status' => 'applied',
                    'last_status_change_at' => '2026-09-28 13:15:00',
                ],
                'histories' => [
                    [
                        'from_status' => null,
                        'to_status' => 'applied',
                        'note' => 'Referral application to hiring manager',
                        'created_at' => '2026-09-28 13:15:00',
                    ],
                ],
            ],
        ];

        // Seeding runs from the CLI with no authenticated session, so the
        // BelongsToUser global scope is dormant and its create-time stamping
        // never fires. The owner must therefore be set explicitly, otherwise
        // every seeded row would be orphaned (user_id = NULL) and invisible to
        // the account that logs in afterwards.
        $owner = User::query()->orderBy('id')->first();
        $ownerId = $owner?->id;

        foreach ($applications as $data) {
            $jobApp = JobApplication::firstOrCreate(
                [
                    'user_id' => $ownerId,
                    'company' => $data['application']['company'],
                    'position' => $data['application']['position'],
                ],
                ['user_id' => $ownerId] + $data['application']
            );

            // Skip the rest if this row already existed (idempotent seeding —
            // nixpacks runs db:seed on every deploy).
            if (! $jobApp->wasRecentlyCreated) {
                continue;
            }

            foreach ($data['histories'] as $history) {
                $jobApp->statusHistories()->create(['user_id' => $ownerId] + $history);
            }

            if ($jobApp->status === 'interview') {
                $jobApp->interviewChecklists()->createMany([
                    ['user_id' => $ownerId, 'title' => 'Riset engineering culture & arsitektur sistem', 'is_completed' => true, 'completed_at' => now()->subDays(5)],
                    ['user_id' => $ownerId, 'title' => 'Review microservices concurrency Go & goroutine pools', 'is_completed' => true, 'completed_at' => now()->subDays(3)],
                    ['user_id' => $ownerId, 'title' => 'Latihan studi kasus STAR behavioral question', 'is_completed' => false],
                    ['user_id' => $ownerId, 'title' => 'Siapkan pertanyaan balik untuk hiring manager', 'is_completed' => false],
                ]);
            }

            if ($jobApp->status === 'offer') {
                $jobApp->offerDetail()->create(['user_id' => $ownerId] + [
                    'base_salary' => 28000000,
                    'salary_period' => 'monthly',
                    'thr' => '1 Bulan Gaji Pokok',
                    'bonus' => 'Performance Bonus tahunan (1-3x gaji)',
                    'allowance' => 'Tunjangan WFH Rp 1.500.000 / bulan',
                    'health_insurance' => 'BPJS + Asuransi Swasta Prudential (Rawat Inap + Gigi)',
                    'work_scheme' => 'Hybrid (2 hari kantor, 3 hari remote)',
                    'deadline_at' => now()->addDays(7)->format('Y-m-d'),
                    'notes' => 'Offering letter resmi dikirim via email. Perlu konfirmasi sebelum deadline.',
                ]);
            }
        }
    }
}
