-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: aplikasi_travel
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `aplikasi_travel`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `aplikasi_travel` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `aplikasi_travel`;

--
-- Table structure for table `biaya_operasional_perjalanan`
--

DROP TABLE IF EXISTS `biaya_operasional_perjalanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `biaya_operasional_perjalanan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jadwal_id` bigint unsigned NOT NULL,
  `jenis_biaya` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `tanggal_biaya` datetime NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `biaya_operasional_perjalanan_jadwal_id_foreign` (`jadwal_id`),
  KEY `biaya_operasional_perjalanan_created_by_foreign` (`created_by`),
  CONSTRAINT `biaya_operasional_perjalanan_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL,
  CONSTRAINT `biaya_operasional_perjalanan_jadwal_id_foreign` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `biaya_operasional_perjalanan`
--

LOCK TABLES `biaya_operasional_perjalanan` WRITE;
/*!40000 ALTER TABLE `biaya_operasional_perjalanan` DISABLE KEYS */;
INSERT INTO `biaya_operasional_perjalanan` VALUES (1,2,'BBM Pagi',250000.00,'Pengisian solar sebelum keberangkatan jadwal pagi.','2026-04-22 06:45:00',1,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,2,'Tol dan Parkir',85000.00,'Tol akses keluar kota dan parkir penjemputan.','2026-04-22 08:10:00',1,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,4,'Makan Pengemudi',45000.00,'Konsumsi driver saat perjalanan jarak jauh.','2026-04-21 12:30:00',1,'2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `biaya_operasional_perjalanan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detail_pemesanan`
--

DROP TABLE IF EXISTS `detail_pemesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pemesanan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pemesanan_id` bigint unsigned NOT NULL,
  `kursi_jadwal_id` bigint unsigned NOT NULL,
  `nomor_kursi` int unsigned NOT NULL,
  `nama_penumpang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp_penumpang` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_kehadiran` enum('menunggu','hadir','tidak_hadir') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `detail_pemesanan_kursi_jadwal_id_unique` (`kursi_jadwal_id`),
  KEY `detail_pemesanan_pemesanan_id_foreign` (`pemesanan_id`),
  CONSTRAINT `detail_pemesanan_kursi_jadwal_id_foreign` FOREIGN KEY (`kursi_jadwal_id`) REFERENCES `kursi_jadwal` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `detail_pemesanan_pemesanan_id_foreign` FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detail_pemesanan`
--

LOCK TABLES `detail_pemesanan` WRITE;
/*!40000 ALTER TABLE `detail_pemesanan` DISABLE KEYS */;
INSERT INTO `detail_pemesanan` VALUES (1,1,1,1,'Ihsan Al Husna','081234567891','menunggu','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,1,2,2,'Teman TravS','081234567893','menunggu','2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,2,13,1,'Ihsan Penumpang','081234567891','menunggu','2026-04-22 00:03:50','2026-04-22 00:03:50'),(4,3,19,1,'Fitri Penumpang','081234567895','hadir','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `detail_pemesanan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_bagasi`
--

DROP TABLE IF EXISTS `item_bagasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_bagasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pemesanan_id` bigint unsigned NOT NULL,
  `deskripsi_item` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `berat_kg` decimal(5,2) DEFAULT NULL,
  `ukuran_cm` int unsigned DEFAULT NULL,
  `jumlah_item` int unsigned NOT NULL DEFAULT '1',
  `foto_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biaya_item` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_bagasi_pemesanan_id_foreign` (`pemesanan_id`),
  CONSTRAINT `item_bagasi_pemesanan_id_foreign` FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_bagasi`
--

LOCK TABLES `item_bagasi` WRITE;
/*!40000 ALTER TABLE `item_bagasi` DISABLE KEYS */;
INSERT INTO `item_bagasi` VALUES (1,2,'Koper kabin hitam',12.00,110,1,NULL,15000.00,'2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `item_bagasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jadwal`
--

DROP TABLE IF EXISTS `jadwal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jadwal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kendaraan_id` bigint unsigned NOT NULL,
  `rute_id` bigint unsigned NOT NULL,
  `pengemudi_id` bigint unsigned NOT NULL,
  `waktu_berangkat` datetime NOT NULL,
  `estimasi_tiba` datetime DEFAULT NULL,
  `status` enum('draft','dibuka','berangkat','selesai','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jadwal_kendaraan_id_foreign` (`kendaraan_id`),
  KEY `jadwal_rute_id_foreign` (`rute_id`),
  KEY `jadwal_pengemudi_id_foreign` (`pengemudi_id`),
  CONSTRAINT `jadwal_kendaraan_id_foreign` FOREIGN KEY (`kendaraan_id`) REFERENCES `kendaraan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jadwal_pengemudi_id_foreign` FOREIGN KEY (`pengemudi_id`) REFERENCES `pengemudi` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `jadwal_rute_id_foreign` FOREIGN KEY (`rute_id`) REFERENCES `rute` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jadwal`
--

LOCK TABLES `jadwal` WRITE;
/*!40000 ALTER TABLE `jadwal` DISABLE KEYS */;
INSERT INTO `jadwal` VALUES (1,1,1,1,'2026-04-23 07:30:00','2026-04-23 15:30:00','dibuka','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,2,2,2,'2026-04-22 09:30:00','2026-04-22 14:45:00','dibuka','2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,2,2,2,'2026-04-24 08:00:00','2026-04-24 13:30:00','dibuka','2026-04-22 00:03:50','2026-04-22 00:03:50'),(4,1,3,1,'2026-04-21 06:00:00','2026-04-21 15:00:00','selesai','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `jadwal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kendaraan`
--

DROP TABLE IF EXISTS `kendaraan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kendaraan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nomor_plat` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `merk_kendaraan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warna` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tahun_kendaraan` int unsigned DEFAULT NULL,
  `tipe_kendaraan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kapasitas_kursi` int unsigned NOT NULL,
  `fasilitas` text COLLATE utf8mb4_unicode_ci,
  `total_kilometer` int unsigned NOT NULL DEFAULT '0',
  `km_servis_berikutnya` int unsigned DEFAULT NULL,
  `tgl_terakhir_servis` date DEFAULT NULL,
  `tgl_pajak_tahunan` date DEFAULT NULL,
  `status_kendaraan` enum('aktif','perawatan','nonaktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kendaraan_nomor_plat_unique` (`nomor_plat`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kendaraan`
--

LOCK TABLES `kendaraan` WRITE;
/*!40000 ALTER TABLE `kendaraan` DISABLE KEYS */;
INSERT INTO `kendaraan` VALUES (1,'KB 1234 TS','Toyota Hiace Premio','Putih',2023,'Travel Executive',6,'AC, charger USB, reclining seat',0,NULL,NULL,NULL,'aktif','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,'KB 5678 TS','Daihatsu Luxio','Biru Malam',2022,'Travel Regular',6,'AC, bagasi luas',0,NULL,NULL,NULL,'aktif','2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,'KB 9012 TS','Isuzu ELF NLR','Silver',2021,'Travel Shuttle',8,'AC, headrest, bagasi atas',0,NULL,NULL,NULL,'perawatan','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `kendaraan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kursi_jadwal`
--

DROP TABLE IF EXISTS `kursi_jadwal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kursi_jadwal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jadwal_id` bigint unsigned NOT NULL,
  `nomor_kursi` int unsigned NOT NULL,
  `status_kursi` enum('tersedia','terkunci','dipesan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tersedia',
  `dikunci_oleh` bigint unsigned DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `lock_expires_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kursi_jadwal_jadwal_id_nomor_kursi_unique` (`jadwal_id`,`nomor_kursi`),
  KEY `kursi_jadwal_dikunci_oleh_foreign` (`dikunci_oleh`),
  CONSTRAINT `kursi_jadwal_dikunci_oleh_foreign` FOREIGN KEY (`dikunci_oleh`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kursi_jadwal_jadwal_id_foreign` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kursi_jadwal`
--

LOCK TABLES `kursi_jadwal` WRITE;
/*!40000 ALTER TABLE `kursi_jadwal` DISABLE KEYS */;
INSERT INTO `kursi_jadwal` VALUES (1,1,1,'dipesan',NULL,NULL,NULL,'2026-04-22 00:03:50'),(2,1,2,'dipesan',NULL,NULL,NULL,'2026-04-22 00:03:50'),(3,1,3,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(4,1,4,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(5,1,5,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(6,1,6,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(7,2,1,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(8,2,2,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(9,2,3,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(10,2,4,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(11,2,5,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(12,2,6,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(13,3,1,'terkunci',2,'2026-04-22 07:03:50','2026-04-22 07:13:50','2026-04-22 00:03:50'),(14,3,2,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(15,3,3,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(16,3,4,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(17,3,5,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(18,3,6,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(19,4,1,'dipesan',NULL,NULL,NULL,'2026-04-22 00:03:50'),(20,4,2,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(21,4,3,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(22,4,4,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(23,4,5,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50'),(24,4,6,'tersedia',NULL,NULL,NULL,'2026-04-22 00:03:50');
/*!40000 ALTER TABLE `kursi_jadwal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_04_21_183707_create_personal_access_tokens_table',1),(5,'2026_04_22_000100_create_master_operasional_tables',1),(6,'2026_04_22_000200_create_transaksi_tables',1),(7,'2026_04_22_000300_create_support_tables',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi`
--

DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pengguna_id` bigint unsigned NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` enum('pembayaran','perjalanan','sistem') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sistem',
  `referensi_id` bigint unsigned DEFAULT NULL,
  `sudah_dibaca` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifikasi_pengguna_id_foreign` (`pengguna_id`),
  CONSTRAINT `notifikasi_pengguna_id_foreign` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi`
--

LOCK TABLES `notifikasi` WRITE;
/*!40000 ALTER TABLE `notifikasi` DISABLE KEYS */;
INSERT INTO `notifikasi` VALUES (1,2,'Pembayaran berhasil dikonfirmasi','Pembayaran untuk booking TRAVS-DEMO-001 sudah berhasil dan tiket elektronik telah diterbitkan.','pembayaran',1,0,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,2,'Pengingat keberangkatan Pontianak - Sanggau','Perjalanan Anda dijadwalkan berangkat dua hari lagi. Mohon selesaikan pembayaran sebelum batas waktu berakhir.','perjalanan',2,0,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,4,'Jadwal hari ini siap berangkat','Anda memiliki jadwal keberangkatan hari ini untuk rute Pontianak - Sanggau pukul 09:30.','perjalanan',2,1,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(4,1,'Rekap biaya operasional diperbarui','Tiga item biaya operasional baru telah dicatat dan siap ditinjau pada modul rekap biaya.','sistem',2,0,'2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `notifikasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pembayaran`
--

DROP TABLE IF EXISTS `pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembayaran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pemesanan_id` bigint unsigned NOT NULL,
  `order_id_gateway` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metode_bayar` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_channel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_bayar` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status_bayar` enum('menunggu','berhasil','gagal','kedaluwarsa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `gateway_response` text COLLATE utf8mb4_unicode_ci,
  `raw_webhook_payload` json DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `webhook_verified_at` datetime DEFAULT NULL,
  `dibayar_pada` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembayaran_pemesanan_id_foreign` (`pemesanan_id`),
  CONSTRAINT `pembayaran_pemesanan_id_foreign` FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pembayaran`
--

LOCK TABLES `pembayaran` WRITE;
/*!40000 ALTER TABLE `pembayaran` DISABLE KEYS */;
INSERT INTO `pembayaran` VALUES (1,1,'PKS-DEMO-001','TRX-TRAVS-DEMO-001','qris','PakKasir Demo',465000.00,'berhasil','Pembayaran demo berhasil.',NULL,NULL,NULL,'2026-04-22 07:03:50','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,2,'DRAFT-DEMO-002','TRX-TRAVS-DEMO-002','transfer','Transfer Bank',195000.00,'menunggu','Menunggu transfer dari penumpang.',NULL,'2026-04-22 07:13:50',NULL,NULL,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,3,'CASH-DEMO-003','TRX-TRAVS-DEMO-003','cash','Bayar Tunai',235000.00,'berhasil','Pembayaran tunai diverifikasi admin.',NULL,NULL,'2026-04-21 05:45:00','2026-04-21 05:45:00','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `pembayaran` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pemesanan`
--

DROP TABLE IF EXISTS `pemesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pemesanan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pengguna_id` bigint unsigned NOT NULL,
  `jadwal_id` bigint unsigned NOT NULL,
  `kode_booking` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_kursi` int unsigned NOT NULL,
  `batas_kunci_kursi` datetime DEFAULT NULL,
  `batas_pembayaran` datetime DEFAULT NULL,
  `lintang_jemput` decimal(10,7) DEFAULT NULL,
  `bujur_jemput` decimal(10,7) DEFAULT NULL,
  `alamat_jemput` text COLLATE utf8mb4_unicode_ci,
  `subtotal_tiket` decimal(12,2) NOT NULL DEFAULT '0.00',
  `biaya_bagasi` decimal(12,2) NOT NULL DEFAULT '0.00',
  `biaya_admin` decimal(12,2) NOT NULL DEFAULT '0.00',
  `diskon` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_harga` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status_pemesanan` enum('draft','menunggu_pembayaran','terbayar','dibatalkan','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pemesanan_kode_booking_unique` (`kode_booking`),
  KEY `pemesanan_pengguna_id_foreign` (`pengguna_id`),
  KEY `pemesanan_jadwal_id_foreign` (`jadwal_id`),
  CONSTRAINT `pemesanan_jadwal_id_foreign` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pemesanan_pengguna_id_foreign` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pemesanan`
--

LOCK TABLES `pemesanan` WRITE;
/*!40000 ALTER TABLE `pemesanan` DISABLE KEYS */;
INSERT INTO `pemesanan` VALUES (1,2,1,'TRAVS-DEMO-001',2,'2026-04-22 07:33:50','2026-04-22 07:08:50',-0.0225000,109.3425000,'Jalan Ampera Raya, Pontianak',450000.00,10000.00,5000.00,0.00,465000.00,'terbayar','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,2,3,'TRAVS-DEMO-002',1,'2026-04-22 07:18:50','2026-04-22 07:13:50',-0.0260000,109.3271000,'Terminal Batu Layang, Pontianak',175000.00,15000.00,5000.00,0.00,195000.00,'menunggu_pembayaran','2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,5,4,'TRAVS-DEMO-003',1,'2026-04-20 07:03:50','2026-04-20 07:03:50',-0.0201000,109.3389000,'Jalan Ahmad Yani, Pontianak',240000.00,0.00,5000.00,10000.00,235000.00,'selesai','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `pemesanan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengaturan_sistem`
--

DROP TABLE IF EXISTS `pengaturan_sistem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengaturan_sistem` (
  `kunci` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nilai` text COLLATE utf8mb4_unicode_ci,
  `tipe_data` enum('integer','decimal','string','boolean','json') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `grup` enum('umum','pemesanan','pembayaran','bagasi','notifikasi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`kunci`),
  KEY `pengaturan_sistem_updated_by_foreign` (`updated_by`),
  KEY `pengaturan_sistem_grup_index` (`grup`),
  CONSTRAINT `pengaturan_sistem_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengaturan_sistem`
--

LOCK TABLES `pengaturan_sistem` WRITE;
/*!40000 ALTER TABLE `pengaturan_sistem` DISABLE KEYS */;
INSERT INTO `pengaturan_sistem` VALUES ('admin_contact','08123456789','string','umum','Kontak utama admin operasional.','2026-04-22 00:03:48',NULL),('admin_fee','5000','decimal','pembayaran','Biaya admin tetap per transaksi.','2026-04-22 00:03:48',NULL),('app_logo','travs-logo.svg','string','umum','Path atau nama file logo aplikasi.','2026-04-22 00:03:48',NULL),('app_name','TravS','string','umum','Nama aplikasi yang tampil di seluruh platform.','2026-04-22 00:03:48',NULL),('auto_cancel_booking','true','boolean','pemesanan','Otomatis membatalkan booking jika pembayaran melewati batas waktu.','2026-04-22 00:03:48',NULL),('baggage_extra_fee_per_cm','500','decimal','bagasi','Tarif tambahan per centimeter.','2026-04-22 00:03:48',NULL),('baggage_extra_fee_per_item','15000','decimal','bagasi','Tarif tambahan per item bagasi.','2026-04-22 00:03:48',NULL),('baggage_extra_fee_per_kg','10000','decimal','bagasi','Tarif tambahan per kilogram.','2026-04-22 00:03:48',NULL),('baggage_free_item_count','1','integer','bagasi','Batas gratis jumlah item bagasi.','2026-04-22 00:03:48',NULL),('baggage_free_size_cm','100','integer','bagasi','Batas gratis ukuran bagasi.','2026-04-22 00:03:48',NULL),('baggage_free_weight_kg','10','integer','bagasi','Batas gratis berat bagasi per penumpang.','2026-04-22 00:03:48',NULL),('baggage_item_enabled','true','boolean','bagasi','Aktifkan hitung bagasi berbasis jumlah item.','2026-04-22 00:03:48',NULL),('baggage_max_item_per_person','3','integer','bagasi','Batas maksimal item bagasi per penumpang.','2026-04-22 00:03:48',NULL),('baggage_max_size_per_person_cm','150','integer','bagasi','Batas maksimal ukuran per penumpang.','2026-04-22 00:03:48',NULL),('baggage_max_total_weight_per_vehicle','50','integer','bagasi','Batas total berat bagasi dalam satu kendaraan.','2026-04-22 00:03:48',NULL),('baggage_max_weight_per_person_kg','20','integer','bagasi','Batas maksimal berat bagasi per penumpang.','2026-04-22 00:03:48',NULL),('baggage_photo_required','true','boolean','bagasi','Menentukan apakah foto bagasi wajib diunggah.','2026-04-22 00:03:48',NULL),('baggage_size_enabled','false','boolean','bagasi','Aktifkan hitung bagasi berbasis ukuran.','2026-04-22 00:03:48',NULL),('baggage_weight_enabled','true','boolean','bagasi','Aktifkan hitung bagasi berbasis berat.','2026-04-22 00:03:48',NULL),('bank_account_name','Rusly Travel','string','pembayaran','Nama pemilik rekening penerima.','2026-04-22 00:03:48',NULL),('bank_account_number','1234567890','string','pembayaran','Nomor rekening penerima.','2026-04-22 00:03:48',NULL),('bank_name','BRI','string','pembayaran','Nama bank rekening penerima.','2026-04-22 00:03:48',NULL),('max_seat_per_booking','4','integer','pemesanan','Jumlah maksimal kursi per transaksi.','2026-04-22 00:03:48',NULL),('notif_departure_reminder','true','boolean','notifikasi','Status notifikasi pengingat keberangkatan.','2026-04-22 00:03:48',NULL),('notif_departure_reminder_template','Pengingat: perjalanan {rute} akan berangkat pada {tanggal} pukul {jam}.','string','notifikasi','Template pengingat keberangkatan.','2026-04-22 00:03:48',NULL),('notif_payment_success','true','boolean','notifikasi','Status notifikasi pembayaran berhasil.','2026-04-22 00:03:48',NULL),('notif_payment_success_template','Pembayaran Anda untuk rute {rute} telah berhasil dikonfirmasi.','string','notifikasi','Template notifikasi pembayaran berhasil.','2026-04-22 00:03:48',NULL),('notif_schedule_changed','true','boolean','notifikasi','Status notifikasi perubahan jadwal.','2026-04-22 00:03:48',NULL),('notif_schedule_changed_template','Jadwal perjalanan Anda untuk rute {rute} telah berubah ke {tanggal_baru} pukul {jam_baru}. Silakan cek detail pemesanan.','string','notifikasi','Template perubahan jadwal.','2026-04-22 00:03:48',NULL),('payment_cash_enabled','true','boolean','pembayaran','Status metode bayar tunai.','2026-04-22 00:03:48',NULL),('payment_cash_note','Silakan bayar kepada pengemudi saat perjalanan','string','pembayaran','Catatan pembayaran tunai.','2026-04-22 00:03:48',NULL),('payment_qris_enabled','true','boolean','pembayaran','Status metode pembayaran QRIS.','2026-04-22 00:03:48',NULL),('payment_transfer_enabled','true','boolean','pembayaran','Status metode transfer bank.','2026-04-22 00:03:48',NULL),('seat_lock_form_minutes','30','integer','pemesanan','Durasi penguncian kursi saat form booking diisi.','2026-04-22 00:03:48',NULL),('seat_lock_payment_minutes','5','integer','pemesanan','Durasi penguncian kursi saat menunggu pembayaran.','2026-04-22 00:03:48',NULL),('service_whatsapp','08123456789','string','umum','WhatsApp layanan pelanggan.','2026-04-22 00:03:48',NULL),('system_email','admin@travs.com','string','umum','Email sistem untuk komunikasi resmi.','2026-04-22 00:03:48',NULL);
/*!40000 ALTER TABLE `pengaturan_sistem` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengemudi`
--

DROP TABLE IF EXISTS `pengemudi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengemudi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pengguna_id` bigint unsigned NOT NULL,
  `nomor_sim` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto_sim` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengemudi_nomor_sim_unique` (`nomor_sim`),
  KEY `pengemudi_pengguna_id_foreign` (`pengguna_id`),
  CONSTRAINT `pengemudi_pengguna_id_foreign` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengemudi`
--

LOCK TABLES `pengemudi` WRITE;
/*!40000 ALTER TABLE `pengemudi` DISABLE KEYS */;
INSERT INTO `pengemudi` VALUES (1,3,'SIM-A-TRAVS-001',NULL,'aktif','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,4,'SIM-A-TRAVS-002',NULL,'aktif','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `pengemudi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengguna`
--

DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengguna` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto_profil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peran` enum('penumpang','pengemudi','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'penumpang',
  `token_push` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengguna_email_unique` (`email`),
  UNIQUE KEY `pengguna_no_hp_unique` (`no_hp`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
INSERT INTO `pengguna` VALUES (1,'Admin TravS','admin@travs.com','081234567890',NULL,'admin',NULL,1,'2026-04-22 00:03:48','$2y$12$3S0xE8XvkPbPrvQsQ364UOsteAzp5G0aR/p0jDaSTm8CmuxmGya0m','JcoeYwWDMA','2026-04-22 00:03:49','2026-04-22 00:03:50'),(2,'Ihsan Penumpang','penumpang@travs.com','081234567891',NULL,'penumpang',NULL,1,'2026-04-22 00:03:49','$2y$12$lnTlSQ.IebRLdmTGEfFP7u8ulUgTThBzh.xxoSTcMcUerds8MlVlO','EW9IaiBzhr','2026-04-22 00:03:49','2026-04-22 00:03:50'),(3,'Rusli Driver','pengemudi@travs.com','081234567892',NULL,'pengemudi',NULL,1,'2026-04-22 00:03:49','$2y$12$pOTFWSD77YDjp5bu/NfFlOh8yzwzeF1jdtNfYLAhd2N2Cirg1ADQe','tLylmmfJao','2026-04-22 00:03:49','2026-04-22 00:03:50'),(4,'Bima Driver','pengemudi2@travs.com','081234567894',NULL,'pengemudi',NULL,1,'2026-04-22 00:03:49','$2y$12$M7t1ywzDHPFa788dzBHFxe.b58hJz2VUAzM2rQtngGdg.XXxPFkMe','ACnJm3q2oH','2026-04-22 00:03:49','2026-04-22 00:03:50'),(5,'Fitri Penumpang','fitri@travs.com','081234567895',NULL,'penumpang',NULL,1,'2026-04-22 00:03:49','$2y$12$CsWSz7zLk9PjpLihnmx9IeEPMHktJHFnzMynzlbOB48GPxGuJ9qwe','4VM6eLvF93','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `pengguna` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rute`
--

DROP TABLE IF EXISTS `rute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rute` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kota_asal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kota_tujuan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi_jalur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jarak_km` decimal(8,2) DEFAULT NULL,
  `estimasi_durasi_menit` int unsigned DEFAULT NULL,
  `harga_tiket` decimal(12,2) NOT NULL,
  `status_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rute`
--

LOCK TABLES `rute` WRITE;
/*!40000 ALTER TABLE `rute` DISABLE KEYS */;
INSERT INTO `rute` VALUES (1,'Pontianak','Sintang','Pontianak - Sanggau - Sintang',395.00,480,225000.00,1,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,'Pontianak','Sanggau','Pontianak - Tayan - Sanggau',267.00,330,175000.00,1,'2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,'Pontianak','Ketapang','Pontianak - Nanga Tayap - Ketapang',380.00,510,240000.00,1,'2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `rute` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tiket_elektronik`
--

DROP TABLE IF EXISTS `tiket_elektronik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiket_elektronik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `detail_pemesanan_id` bigint unsigned NOT NULL,
  `kode_qr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_unik_qr` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sudah_dipindai` tinyint(1) NOT NULL DEFAULT '0',
  `dipindai_pada` datetime DEFAULT NULL,
  `diterbitkan_pada` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tiket_elektronik_detail_pemesanan_id_unique` (`detail_pemesanan_id`),
  UNIQUE KEY `tiket_elektronik_token_unik_qr_unique` (`token_unik_qr`),
  CONSTRAINT `tiket_elektronik_detail_pemesanan_id_foreign` FOREIGN KEY (`detail_pemesanan_id`) REFERENCES `detail_pemesanan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tiket_elektronik`
--

LOCK TABLES `tiket_elektronik` WRITE;
/*!40000 ALTER TABLE `tiket_elektronik` DISABLE KEYS */;
INSERT INTO `tiket_elektronik` VALUES (1,1,'TRAVS-QR-1','6acc2883-1dc0-4cc1-85b2-c50a2ee6f263',0,NULL,'2026-04-22 07:03:50','2026-04-22 00:03:50','2026-04-22 00:03:50'),(2,2,'TRAVS-QR-2','bbbbc7d8-b0f5-4dd8-b0d5-d91370e6627d',0,NULL,'2026-04-22 07:03:50','2026-04-22 00:03:50','2026-04-22 00:03:50'),(3,4,'TRAVS-QR-4','5338804a-fb35-42d1-8bf1-7e60ed36b095',1,'2026-04-21 05:55:00','2026-04-21 05:40:00','2026-04-22 00:03:50','2026-04-22 00:03:50');
/*!40000 ALTER TABLE `tiket_elektronik` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'aplikasi_travel'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-17  8:17:32
