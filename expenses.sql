-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : lun. 06 avr. 2026 à 16:47
-- Version du serveur : 10.11.15-MariaDB
-- Version de PHP : 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `easykiv1_lx`
--

-- --------------------------------------------------------

--
-- Structure de la table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` enum('USD','CDF') DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `expenses`
--

INSERT INTO `expenses` (`id`, `user_id`, `title`, `amount`, `currency`, `description`, `expense_date`, `created_at`) VALUES
(4, 1, 'Languette', 80.00, 'USD', 'Achat en dehors', '2026-03-21', '2026-03-21 16:27:11'),
(6, 1, 'Restaurantion', 2.00, 'USD', '', '2026-03-21', '2026-03-21 16:32:34'),
(7, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-03-25 13:39:55'),
(11, 1, 'Restauration', 1.00, 'USD', '', '2026-03-25', '2026-03-25 17:48:20'),
(12, 1, 'Entretien voiture', 10.00, 'USD', '', '2026-03-25', '2026-03-25 17:48:45'),
(13, 1, 'Restauration', 1.00, 'USD', 'Resto', '2026-03-30', '2026-03-30 19:42:34'),
(14, 1, 'Ladm 20 mukuna', 30.00, 'USD', '', '2026-03-30', '2026-03-30 19:42:54'),
(15, 1, 'Restauration', 1.00, 'USD', '', '2026-04-01', '2026-04-01 16:49:37'),
(16, 1, 'Achat courant', 10.00, 'USD', '', '2026-04-02', '2026-04-02 16:40:33'),
(17, 1, 'Paiement dette pk', 50.00, 'USD', '', '2026-04-02', '2026-04-02 16:40:52'),
(18, 1, 'Lex /rachek', 10.00, 'USD', '', '2026-04-02', '2026-04-02 16:41:13'),
(19, 1, 'Acc ladm 100$ du centre', 100.00, 'USD', '', '2026-04-03', '2026-04-03 09:47:23'),
(20, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 13:53:12'),
(21, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 13:55:10'),
(22, 4, 'courant', 15.00, 'USD', 'ACC paiement courant', '2026-04-06', '2026-04-06 13:55:43'),
(23, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 13:55:46'),
(24, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 13:56:51'),
(25, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 13:59:19'),
(27, 4, 'achat rail', 5.00, 'USD', '', '2026-04-06', '2026-04-06 14:01:51'),
(29, 4, 'LUNCH', 1.00, 'USD', '', '2026-04-06', '2026-04-06 14:03:01'),
(33, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:04:26'),
(35, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:10:16'),
(36, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:11:12'),
(37, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:12:21'),
(38, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:26:38'),
(39, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:27:20'),
(40, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:27:22'),
(41, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:27:44'),
(42, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:28:09'),
(43, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:39:49'),
(44, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:39:54'),
(45, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:42:31'),
(46, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:43:19'),
(47, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:43:35'),
(48, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:44:11'),
(49, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:44:14'),
(50, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:44:16'),
(51, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:44:19'),
(52, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:46:28'),
(53, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:46:47'),
(54, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:46:52'),
(55, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:46:54'),
(57, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:46:56'),
(59, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:47:39'),
(60, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:47:48'),
(61, 4, 'PAULIN', 55.00, 'USD', '', '2026-04-06', '2026-04-06 14:48:52'),
(62, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:48:53'),
(63, 4, 'MAMAN DGI', 10000.00, 'CDF', '', '2026-04-06', '2026-04-06 14:49:44'),
(64, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:49:45'),
(65, 4, 'LEX', 20.00, 'USD', '', '2026-04-06', '2026-04-06 14:50:19'),
(66, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:50:21'),
(67, 4, 'KAVOTA', 10.00, 'USD', 'ACC BOARD', '2026-04-06', '2026-04-06 14:51:21'),
(68, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:51:22'),
(69, 4, 'RESTAURATION', 1500.00, 'CDF', '', '2026-04-06', '2026-04-06 14:52:19'),
(70, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:52:25'),
(71, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:53:29'),
(72, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:53:32'),
(73, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:53:36'),
(74, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:53:42'),
(75, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:54:41'),
(76, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:57:24'),
(77, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 14:57:28'),
(78, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 15:04:06'),
(79, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 15:04:09'),
(80, 4, 'Achat board', 90.00, 'USD', 'ACC BOARD', '2026-03-20', '2026-04-06 15:48:45'),
(84, 3, 'test', 1.00, 'USD', '', '2026-04-06', '2026-04-06 16:14:25'),
(85, 3, 'test', 1.00, 'USD', '', '2026-04-06', '2026-04-06 16:15:52');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
