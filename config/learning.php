<?php

return [
    'summaries' => [
        [
            'label' => 'Tugas',
            'completed' => 15,
            'total' => 20,
            'icon' => 'clipboard',
            'color' => 'bg-teal-50 text-teal-700',
            'bar' => 'bg-teal-600',
        ],
        [
            'label' => 'Ulangan Harian',
            'completed' => 8,
            'total' => 10,
            'icon' => 'book',
            'color' => 'bg-blue-50 text-blue-700',
            'bar' => 'bg-blue-500',
        ],
        [
            'label' => 'Ulangan Semester',
            'completed' => 2,
            'total' => 4,
            'icon' => 'chart',
            'color' => 'bg-orange-50 text-orange-700',
            'bar' => 'bg-orange-400',
        ],
    ],
    'activities' => [
        'deadline' => [
            [
                'subject' => 'Matematika',
                'title' => 'Tugas Persamaan Kuadrat',
                'due' => '25 Okt',
                'icon' => 'calculator',
                'color' => 'bg-violet-50 text-violet-600',
            ],
            [
                'subject' => 'Bahasa Indonesia',
                'title' => 'Resensi Buku',
                'due' => '27 Okt',
                'icon' => 'book',
                'color' => 'bg-orange-50 text-orange-600',
            ],
            [
                'subject' => 'Fisika',
                'title' => 'Laporan Praktikum',
                'due' => '30 Okt',
                'icon' => 'atom',
                'color' => 'bg-blue-50 text-blue-600',
            ],
        ],
        'susulan' => [
            [
                'subject' => 'Kimia',
                'title' => 'Laporan Praktikum',
                'due' => '15 Okt',
                'icon' => 'flask',
                'color' => 'bg-violet-50 text-violet-600',
            ],
            [
                'subject' => 'Sejarah',
                'title' => 'Ulangan Harian',
                'due' => '18 Okt',
                'icon' => 'landmark',
                'color' => 'bg-orange-50 text-orange-600',
            ],
            [
                'subject' => 'Biologi',
                'title' => 'Proyek Ekosistem',
                'due' => '20 Okt',
                'icon' => 'leaf',
                'color' => 'bg-teal-50 text-teal-600',
            ],
        ],
    ],
    'subjects' => [
        'matematika' => [
            'name' => 'Matematika',
            'icon' => 'calculator',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [
                [
                    'id' => 1,
                    'title' => 'Tugas: Aljabar',
                    'details' => [
                        'Deadline: 20 Okt 2023',
                        'Status: Selesai',
                    ],
                    'completed' => true,
                    'type' => 'tugas',
                ],
                [
                    'id' => 2,
                    'title' => 'Ulangan Harian 1',
                    'details' => [
                        'Tanggal: 25 Okt 2023',
                        'Nilai: 90',
                    ],
                    'completed' => true,
                    'type' => 'harian',
                ],
                [
                    'id' => 3,
                    'title' => 'Tugas: Geometri',
                    'details' => [
                        'Deadline: 30 Okt 2023',
                        'Belum Selesai',
                    ],
                    'completed' => false,
                    'type' => 'tugas',
                ],
                [
                    'id' => 4,
                    'title' => 'Ulangan Semester Ganjil',
                    'details' => [
                        'Jadwal: 15 Des 2023',
                        'Belum Mengikuti',
                    ],
                    'completed' => false,
                    'type' => 'semester',
                ],
            ],
        ],
        'bahasa-indonesia' => [
            'name' => 'Bahasa Indonesia',
            'icon' => 'book',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'fisika' => [
            'name' => 'Fisika',
            'icon' => 'atom',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'biologi' => [
            'name' => 'Biologi',
            'icon' => 'leaf',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'sejarah' => [
            'name' => 'Sejarah',
            'icon' => 'landmark',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'ekonomi' => [
            'name' => 'Ekonomi',
            'icon' => 'chart',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'kimia' => [
            'name' => 'Kimia',
            'icon' => 'flask',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'geografi' => [
            'name' => 'Geografi',
            'icon' => 'globe',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'seni-budaya' => [
            'name' => 'Seni Budaya',
            'icon' => 'palette',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
        'penjasorkes' => [
            'name' => 'Penjasorkes',
            'icon' => 'activity',
            'teacher' => 'Pak Budi Santoso',
            'tasks' => [],
        ],
    ],
];
