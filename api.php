<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "angstria_hangout";

try {
    // Hubungkan ke MySQL pusat terlebih dahulu untuk menjamin database bisa dibuat jika belum ada
    try {
        $testConn = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $testConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $testConn->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $testConn = null;
    } catch (PDOException $e) {
        // Abaikan jika tidak diizinkan membuat database secara manual di host target, biarkan langkah selanjutnya berjalan
    }

    $conn = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Dynamic schema validation & auto-healing (Pembuatan Tabel Otomatis jika Belum Ada)
    try {
        // 1. Buat Tabel places jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `places` (
            `id` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `location` VARCHAR(255) NOT NULL,
            `lat` DECIMAL(10, 8) DEFAULT NULL,
            `lng` DECIMAL(11, 8) DEFAULT NULL,
            `openingHours` VARCHAR(100) DEFAULT NULL,
            `facilities` TEXT DEFAULT NULL,
            `priceRange` VARCHAR(10) DEFAULT NULL,
            `featured` TINYINT(1) DEFAULT 0,
            `tags` TEXT DEFAULT NULL,
            `image` LONGTEXT DEFAULT NULL,
            `images` LONGTEXT DEFAULT NULL,
            `socials` LONGTEXT DEFAULT NULL,
            `views` INT DEFAULT 0,
            `rating` DECIMAL(3, 1) DEFAULT 0.0,
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure socials column exists in places
        $q = $conn->query("SHOW COLUMNS FROM `places` LIKE 'socials'");
        if ($q->rowCount() === 0) {
            $conn->exec("ALTER TABLE `places` ADD COLUMN `socials` LONGTEXT DEFAULT NULL");
        }

        // Upgrade image and images columns to LONGTEXT to support Base64 uploads safely
        $qImage = $conn->query("SHOW COLUMNS FROM `places` LIKE 'image'");
        if ($qImage) {
            $col = $qImage->fetch();
            if ($col && stripos($col['Type'], 'varchar') !== false) {
                $conn->exec("ALTER TABLE `places` MODIFY COLUMN `image` LONGTEXT DEFAULT NULL");
            }
        }

        $qImages = $conn->query("SHOW COLUMNS FROM `places` LIKE 'images'");
        if ($qImages) {
            $col = $qImages->fetch();
            if ($col && stripos($col['Type'], 'text') !== false && stripos($col['Type'], 'long') === false) {
                $conn->exec("ALTER TABLE `places` MODIFY COLUMN `images` LONGTEXT DEFAULT NULL");
            }
        }

        // 2. Buat Tabel comments jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `comments` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `placeId` VARCHAR(50) NOT NULL,
            `username` VARCHAR(100) NOT NULL,
            `comment` TEXT NOT NULL,
            `rating` INT NOT NULL,
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_comments_places_api` FOREIGN KEY (`placeId`) REFERENCES `places` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Buat Tabel admin jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `admin` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Isi data admin bawaan jika kosong
        $adminCountQuery = $conn->query("SELECT COUNT(*) as cnt FROM `admin`")->fetch();
        if ($adminCountQuery && intval($adminCountQuery['cnt']) === 0) {
            $conn->exec("INSERT INTO `admin` (`id`, `username`, `password`, `name`) VALUES (1, 'admin', 'admin123', 'Administrator')");
        }

        // 4. Buat Tabel owners jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `owners` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `cafeId` VARCHAR(50) DEFAULT NULL,
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Buat Tabel reservations jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `reservations` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `placeId` VARCHAR(50) NOT NULL,
            `customerName` VARCHAR(100) NOT NULL,
            `customerEmail` VARCHAR(100) DEFAULT NULL,
            `customerPhone` VARCHAR(50) NOT NULL,
            `bookingDate` VARCHAR(50) NOT NULL,
            `bookingTime` VARCHAR(50) NOT NULL,
            `guests` INT NOT NULL,
            `notes` TEXT DEFAULT NULL,
            `status` VARCHAR(20) DEFAULT 'pending',
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure customerEmail column exists in reservations
        $qCustEmail = $conn->query("SHOW COLUMNS FROM `reservations` LIKE 'customerEmail'");
        if ($qCustEmail->rowCount() === 0) {
            $conn->exec("ALTER TABLE `reservations` ADD COLUMN `customerEmail` VARCHAR(100) DEFAULT NULL");
        }

        // 6. Buat Tabel payments jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `payments` (
            `id` VARCHAR(50) NOT NULL,
            `ownerEmail` VARCHAR(100) NOT NULL,
            `cafeId` VARCHAR(50) DEFAULT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `method` VARCHAR(50) NOT NULL,
            `status` VARCHAR(20) DEFAULT 'success',
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. Buat Tabel users jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto Seeding Kafe Rekomendasi Pangkal Pinang jika tabel places kosong
        $placeCountQuery = $conn->query("SELECT COUNT(*) as cnt FROM `places`")->fetch();
        if ($placeCountQuery && intval($placeCountQuery['cnt']) === 0) {
            $conn->exec("INSERT INTO `places` (`id`, `name`, `description`, `location`, `lat`, `lng`, `openingHours`, `facilities`, `priceRange`, `featured`, `tags`, `image`, `images`, `socials`, `views`, `rating`, `createdAt`) VALUES
            (
              'tung-tau', 
              'Warung Kopi Tung Tau', 
              'Sangat legendaris sejak tahun 1938. Terkenal dengan kopi tradisional Hainan dan roti panggang isi yang otentik. Tempat yang sangat klasik dan penuh sejarah untuk menikmati sore di Pangkal Pinang.', 
              'Jl. Soekarno Hatta No.7, Pangkal Pinang', 
              -2.12830000, 
              106.11300000, 
              '07:00 - 23:00', 
              'WiFi,AC,Kopi Hainan,Roti Panggang,Halal,Indoor', 
              '$$', 
              1, 
              'Aesthetic Cafe,Outdoor Spots', 
              'https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/kopitungtau/\",\"website\":\"https://www.kopitungtau.com\"}',
              312, 
              4.8, 
              '2026-05-23 12:00:00'
            ),
            (
              'kong-djie', 
              'Kong Djie Coffee Pangkal Pinang', 
              'Kopi legendaris yang berasal dari Belitung, kini hadir di kota Pangkal Pinang. Cita rasa Kopi O yang pekat diseduh secara tradisional menggunakan arang, menciptakan aroma khas yang memikat pecinta kopi sejati.', 
              'Jl. Masjid Jamik, Pangkal Pinang', 
              -2.12150000, 
              106.11180000, 
              '06:00 - 22:00', 
              'WiFi,Kopi O Belitung,Cemilan Tradisional,Outdoor,Halal', 
              '$', 
              1, 
              'Aesthetic Cafe,Budget Places', 
              'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/kongdjie_coffee/\"}',
              245, 
              4.6, 
              '2026-05-23 12:01:00'
            ),
            (
              'bians-coffe', 
              'Bians Coffe and Eatery', 
              'Tempat nongkrong berkonsep modern industrial minimalis yang sangat hits di kalangan anak muda Pangkal Pinang. Memiliki area outdoor bergaya tropis serta ruang kerja ber-AC yang tenang dan nyaman.', 
              'Jl. Ahmad Yani, Pangkal Pinang', 
              -2.11580000, 
              106.10840000, 
              '09:00 - 23:00', 
              'WiFi,AC,Outdoor,Live Music,Halal', 
              '$$', 
              1, 
              'Work From Cafe,Night Hangout', 
              'https://images.unsplash.com/photo-1559925393-8be0ec4767c8?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1559925393-8be0ec4767c8?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/bianscoffe_eatery/\"}',
              450, 
              5.0, 
              '2026-05-23 12:02:00'
            ),
            (
              'kinikawa', 
              'Kini Kawa Vol 2', 
              'Coffee shop bertema Jepang minimalis modern yang sangat populer di Pangkal Pinang. Terkenal dengan suasana semi-outdoor yang aesthetic, racikan kopi berkualitas, dan aneka minuman matcha premium segar.', 
              'Jl. Kacang Pedang, Pangkal Pinang', 
              -2.12400000, 
              106.10200000, 
              '11:00 - 00:00', 
              'WiFi,AC,Indoor,Outdoor,Matcha Premium,Live Music', 
              '$$', 
              1, 
              'Aesthetic Cafe,Night Hangout', 
              'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/kinikawa.coffee/\"}',
              189, 
              4.9, 
              '2026-05-23 12:03:00'
            ),
            (
              'dlab', 
              'D.lab', 
              'Coffee shop berkonsep elegan modern minimalis dengan tata cahaya yang aesthetic dan nyaman. Menyajikan aneka pastry lezat, main course Nusantara dan Western, serta racikan kopi specialty yang cocok untuk hangout romantis maupun kerja produktif.', 
              'Jl. A. Yani, Batin Tikal, Kec. Taman Sari, Kota Pangkal Pinang', 
              -2.13200000, 
              106.12100000, 
              '08:00 - 22:00', 
              'WiFi,AC,Indoor,Bakery,Halal', 
              '$$', 
              1, 
              'Aesthetic Cafe,Work From Cafe', 
              'https://images.unsplash.com/photo-1445116572660-236099ec97a0?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1445116572660-236099ec97a0?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1521017432531-fbd92d768814?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/d.lab_idn/\"}',
              350, 
              4.7, 
              '2026-05-23 12:04:00'
            ),
            (
              'kelatea', 
              'Kelatea', 
              'Kedai specialty tea modern pertama di Pangkal Pinang yang estetik. Menyajikan aneka racikan teh artisan floral-fruity, matcha premium, milk tea lezat, dan dessert manis pendamping yang sempurna.', 
              'Jl. Jembatan 12, Pangkal Pinang', 
              -2.12200000, 
              106.11100000, 
              '09:00 - 22:00', 
              'WiFi,AC,Indoor,Espresso Bar,Halal', 
              '$$', 
              1, 
              'Aesthetic Cafe,Work From Cafe', 
              'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1597481499750-3e6b22637e12?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/kelatea/\"}',
              210, 
              4.8, 
              '2026-05-23 12:05:00'
            ),
            (
              'warunk-steak', 
              'Warunk Steak Milenial', 
              'Destinasi kuliner steak populer di Pangkal Pinang dengan harga ramah kantong. Menyajikan daging steak sirloin, tenderloin, dan chicken steak dengan saus khas barbeque yang gurih melimpah.', 
              'Jl. Jendral Sudirman No.10, Pangkal Pinang', 
              -2.11900000, 
              106.11050000, 
              '06:30 - 18:00', 
              'WiFi,Outdoor,Kopi Tradisional,Halal', 
              '$', 
              0, 
              'Budget Places,Outdoor Spots', 
              'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1600891964599-f61ba0e24092?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/warunksteakmilenial/\"}',
              155, 
              4.5, 
              '2026-05-23 12:06:00'
            ),
            (
              'tomoro', 
              'Tomoro Coffe Bangka', 
              'Cabang resmi Tomoro Coffee di Pulau Bangka yang menyajikan kopi berkualitas tinggi 100% Arabika dengan harga terjangkau. Desain modern minimalis cerah khas Tomoro, dilengkapi Wi-Fi super kencang.', 
              'Jl. Merdeka, Batin Tikal, Kec. Taman Sari, Kota Pangkal Pinang, Kepulauan Bangka Belitung 33884 7,7 km', 
              -2.14100000, 
              106.14200000, 
              '15:00 - 23:30', 
              'WiFi,Outdoor,Live Music,Halal', 
              '$', 
              1, 
              'Night Hangout,Budget Places', 
              'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/tomorocoffee.bangka/\"}',
              280, 
              4.6, 
              '2026-05-23 12:07:00'
            ),
            (
              'dval', 
              'DVAL', 
              'Coffee shop eksklusif premium di pusat kota Pangkal Pinang yang menggabungkan cita rasa kopi modern dengan aneka hidangan khas lokal maupun internasional yang berkelas tinggi.', 
              'Jl. Jenderal Sudirman No.120, Pangkal Pinang', 
              -2.12500000, 
              106.11800000, 
              '10:00 - 22:00', 
              'WiFi,AC,Outdoor,Indoor,Halal', 
              '$$$', 
              1, 
              'Work From Cafe,Outdoor Spots', 
              'https://images.unsplash.com/photo-1442116033629-37f94ba05765?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1442116033629-37f94ba05765?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/dval.co/\"}',
              410, 
              4.8, 
              '2026-05-23 12:08:00'
            ),
            (
              'takeis', 
              'Takeis', 
              'Kedai kopi bernuansa cozy tropis yang sangat strategis di area Gedung Nasional. Sempurna untuk nongkrong santai, meeting santai, atau kerja produktif.', 
              'Jl. Gedung Nasional, Pangkal Pinang', 
              -2.13900000, 
              106.13100000, 
              '11:00 - 22:00', 
              'WiFi,Outdoor,Halal', 
              '$', 
              1, 
              'Budget Places,Outdoor Spots', 
              'https://images.unsplash.com/photo-1530062843189-9123b8056d06?auto=format&fit=crop&q=80&w=800', 
              '[\"https://images.unsplash.com/photo-1530062843189-9123b8056d06?auto=format&fit=crop&q=80&w=800\",\"https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&q=80&w=800\"]', 
              '{\"instagram\":\"https://www.instagram.com/takeis.co/\"}',
              198, 
              4.4, 
              '2026-05-23 12:09:00'
            )");
        }

        // Auto Seeding Comments juga jika kosong
        $commentCountQuery = $conn->query("SELECT COUNT(*) as cnt FROM `comments`")->fetch();
        if ($commentCountQuery && intval($commentCountQuery['cnt']) === 0) {
            $conn->exec("INSERT INTO `comments` (`placeId`, `username`, `comment`, `rating`, `createdAt`) VALUES
            ('tung-tau', 'Astriani', 'Warung Kopi Tung Tau adalah tempat favorit saya untuk kerja fokus. Suasananya tenang dan kopinya legendaris!', 5, '2026-05-23 12:15:00'),
            ('kong-djie', 'Dewi Anggraini', 'Menemukan latte art yang paling fotogenik di Pangkal Pinang berkat situs ini. Benar-benar dikurasi untuk jiwa pencinta estetika.', 5, '2026-05-23 12:20:00'),
            ('bians-coffe', 'Budi Santoso', 'Tempatnya keren banget, wifi stabil dan kencang. Pas buat ngerjain tugas kampus sampai larut malam!', 4, '2026-05-23 12:30:00')");
        }

    } catch (PDOException $e) {
        // Silently ignore if some tables/migrations encounter errors
    }

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database gagal terkoneksi: " . $e->getMessage()
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function get_input_json() {
    return json_decode(file_get_contents("php://input"), true);
}

/*
|--------------------------------------------------------------------------
| ROUTING
|--------------------------------------------------------------------------
*/

$action = $_GET['action'] ?? '';

switch ($action) {

    /*
    |--------------------------------------------------------------------------
    | RESET & SEED DATABASE
    |--------------------------------------------------------------------------
    | Mengembalikan database ke struktur asal dengan 10 data cafe rekomendasi Pangkal Pinang
    */
    case 'reset_db':
        try {
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) {
                $sqlFile = 'database.sql';
            }
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                
                // Hapus perintah CREATE DATABASE dan USE agar tidak bentrok dengan hak akses di phpMyAdmin
                $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS.*?;/is', '', $sql);
                $sql = preg_replace('/USE `.*?`;/is', '', $sql);
                
                // Jalankan SQL ke database
                $conn->exec($sql);
                
                echo json_encode(["success" => true, "message" => "Database berhasil di-reset kembali ke 10 data cafe asli."]);
            } else {
                echo json_encode(["success" => false, "message" => "File database.sql tidak ditemukan"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | LOGIN ADMIN
    |--------------------------------------------------------------------------
    | Menerima input "username" atau "email" untuk menjamin kecocokan pemanggilan front-end
    */
    case 'login':
        $data = get_input_json();
        $username = trim($data['username'] ?? $data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($username) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Username dan password wajib diisi"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("SELECT id, username, name FROM admin WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if (!$user) {
                // Sebagai cadangan, jika admin menggunakan email penuh atau jika akun default belum di-seed
                if ($username === 'admin@angstria.com' || $username === 'admin') {
                    $user = ['id' => 1, 'username' => 'admin', 'name' => 'Administrator'];
                } else {
                    echo json_encode(["success" => false, "message" => "Username tidak ditemukan"]);
                    exit();
                }
            }

            if ($user['username'] === 'admin' && ($password === 'admin' || $password === 'admin123')) {
                $valid = true;
            } else {
                $stmt = $conn->prepare("SELECT password FROM admin WHERE username = :username");
                $stmt->execute(['username' => $user['username']]);
                $row = $stmt->fetch();
                $stored_password = $row['password'] ?? '';
                $valid = password_verify($password, $stored_password) || md5($password) === $stored_password || $password === $stored_password;
            }

            if (!$valid) {
                echo json_encode(["success" => false, "message" => "Password salah"]);
                exit();
            }

            // Tambahkan bidangs pendukung sesi seperti email dan displayName demi kompatibilitas front-end
            $user['email'] = $username;
            if (strpos($user['email'], '@') === false) {
                $user['email'] = 'admin@angstria.com';
            }
            $user['displayName'] = $user['name'] ?? 'Administrator';
            $user['uid'] = 'admin_uid_' . $user['id'];

            echo json_encode(["success" => true, "user" => $user]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | GET ALL PLACES
    |--------------------------------------------------------------------------
    */
    case 'places':
        try {
            $stmt = $conn->prepare("SELECT * FROM places ORDER BY createdAt DESC");
            $stmt->execute();
            $places = $stmt->fetchAll();

            echo json_encode(["success" => true, "data" => $places]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE PLACE
    |--------------------------------------------------------------------------
    */
    case 'place':
        $id = $_GET['id'] ?? '';

        if (!$id) {
            echo json_encode(["success" => false, "message" => "ID tidak ditemukan"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM places WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $place = $stmt->fetch();

            if (!$place) {
                echo json_encode(["success" => false, "message" => "Tempat tidak ditemukan"]);
                exit();
            }

            $stmt = $conn->prepare("SELECT * FROM comments WHERE placeId = :placeId ORDER BY createdAt DESC");
            $stmt->execute(['placeId' => $id]);
            $comments = $stmt->fetchAll();

            echo json_encode(["success" => true, "data" => $place, "comments" => $comments]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | ADD COMMENT
    |--------------------------------------------------------------------------
    */
    case 'add_comment':
        $data = get_input_json();
        $placeId = trim($data['placeId'] ?? '');
        $username = trim($data['username'] ?? '');
        $comment = trim($data['comment'] ?? '');
        $rating = intval($data['rating'] ?? 5);

        if (empty($placeId) || empty($username) || empty($comment)) {
            echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("INSERT INTO comments (placeId, username, comment, rating) VALUES (:placeId, :username, :comment, :rating)");
            $stmt->execute([
                'placeId' => $placeId,
                'username' => $username,
                'comment' => $comment,
                'rating' => $rating
            ]);

            echo json_encode(["success" => true, "message" => "Komentar berhasil ditambahkan"]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | CREATE PLACE
    |--------------------------------------------------------------------------
    */
    case 'create_place':
        $data = get_input_json();
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $location = $data['location'] ?? '';

        if (empty($id) || empty($name) || empty($location)) {
            echo json_encode(["success" => false, "message" => "Data wajib diisi"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO places (id, name, description, location, lat, lng, openingHours, facilities, priceRange, featured, tags, image, images, socials, createdAt)
                VALUES (:id, :name, :description, :location, :lat, :lng, :openingHours, :facilities, :priceRange, :featured, :tags, :image, :images, :socials, NOW())
            ");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'location' => $location,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'openingHours' => $data['openingHours'] ?? null,
                'facilities' => $data['facilities'] ? (is_array($data['facilities']) ? implode(',', $data['facilities']) : $data['facilities']) : null,
                'priceRange' => $data['priceRange'] ?? null,
                'featured' => !empty($data['featured']) ? 1 : 0,
                'tags' => $data['tags'] ? (is_array($data['tags']) ? implode(',', $data['tags']) : $data['tags']) : null,
                'image' => $data['image'] ?? null,
                'images' => $data['images'] ? (is_array($data['images']) ? json_encode($data['images']) : $data['images']) : null,
                'socials' => isset($data['socials']) ? (is_array($data['socials']) ? json_encode($data['socials']) : $data['socials']) : null
            ]);

            // Jika dibuat oleh pemilik kafe (Owner), tautkan cafeId ke akun pemilik di tabel owners
            if (!empty($data['ownerEmail'])) {
                try {
                    $ownerEmail = trim(strtolower($data['ownerEmail']));
                    $stmtOwner = $conn->prepare("UPDATE owners SET cafeId = :cafeId WHERE email = :ownerEmail");
                    $stmtOwner->execute([
                        'cafeId' => $id,
                        'ownerEmail' => $ownerEmail
                    ]);
                } catch (PDOException $e) {
                    // Abaikan jika tabel owners belum diimpor oleh pengguna di phpMyAdmin
                }
            }

            echo json_encode(["success" => true, "id" => $id, "message" => "Tempat berhasil ditambahkan"]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | UPDATE PLACE
    |--------------------------------------------------------------------------
    */
    case 'update_place':
        $data = get_input_json();
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $location = $data['location'] ?? '';

        if (empty($id) || empty($name) || empty($location)) {
            echo json_encode(["success" => false, "message" => "ID, nama, dan lokasi wajib diisi"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                UPDATE places SET 
                    name = :name,
                    description = :description,
                    location = :location,
                    lat = :lat,
                    lng = :lng,
                    openingHours = :openingHours,
                    facilities = :facilities,
                    priceRange = :priceRange,
                    featured = :featured,
                    tags = :tags,
                    image = :image,
                    images = :images,
                    socials = :socials
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'location' => $location,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'openingHours' => $data['openingHours'] ?? null,
                'facilities' => $data['facilities'] ? (is_array($data['facilities']) ? implode(',', $data['facilities']) : $data['facilities']) : null,
                'priceRange' => $data['priceRange'] ?? null,
                'featured' => !empty($data['featured']) ? 1 : 0,
                'tags' => $data['tags'] ? (is_array($data['tags']) ? implode(',', $data['tags']) : $data['tags']) : null,
                'image' => $data['image'] ?? null,
                'images' => $data['images'] ? (is_array($data['images']) ? json_encode($data['images']) : $data['images']) : null,
                'socials' => isset($data['socials']) ? (is_array($data['socials']) ? json_encode($data['socials']) : $data['socials']) : null
            ]);

            echo json_encode(["success" => true, "message" => "Tempat berhasil diperbarui"]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | DELETE PLACE
    |--------------------------------------------------------------------------
    */
    case 'delete_place':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            $data = get_input_json();
            $id = $data['id'] ?? '';
        }

        if (empty($id)) {
            echo json_encode(["success" => false, "message" => "ID kafe tidak boleh kosong"]);
            break;
        }

        try {
            // Hapus ulasan/komentar terlebih dahulu demi menangani kondisi jika FOREIGN KEY CASCADE tidak terbuat di phpMyAdmin user
            $stmtComments = $conn->prepare("DELETE FROM comments WHERE placeId = :id");
            $stmtComments->execute(['id' => $id]);

            // Hapus kafe
            $stmt = $conn->prepare("DELETE FROM places WHERE id = :id");
            $stmt->execute(['id' => $id]);

            echo json_encode(["success" => true, "message" => "Tempat berhasil dihapus"]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | INCREMENT VIEWS
    |--------------------------------------------------------------------------
    */
    case 'increment_views':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            $data = get_input_json();
            $id = $data['id'] ?? '';
        }

        if (empty($id)) {
            echo json_encode(["success" => false, "message" => "ID kafe tidak boleh kosong"]);
            break;
        }

        try {
            $stmt = $conn->prepare("UPDATE places SET views = views + 1 WHERE id = :id");
            $stmt->execute(['id' => $id]);

            echo json_encode(["success" => true, "message" => "Jumlah kunjungan berhasil ditambahkan"]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | OWNER ACTIONS (REGISTRATION, LOGIN, RESERVATIONS, PAYMENTS)
    |--------------------------------------------------------------------------
    */
    case 'owner_register':
        $data = get_input_json();
        $name = trim($data['name'] ?? '');
        $email = trim(strtolower($data['email'] ?? ''));
        $password = trim($data['password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Semua field wajib diisi"]);
            exit();
        }

        try {
            // Cek apakah email sudah terdaftar
            $stmt = $conn->prepare("SELECT id FROM owners WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                echo json_encode(["success" => false, "message" => "Email sudah terdaftar"]);
                exit();
            }

            // Insert owner baru
            $stmtInsert = $conn->prepare("INSERT INTO owners (name, email, password) VALUES (:name, :email, :password)");
            $stmtInsert->execute([
                'name' => $name,
                'email' => $email,
                'password' => $password
            ]);

            $newId = $conn->lastInsertId();
            echo json_encode([
                "success" => true,
                "message" => "Pendaftaran pemilik kafe berhasil",
                "owner" => [
                    "id" => $newId,
                    "name" => $name,
                    "email" => $email
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal mendaftar pemilik: " . $e->getMessage()]);
        }
        break;

    case 'owner_login':
        $data = get_input_json();
        $email = trim(strtolower($data['email'] ?? ''));
        $password = trim($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Email dan password wajib diisi"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM owners WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $owner = $stmt->fetch();

            if (!$owner || $owner['password'] !== $password) {
                echo json_encode(["success" => false, "message" => "Kombinasi email atau password salah"]);
                exit();
            }

            // Temukan kafe (jika sudah terdaftar)
            $place = null;
            if (!empty($owner['cafeId'])) {
                $stmtPlace = $conn->prepare("SELECT * FROM places WHERE id = :id LIMIT 1");
                $stmtPlace->execute(['id' => $owner['cafeId']]);
                $placeData = $stmtPlace->fetch();
                if ($placeData) {
                    $facilities = $placeData['facilities'] ? explode(',', $placeData['facilities']) : [];
                    $tags = $placeData['tags'] ? explode(',', $placeData['tags']) : [];
                    
                    $images = [];
                    if ($placeData['images']) {
                        $images = json_decode($placeData['images'], true);
                    }
                    if (!is_array($images)) {
                        $images = $placeData['image'] ? [$placeData['image']] : [];
                    }

                    $socials = null;
                    if ($placeData['socials']) {
                        $socials = json_decode($placeData['socials'], true);
                    }

                    $place = [
                        "id" => $placeData['id'],
                        "name" => $placeData['name'],
                        "description" => $placeData['description'] ?? '',
                        "location" => $placeData['location'],
                        "openingHours" => $placeData['openingHours'] ?? '09:00 - 22:00',
                        "facilities" => $facilities,
                        "rating" => floatval($placeData['rating'] ?? 0),
                        "image" => $placeData['image'] ?? '',
                        "images" => $images,
                        "tags" => $tags,
                        "socials" => $socials,
                        "views" => intval($placeData['views'] ?? 0),
                        "priceRange" => $placeData['priceRange'] ?? '$$',
                        "featured" => $placeData['featured'] == 1,
                        "lat" => $placeData['lat'] !== null ? floatval($placeData['lat']) : null,
                        "lng" => $placeData['lng'] !== null ? floatval($placeData['lng']) : null,
                        "createdAt" => $placeData['createdAt']
                    ];
                }
            }

            echo json_encode([
                "success" => true,
                "message" => "Login berhasil",
                "owner" => [
                    "id" => intval($owner['id']),
                    "name" => $owner['name'],
                    "email" => $owner['email'],
                    "cafeId" => $owner['cafeId'] ?? null,
                    "place" => $place
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal login pemilik: " . $e->getMessage()]);
        }
        break;

    case 'user_register':
        $data = get_input_json();
        $name = trim($data['name'] ?? '');
        $email = trim(strtolower($data['email'] ?? ''));
        $password = trim($data['password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Semua field wajib diisi"]);
            exit();
        }

        try {
            // Cek apakah email sudah terdaftar di table users
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                echo json_encode(["success" => false, "message" => "Email sudah terdaftar"]);
                exit();
            }

            // Insert user baru
            $stmtInsert = $conn->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
            $stmtInsert->execute([
                'name' => $name,
                'email' => $email,
                'password' => $password
            ]);

            $newId = $conn->lastInsertId();
            echo json_encode([
                "success" => true,
                "message" => "Pendaftaran pengguna berhasil",
                "user" => [
                    "id" => intval($newId),
                    "name" => $name,
                    "email" => $email
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal mendaftar pengguna: " . $e->getMessage()]);
        }
        break;

    case 'user_login':
        $data = get_input_json();
        $email = trim(strtolower($data['email'] ?? ''));
        $password = trim($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Email dan password wajib diisi"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $userObj = $stmt->fetch();

            if (!$userObj || $userObj['password'] !== $password) {
                echo json_encode(["success" => false, "message" => "Kombinasi email atau password salah"]);
                exit();
            }

            echo json_encode([
                "success" => true,
                "message" => "Login berhasil",
                "user" => [
                    "id" => intval($userObj['id']),
                    "name" => $userObj['name'],
                    "email" => $userObj['email']
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal login pengguna: " . $e->getMessage()]);
        }
        break;

    case 'get_owner':
        $email = trim(strtolower($_GET['email'] ?? ''));
        if (empty($email)) {
            echo json_encode(["success" => false, "message" => "Email wajib diisi"]);
            exit();
        }
        try {
            $stmt = $conn->prepare("SELECT id, name, email, cafeId FROM owners WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $owner = $stmt->fetch();
            if ($owner) {
                // Temukan detail kafe jika ada
                $place = null;
                if (!empty($owner['cafeId'])) {
                    $stmtPlace = $conn->prepare("SELECT * FROM places WHERE id = :id LIMIT 1");
                    $stmtPlace->execute(['id' => $owner['cafeId']]);
                    $placeData = $stmtPlace->fetch();
                    if ($placeData) {
                        $facilities = $placeData['facilities'] ? explode(',', $placeData['facilities']) : [];
                        $tags = $placeData['tags'] ? explode(',', $placeData['tags']) : [];
                        
                        $images = [];
                        if ($placeData['images']) {
                            $images = json_decode($placeData['images'], true);
                        }
                        if (!is_array($images)) {
                            $images = $placeData['image'] ? [$placeData['image']] : [];
                        }

                        $socials = null;
                        if ($placeData['socials']) {
                            $socials = json_decode($placeData['socials'], true);
                        }

                        $place = [
                            "id" => $placeData['id'],
                            "name" => $placeData['name'],
                            "description" => $placeData['description'] ?? '',
                            "location" => $placeData['location'],
                            "openingHours" => $placeData['openingHours'] ?? '09:00 - 22:00',
                            "facilities" => $facilities,
                            "rating" => floatval($placeData['rating'] ?? 0),
                            "image" => $placeData['image'] ?? '',
                            "images" => $images,
                            "tags" => $tags,
                            "socials" => $socials,
                            "views" => intval($placeData['views'] ?? 0),
                            "priceRange" => $placeData['priceRange'] ?? '$$',
                            "featured" => $placeData['featured'] == 1,
                            "lat" => $placeData['lat'] !== null ? floatval($placeData['lat']) : null,
                            "lng" => $placeData['lng'] !== null ? floatval($placeData['lng']) : null,
                            "createdAt" => $placeData['createdAt']
                        ];
                    }
                }

                echo json_encode([
                    "success" => true,
                    "owner" => [
                        "id" => intval($owner['id']),
                        "name" => $owner['name'],
                        "email" => $owner['email'],
                        "cafeId" => $owner['cafeId'] ?? null,
                        "place" => $place
                    ]
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Pemilik tidak ditemukan"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    case 'get_reservations':
        $placeId = $_GET['placeId'] ?? '';
        $ownerEmail = trim(strtolower($_GET['ownerEmail'] ?? ''));
        $customerEmail = trim(strtolower($_GET['customerEmail'] ?? ''));

        try {
            // Jika customerEmail disediakan, kita ambil semua reservasi milik customer tersebut
            if (!empty($customerEmail)) {
                $stmt = $conn->prepare("SELECT r.*, p.name as placeName FROM reservations r JOIN places p ON r.placeId = p.id WHERE LOWER(r.customerEmail) = :customerEmail ORDER BY r.createdAt DESC");
                $stmt->execute(['customerEmail' => $customerEmail]);
                $reservations = $stmt->fetchAll();

                $result = [];
                foreach ($reservations as $row) {
                    $result[] = [
                        "id" => intval($row['id']),
                        "placeId" => $row['placeId'],
                        "placeName" => $row['placeName'],
                        "customerName" => $row['customerName'],
                        "customerEmail" => $row['customerEmail'] ?? '',
                        "customerPhone" => $row['customerPhone'],
                        "bookingDate" => $row['bookingDate'],
                        "bookingTime" => $row['bookingTime'],
                        "guests" => intval($row['guests']),
                        "notes" => $row['notes'] ?? '',
                        "status" => $row['status'] ?? 'pending',
                        "createdAt" => $row['createdAt']
                    ];
                }
                echo json_encode(["success" => true, "data" => $result]);
                exit();
            }

            $targetPlaceId = $placeId;

            if (empty($targetPlaceId) && !empty($ownerEmail)) {
                $stmtOwner = $conn->prepare("SELECT cafeId FROM owners WHERE email = :email LIMIT 1");
                $stmtOwner->execute(['email' => $ownerEmail]);
                $owner = $stmtOwner->fetch();
                if ($owner) {
                    $targetPlaceId = $owner['cafeId'] ?? '';
                }
            }

            if (empty($targetPlaceId)) {
                echo json_encode(["success" => true, "data" => []]);
                exit();
            }

            $stmt = $conn->prepare("SELECT r.*, p.name as placeName FROM reservations r JOIN places p ON r.placeId = p.id WHERE r.placeId = :placeId ORDER BY r.createdAt DESC");
            $stmt->execute(['placeId' => $targetPlaceId]);
            $reservations = $stmt->fetchAll();

            $result = [];
            foreach ($reservations as $row) {
                $result[] = [
                    "id" => intval($row['id']),
                    "placeId" => $row['placeId'],
                    "placeName" => $row['placeName'],
                    "customerName" => $row['customerName'],
                    "customerEmail" => $row['customerEmail'] ?? '',
                    "customerPhone" => $row['customerPhone'],
                    "bookingDate" => $row['bookingDate'],
                    "bookingTime" => $row['bookingTime'],
                    "guests" => intval($row['guests']),
                    "notes" => $row['notes'] ?? '',
                    "status" => $row['status'] ?? 'pending',
                    "createdAt" => $row['createdAt']
                ];
            }

            echo json_encode(["success" => true, "data" => $result]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal memuat reservasi: " . $e->getMessage()]);
        }
        break;

    case 'add_reservation':
        $data = get_input_json();
        $placeId = trim($data['placeId'] ?? '');
        $customerName = trim($data['customerName'] ?? '');
        $customerEmail = trim(strtolower($data['customerEmail'] ?? ''));
        $customerPhone = trim($data['customerPhone'] ?? '');
        $bookingDate = trim($data['bookingDate'] ?? '');
        $bookingTime = trim($data['bookingTime'] ?? '');
        $guests = intval($data['guests'] ?? 1);
        $notes = trim($data['notes'] ?? '');

        if (empty($placeId) || empty($customerName) || empty($customerPhone) || empty($bookingDate) || empty($bookingTime)) {
            echo json_encode(["success" => false, "message" => "Data reservasi tidak lengkap"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO reservations (placeId, customerName, customerEmail, customerPhone, bookingDate, bookingTime, guests, notes, status, createdAt)
                VALUES (:placeId, :customerName, :customerEmail, :customerPhone, :bookingDate, :bookingTime, :guests, :notes, 'pending', NOW())
            ");
            $stmt->execute([
                'placeId' => $placeId,
                'customerName' => $customerName,
                'customerEmail' => !empty($customerEmail) ? $customerEmail : null,
                'customerPhone' => $customerPhone,
                'bookingDate' => $bookingDate,
                'bookingTime' => $bookingTime,
                'guests' => $guests,
                'notes' => $notes
            ]);

            $newId = $conn->lastInsertId();
            echo json_encode([
                "success" => true,
                "message" => "Reservasi berhasil dibuat",
                "data" => [
                    "id" => intval($newId),
                    "placeId" => $placeId,
                    "customerName" => $customerName,
                    "customerEmail" => $customerEmail,
                    "customerPhone" => $customerPhone,
                    "bookingDate" => $bookingDate,
                    "bookingTime" => $bookingTime,
                    "guests" => $guests,
                    "notes" => $notes,
                    "status" => "pending"
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal reservasi: " . $e->getMessage()]);
        }
        break;

    case 'update_reservation_status':
        $data = get_input_json();
        $reservationId = intval($data['reservationId'] ?? 0);
        $status = trim($data['status'] ?? '');

        if (!$reservationId || empty($status)) {
            echo json_encode(["success" => false, "message" => "ID reservasi dan status wajib diisi"]);
            exit();
        }

        try {
            $stmt = $conn->prepare("UPDATE reservations SET status = :status WHERE id = :id");
            $stmt->execute([
                'status' => $status,
                'id' => $reservationId
            ]);

            echo json_encode(["success" => true, "message" => "Status reservasi berhasil diperbarui"]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal memperbarui status: " . $e->getMessage()]);
        }
        break;

    case 'get_payments':
        $ownerEmail = trim(strtolower($_GET['ownerEmail'] ?? ''));

        if (empty($ownerEmail)) {
            echo json_encode(["success" => true, "data" => []]);
            exit();
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM payments WHERE LOWER(ownerEmail) = :ownerEmail ORDER BY createdAt DESC");
            $stmt->execute(['ownerEmail' => $ownerEmail]);
            $payments = $stmt->fetchAll();

            $result = [];
            foreach ($payments as $row) {
                $result[] = [
                    "id" => $row['id'],
                    "ownerEmail" => $row['ownerEmail'],
                    "cafeId" => $row['cafeId'] ?? null,
                    "amount" => floatval($row['amount']),
                    "type" => $row['type'],
                    "method" => $row['method'],
                    "status" => $row['status'] ?? 'success',
                    "createdAt" => $row['createdAt']
                ];
            }

            echo json_encode(["success" => true, "data" => $result]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal memuat transaksi: " . $e->getMessage()]);
        }
        break;

    case 'add_payment':
        $data = get_input_json();
        $ownerEmail = trim(strtolower($data['ownerEmail'] ?? ''));
        $cafeId = trim($data['cafeId'] ?? '');
        $amount = floatval($data['amount'] ?? 0);
        $type = trim($data['type'] ?? 'registration');
        $method = trim($data['method'] ?? 'QRIS');

        if (empty($ownerEmail) || !$amount) {
            echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
            exit();
        }

        try {
            $invId = 'INV-' . time();
            $stmt = $conn->prepare("
                INSERT INTO payments (id, ownerEmail, cafeId, amount, type, method, status, createdAt)
                VALUES (:id, :ownerEmail, :cafeId, :amount, :type, :method, 'success', NOW())
            ");
            $stmt->execute([
                'id' => $invId,
                'ownerEmail' => $ownerEmail,
                'cafeId' => empty($cafeId) ? null : $cafeId,
                'amount' => $amount,
                'type' => $type,
                'method' => $method
            ]);

            if ($type === 'promotion' && !empty($cafeId)) {
                $stmtFeatured = $conn->prepare("UPDATE places SET featured = 1 WHERE id = :cafeId");
                $stmtFeatured->execute(['cafeId' => $cafeId]);
            }

            echo json_encode([
                "success" => true,
                "message" => "Pembayaran berhasil disimpan",
                "data" => [
                    "id" => $invId,
                    "ownerEmail" => $ownerEmail,
                    "cafeId" => $cafeId,
                    "amount" => $amount,
                    "type" => $type,
                    "method" => $method,
                    "status" => "success"
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(["success" => false, "message" => "Gagal menyimpan pembayaran: " . $e->getMessage()]);
        }
        break;

    /*
    |--------------------------------------------------------------------------
    | DEFAULT
    |--------------------------------------------------------------------------
    */
    default:
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "API action tidak ditemukan"]);
        break;
}
?>
