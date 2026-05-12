/* TuNotes Admin — Dummy Data */
const DATA = {
  stats: {
    totalUsers: 12847,
    activeUsers: 9421,
    suspendedUsers: 86,
    inactiveUsers: 3340,
    totalNotes: 8492,
    totalDownloads: 45231,
    aiAccuracy: 94.7,
    totalComments: 18420,
    approvedComments: 17985,
    flaggedComments: 23,
    avgRating: 4.6,
    reportedNotes: 14,
    dau: 2156,
    mau: 8432,
    retention: 72,
  },

  users: [
    { id: 1, name: 'Lani Pratiwi',     email: 'lani@tunotes.id',    status: 'active',    role: 'super_admin', notes: 42, lastActive: '2 menit lalu' },
    { id: 2, name: 'Ahmad Santoso',    email: 'ahmad@tunotes.id',   status: 'active',    role: 'admin',       notes: 28, lastActive: '15 menit lalu' },
    { id: 3, name: 'Siti Nurhaliza',   email: 'siti@tunotes.id',    status: 'active',    role: 'user',        notes: 67, lastActive: '1 jam lalu' },
    { id: 4, name: 'Budi Raharjo',     email: 'budi@tunotes.id',    status: 'suspended', role: 'user',        notes: 12, lastActive: '3 hari lalu' },
    { id: 5, name: 'Sarah Wijaya',     email: 'sarah@tunotes.id',   status: 'active',    role: 'admin',       notes: 89, lastActive: '5 menit lalu' },
    { id: 6, name: 'David Kim',        email: 'david@tunotes.id',   status: 'inactive',  role: 'user',        notes: 5,  lastActive: '2 minggu lalu' },
    { id: 7, name: 'Lina Fatimah',     email: 'lina@tunotes.id',    status: 'active',    role: 'user',        notes: 34, lastActive: '30 menit lalu' },
    { id: 8, name: 'Marcus Johnson',   email: 'marcus@tunotes.id',  status: 'active',    role: 'user',        notes: 21, lastActive: '4 jam lalu' },
    { id: 9, name: 'Emily Rodriguez',  email: 'emily@tunotes.id',   status: 'suspended', role: 'user',        notes: 8,  lastActive: '1 minggu lalu' },
    { id: 10, name: 'Jessica Kim',     email: 'jessica@tunotes.id', status: 'active',    role: 'user',        notes: 51, lastActive: '20 menit lalu' },
  ],

  notes: [
    { id: 1, title: 'Kalkulus Integral dan Diferensial', author: 'Dr. Ahmad Santoso', category: 'Matematika', status: 'published', rating: 4.8, downloads: 234, reports: 0, date: '2 Apr 2024' },
    { id: 2, title: 'Fisika Kuantum Dasar',              author: 'Prof. Sarah Wijaya', category: 'Fisika',     status: 'published', rating: 4.9, downloads: 189, reports: 0, date: '2 Aug 2024' },
    { id: 3, title: 'Algoritma dan Struktur Data',       author: 'Dr. Budi Raharjo',   category: 'Informatika',status: 'published', rating: 4.7, downloads: 156, reports: 0, date: '11 Jul 2024' },
    { id: 4, title: 'Manajemen Keuangan',                author: 'Dr. Lisa Permata',   category: 'Ekonomi',    status: 'review',    rating: 4.6, downloads: 98,  reports: 1, date: '20 Jun 2024' },
    { id: 5, title: 'Catatan Spam Promosi',              author: 'Anonim123',          category: 'Lainnya',    status: 'flagged',   rating: 1.2, downloads: 4,   reports: 8, date: '5 Mei 2024' },
    { id: 6, title: 'Pengenalan Database',               author: 'Prof. Siti Nurhaliza', category: 'Informatika', status: 'published', rating: 4.6, downloads: 142, reports: 0, date: '10 Mei 2024' },
    { id: 7, title: 'HTML & CSS Fundamentals',           author: 'Ir. Budi Santoso',   category: 'Informatika', status: 'published', rating: 4.7, downloads: 178, reports: 0, date: '18 Mei 2024' },
    { id: 8, title: 'Materi Salah Mata Kuliah',          author: 'Pengguna99',         category: 'Lainnya',    status: 'flagged',   rating: 2.1, downloads: 12,  reports: 5, date: '22 Mei 2024' },
  ],

  comments: [
    { id: 1, user: 'Emily Rodriguez', note: 'Advanced ML Concepts',  text: 'Great notes! The explanation of backpropagation is really clear. Thanks for sharing!', time: '2 jam lalu', likes: 5, status: 'approved' },
    { id: 2, user: 'Marcus Johnson',  note: 'Advanced ML Concepts',  text: 'Could you elaborate more on the CNN section? Pooling layers masih membingungkan.',       time: '5 jam lalu', likes: 2, status: 'approved' },
    { id: 3, user: 'Jessica Kim',     note: 'Kalkulus Integral',     text: 'Perfect timing! Midterm minggu depan dan ini persis yang saya butuhkan.',                time: '1 hari lalu', likes: 8, status: 'approved' },
    { id: 4, user: 'Akun Spam',       note: 'Fisika Kuantum Dasar',  text: 'Klik link di bio untuk dapat hadiah gratis!!!',                                            time: '3 jam lalu', likes: 0, status: 'flagged' },
    { id: 5, user: 'Troll User',      note: 'Algoritma & Struktur',  text: 'Catatan jelek banget, buang-buang waktu.',                                                 time: '6 jam lalu', likes: 0, status: 'flagged' },
    { id: 6, user: 'Lina Fatimah',    note: 'Manajemen Keuangan',    text: 'Sangat membantu untuk persiapan UAS. Terima kasih!',                                       time: '8 jam lalu', likes: 12, status: 'approved' },
  ],

  notifications: [
    { id: 1, title: 'Pemeliharaan Server',  type: 'maintenance', target: 'Semua Pengguna', time: '2 jam lalu',  status: 'sent',      message: 'Server akan dipelihara pada Sabtu 22:00–00:00 WIB.' },
    { id: 2, title: 'Fitur Baru: Notes AI', type: 'info',        target: 'Semua Pengguna', time: 'Kemarin',     status: 'sent',      message: 'Notes AI sekarang mendukung mode Penelitian.' },
    { id: 3, title: 'Ujian Tengah Semester',type: 'academic',    target: 'Pengguna Aktif', time: 'Besok 08:00', status: 'scheduled', message: 'Periode UTS dimulai pekan depan, siapkan catatanmu.' },
  ],

  recentActivity: [
    { text: 'Lani Pratiwi mengunggah catatan "Kalkulus II"',           time: '2 menit lalu',  color: '#22c55e' },
    { text: 'Komentar baru di "Fisika Kuantum Dasar"',                  time: '5 menit lalu',  color: '#0ea5e9' },
    { text: 'Catatan "Spam Promosi" dilaporkan 3 pengguna',             time: '12 menit lalu', color: '#ef4444' },
    { text: 'Sarah Wijaya menjadi Admin',                                time: '1 jam lalu',    color: '#8b5cf6' },
    { text: 'Notes AI memproses 156 ringkasan baru',                     time: '2 jam lalu',    color: '#f59e0b' },
  ],

  popularNotes: [
    { title: 'Kalkulus Integral dan Diferensial', rating: 4.8, downloads: 234 },
    { title: 'Fisika Kuantum Dasar',              rating: 4.9, downloads: 189 },
    { title: 'HTML & CSS Fundamentals',           rating: 4.7, downloads: 178 },
    { title: 'Algoritma dan Struktur Data',       rating: 4.7, downloads: 156 },
    { title: 'Pengenalan Database',               rating: 4.6, downloads: 142 },
  ],

  popularCourses: [
    { name: 'Kalkulus II',           notes: 124, users: 2840 },
    { name: 'Algoritma & Struktur Data', notes: 98, users: 2310 },
    { name: 'Fisika Modern',         notes: 76, users: 1920 },
    { name: 'Basis Data',            notes: 88, users: 1750 },
    { name: 'Machine Learning',      notes: 64, users: 1480 },
  ],
};
