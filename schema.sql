-- ====================================================================
-- Angstria Hangout - MySQL Database Export Script for phpMyAdmin
-- Desired Location: Pangkal Pinang Hangout Recommendations
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `angstria_hangout` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `angstria_hangout`;

-- 1. Table Structure for `places`
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `places`;

CREATE TABLE `places` (
  `id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(255) NOT NULL,
  `lat` DECIMAL(10, 8) DEFAULT NULL,
  `lng` DECIMAL(11, 8) DEFAULT NULL,
  `openingHours` VARCHAR(100) DEFAULT NULL,
  `facilities` TEXT DEFAULT NULL, -- Stored as comma-separated or JSON string
  `priceRange` VARCHAR(10) DEFAULT NULL,
  `featured` TINYINT(1) DEFAULT 0,
  `tags` TEXT DEFAULT NULL, -- Stored as comma-separated or JSON string
  `image` LONGTEXT DEFAULT NULL,
  `images` LONGTEXT DEFAULT NULL, -- Stored as JSON array string
  `socials` LONGTEXT DEFAULT NULL, -- Stored as JSON object string
  `views` INT DEFAULT 0,
  `rating` DECIMAL(3, 1) DEFAULT 0.0,
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table Structure for `comments`
CREATE TABLE `comments` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `placeId` VARCHAR(50) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `comment` TEXT NOT NULL,
  `rating` INT NOT NULL,
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_comments_places` FOREIGN KEY (`placeId`) REFERENCES `places` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table Structure for `admin`
CREATE TABLE `admin` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeding Data for `admin`
INSERT INTO `admin` (`id`, `username`, `password`, `name`) VALUES
(1, 'admin', 'admin123', 'Administrator');

-- 4. Seeding Data for `places`
INSERT INTO `places` (`id`, `name`, `description`, `location`, `lat`, `lng`, `openingHours`, `facilities`, `priceRange`, `featured`, `tags`, `image`, `images`, `socials`, `views`, `rating`, `createdAt`) VALUES
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
  '["https://images.unsplash.com/photo-1554118811-1e0d58224f24?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/kopitungtau/","website":"https://www.kopitungtau.com"}',
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
  '["https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/kongdjie_coffee/"}',
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
  '["https://images.unsplash.com/photo-1559925393-8be0ec4767c8?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/bianscoffe_eatery/"}',
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
  '["https://images.unsplash.com/photo-1536256263959-770b48d82b0a?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/kinikawa.coffee/"}',
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
  '["https://images.unsplash.com/photo-1445116572660-236099ec97a0?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1521017432531-fbd92d768814?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/d.lab_idn/"}',
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
  '["https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1597481499750-3e6b22637e12?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/kelatea/"}',
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
  '["https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1600891964599-f61ba0e24092?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/warunksteakmilenial/"}',
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
  '["https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/tomorocoffee.bangka/"}',
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
  '["https://images.unsplash.com/photo-1442116033629-37f94ba05765?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/dval.co/"}',
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
  '["https://images.unsplash.com/photo-1530062843189-9123b8056d06?auto=format&fit=crop&q=80&w=800","https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&q=80&w=800"]', 
  '{"instagram":"https://www.instagram.com/takeis.co/"}',
  198, 
  4.4, 
  '2026-05-23 12:09:00'
);

-- 5. Seeding Data for `comments`
INSERT INTO `comments` (`placeId`, `username`, `comment`, `rating`, `createdAt`) VALUES
('tung-tau', 'Astriani', 'Warung Kopi Tung Tau adalah tempat favorit saya untuk kerja fokus. Suasananya tenang dan kopinya legendaris!', 5, '2026-05-23 12:15:00'),
('kong-djie', 'Dewi Anggraini', 'Menemukan latte art yang paling fotogenik di Pangkal Pinang berkat situs ini. Benar-benar dikurasi untuk jiwa pencinta estetika.', 5, '2026-05-23 12:20:00'),
('bians-coffe', 'Budi Santoso', 'Tempatnya keren banget, wifi stabil dan kencang. Pas buat ngerjain tugas kampus sampai larut malam!', 4, '2026-05-23 12:30:00');

-- 6. Table Structure for `owners`
CREATE TABLE IF NOT EXISTS `owners` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `cafeId` VARCHAR(50) DEFAULT NULL,
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table Structure for `reservations`
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `placeId` VARCHAR(50) NOT NULL,
  `customerName` VARCHAR(100) NOT NULL,
  `customerPhone` VARCHAR(50) NOT NULL,
  `bookingDate` VARCHAR(50) NOT NULL,
  `bookingTime` VARCHAR(50) NOT NULL,
  `guests` INT NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'pending',
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table Structure for `payments`
CREATE TABLE IF NOT EXISTS `payments` (
  `id` VARCHAR(50) NOT NULL,
  `ownerEmail` VARCHAR(100) NOT NULL,
  `cafeId` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `type` VARCHAR(50) NOT NULL, -- 'registration' or 'promotion'
  `method` VARCHAR(50) NOT NULL,
  `status` VARCHAR(20) DEFAULT 'success',
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

