-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th12 21, 2025 lúc 05:06 PM
-- Phiên bản máy phục vụ: 8.4.3
-- Phiên bản PHP: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `clothing_store`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_id`, `sort_order`) VALUES
(1, 'TOPS', NULL, 1),
(10, 'ACCESSORIES', NULL, 3),
(11, 'BAGS', NULL, 4),
(12, 'WOMENSWEAR', NULL, 5),
(13, 'T-SHIRTS & POLO SHIRTS', 1, 0),
(16, 'SWEATSHIRTS & HOODIES', 1, 0),
(17, 'OUTERWEAR', 1, 0),
(21, 'COLLAB', NULL, 6);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `colors`
--

CREATE TABLE `colors` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `colors`
--

INSERT INTO `colors` (`id`, `name`) VALUES
(1, 'Đen'),
(3, 'Xanh'),
(4, 'Trắng'),
(5, 'Nâu'),
(6, 'Đỏ'),
(7, 'Xanh lá'),
(8, 'Be'),
(9, 'Xám'),
(10, 'Hồng'),
(11, 'Vàng');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `comments`
--

CREATE TABLE `comments` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `rating` tinyint DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_comment` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `comments`
--

INSERT INTO `comments` (`id`, `user_id`, `product_id`, `rating`, `comment`, `date_comment`, `is_hidden`) VALUES
(2, 5, 134, 5, 'hang dep chat luong', '2025-12-12 10:44:29', 1),
(3, 6, 25, 5, 'đẹp', '2025-12-18 15:00:40', 0),
(4, 6, 70, 5, 'đẹp', '2025-12-18 15:00:48', 1),
(5, 18, 4, 2, 'ád', '2025-12-18 23:27:14', 0),
(6, 18, 5, 5, 'ád', '2025-12-18 23:27:28', 0),
(7, 18, 21, 3, 'ád', '2025-12-18 23:27:31', 0),
(8, 18, 22, 5, 'ád', '2025-12-18 23:27:39', 0),
(9, 18, 12, 5, 'dep', '2025-12-18 23:27:48', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `news`
--

CREATE TABLE `news` (
  `id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `address` varchar(10000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `infor` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `news`
--

INSERT INTO `news` (`id`, `title`, `image`, `content`, `created_at`, `address`, `infor`) VALUES
(1, 'BST Hè 2025: Tỏa Sáng Với Phong Cách Năng Động', 'http://localhost/clothing_store/uploads/img1.jpg', 'Chào đón mùa hè 2025, Yamy Shop chính thức trình làng bộ sưu tập \"Sunshine Vibes\" – nơi hội tụ của sự tươi mới, thoải mái và thời thượng. Với chất liệu thoáng mát, họa tiết trẻ trung và gam màu rực rỡ, BST lần này hứa hẹn mang đến trải nghiệm thời trang đỉnh cao cho các tín đồ yêu thích phong cách năng động. Đặc biệt, các mẫu áo croptop, chân váy denim và set đồ matching sẽ giúp bạn tỏa sáng trong mọi hoạt động hè. Khám phá ngay tại hệ thống cửa hàng Yamy Shop!', '2025-07-30 12:47:44', '', ''),
(2, 'Sale cực sốc tháng 8', 'http://localhost/clothing_store/uploads/sale8.jpg', 'SALE CỰC SỐC THÁNG 8 – SHOP YAMY KHUYẾN MÃI KHỦNG LÊN ĐẾN 70%\r\nTháng 8 này, Shop Yamy bùng nổ ưu đãi “chấn động” với chương trình Sale Cực Sốc – giảm giá lên đến 50%-70% tất cả các sản phẩm quần áo thời trang. Đây là cơ hội vàng để các tín đồ thời trang nâng cấp tủ đồ với mức giá siêu hời, săn ngay những item hot trend mùa hè và chuẩn bị cho mùa thu đang đến gần.\r\n\r\n', '2025-07-30 13:08:41', '1. Vì sao bạn không thể bỏ lỡ “Sale Cực Sốc Tháng 8” tại Yamy?\r\n▪️Giảm giá sâu chưa từng có: Nhiều sản phẩm được giảm trực tiếp 50% - 70%.\r\n\r\n▪️Áp dụng toàn bộ cửa hàng: Từ áo thun, áo sơ mi, chân váy, quần jeans, quần âu cho tới đầm dự tiệc, set bộ công sở.\r\nHàng mới về cũng giảm: Không chỉ hàng tồn kho, ngay cả các mẫu New Arrival cũng được áp dụng ưu đãi.\r\n\r\n▪️Miễn phí đổi trả 7 ngày: Mua online hay offline đều được đổi nếu sản phẩm chưa qua sử dụng.\r\n\r\n▪️Số lượng có hạn: Nhiều mẫu hot trend cháy hàng chỉ sau vài giờ mở bán.\r\n\r\n2. Bộ sưu tập khuyến mãi tháng 8 – Đẹp mê ly, giá mê hoặc\r\n▪️Áo thun basic – Item quốc dân chỉ từ 89K\r\nNhững mẫu áo thun Yamy được làm từ cotton cao cấp, co giãn tốt, thoáng mát, phối được với mọi loại trang phục. Giảm ngay 50%, chỉ còn từ 89.000đ.\r\n\r\n▪️Đầm công sở & dự tiệc – Thanh lịch, sang trọng\r\n\r\n▪️Các mẫu đầm Yamy tôn dáng, chất vải mềm mịn, lên form chuẩn. Sale sốc từ 399K xuống chỉ còn 199K.\r\n\r\n▪️Quần jeans & quần baggy – Năng động, trẻ trung\r\n\r\n▪️Jeans Yamy đa dạng kiểu dáng: skinny, straight, wide leg… Sale khủng từ 450K xuống còn 199K.\r\n\r\n▪️Chân váy & quần short – Nữ tính, quyến rũ\r\nDễ phối đồ, mặc đi làm hay đi chơi đều hợp. Giảm đến 60%.\r\n\r\n3. Ưu đãi đặc biệt dành riêng cho khách hàng online\r\n▪️Freeship toàn quốc cho đơn từ 299K.\r\n▪️Tặng ngay voucher 50K cho đơn hàng tiếp theo.\r\n▪️Flash Sale Online: Giờ vàng 12h và 20h mỗi ngày, giá giảm thêm 10% trên mức sale hiện tại.\r\n\r\n4. Mẹo săn sale hiệu quả tại Shop Yamy\r\n▪️Theo dõi Fanpage và Website để nhận thông báo sớm nhất.\r\n▪️Chuẩn bị giỏ hàng trước và canh giờ vàng để chốt đơn nhanh.\r\n▪️Ưu tiên thanh toán online để tránh tình trạng “sold out”.\r\n\r\n5. Thời gian & địa điểm diễn ra\r\nThời gian: Từ 01/08 đến hết 31/08 hoặc đến khi hết hàng.\r\n\r\nĐịa điểm:\r\n▪️Mua trực tiếp tại hệ thống cửa hàng Yamy Shop.\r\n▪️Đặt online qua Website chính thức hoặc các sàn thương mại điện tử.\r\n\r\n6. Lời kết – Tháng 8 mua sắm thả ga cùng Yamy\r\n“Sale Cực Sốc Tháng 8” của Shop Yamy chính là thời điểm vàng để bạn nâng cấp tủ đồ với chi phí siêu tiết kiệm. Chỉ trong tháng này, mọi item từ basic đến sang chảnh đều giảm mạnh, giúp bạn vừa tiết kiệm vừa sở hữu những bộ đồ thời thượng.\r\n\r\n💬 Đừng chần chừ – Số lượng có hạn, nhanh tay săn sale ngay hôm nay tại Shop Yamy!\r\n--------------------------', 'GIỜ MỞ CỬA:\r\n- Hà Nội, TP.HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(4, 'Back To School: Set đồ năng động cho sinh viên', 'http://localhost/clothing_store/uploads/back_to_school.jpg', 'Hè sắp qua, Yamy gợi ý các set đồ \"Back To School\" cực chất: hoodie + chân váy chữ A, quần jogger + áo thun oversize...', '2025-07-30 13:22:47', '', ''),
(5, '5 phụ kiện “nhỏ mà có võ” bạn nên sở hữu', 'https://hmkeyewear.com/wp-content/uploads/2024/12/thoi-trang-cong-so-nam-9.jpg', 'Khám phá 5 phụ kiện thời trang nhỏ nhưng mang lại hiệu quả lớn: đồng hồ, kính mát, thắt lưng, túi xách và mũ. Bí quyết phối đồ giúp bạn nổi bật ở mọi nơi.', '2025-07-30 13:31:41', 'Giới thiệu\r\nTrong thế giới thời trang, không phải lúc nào quần áo cũng là yếu tố quyết định phong cách. Đôi khi, chính những phụ kiện thời trang nhỏ nhưng tinh tế lại tạo nên dấu ấn khác biệt cho người mặc. Dưới đây là 5 phụ kiện “nhỏ mà có võ” mà bất kỳ ai cũng nên sở hữu để nâng tầm gu ăn mặc của mình.\r\n\r\n1. Đồng hồ – Phụ kiện khẳng định phong cách và đẳng cấp:\r\nĐồng hồ đeo tay không chỉ giúp bạn quản lý thời gian mà còn là biểu tượng của sự lịch lãm và chuyên nghiệp. Một chiếc đồng hồ phù hợp có thể nâng tầm cả set đồ, từ công sở đến dạo phố.\r\n\r\n▪️Cách phối: Nam có thể chọn đồng hồ dây da hoặc dây kim loại để đi làm, đồng hồ thể thao khi đi chơi. Nữ có thể chọn đồng hồ mặt nhỏ tinh tế hoặc đồng hồ thời trang phối cùng vòng tay.\r\n▪️Từ khóa phụ: đồng hồ nam, đồng hồ nữ, đồng hồ thời trang cao cấp.\r\n\r\n2. Kính mát – Bảo vệ đôi mắt và tôn thêm thần thái:\r\nKính mát vừa giúp bảo vệ mắt khỏi tia UV, vừa mang đến sự cuốn hút cho người đeo. Một chiếc kính mát hợp khuôn mặt có thể khiến bạn trở nên sang trọng, cá tính hoặc đầy bí ẩn.\r\n\r\n▪️Cách phối: Kính aviator cho phong cách nam tính, kính tròn retro cho phong cách vintage, kính mắt mèo cho nữ thêm quyến rũ.\r\n▪️Từ khóa phụ: kính mát nam, kính mát nữ, kính chống tia UV.\r\n\r\n3. Thắt lưng – Điểm nhấn nhỏ, hiệu quả lớn:\r\nDù chỉ là chi tiết nhỏ, thắt lưng lại giúp set đồ trở nên hoàn thiện và cân đối hơn. Một chiếc thắt lưng đẹp không chỉ giữ trang phục gọn gàng mà còn thể hiện gu thẩm mỹ của bạn.\r\n\r\n▪️Cách phối: Nam có thể dùng thắt lưng da đen hoặc nâu để tạo sự lịch lãm, nữ có thể dùng thắt lưng bản nhỏ để tạo eo khi mặc váy.\r\n▪️Từ khóa phụ: thắt lưng nam da thật, thắt lưng nữ thời trang, phụ kiện thắt lưng đẹp.\r\n\r\n4. Túi xách – Sự tiện lợi và thời trang trong một món đồ:\r\nTúi xách không chỉ để đựng đồ mà còn là điểm nhấn giúp outfit thêm cuốn hút. Chọn túi phù hợp sẽ giúp bạn nổi bật giữa đám đông.\r\n\r\n▪️Cách phối: Nam có thể chọn balo da hoặc túi đeo chéo, nữ có thể chọn túi tote cho phong cách năng động hoặc clutch cho sự sang trọng.\r\n▪️Từ khóa phụ: túi xách nam, túi xách nữ, túi thời trang cao cấp.\r\n\r\n5. Mũ – Hoàn thiện phong cách và tạo cá tính riêng\r\nMũ vừa giúp bảo vệ khỏi nắng mưa, vừa thể hiện phong cách cá nhân rõ nét. Tùy vào loại mũ, bạn có thể biến hóa nhiều phong cách khác nhau.\r\n\r\n▪️Cách phối: Mũ lưỡi trai cho phong cách thể thao, mũ fedora cho phong cách cổ điển, mũ beret cho nét nhẹ nhàng, nghệ sĩ.\r\n▪️Từ khóa phụ: mũ lưỡi trai nam, mũ thời trang nữ, phụ kiện mũ đẹp.\r\n--------------------------', 'GIỜ MỞ CỬA:\r\n- Hà Nội, TP.HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(6, 'Yamy Signature – Tuyên ngôn thời trang của cô nàng hiện đại', 'http://localhost/clothing_store/uploads/yamy_signature.jpg', '▪️Trong thế giới thời trang đầy biến động, mỗi cô gái đều mong muốn tìm cho mình một phong cách riêng – một dấu ấn cá nhân không thể nhầm lẫn. Yamy Signature ra đời với sứ mệnh đồng hành cùng những cô nàng hiện đại, mang đến những thiết kế không chỉ đẹp mắt mà còn phản ánh trọn vẹn cá tính và lối sống của bạn.\r\n\r\n▪️Với triết lý \"Thời trang là cách bạn kể câu chuyện của chính mình\", Yamy Signature chú trọng đến từng chi tiết, từ chất liệu cao cấp, đường may tinh tế cho đến những gam màu, họa tiết và kiểu dáng được lựa chọn kỹ lưỡng. Mỗi sản phẩm đều là sự hòa quyện giữa sự thanh lịch, trẻ trung và nét phá cách đầy cuốn hút – giúp bạn tự tin xuất hiện ở bất cứ đâu, từ công sở, dạo phố đến những buổi tiệc sang trọng.\r\n\r\n▪️Điểm đặc biệt của Yamy Signature nằm ở khả năng bắt kịp xu hướng quốc tế nhưng vẫn giữ được sự tinh tế, tối giản và tính ứng dụng cao. Chúng tôi hiểu rằng thời trang không chỉ để ngắm, mà phải dễ dàng phối đồ, thoải mái khi mặc và phản ánh trọn vẹn phong thái của người sở hữu.\r\n\r\n▪️Không đơn thuần là quần áo, Yamy Signature muốn mỗi thiết kế trở thành một “tuyên ngôn” cá nhân – khẳng định rằng bạn là cô gái biết mình muốn gì, dám thể hiện bản thân và không ngại tỏa sáng. Dù bạn theo đuổi phong cách nữ tính nhẹ nhàng hay mạnh mẽ, cá tính, Yamy Signature đều có thể trở thành “người bạn đồng hành” hoàn hảo.\r\n\r\n▪️Hãy để Yamy Signature giúp bạn biến mỗi ngày trở thành một sàn diễn, nơi bạn tự tin sải bước với phong cách và dấu ấn của chính mình.\r\n--------------------------', '2025-07-30 13:35:30', '', 'GIỜ MỞ CỬA:\r\n- Hà Nội, TP:HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(7, 'Yamy Style: Khi gu thời trang lên ngôi giữa phố đông', 'https://bizweb.dktcdn.net/100/369/010/collections/02.jpg?v=1641637095720', 'Khám phá Yamy Style – thương hiệu thời trang hiện đại. Phong cách trẻ trung, cuốn hút và đầy cá tính, giúp bạn tỏa sáng giữa phố đông.', '2025-07-30 13:37:48', '▪️Yamy Style – Dấu ấn thời trang giữa nhịp sống hiện đại:\r\nGiữa những con phố đông đúc, gu thời trang không chỉ là cách bạn ăn mặc mà còn là ngôn ngữ để khẳng định bản thân. Yamy Style ra đời để mang đến cho phái đẹp những thiết kế tinh tế, trẻ trung và đầy sức sống – giúp bạn luôn nổi bật, tự tin và khác biệt.\r\n\r\n▪️Phong cách dành riêng cho nam và nữ thành thị:\r\nVới triết lý \"Thời trang không chỉ để mặc, mà để sống cùng\", Yamy Style chú trọng từng chi tiết – từ chất liệu cao cấp, form dáng chuẩn cho đến những đường may tỉ mỉ. Dù bạn là nam hay nữ, yêu thích sự năng động của street style hay sự thanh lịch của phong cách tối giản, Yamy Style đều mang đến lựa chọn phù hợp. Mỗi thiết kế đều giúp tôn lên vẻ ngoài cuốn hút, sự tự tin và cá tính riêng của từng người, để bạn luôn nổi bật giữa phố đông.\r\n\r\n▪️Bắt kịp xu hướng, nhưng vẫn giữ chất riêng:\r\nYamy Style liên tục cập nhật những xu hướng thời trang mới nhất từ quốc tế và kết hợp khéo léo với bản sắc riêng. Từ áo khoác cá tính, váy đầm duyên dáng đến những set đồ mix & match đầy sáng tạo – tất cả đều được thiết kế để tôn vinh vóc dáng và cá tính của bạn.\r\n\r\n▪️Tỏa sáng giữa phố đông cùng Yamy Style:\r\nKhông chỉ là thương hiệu, Yamy Style là người bạn đồng hành giúp bạn biến mỗi con phố thành sàn diễn thời trang của riêng mình. Mỗi bộ trang phục là một tuyên ngôn: \"Tôi khác biệt, tôi tự tin và tôi dẫn đầu xu hướng\".\r\n--------------------------', 'GIỜ MỞ CỬA:\r\n- Hà Nội, TP.HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(8, 'Hệ Thống Cửa Hàng', 'https://click49.vn/wp-content/uploads/2018/08/1.jpg', 'Hotline: 039.336.1913 - 039.333.1359\r\nWebsite: http://localhost/streetsoul_store1/\r\n', '2025-08-11 12:14:57', 'Địa Chỉ:\r\nTP.HCM:\r\n▪️Phường Sài Gòn - The New Playground, Tầng B1 Vincom Center Đồng Khởi, 72 Lê Thánh Tôn.\r\n▪️Phường An Lạc - Tầng 1 TTTM Aeon Mall Bình Tân, số 1 đường số 17A.\r\n▪️Phường Hòa Hưng - 561 Sư Vạn Hạnh.\r\n▪️Phường Sài Gòn - The New Playground 26 Lý Tự Trọng.\r\n▪️Phường Gò Vấp - 326 Quang Trung.\r\n▪️Phường Thủ Dầu Một - 28 Yersin.\r\nHà Nội:\r\n▪️ 1221 Giải Phóng \r\n▪️ 154 Quang Trung - Hà Đông\r\n▪️ 34 Trần Phú - Hà Đông\r\nHoài Đức:\r\n▪️ 312 Khu 6 Trạm Trôi - Hoài Đức\r\nThị xã Sơn Tây:\r\n▪️ 195 Quang Trung - Tx.Sơn Tây\r\nTP. Thanh Hóa\r\n▪️ 236-238 Lê Hoàn\r\nTP.Vinh, Nghệ An\r\n▪️ 167 Nguyễn Văn Cừ\r\n--------------------------', 'Liên hệ:\r\nMọi ý kiến đóng góp cũng như yêu cầu khiếu nại xin vui lòng liên hệ: 039.336.1913\r\nGIỜ MỞ CỬA:\r\n- Hà Nội, TP.HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(9, 'Chính sách đổi hàng', 'https://jkhoreca.com/wp-content/uploads/2021/07/chinh-sach-doi-tra-bao-hanh.jpg', 'I. QUY ĐỊNH ĐỔI HÀNG ONLINE\r\n1. Chính sách áp dụng\r\n▪️Áp dụng 01 lần đổi/01 đơn hàng\r\n▪️Không áp dụng đổi với sản phẩm phụ kiện và đồ lót\r\n▪️Sản phẩm nguyên giá được đổi sang sản phẩm nguyên khác còn hàng tại website có giá trị bằng hoặc lớn hơn (KH bù thêm chênh lệch nếu lớn hơn)\r\n▪️Không hỗ trợ đổi các sản phẩm giảm giá/khuyên mại\r\n\r\n2. Điều kiện đổi sản phẩm\r\n▪️Đổi hàng trong vòng 3 ngày kể từ ngày khách hàng nhận được sản phẩm.\r\n▪️Sản phẩm còn nguyên tem, mác và chưa qua sử dụng   \r\n \r\n3. Thực hiện đổi sản phẩm\r\n▪️Bước 1: Liên hệ fanpage https://www.facebook.com/yamyshop.vn/ để xác nhận đổi hàng.\r\n▪️Bước 2: Gửi hàng về địa chỉ Kho \r\n▪️Bước 3:Yamy gửi đổi sản phẩm mới khi nhận được hàng. Trong trường hợp hết hàng,  Yamy sẽ liên hệ xác nhận.\r\n\r\n▪️Lưu ý:\r\nKho online không nhận giữ hàng trong thời gian khách hàng gửi sản phẩm về để đổi hàng.', '2025-08-11 13:09:24', 'II. QUY ĐỊNH ĐỔI SẢN PHẨM MUA TẠI CỬA HÀNG\r\n\r\n1.Chính sách đổi hàng được áp dụng trong vòng 30 ngày kể từ ngày mua hàng.\r\n\r\n2.Khách hàng được đổi không giới hạn số lần trong 30 ngày.\r\n\r\n3.Quý khách vui lòng mang theo hóa đơn bán lẻ khi đổi hàng.\r\n\r\n4.Sản phẩm đổi phải còn nguyên tem nhãn mác và trong tình trạng như ban đầu (chưa giặt, chưa qua sử dụng, chưa qua sửa chữa, không bị rách hoặc hư hại).\r\n\r\n5.Vì lý do sức khỏe, sản phẩm đồ lót, phụ kiện, mũ, túi xách, balo không áp dụng đổi hàng.\r\n6.Khách hàng có thể đổi hàng tại tất cả các cửa hàng trong hệ thống Yamy.\r\n\r\n7.Sản phẩm sau khi đổi sẽ áp dụng giá bán tại thời điểm đổi hàng. Hóa đơn sau khi đổi phải có giá trị bằng hoặc cao hơn tổng giá trị sản phẩm trước khi đổi.\r\n--------------------------', 'GIỜ MỞ CỬA:\r\n- Hà Nội, TP.HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(10, 'Chính sách bảo mật thông tin', 'https://media.loveitopcdn.com/1185/chinh-sach-bao-mat-thong-tin.jpg', '- CHÍNH SÁCH BẢO VỆ THÔNG TIN KHÁCH HÀNG:\r\nCảm ơn bạn đã truy cập vào trang website của thương hiệu Thời trang Yamy Shop.\r\n\r\nChúng tôi tôn trọng và cam kết sẽ bảo mật những thông tin mang tính riêng tư của bạn. Xin vui lòng đọc bản Chính sách bảo vệ thông tin cá nhân của người tiêu dùng dưới đây để hiểu hơn những cam kết mà chúng tôi thực hiện nhằm tôn trọng và bảo vệ quyền lợi của người truy cập.\r\n\r\nBảo vệ thông tin cá nhân của người tiêu dùng và gây dựng được niềm tin cho bạn là vấn đề rất quan trọng với chúng tôi. Vì vậy, chúng tôi sẽ dùng tên và các thông tin khác liên quan đến bạn tuân thủ theo nội dung của chính sách này. Chúng tôi chỉ thu thập những thông tin cần thiết liên quan đến giao dịch mua bán.\r\n\r\n- CHÍNH SÁCH BẢO VỆ THÔNG TIN CÁ NHÂN CỦA NGƯỜI TIÊU DÙNG:\r\nNgười Tiêu Dùng hoặc Khách hàng sẽ được yêu cầu điền đầy đủ các thông tin theo các trường thông tin theo mẫu có sẵn trên Website như: Họ và Tên, địa chỉ (nhà riêng hoặc văn phòng), địa chỉ email (công ty hoặc cá nhân), số điện thoại (di động, nhà riêng hoặc văn phòng), Thông tin này được yêu cầu để phục vụ việc đặt hàng online của Khách hàng (bao gồm gửi email xác nhận đặt hàng đến Khách hàng).\r\n\r\n-Thu thập cookie & lưu lượng truy cập:\r\nCookie là những thư mục dữ liệu được lưu trữ tạm thời hoặc lâu dài trong ổ cứng máy tính của Khách hàng. Các cookie được sử dụng để xác minh, truy tìm lượt (bảo vệ trạng thái) và duy trì thông tin cụ thể về việc sử dụng và người sử dụng Website, như các tuỳ chọn cho trang web hoặc thông tin về giỏ hàng điện tử của họ. Những thư mục cookie cũng có thể được các công ty cung cấp dịch vụ quảng cáo đã ký kết Hợp đồng với ATINO đặt trong máy tính của Khách hàng với mục đích nêu trên và dữ liệu được thu thập bởi những cookie này là hoàn toàn vô danh. Nếu không đồng ý, Khách hàng có thể xoá tất cả các cookie đã nằm trong ổ cứng máy tính của mình bằng cách tìm kiếm các thư mục với “cookie” trong tên của nó và xoá đi. Trong tương lai, Khách hàng có thể chỉnh sửa các lựa chọn trong trình duyệt của mình để các cookie (tương lai) bị chặn; Xin được lưu ý rằng: Nếu làm vậy, Khách hàng có thể không sử dụng được đầy đủ các tính năng của Website Để biết thêm thông tin về (cách sử dụng và không nhận) cookie, Khách hàng vui lòng truy cập vào website www.allaboutcookies.org.\r\n\r\nLưu lượng truy cập: Trên website có những đoạn mã được sử dụng với mục đích báo cáo lưu lượng truy cập trang web, số khách truy cập, kiểm tra và báo cáo quảng cáo, và tính cá nhân hoá.  sử dụng chỉ để thu thập dữ liệu vô danh.', '2025-08-11 13:20:34', '1.MỤC ĐÍCH THU THẬP THÔNG TIN CÁ NHÂN CỦA NGƯỜI TIÊU DÙNG:\r\nCung cấp dịch vụ cho Khách hàng và quản lý, sử dụng thông tin cá nhân của Người Tiêu Dùng nhằm mục đích quản lý cơ sở dữ liệu về Người Tiêu Dùng và kịp thời xử lý các tình huống phát sinh (nếu có).\r\n\r\n2. PHẠM VI SỬ DỤNG THÔNG TIN CÁ NHÂN:\r\nWebsite sử dụng thông tin của Người Tiêu Dùng cung cấp để:\r\nCung cấp các dịch vụ đến Người Tiêu Dùng;\r\n\r\n• Gửi các thông báo về các hoạt động trao đổi thông tin giữa Người Tiêu Dùng và Yamy;\r\n• Ngăn ngừa các hoạt động phá hủy, chiếm đoạt tài khoản người dùng của Người Tiêu Dùng hoặc các hoạt động giả mạo Người Tiêu Dùng;\r\n• Liên lạc và giải quyết khiếu nại với Người Tiêu Dùng;\r\n• Trong trường hợp có yêu cầu của cơ quan quản lý nhà nước có thẩm quyền.\r\n\r\n3. THỜI GIAN LƯU TRỮ THÔNG TIN CÁ NHÂN:\r\nKhông có thời hạn ngoại trừ trường hợp Người Tiêu Dùng gửi có yêu cầu hủy bỏ tới cho Ban quản trị hoặc Công ty giải thể hoặc bị phá sản.\r\n\r\n4. NHỮNG NGƯỜI HOẶC TỔ CHỨC CÓ THỂ ĐƯỢC TIẾP CẬN VỚI THÔNG TIN CÁ NHÂN CỦA NGƯỜI TIÊU DÙNG:\r\nNgười Tiêu Dùng đồng ý rằng, trong trường hợp cần thiết, các cơ quan/ tổ chức/cá nhân sau có quyền được tiếp cận và thu thập các thông tin cá nhân của mình, bao gồm:\r\n- Ban quản trị.\r\n• Bên thứ ba có dịch vụ tích hợp với Website atino.vn\r\n• Công ty tổ chức sự kiện và nhà tài trợ phối hợp cùng Yamy\r\n• Công ty nghiên cứu thị trường\r\n• Cố vấn tài chính, pháp lý và Công ty kiểm toán\r\n• Bên khiếu nại chứng minh được hành vi vi phạm của Người Tiêu Dùng\r\n• Theo yêu cầu của cơ quan nhà nước có thẩm quyền\r\n\r\n5. ĐỊA CHỈ CỦA ĐƠN VỊ THU THẬP VÀ QUẢN LÝ THÔNG TIN:\r\nHỘ KINH DOANH YAMY SHOP\r\nĐịa chỉ ĐKKD: Nguyễn Văn Ni, Tổ 1, Khu phố 6, Thị Trấn Củ Chi.\r\nCSKH & Bán hàng Online: 039.336.1913\r\n\r\n6.CAM KẾT BẢO MẬT THÔNG TIN CÁ NHÂN CỦA NGƯỜI TIÊU DÙNG:\r\n \r\nThông tin cá nhân của Người Tiêu Dùng trên Website được Ban quản trị cam kết bảo mật tuyệt đối theo chính sách bảo mật thông tin cá nhân được đăng tải trên Website yamy.vn . Việc thu thập và sử dụng thông tin của mỗi Người Tiêu Dùng chỉ được thực hiện khi có sự đồng ý của Người Tiêu Dùng trừ những trường hợp pháp luật có quy định khác và quy định này.\r\n\r\nKhông sử dụng, không chuyển giao, cung cấp hoặc tiết lộ cho bên thứ 3 về thông tin cá nhân của Người Tiêu Dùng khi không có sự đồng ý của Người Tiêu Dùng ngoại trừ các trường hợp được quy định tại quy định này hoặc quy định của pháp luật.\r\n\r\nTrong trường hợp máy chủ lưu trữ thông tin bị hacker tấn công dẫn đến mất mát dữ liệu cá nhân của Người Tiêu Dùng, Ban quản trị có trách nhiệm thông báo và làm việc với cơ quan chức năng điều tra và xử lý kịp thời, đồng thời thông báo cho Người Tiêu Dùng được biết về vụ việc.\r\n\r\n8.CƠ CHẾ TIẾP NHẬN VÀ GIẢI QUYẾT KHIẾU NẠI LIÊN QUAN ĐẾN VIỆC THÔNG TIN CỦA NGƯỜI TIÊU DÙNG:\r\n\r\nKhi phát hiện thông tin cá nhân của mình bị sử dụng sai mục đích hoặc phạm vi, Người Tiêu Dùng gọi điện thoại tới số 039.336. để khiếu nại và cung cấp chứng cứ liên quan tới vụ việc cho Ban quản trị. Ban quản trị cam kết sẽ phản hồi ngay lập tức hoặc muộn nhất là trong vòng 24 (hai mươi tư) giờ làm việc kể từ thời điểm nhận được khiếu nại.\r\n--------------------------', 'GIỜ MỞ CỬA:\r\n- Hà Nội, TP.HCM: 8h30 - 22h30\r\n- Ngoại thành & tỉnh khác: 8h30 - 22h00'),
(11, 'sale30', 'https://thuthuatnhanh.com/wp-content/uploads/2022/06/Anh-sale.jpg', '123', '2025-12-07 14:23:16', '123', '123');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cod',
  `status` enum('Chờ xác nhận','Chờ thanh toán','Đã thanh toán','Đang xử lý','Đơn hàng đang được giao','Đã giao hàng','Hủy đơn hàng') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(50) DEFAULT NULL,
  `recipient_address` text,
  `recipient_email` varchar(255) DEFAULT NULL,
  `note` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total`, `payment_method`, `status`, `created_at`, `recipient_name`, `recipient_phone`, `recipient_address`, `recipient_email`, `note`) VALUES
(42, 5, 850000.00, 'cod', 'Đơn hàng đang được giao', '2025-11-27 17:29:34', '123', '0393361913', '123', 'tienptps40528@gmail.com', '123'),
(44, 5, 850000.00, 'cod', 'Đơn hàng đang được giao', '2025-11-27 17:38:07', 'dasdy', '1231231231', '123123', 'tienptps40528@gmail.com', '123123'),
(45, 6, 630000.00, 'cod', 'Đơn hàng đang được giao', '2025-11-27 18:35:19', 'tuiten', '1231231231', '123', 'tienptps40528@gmail.com', '123'),
(46, 6, 590000.00, 'cod', 'Đã giao hàng', '2025-11-28 03:09:12', 'we', '0393361913', 'qwe', 'tienptps40528@gmail.com', 'qwe'),
(51, 6, 1310000.00, 'cod', 'Đã giao hàng', '2025-12-03 07:33:22', 'tuiten', '0393361913', 'ad', 'tienptps40528@gmail.com', 'ádadad'),
(52, 6, 850000.00, 'cod', 'Đã giao hàng', '2025-12-03 07:38:37', 'tien', '1234567891', 'SÁ', 'tienptps40528@gmail.com', 'ÁDAD'),
(53, 6, 2550000.00, 'cod', 'Đã giao hàng', '2025-12-03 07:40:41', 'tien', '0393361913', '112313', 'tienptps40528@gmail.com', '12313'),
(54, 6, 850000.00, 'cod', 'Chờ xác nhận', '2025-12-03 07:55:56', 'tien', '0393361913', 'sadad', 'tienptps40528@gmail.com', 'ádad'),
(55, 6, 123456.00, 'vnpay', 'Chờ xác nhận', '2025-12-08 05:37:34', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(56, 6, 210000.00, 'cod', 'Chờ xác nhận', '2025-12-08 05:37:52', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(57, 6, 800000.00, 'vnpay', 'Đang xử lý', '2025-12-08 05:47:11', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(58, 6, 470000.00, 'cod', 'Hủy đơn hàng', '2025-12-08 05:50:12', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(59, 6, 210000.00, 'cod', 'Đang xử lý', '2025-12-09 04:42:46', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(60, 6, 1395000.00, 'cod', 'Đơn hàng đang được giao', '2025-12-09 04:49:35', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(61, 6, 210000.00, 'cod', 'Hủy đơn hàng', '2025-12-09 04:53:30', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(62, 6, 210000.00, 'cod', 'Đã giao hàng', '2025-12-09 05:11:20', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(63, 6, 210000.00, 'cod', 'Đã giao hàng', '2025-12-10 05:46:35', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(64, 6, 350000.00, 'cod', 'Đã giao hàng', '2025-12-10 06:28:32', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(65, 6, 350000.00, 'cod', 'Đã giao hàng', '2025-12-10 06:35:43', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(68, 6, 210000.00, 'cod', 'Đã giao hàng', '2025-12-11 08:43:50', 'Phan Tiến Anh', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(69, 6, 400000.00, 'cod', 'Chờ xác nhận', '2025-12-11 10:04:03', 'Phan Tiến Anh', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(70, 6, 680000.00, 'vnpay', 'Chờ xác nhận', '2025-12-11 18:19:44', 'trongtien1', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(71, 6, 210000.00, 'cod', 'Chờ xác nhận', '2025-12-11 18:24:55', 'Phan Tiến Anh', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(72, 6, 820000.00, 'cod', 'Chờ xác nhận', '2025-12-12 03:24:11', 'Phan Tiến Anh', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(73, 6, 830000.00, 'cod', 'Chờ xác nhận', '2025-12-12 03:29:20', 'Phan Tiến Anh', '0393361913', 'asd', 'tienptpssd40528@gmail.com', NULL),
(74, 5, 210000.00, 'cod', 'Đã giao hàng', '2025-12-12 03:43:32', 'Phan Trọng Tiến', '0393361913', 'nguyễn văn ni, tổ 1, khu phố 6, thị trấn củ chi', 'tienptps40528@gmail.com', NULL),
(75, 15, 187000.00, 'cod', 'Hủy đơn hàng', '2025-12-12 04:14:34', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(76, 15, 830000.00, 'cod', 'Đang xử lý', '2025-12-12 04:16:22', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(77, 15, 217000.00, 'cod', 'Chờ xác nhận', '2025-12-12 17:23:11', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(78, 15, 868000.00, 'vnpay', 'Chờ xác nhận', '2025-12-12 17:23:26', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(79, 15, 868000.00, 'vnpay', 'Đang xử lý', '2025-12-12 17:26:16', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(80, 15, 850000.00, 'vnpay', 'Đang xử lý', '2025-12-12 17:29:20', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(81, 15, 850000.00, 'vnpay', 'Đang xử lý', '2025-12-12 17:35:08', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(82, 15, 850000.00, 'vnpay', 'Đang xử lý', '2025-12-12 17:41:27', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(83, 15, 850000.00, 'cod', 'Đang xử lý', '2025-12-12 17:55:39', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(84, 15, 850000.00, 'cod', 'Đang xử lý', '2025-12-12 17:56:03', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(85, 15, 217000.00, 'vnpay', 'Đang xử lý', '2025-12-12 17:56:17', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(86, 15, 217000.00, 'vnpay', 'Đang xử lý', '2025-12-12 18:04:38', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(87, 15, 217000.00, 'vnpay', 'Đang xử lý', '2025-12-12 18:06:46', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(88, 15, 217000.00, 'vnpay', 'Đang xử lý', '2025-12-12 18:14:53', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(89, 15, 217000.00, 'cod', 'Chờ xác nhận', '2025-12-12 18:22:51', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(90, 15, 217000.00, 'cod', 'Đang xử lý', '2025-12-12 18:35:47', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(91, 15, 224000.00, 'vnpay', 'Đang xử lý', '2025-12-13 03:15:50', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(92, 15, 850000.00, 'vnpay', 'Đang xử lý', '2025-12-13 03:22:57', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(93, 15, 187000.00, 'cod', 'Đang xử lý', '2025-12-13 04:42:41', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(94, 15, 208000.00, 'cod', 'Đang xử lý', '2025-12-13 05:52:02', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(95, 15, 201000.00, 'vnpay', 'Đang xử lý', '2025-12-13 05:52:20', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(96, 15, 194000.00, 'cod', 'Đang xử lý', '2025-12-13 08:17:18', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(97, 15, 850000.00, 'vnpay', 'Đã giao hàng', '2025-12-13 08:17:44', 'haidang5305', '0365858481', 'heloo', 'hutydang3107@gmail.com', NULL),
(98, 14, 850000.00, 'cod', 'Đã giao hàng', '2025-12-13 08:24:56', 'maianh', '0365858481', 'Công viên Phần mềm Quang Trung (Phường Trung Mỹ Tây, Quận 12', 'hutydang@gmail.com', NULL),
(99, 14, 1117000.00, 'cod', 'Đang xử lý', '2025-12-13 08:36:43', 'maianh', '61649', 'ada', 'hutydang@gmail.com', NULL),
(100, 18, 238000.00, 'cod', 'Đã giao hàng', '2025-12-18 16:10:56', 'hutydang', '0365858481', '12312', 'hanmaianh03@gmail.com', NULL),
(101, 18, 226350.00, 'cod', 'Đã giao hàng', '2025-12-18 16:11:47', 'hutydang', '0365858481', '12312', 'hanmaianh03@gmail.com', NULL),
(102, 18, 3407000.00, 'cod', 'Đã giao hàng', '2025-12-18 16:24:25', 'hutydang', '0365858481', '12312', 'hanmaianh03@gmail.com', NULL),
(103, 18, 11560000.00, 'cod', 'Đã giao hàng', '2025-12-18 16:25:29', 'hutydang', '0365858481', '12312', 'hanmaianh03@gmail.com', NULL),
(104, 18, 850000.00, 'cod', 'Đã giao hàng', '2025-12-18 16:39:19', 'hutydang', '0365858481', '12312', 'hanmaianh03@gmail.com', NULL),
(105, 18, 850000.00, 'vnpay', 'Chờ xác nhận', '2025-12-21 16:27:35', 'hutydang', '0365858481', '85/2 Pham The Hien', 'hanmaianh03@gmail.com', NULL),
(106, 18, 850000.00, 'vnpay', 'Chờ xác nhận', '2025-12-21 16:28:11', 'hutydang', 'zddfdf', '85/2 Pham The Hien', 'hanmaianh03@gmail.com', NULL),
(107, 18, 850000.00, 'cod', 'Chờ xác nhận', '2025-12-21 16:28:30', 'hutydang', 'zddfdf', '12312312', 'hanmaianh03@gmail.com', NULL),
(108, 18, 850000.00, 'vnpay', 'Chờ xác nhận', '2025-12-21 16:37:27', 'hutydang', '0365858481', '85/2 Pham The Hien', 'hanmaianh03@gmail.com', NULL),
(109, 18, 850000.00, 'vnpay', 'Chờ thanh toán', '2025-12-21 16:48:38', 'hutydang', '0365858481', '85/2 Pham The Hien', 'hanmaianh03@gmail.com', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_details`
--

CREATE TABLE `order_details` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `variant_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `order_details`
--

INSERT INTO `order_details` (`id`, `order_id`, `product_id`, `variant_id`, `quantity`, `price`) VALUES
(29, 33, 1235, NULL, 4, 850000.00),
(30, 33, 489, NULL, 2, 850000.00),
(31, 34, 1206, NULL, 1, 850000.00),
(32, 35, 1206, NULL, 1, 850000.00),
(33, 36, 1206, NULL, 1, 850000.00),
(34, 37, 1206, NULL, 1, 850000.00),
(35, 38, 1206, NULL, 1, 850000.00),
(36, 39, 1206, NULL, 1, 850000.00),
(37, 39, 866, NULL, 3, 850000.00),
(38, 40, 1206, NULL, 1, 850000.00),
(39, 40, 866, NULL, 3, 850000.00),
(40, 41, 130, 648, 1, 450000.00),
(41, 42, 76, 76, 1, 850000.00),
(42, 43, 77, 77, 1, 830000.00),
(43, 44, 72, 72, 1, 850000.00),
(44, 45, 77, 77, 1, 830000.00),
(45, 46, 131, 653, 1, 690000.00),
(46, 47, 130, 649, 1, 460000.00),
(47, 48, 130, 649, 1, 460000.00),
(48, 49, 130, 648, 1, 45000.00),
(49, 50, 131, 653, 1, 690000.00),
(50, 51, 130, 649, 1, 460000.00),
(51, 51, 77, 509, 1, 850000.00),
(52, 52, 76, 76, 1, 850000.00),
(53, 53, 73, 73, 1, 850000.00),
(54, 53, 74, 74, 1, 850000.00),
(55, 53, 75, 75, 1, 850000.00),
(56, 54, 76, 76, 1, 850000.00),
(57, 55, 136, NULL, 1, 123456.00),
(58, 56, 133, NULL, 1, 210000.00),
(59, 57, 27, NULL, 2, 440000.00),
(60, 58, 130, NULL, 1, 470000.00),
(61, 59, 134, NULL, 1, 210000.00),
(62, 60, 20, NULL, 1, 595000.00),
(63, 60, 71, NULL, 1, 850000.00),
(64, 61, 134, NULL, 1, 210000.00),
(65, 62, 134, NULL, 1, 210000.00),
(66, 63, 133, NULL, 1, 210000.00),
(67, 64, 136, NULL, 1, 350000.00),
(68, 65, 136, NULL, 1, 350000.00),
(69, 66, 20, NULL, 1, 595000.00),
(70, 67, 136, NULL, 1, 350000.00),
(71, 68, 134, NULL, 1, 210000.00),
(72, 69, 132, NULL, 1, 400000.00),
(73, 70, 77, NULL, 1, 830000.00),
(74, 71, 133, NULL, 1, 210000.00),
(75, 72, 37, NULL, 1, 850000.00),
(76, 73, 77, NULL, 1, 830000.00),
(77, 74, 134, NULL, 1, 210000.00),
(78, 75, 134, NULL, 1, 217000.00),
(79, 76, 77, NULL, 1, 830000.00),
(80, 77, 134, NULL, 1, 217000.00),
(81, 78, 134, NULL, 4, 217000.00),
(82, 79, 134, NULL, 4, 217000.00),
(83, 80, 71, NULL, 1, 850000.00),
(84, 81, 71, NULL, 1, 850000.00),
(85, 82, 71, NULL, 1, 850000.00),
(86, 83, 71, 481, 1, 850000.00),
(87, 84, 71, 479, 1, 850000.00),
(88, 85, 134, 666, 1, 217000.00),
(89, 86, 134, 666, 1, 217000.00),
(90, 87, 134, 666, 1, 217000.00),
(91, 88, 134, 666, 1, 217000.00),
(92, 89, 134, 666, 1, 217000.00),
(93, 90, 134, 666, 1, 217000.00),
(94, 91, 134, 667, 1, 224000.00),
(95, 92, 71, 478, 1, 850000.00),
(96, 93, 134, 666, 1, 217000.00),
(97, 94, 134, 669, 1, 238000.00),
(98, 95, 134, 668, 1, 231000.00),
(99, 96, 134, 667, 1, 224000.00),
(100, 97, 71, 71, 1, 850000.00),
(101, 98, 71, 479, 1, 850000.00),
(102, 99, 136, 671, 1, 350000.00),
(103, 99, 134, 666, 1, 217000.00),
(104, 99, 133, 660, 1, 210000.00),
(105, 99, 132, 655, 1, 400000.00),
(106, 100, 134, NULL, 1, 238000.00),
(107, 101, 134, NULL, 1, 231000.00),
(108, 102, 73, NULL, 1, 850000.00),
(109, 102, 77, NULL, 1, 830000.00),
(110, 102, 134, NULL, 1, 217000.00),
(111, 102, 137, NULL, 2, 250000.00),
(112, 102, 133, NULL, 1, 210000.00),
(113, 102, 21, NULL, 1, 850000.00),
(114, 103, 21, NULL, 1, 850000.00),
(115, 103, 21, NULL, 1, 850000.00),
(116, 103, 22, NULL, 1, 850000.00),
(117, 103, 22, NULL, 3, 850000.00),
(118, 103, 22, NULL, 1, 850000.00),
(119, 103, 12, NULL, 2, 850000.00),
(120, 103, 12, NULL, 1, 850000.00),
(121, 103, 5, NULL, 1, 765000.00),
(122, 103, 5, NULL, 2, 765000.00),
(123, 103, 4, NULL, 1, 765000.00),
(124, 104, 21, NULL, 1, 850000.00),
(125, 105, 21, NULL, 1, 850000.00),
(126, 106, 21, NULL, 1, 850000.00),
(127, 107, 21, NULL, 1, 850000.00),
(128, 108, 21, NULL, 1, 850000.00),
(129, 109, 21, NULL, 1, 850000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `category_id` int DEFAULT NULL,
  `brand_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `status` tinyint(1) DEFAULT '1',
  `quantity` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `category_id`, `brand_id`, `created_at`, `updated_at`, `is_featured`, `discount_percent`, `status`, `quantity`) VALUES
(1, '\'Y\' Patches Black T shirt   Black', 'Sản phẩm: \'Y\' Patches Black T shirt   Black', 13, NULL, '2025-11-11 00:43:55', NULL, 0, 0.00, 1, 38),
(2, 'DC x The Underdogs T shirt Black', 'Sản phẩm: DC x The Underdogs T shirt Black', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 51),
(3, 'Dico Star Print T-Shirt Navy\n', 'Sản phẩm: Dico Fluffy Print T Shirt Navy', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 31),
(4, 'Dico Jr Variation T Shirt Black', 'Sản phẩm: Dico Jr Variation T Shirt Black', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 10.00, 1, 74),
(5, 'Dico Jr Variation T Shirt White', 'Sản phẩm: Dico Jr Variation T Shirt White', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 10.00, 1, 74),
(6, 'DirtyCoins Bình Tân Embroidered Polo Black', 'Sản phẩm: DirtyCoins Bình Tân Embroidered Polo Black01', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 56),
(7, 'DirtyCoins Double Trouble Oversized Hoodie Brown', 'Sản phẩm: DirtyCoins Double Trouble Oversized Hoodie Brown1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 83),
(8, 'DirtyCoins Endless Summer T Shirt White', 'Sản phẩm: DirtyCoins Endless Summer T Shirt White1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 60),
(9, 'DirtyCoins Floral Silhouette Shirt Tan', 'Sản phẩm: DirtyCoins Floral Silhouette Shirt Tan1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 72),
(10, 'DirtyCoins Hustling Boxy T Shirt Red', 'Sản phẩm: DirtyCoins Hustling Boxy T Shirt Red1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 46),
(11, 'DirtyCoins Lil Pony T Shirt Black', 'Sản phẩm: DirtyCoins Lil Pony T Shirt Black1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 52),
(12, 'DirtyCoins Patch In Heart T Shirt Black', 'Sản phẩm: DirtyCoins Patch In Heart T Shirt Black', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 83),
(13, 'DirtyCoins Patch In Heart T Shirt White', 'Sản phẩm: DirtyCoins Patch In Heart T Shirt White', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 78),
(14, 'DirtyCoins Printed Label Tank Top Black', 'Sản phẩm: DirtyCoins Printed Label Tank Top Black1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 56),
(15, 'DirtyCoins Printed Label Tank Top White', 'Sản phẩm: DirtyCoins Printed Label Tank Top White1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 45),
(16, 'DirtyCoins Rope Embroidery Knit Polo Black', 'Sản phẩm: DirtyCoins Rope Embroidery Knit Polo Black1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 42),
(17, 'DirtyCoins Rope Embroidery Knit Polo Off White', 'Sản phẩm: DirtyCoins Rope Embroidery Knit Polo Off White1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 72),
(18, 'DirtyCoins Seven Cherry T Shirt White', 'Sản phẩm: DirtyCoins Seven Cherry T Shirt White', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 50),
(19, 'DirtyCoins Stripe Tee Trompe Loeil Print T Shirt White', 'Sản phẩm: DirtyCoins Stripe Tee Trompe Loeil Print T Shirt White1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 74),
(20, 'DirtyCoins Striped Destroy All Print T Shirt Red Blue', 'Sản phẩm: DirtyCoins Striped Destroy All Print T Shirt Red Blue1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 30.00, 1, 78),
(21, 'DirtyCoins Striped Soccer Jersey Baby Blue White', 'Sản phẩm: DirtyCoins Striped Soccer Jersey Baby Blue White', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 63),
(22, 'DirtyCoins Striped Soccer Polo Jersey Black White', 'Sản phẩm: DirtyCoins Striped Soccer Polo Jersey Black White01', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 80),
(23, 'DirtyCoins Western Logo Print T Shirt Green', 'Sản phẩm: DirtyCoins Western Logo Print T Shirt Green1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 45),
(24, 'DirtyCoins Wild West Fade Relaxed Tan', 'Sản phẩm: DirtyCoins Wild West Fade Relaxed Tan1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 97),
(25, 'Flannel Rope Script Embroidery Wash Candy', 'Sản phẩm: Flannel Rope Script Embroidery Wash Candy1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 30.00, 1, 51),
(26, 'Flannel Rope Script Embroidery Wash Sand', 'Sản phẩm: Flannel Rope Script Embroidery Wash Sand', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 30.00, 1, 67),
(27, 'Frayed Logo Denim Jacket Black', 'Sản phẩm: Frayed Logo Denim Jacket Black', 17, NULL, '2025-11-11 00:43:56', NULL, 0, 20.00, 1, 54),
(28, 'Knit Polo Premium Garment White Green', 'Sản phẩm: Knit Polo Premium Garment White Green', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 20.00, 1, 61),
(29, 'Resort Cuban Shirt Cream', 'Sản phẩm: Resort Cuban Shirt Cream1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 40),
(30, 'Soccer Jersey Dico Seven Red Green', 'Sản phẩm: Soccer Jersey Dico Seven Red Green', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 10.00, 1, 58),
(31, 'Y Embroidered Denim Shirt Black', 'Sản phẩm: Y Embroidered Denim Shirt Black', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 71),
(32, 'Y Patches Relaxed Hoodie Black0', 'Sản phẩm: Y Patches Relaxed Hoodie Black01', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 55),
(33, 'Y Patches Relaxed Hoodie Grey', 'Sản phẩm: Y Patches Relaxed Hoodie Grey01', 16, NULL, '2025-11-11 00:43:56', NULL, 0, 10.00, 1, 56),
(34, 'DirtyCoins With Colibri T Shirt White', 'Sản phẩm: DirtyCoins With Colibri T Shirt White1', 13, NULL, '2025-11-11 00:43:56', NULL, 0, 0.00, 1, 56),
(35, 'Cap Dico Script Embroidery Cream', NULL, 10, NULL, '2025-11-12 00:53:00', NULL, 0, 0.00, 1, 70),
(36, 'Cap DirtyCoins Racing Crew Embroidery Black', NULL, 10, NULL, '2025-11-12 00:53:00', NULL, 0, 0.00, 1, 35),
(37, 'DC x LA LUNE Phoenix Reversible Puffer Jacket', NULL, 17, NULL, '2025-11-12 00:53:00', NULL, 0, 0.00, 1, 64),
(38, 'Dad Cap Denim DirtyCoins Arc Embroidery', NULL, 10, NULL, '2025-11-12 00:53:13', NULL, 0, 0.00, 1, 75),
(39, 'Denim Shorts Studs - Blue Wash0', NULL, 19, NULL, '2025-11-12 00:53:13', NULL, 0, 0.00, 1, 69),
(40, 'Dico Mate Hoodie - Black', NULL, 16, NULL, '2025-11-12 00:53:47', NULL, 0, 30.00, 1, 43),
(41, 'DirtyCoins Academy Sweatshorts - Pink', NULL, 19, NULL, '2025-11-12 00:53:47', NULL, 0, 0.00, 1, 37),
(42, 'DirtyCoins Big Pounch Cargo Pants - Black', NULL, 18, NULL, '2025-11-12 00:53:48', NULL, 0, 0.00, 1, 59),
(43, 'DirtyCoins Big Pounch Cargo Pants - Brown', NULL, 18, NULL, '2025-11-12 00:53:48', NULL, 0, 0.00, 1, 66),
(44, 'DirtyCoins Casual Baggy Cargo Pants Black Wash', NULL, 18, NULL, '2025-11-12 00:54:02', NULL, 0, 0.00, 1, 58),
(45, 'DirtyCoins Cobruhh T-Shirt White', NULL, 13, NULL, '2025-11-12 00:54:03', NULL, 0, 20.00, 1, 39),
(46, 'DirtyCoins Curve Ripstop Shorts Black', NULL, 19, NULL, '2025-11-12 00:54:03', NULL, 0, 0.00, 1, 59),
(47, 'DirtyCoins Curve Ripstop Shorts Red', NULL, 19, NULL, '2025-11-12 00:54:03', NULL, 0, 0.00, 1, 55),
(48, 'DirtyCoins Distressed Double Knee Denim Pants Brown0', NULL, 18, NULL, '2025-11-12 00:54:03', NULL, 0, 0.00, 1, 71),
(49, 'DirtyCoins Drawstring Camo Denim Cargo Pants', NULL, 18, NULL, '2025-11-12 00:54:18', NULL, 0, 0.00, 1, 61),
(50, 'DirtyCoins Embroidery Chain Knit Polo Cream', '', 13, NULL, '2025-11-12 00:54:19', NULL, 0, 0.00, 1, 67),
(51, 'DirtyCoins Floral Silhouette White', NULL, 13, NULL, '2025-11-12 00:54:50', NULL, 0, 0.00, 1, 43),
(52, 'DirtyCoins Splicing Cargo Pants Blue Wash', NULL, 18, NULL, '2025-11-12 00:56:49', NULL, 0, 0.00, 1, 46),
(53, 'DirtyCoins Stain Disstress Baggy Denim Pants Faded Blue0', NULL, 18, NULL, '2025-11-12 00:56:49', NULL, 0, 0.00, 1, 82),
(54, 'DirtyCoins Star Hoodie - Baby Blue', NULL, 16, NULL, '2025-11-12 00:56:49', NULL, 0, 0.00, 1, 26),
(55, 'DirtyCoins Star Hoodie - Black', NULL, 16, NULL, '2025-11-12 00:56:50', NULL, 0, 0.00, 1, 70),
(56, 'DirtyCoins Star Sweatshorts - Baby Blue', NULL, 19, NULL, '2025-11-12 00:56:50', NULL, 0, 0.00, 1, 55),
(57, 'DirtyCoins Underdogs Nylon Shorts Black', NULL, 19, NULL, '2025-11-12 00:58:17', NULL, 0, 0.00, 1, 47),
(58, 'DirtyCoins Y2K Jersey Football Pink', NULL, 13, NULL, '2025-11-12 00:59:08', NULL, 0, 20.00, 1, 71),
(59, 'Double Knee Shorts Distressed Blue Wash', NULL, 19, NULL, '2025-11-12 00:59:08', NULL, 0, 0.00, 1, 36),
(60, 'Flame Wash Relaxed Denim Pants Black', NULL, 18, NULL, '2025-11-12 00:59:08', NULL, 0, 0.00, 1, 68),
(61, 'Frayed Logo Baggy Denim Pants - Black', NULL, 18, NULL, '2025-11-12 00:59:40', NULL, 0, 0.00, 1, 60),
(62, 'If I Play I Play To Win T-Shirt - Black', NULL, 13, NULL, '2025-11-12 01:00:12', NULL, 0, 0.00, 1, 37),
(63, 'If I Play I Play To Win T-Shirt - White', NULL, 13, NULL, '2025-11-12 01:00:12', NULL, 0, 0.00, 1, 68),
(64, 'Leather Patch Beanie', NULL, 10, NULL, '2025-11-12 01:00:33', NULL, 0, 0.00, 1, 118),
(65, 'Letters Monogram Knit Sweater - Blue', NULL, 16, NULL, '2025-11-12 01:00:33', NULL, 0, 0.00, 1, 73),
(66, 'Letters Monogram Knit Sweater - Pink', NULL, 16, NULL, '2025-11-12 01:00:33', NULL, 0, 0.00, 1, 50),
(67, 'Letters Monogram Knit Sweater - Tan', NULL, 16, NULL, '2025-11-12 01:00:33', NULL, 0, 0.00, 1, 50),
(68, 'Logo Patched Baggy Sweatshorts Grey', NULL, 19, NULL, '2025-11-12 01:00:34', NULL, 0, 0.00, 1, 49),
(69, 'Play To Win Oversized Hoodie - Black', NULL, 16, NULL, '2025-11-12 01:00:36', NULL, 0, 0.00, 1, 36),
(70, 'Saigon Star Big Mesh Football Jersey - Blue', NULL, 13, NULL, '2025-11-12 01:00:58', NULL, 0, 0.00, 1, 73),
(71, 'Saigon Star Big Mesh Football Jersey - Red', NULL, 13, NULL, '2025-11-12 01:00:58', NULL, 0, 0.00, 1, 56),
(72, 'Striped Script Logo Shorts - Grey', NULL, 19, NULL, '2025-11-12 01:01:26', NULL, 0, 0.00, 1, 44),
(73, 'University Felt Varsity Jacket - Black', NULL, 17, NULL, '2025-11-12 01:01:26', NULL, 0, 0.00, 1, 67),
(74, 'Wavy Dico Jr Mesh Cap - Black', NULL, 10, NULL, '2025-11-12 01:01:26', NULL, 0, 0.00, 1, 47),
(75, 'Y Embroidery Relaxed Denim Pants', NULL, 18, NULL, '2025-11-12 01:01:57', NULL, 0, 0.00, 1, 66),
(76, 'Y Logo Cap - Black', NULL, 10, NULL, '2025-11-12 01:01:57', NULL, 0, 0.00, 1, 62),
(77, 'Y Patch Crochet Polo Black', 'TSHIT', 16, NULL, '2025-11-12 01:01:57', NULL, 3, 0.00, 1, 78),
(130, 'Áo Sơ Mi Tay Ngắn Vải Nhung Corduroy Retro Ít Nhăn Seventy Seven 022 Nâu Nhạt', 'VẢI CORDUROY RETRO VIBE: Vải Corduroy 100% Polyester ít nhăn đứng form mang vẻ đẹp retro ấm áp', 13, NULL, '2025-11-27 13:26:39', NULL, 0, 0.00, 1, 398),
(131, 'combo', 'Chất liệu cao cấp', 11, NULL, '2025-11-28 00:05:59', NULL, 0, 0.00, 1, 137),
(132, 'Áo Sơ Mi Tay Ngắn Dragon Ball Z 024 Nâu Đậm', 'Kỹ thuật in Rubber tiên tiến với mực in cao cấp bền màu chống bong tróc đảm bảo độ sắc nét', 13, NULL, '2025-12-03 16:41:26', NULL, 2, 0.00, 1, 500),
(133, 'Quần Short 5 Inch Dù Mỏng Nhẹ Non Branded 006 Đen', 'THOÁNG MÁT NHANH KHÔ: Chất liệu dù parachute nhẹ mỏng có khả năng thoát ẩm tốt nhanh khô.', 11, NULL, '2025-12-04 10:44:23', NULL, 3, 30.00, 1, 500),
(134, 'Quần Short 5 Inch Dù Mỏng Nhẹ Non Branded 006 Đỏ', 'THOÁNG MÁT NHANH KHÔ: Chất liệu dù parachute nhẹ mỏng có khả năng thoát ẩm tốt nhanh khô', 13, NULL, '2025-12-04 11:08:32', NULL, 3, 30.00, 1, 400),
(136, 'Nons xam', 'asd', 11, NULL, '2025-12-08 12:35:24', NULL, 1, 0.00, 1, 200),
(137, 'combo', 'aadada', 10, NULL, '2025-12-13 15:43:07', NULL, 0, 0.00, 1, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_images`
--

CREATE TABLE `product_images` (
  `id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `created_at`) VALUES
(1, 1, '\'Y\' Patches Black T-shirt - Black1.jpg', '2025-11-11 00:43:55'),
(2, 2, 'DC x The Underdogs T-shirt Black1.jpg', '2025-11-11 00:43:56'),
(3, 3, 'Dico Fluffy Print T-Shirt Black1.jpg', '2025-11-11 00:43:56'),
(4, 4, 'Dico Jr Variation T-Shirt Black1.jpg', '2025-11-11 00:43:56'),
(5, 5, 'Dico Jr Variation T-Shirt White1.jpg', '2025-11-11 00:43:56'),
(6, 6, 'DirtyCoins Bình Tân Embroidered Polo Black01.pg', '2025-11-11 00:43:56'),
(7, 7, 'DirtyCoins Double Trouble Oversized Hoodie Brown1.jpg', '2025-11-11 00:43:56'),
(8, 8, 'DirtyCoins Endless Summer T-Shirt White1.jpg', '2025-11-11 00:43:56'),
(9, 9, 'DirtyCoins Floral Silhouette Shirt Tan1.jpg', '2025-11-11 00:43:56'),
(10, 10, 'DirtyCoins Hustling Boxy T-Shirt Red1.jpg', '2025-11-11 00:43:56'),
(11, 11, 'DirtyCoins Lil Pony T-Shirt Black1.jpg', '2025-11-11 00:43:56'),
(12, 12, 'DirtyCoins Patch In Heart T-Shirt Black1.jpg', '2025-11-11 00:43:56'),
(13, 13, 'DirtyCoins Patch In Heart T-Shirt White1.jpg', '2025-11-11 00:43:56'),
(14, 14, 'DirtyCoins Printed Label Tank Top Black1.jpg', '2025-11-11 00:43:56'),
(15, 15, 'DirtyCoins Printed Label Tank Top White1.jpg', '2025-11-11 00:43:56'),
(16, 16, 'DirtyCoins Rope Embroidery Knit Polo Black1.jpg', '2025-11-11 00:43:56'),
(17, 17, 'DirtyCoins Rope Embroidery Knit Polo Off White1.jpg', '2025-11-11 00:43:56'),
(18, 18, 'DirtyCoins Seven Cherry T-Shirt White1.jpg', '2025-11-11 00:43:56'),
(19, 19, 'DirtyCoins Stripe Tee Trompe Loeil Print T-Shirt White1.jpg', '2025-11-11 00:43:56'),
(20, 20, 'DirtyCoins Striped Destroy All Print T-Shirt Red Blue1.jpg', '2025-11-11 00:43:56'),
(21, 21, 'DirtyCoins Striped Soccer Jersey Baby Blue White01.jpg', '2025-11-11 00:43:56'),
(22, 22, 'DirtyCoins Striped Soccer Polo Jersey Black White01.jpg', '2025-11-11 00:43:56'),
(23, 23, 'DirtyCoins Western Logo Print T-Shirt Green1.jpg', '2025-11-11 00:43:56'),
(24, 24, 'DirtyCoins Wild West Fade Relaxed Tan1.jpg', '2025-11-11 00:43:56'),
(25, 25, 'Flannel Rope Script Embroidery Wash Candy1.jpg', '2025-11-11 00:43:56'),
(26, 26, 'Flannel Rope Script Embroidery Wash Sand1.jpg', '2025-11-11 00:43:56'),
(27, 27, 'Frayed Logo Denim Jacket - Black01.jpg', '2025-11-11 00:43:56'),
(28, 28, 'Knit Polo Premium Garment White Green1.jpg', '2025-11-11 00:43:56'),
(29, 29, 'Resort Cuban Shirt Cream1.jpg', '2025-11-11 00:43:56'),
(30, 30, 'Soccer Jersey Dico Seven Red Green01.jpg', '2025-11-11 00:43:56'),
(31, 31, 'Y Embroidered Denim Shirt Black01.jpg', '2025-11-11 00:43:56'),
(32, 32, 'Y Patches Relaxed Hoodie Black01.jpg', '2025-11-11 00:43:56'),
(33, 33, 'Y Patches Relaxed Hoodie Grey1.jpg', '2025-11-11 00:43:56'),
(34, 34, 'DirtyCoins With Colibri T-Shirt White1.png', '2025-11-11 00:43:56'),
(35, 35, 'Cap Dico Script Embroidery Cream1.jpg', '2025-11-12 00:53:00'),
(36, 36, 'Cap DirtyCoins Racing Crew Embroidery Black1.jpg', '2025-11-12 00:53:00'),
(37, 37, 'DC x LA LUNE Phoenix Reversible Puffer Jacket01.jpg', '2025-11-12 00:53:00'),
(38, 38, 'Dad Cap Denim DirtyCoins Arc Embroidery1.jpg', '2025-11-12 00:53:13'),
(39, 39, 'Denim Shorts Studs - Blue Wash01.jpg', '2025-11-12 00:53:13'),
(40, 40, 'Dico Mate Hoodie - Black1.jpg', '2025-11-12 00:53:47'),
(41, 41, 'DirtyCoins Academy Sweatshorts - Pink1.jpg', '2025-11-12 00:53:47'),
(42, 42, 'DirtyCoins Big Pounch Cargo Pants - Black01.jpg', '2025-11-12 00:53:48'),
(43, 43, 'DirtyCoins Big Pounch Cargo Pants - Brown01.jpg', '2025-11-12 00:53:48'),
(44, 44, 'DirtyCoins Casual Baggy Cargo Pants Black Wash01.jpg', '2025-11-12 00:54:02'),
(45, 45, 'DirtyCoins Cobruhh T-Shirt White1.jpg', '2025-11-12 00:54:03'),
(46, 46, 'DirtyCoins Curve Ripstop Shorts Black1.jpg', '2025-11-12 00:54:03'),
(47, 47, 'DirtyCoins Curve Ripstop Shorts Red1.jpg', '2025-11-12 00:54:03'),
(48, 48, 'DirtyCoins Distressed Double Knee Denim Pants Brown01.jpg', '2025-11-12 00:54:03'),
(49, 49, 'DirtyCoins Drawstring Camo Denim Cargo Pants1.jpg', '2025-11-12 00:54:18'),
(50, 50, 'DirtyCoins Embroidery Chain Knit Polo Cream01.jpg', '2025-11-12 00:54:19'),
(51, 51, 'DirtyCoins Floral Silhouette White1.jpg', '2025-11-12 00:54:50'),
(52, 52, 'DirtyCoins Splicing Cargo Pants Blue Wash1.jpg', '2025-11-12 00:56:49'),
(53, 53, 'DirtyCoins Stain Disstress Baggy Denim Pants Faded Blue01.jpg', '2025-11-12 00:56:49'),
(54, 54, 'DirtyCoins Star Hoodie - Baby Blue1.jpg', '2025-11-12 00:56:49'),
(55, 55, 'DirtyCoins Star Hoodie - Black1.jpg', '2025-11-12 00:56:50'),
(56, 56, 'DirtyCoins Star Sweatshorts - Baby Blue1.jpg', '2025-11-12 00:56:50'),
(57, 57, 'DirtyCoins Underdogs Nylon Shorts Black1.jpg', '2025-11-12 00:58:17'),
(58, 58, 'DirtyCoins Y2K Jersey Football Pink1.jpg', '2025-11-12 00:59:08'),
(59, 59, 'Double Knee Shorts Distressed Blue Wash1.jpg', '2025-11-12 00:59:08'),
(60, 60, 'Flame Wash Relaxed Denim Pants Black01.jpg', '2025-11-12 00:59:08'),
(61, 61, 'Frayed Logo Baggy Denim Pants - Black1.jpg', '2025-11-12 00:59:40'),
(62, 62, 'If I Play I Play To Win T-Shirt - Black1.jpg', '2025-11-12 01:00:12'),
(63, 63, 'If I Play I Play To Win T-Shirt - White1.jpg', '2025-11-12 01:00:12'),
(64, 64, 'Leather Patch Beanie1.jpg', '2025-11-12 01:00:33'),
(65, 65, 'Letters Monogram Knit Sweater - Blue1.jpg', '2025-11-12 01:00:33'),
(66, 66, 'Letters Monogram Knit Sweater - Pink1.jpg', '2025-11-12 01:00:33'),
(67, 67, 'Letters Monogram Knit Sweater - Tan1.jpg', '2025-11-12 01:00:33'),
(68, 68, 'Logo Patched Baggy Sweatshorts Grey01.jpg', '2025-11-12 01:00:34'),
(69, 69, 'Play To Win Oversized Hoodie - Black1.jpg', '2025-11-12 01:00:36'),
(70, 70, 'Saigon Star Big Mesh Football Jersey - Blue1.jpg', '2025-11-12 01:00:58'),
(71, 71, 'Saigon Star Big Mesh Football Jersey - Red1.jpg', '2025-11-12 01:00:58'),
(72, 72, 'Striped Script Logo Shorts - Grey1.jpg', '2025-11-12 01:01:26'),
(73, 73, 'University Felt Varsity Jacket - Black1.jpg', '2025-11-12 01:01:26'),
(74, 74, 'Wavy Dico Jr Mesh Cap - Black1.jpg', '2025-11-12 01:01:26'),
(75, 75, 'Y Embroidery Relaxed Denim Pants1.jpg', '2025-11-12 01:01:57'),
(76, 76, 'Y Logo Cap - Black1.jpg', '2025-11-12 01:01:57'),
(77, 77, 'Y Patch Crochet Polo Black01.jpg', '2025-11-12 01:01:57'),
(78, 128, '6927e9c4d40cd5.90407121___o_Thun_Tay_Ng___n_Waffle_Tho__ng_Kh___Seventy_Seven_010_N__u.jpg', '2025-11-27 13:03:48'),
(79, 129, '6927ee8ba5c151.94979985_balo-kh-i-nguyen-13-den-1174881028.webp', '2025-11-27 13:24:11'),
(80, 130, 'prod_69288d2846f403.69690011_ao-s-mi-seventy-seven-22-be-1174882837.webp', '2025-11-27 13:26:39'),
(81, 131, 'prod_693b90e9190e28.77287651_combo-ao-den.jpg', '2025-11-28 00:05:59'),
(82, 132, 'prod_693663edcb59d2.29737788_ao-thun-seventy-seven-be.webp', '2025-12-03 16:41:26'),
(83, 133, 'prod_693663e2c13d23.21710117___o_Thun_Tay_Ng___n_Waffle_Tho__ng_Kh___Seventy_Seven_010_N__u.jpg', '2025-12-04 10:44:23'),
(84, 134, 'prod_693663c6540606.46074694_qu-n-short-non-branded-06-d-1174882305.webp', '2025-12-04 11:08:32'),
(85, 135, '69354d8e036371.34155946_OIP.jpg', '2025-12-07 16:49:02'),
(86, 136, '6936639c62c217.53203456_non-y2010-05-nau-xam-1174878932.webp', '2025-12-08 12:35:24'),
(87, 137, 'prod_693d271b8048a1.79117892_combo-ao-xanh.jpg', '2025-12-13 15:43:07');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `size_id` int DEFAULT NULL,
  `color_id` int DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `price_reduced` decimal(12,2) DEFAULT NULL,
  `quantity` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `price`, `price_reduced`, `quantity`) VALUES
(1, 1, 1, 1, 850000.00, 850000.00, 9),
(2, 2, 1, 2, 850000.00, 850000.00, 19),
(3, 3, 1, 3, 850000.00, 850000.00, 5),
(4, 4, 1, 1, 850000.00, 765000.00, 11),
(5, 5, 1, 4, 850000.00, 765000.00, 19),
(6, 6, 1, 1, 850000.00, 850000.00, 2),
(7, 7, 1, 5, 850000.00, 850000.00, 13),
(8, 8, 1, 4, 850000.00, 850000.00, 19),
(9, 9, 1, 5, 850000.00, 850000.00, 14),
(10, 10, 1, 6, 850000.00, 850000.00, 13),
(11, 11, 1, 1, 850000.00, 850000.00, 1),
(12, 12, 1, 1, 850000.00, 850000.00, 10),
(13, 13, 1, 4, 850000.00, 850000.00, 7),
(14, 14, 1, 1, 850000.00, 850000.00, 3),
(15, 15, 1, 4, 850000.00, 850000.00, 15),
(16, 16, 1, 1, 850000.00, 850000.00, 6),
(17, 17, 1, 4, 850000.00, 850000.00, 7),
(18, 18, 1, 4, 850000.00, 850000.00, 16),
(19, 19, 1, 4, 850000.00, 850000.00, 18),
(20, 20, 1, 3, 850000.00, 595000.00, 0),
(21, 21, 1, 4, 850000.00, 850000.00, 12),
(22, 22, 1, 1, 850000.00, 850000.00, 16),
(23, 23, 1, 7, 850000.00, 850000.00, 4),
(24, 24, 1, 5, 850000.00, 850000.00, 16),
(25, 25, 1, 6, 850000.00, 595000.00, 2),
(26, 26, 1, 5, 850000.00, 595000.00, 8),
(27, 27, 1, 1, 550000.00, 440000.00, 11),
(28, 28, 1, 4, 850000.00, 680000.00, 10),
(29, 29, 1, 8, 850000.00, 850000.00, 18),
(30, 30, 1, 7, 850000.00, 765000.00, 17),
(31, 31, 1, 1, 850000.00, 850000.00, 12),
(32, 32, 1, 1, 850000.00, 850000.00, 9),
(33, 33, 1, 9, 500000.00, 450000.00, 10),
(34, 34, 1, 4, 850000.00, 850000.00, 3),
(35, 35, 1, 8, 850000.00, 850000.00, 6),
(36, 36, 1, 1, 850000.00, 850000.00, 20),
(37, 37, 1, 6, 850000.00, 850000.00, 19),
(38, 38, 1, 3, 850000.00, 850000.00, 17),
(39, 39, 1, 3, 850000.00, 850000.00, 6),
(40, 40, 1, 1, 850000.00, 595000.00, 0),
(41, 41, 1, 10, 850000.00, 850000.00, 3),
(42, 42, 1, 1, 850000.00, 850000.00, 15),
(43, 43, 1, 5, 850000.00, 850000.00, 3),
(44, 44, 1, 1, 850000.00, 850000.00, 15),
(45, 45, 1, 4, 850000.00, 680000.00, 2),
(46, 46, 1, 1, 850000.00, 850000.00, 7),
(47, 47, 1, 6, 850000.00, 850000.00, 11),
(48, 48, 1, 5, 850000.00, 850000.00, 12),
(49, 49, 1, 5, 850000.00, 850000.00, 8),
(50, 50, 1, 8, 850000.00, 850000.00, 3),
(51, 51, 1, 4, 850000.00, 850000.00, 11),
(52, 52, 1, 3, 850000.00, 850000.00, 5),
(53, 53, 1, 3, 850000.00, 850000.00, 16),
(54, 54, 1, 3, 850000.00, 850000.00, 0),
(55, 55, 1, 1, 850000.00, 850000.00, 18),
(56, 56, 1, 3, 850000.00, 850000.00, 4),
(57, 57, 1, 1, 850000.00, 850000.00, 8),
(58, 58, 1, 10, 850000.00, 680000.00, 9),
(59, 59, 1, 3, 850000.00, 850000.00, 1),
(60, 60, 1, 1, 850000.00, 850000.00, 0),
(61, 61, 1, 1, 850000.00, 850000.00, 19),
(62, 62, 1, 1, 850000.00, 850000.00, 11),
(63, 63, 1, 4, 850000.00, 850000.00, 17),
(64, 64, 1, NULL, 850000.00, 850000.00, 13),
(65, 65, 1, 3, 850000.00, 850000.00, 12),
(66, 66, 1, 10, 850000.00, 850000.00, 3),
(67, 67, 1, 5, 850000.00, 850000.00, 19),
(68, 68, 1, 9, 850000.00, 850000.00, 3),
(69, 69, 1, 1, 850000.00, 850000.00, 1),
(70, 70, 1, 3, 850000.00, 850000.00, 16),
(71, 71, 1, 6, 850000.00, 850000.00, 13),
(72, 72, 1, 9, 850000.00, 850000.00, 2),
(73, 73, 1, 1, 850000.00, 850000.00, 16),
(74, 74, 1, 1, 850000.00, 850000.00, 11),
(75, 75, 1, 3, 850000.00, 850000.00, 10),
(76, 76, 1, 1, 850000.00, 850000.00, 17),
(77, 77, 1, 1, 840000.00, 830000.00, 2),
(128, 1, 5, 1, 850000.00, 850000.00, 3),
(129, 1, 4, 1, 850000.00, 850000.00, 2),
(130, 1, 2, 1, 850000.00, 850000.00, 4),
(131, 1, 3, 1, 850000.00, 850000.00, 12),
(132, 1, 6, 1, 850000.00, 850000.00, 8),
(133, 2, 5, 2, 850000.00, 850000.00, 4),
(134, 2, 4, 2, 850000.00, 850000.00, 16),
(135, 2, 2, 2, 850000.00, 850000.00, 5),
(136, 2, 3, 2, 850000.00, 850000.00, 0),
(137, 2, 6, 2, 850000.00, 850000.00, 7),
(138, 3, 5, 3, 850000.00, 850000.00, 12),
(139, 3, 4, 3, 850000.00, 850000.00, 0),
(140, 3, 2, 3, 850000.00, 850000.00, 5),
(141, 3, 3, 3, 850000.00, 850000.00, 4),
(142, 3, 6, 3, 850000.00, 850000.00, 5),
(143, 4, 5, 1, 850000.00, 765000.00, 13),
(144, 4, 4, 1, 850000.00, 765000.00, 11),
(145, 4, 2, 1, 850000.00, 765000.00, 13),
(146, 4, 3, 1, 850000.00, 765000.00, 14),
(147, 4, 6, 1, 850000.00, 765000.00, 12),
(148, 5, 5, 4, 850000.00, 765000.00, 16),
(149, 5, 4, 4, 850000.00, 765000.00, 2),
(150, 5, 2, 4, 850000.00, 765000.00, 4),
(151, 5, 3, 4, 850000.00, 765000.00, 14),
(152, 5, 6, 4, 850000.00, 765000.00, 19),
(153, 6, 5, 1, 850000.00, 850000.00, 11),
(154, 6, 4, 1, 850000.00, 850000.00, 19),
(155, 6, 2, 1, 850000.00, 850000.00, 20),
(156, 6, 3, 1, 850000.00, 850000.00, 0),
(157, 6, 6, 1, 850000.00, 850000.00, 4),
(158, 7, 5, 5, 850000.00, 850000.00, 1),
(159, 7, 4, 5, 850000.00, 850000.00, 13),
(160, 7, 2, 5, 850000.00, 850000.00, 20),
(161, 7, 3, 5, 850000.00, 850000.00, 19),
(162, 7, 6, 5, 850000.00, 850000.00, 17),
(163, 8, 5, 4, 850000.00, 850000.00, 8),
(164, 8, 4, 4, 850000.00, 850000.00, 10),
(165, 8, 2, 4, 850000.00, 850000.00, 3),
(166, 8, 3, 4, 850000.00, 850000.00, 8),
(167, 8, 6, 4, 850000.00, 850000.00, 12),
(168, 9, 5, 5, 850000.00, 850000.00, 13),
(169, 9, 4, 5, 850000.00, 850000.00, 7),
(170, 9, 2, 5, 850000.00, 850000.00, 0),
(171, 9, 3, 5, 850000.00, 850000.00, 20),
(172, 9, 6, 5, 850000.00, 850000.00, 18),
(173, 10, 5, 6, 850000.00, 850000.00, 8),
(174, 10, 4, 6, 850000.00, 850000.00, 6),
(175, 10, 2, 6, 850000.00, 850000.00, 8),
(176, 10, 3, 6, 850000.00, 850000.00, 2),
(177, 10, 6, 6, 850000.00, 850000.00, 9),
(178, 11, 5, 1, 850000.00, 850000.00, 18),
(179, 11, 4, 1, 850000.00, 850000.00, 19),
(180, 11, 2, 1, 850000.00, 850000.00, 1),
(181, 11, 3, 1, 850000.00, 850000.00, 9),
(182, 11, 6, 1, 850000.00, 850000.00, 4),
(183, 12, 5, 1, 850000.00, 850000.00, 14),
(184, 12, 4, 1, 850000.00, 850000.00, 17),
(185, 12, 2, 1, 850000.00, 850000.00, 20),
(186, 12, 3, 1, 850000.00, 850000.00, 10),
(187, 12, 6, 1, 850000.00, 850000.00, 12),
(188, 13, 5, 4, 850000.00, 850000.00, 6),
(189, 13, 4, 4, 850000.00, 850000.00, 19),
(190, 13, 2, 4, 850000.00, 850000.00, 15),
(191, 13, 3, 4, 850000.00, 850000.00, 16),
(192, 13, 6, 4, 850000.00, 850000.00, 15),
(193, 14, 5, 1, 850000.00, 850000.00, 7),
(194, 14, 4, 1, 850000.00, 850000.00, 11),
(195, 14, 2, 1, 850000.00, 850000.00, 13),
(196, 14, 3, 1, 850000.00, 850000.00, 14),
(197, 14, 6, 1, 850000.00, 850000.00, 8),
(198, 15, 5, 4, 850000.00, 850000.00, 1),
(199, 15, 4, 4, 850000.00, 850000.00, 0),
(200, 15, 2, 4, 850000.00, 850000.00, 18),
(201, 15, 3, 4, 850000.00, 850000.00, 7),
(202, 15, 6, 4, 850000.00, 850000.00, 4),
(203, 16, 5, 1, 850000.00, 850000.00, 20),
(204, 16, 4, 1, 850000.00, 850000.00, 5),
(205, 16, 2, 1, 850000.00, 850000.00, 7),
(206, 16, 3, 1, 850000.00, 850000.00, 1),
(207, 16, 6, 1, 850000.00, 850000.00, 3),
(208, 17, 5, 4, 850000.00, 850000.00, 14),
(209, 17, 4, 4, 850000.00, 850000.00, 17),
(210, 17, 2, 4, 850000.00, 850000.00, 4),
(211, 17, 3, 4, 850000.00, 850000.00, 10),
(212, 17, 6, 4, 850000.00, 850000.00, 20),
(213, 18, 5, 4, 850000.00, 850000.00, 5),
(214, 18, 4, 4, 850000.00, 850000.00, 8),
(215, 18, 2, 4, 850000.00, 850000.00, 6),
(216, 18, 3, 4, 850000.00, 850000.00, 5),
(217, 18, 6, 4, 850000.00, 850000.00, 10),
(218, 19, 5, 4, 850000.00, 850000.00, 13),
(219, 19, 4, 4, 850000.00, 850000.00, 15),
(220, 19, 2, 4, 850000.00, 850000.00, 13),
(221, 19, 3, 4, 850000.00, 850000.00, 2),
(222, 19, 6, 4, 850000.00, 850000.00, 13),
(223, 20, 5, 3, 850000.00, 595000.00, 19),
(224, 20, 4, 3, 850000.00, 595000.00, 14),
(225, 20, 2, 3, 850000.00, 595000.00, 11),
(226, 20, 3, 3, 850000.00, 595000.00, 15),
(227, 20, 6, 3, 850000.00, 595000.00, 19),
(228, 21, 5, 4, 850000.00, 850000.00, 12),
(229, 21, 4, 4, 850000.00, 850000.00, 3),
(230, 21, 2, 4, 850000.00, 850000.00, 20),
(231, 21, 3, 4, 850000.00, 850000.00, 9),
(232, 21, 6, 4, 850000.00, 850000.00, 7),
(233, 22, 5, 1, 850000.00, 850000.00, 8),
(234, 22, 4, 1, 850000.00, 850000.00, 19),
(235, 22, 2, 1, 850000.00, 850000.00, 9),
(236, 22, 3, 1, 850000.00, 850000.00, 9),
(237, 22, 6, 1, 850000.00, 850000.00, 19),
(238, 23, 5, 7, 850000.00, 850000.00, 6),
(239, 23, 4, 7, 850000.00, 850000.00, 14),
(240, 23, 2, 7, 850000.00, 850000.00, 10),
(241, 23, 3, 7, 850000.00, 850000.00, 10),
(242, 23, 6, 7, 850000.00, 850000.00, 1),
(243, 24, 5, 5, 850000.00, 850000.00, 17),
(244, 24, 4, 5, 850000.00, 850000.00, 18),
(245, 24, 2, 5, 850000.00, 850000.00, 18),
(246, 24, 3, 5, 850000.00, 850000.00, 17),
(247, 24, 6, 5, 850000.00, 850000.00, 11),
(248, 25, 5, 6, 850000.00, 595000.00, 2),
(249, 25, 4, 6, 850000.00, 595000.00, 0),
(250, 25, 2, 6, 850000.00, 595000.00, 15),
(251, 25, 3, 6, 850000.00, 595000.00, 13),
(252, 25, 6, 6, 850000.00, 595000.00, 19),
(253, 26, 5, 5, 850000.00, 595000.00, 15),
(254, 26, 4, 5, 850000.00, 595000.00, 20),
(255, 26, 2, 5, 850000.00, 595000.00, 12),
(256, 26, 3, 5, 850000.00, 595000.00, 1),
(257, 26, 6, 5, 850000.00, 595000.00, 11),
(258, 27, 5, 1, 550000.00, 440000.00, 13),
(259, 27, 4, 1, 550000.00, 440000.00, 9),
(260, 27, 2, 1, 550000.00, 440000.00, 5),
(261, 27, 3, 1, 550000.00, 440000.00, 2),
(262, 27, 6, 1, 550000.00, 440000.00, 14),
(263, 28, 5, 4, 850000.00, 680000.00, 1),
(264, 28, 4, 4, 850000.00, 680000.00, 6),
(265, 28, 2, 4, 850000.00, 680000.00, 8),
(266, 28, 3, 4, 850000.00, 680000.00, 20),
(267, 28, 6, 4, 850000.00, 680000.00, 16),
(268, 29, 5, 8, 850000.00, 850000.00, 0),
(269, 29, 4, 8, 850000.00, 850000.00, 13),
(270, 29, 2, 8, 850000.00, 850000.00, 4),
(271, 29, 3, 8, 850000.00, 850000.00, 3),
(272, 29, 6, 8, 850000.00, 850000.00, 2),
(273, 30, 5, 7, 850000.00, 765000.00, 3),
(274, 30, 4, 7, 850000.00, 765000.00, 11),
(275, 30, 2, 7, 850000.00, 765000.00, 2),
(276, 30, 3, 7, 850000.00, 765000.00, 19),
(277, 30, 6, 7, 850000.00, 765000.00, 6),
(278, 31, 5, 1, 850000.00, 850000.00, 14),
(279, 31, 4, 1, 850000.00, 850000.00, 13),
(280, 31, 2, 1, 850000.00, 850000.00, 2),
(281, 31, 3, 1, 850000.00, 850000.00, 13),
(282, 31, 6, 1, 850000.00, 850000.00, 17),
(283, 32, 5, 1, 850000.00, 850000.00, 7),
(284, 32, 4, 1, 850000.00, 850000.00, 5),
(285, 32, 2, 1, 850000.00, 850000.00, 2),
(286, 32, 3, 1, 850000.00, 850000.00, 17),
(287, 32, 6, 1, 850000.00, 850000.00, 15),
(288, 33, 5, 9, 500000.00, 450000.00, 7),
(289, 33, 4, 9, 500000.00, 450000.00, 8),
(290, 33, 2, 9, 500000.00, 450000.00, 0),
(291, 33, 3, 9, 500000.00, 450000.00, 19),
(292, 33, 6, 9, 500000.00, 450000.00, 12),
(293, 34, 5, 4, 850000.00, 850000.00, 3),
(294, 34, 4, 4, 850000.00, 850000.00, 0),
(295, 34, 2, 4, 850000.00, 850000.00, 12),
(296, 34, 3, 4, 850000.00, 850000.00, 19),
(297, 34, 6, 4, 850000.00, 850000.00, 19),
(298, 35, 5, 8, 850000.00, 850000.00, 15),
(299, 35, 4, 8, 850000.00, 850000.00, 18),
(300, 35, 2, 8, 850000.00, 850000.00, 6),
(301, 35, 3, 8, 850000.00, 850000.00, 18),
(302, 35, 6, 8, 850000.00, 850000.00, 7),
(303, 36, 5, 1, 850000.00, 850000.00, 4),
(304, 36, 4, 1, 850000.00, 850000.00, 0),
(305, 36, 2, 1, 850000.00, 850000.00, 8),
(306, 36, 3, 1, 850000.00, 850000.00, 1),
(307, 36, 6, 1, 850000.00, 850000.00, 2),
(308, 37, 5, 6, 850000.00, 850000.00, 8),
(309, 37, 4, 6, 850000.00, 850000.00, 11),
(310, 37, 2, 6, 850000.00, 850000.00, 11),
(311, 37, 3, 6, 850000.00, 850000.00, 1),
(312, 37, 6, 6, 850000.00, 850000.00, 14),
(313, 38, 5, 3, 850000.00, 850000.00, 6),
(314, 38, 4, 3, 850000.00, 850000.00, 10),
(315, 38, 2, 3, 850000.00, 850000.00, 13),
(316, 38, 3, 3, 850000.00, 850000.00, 11),
(317, 38, 6, 3, 850000.00, 850000.00, 18),
(318, 39, 5, 3, 850000.00, 850000.00, 13),
(319, 39, 4, 3, 850000.00, 850000.00, 15),
(320, 39, 2, 3, 850000.00, 850000.00, 15),
(321, 39, 3, 3, 850000.00, 850000.00, 11),
(322, 39, 6, 3, 850000.00, 850000.00, 9),
(323, 40, 5, 1, 850000.00, 595000.00, 14),
(324, 40, 4, 1, 850000.00, 595000.00, 2),
(325, 40, 2, 1, 850000.00, 595000.00, 9),
(326, 40, 3, 1, 850000.00, 595000.00, 18),
(327, 40, 6, 1, 850000.00, 595000.00, 0),
(328, 41, 5, 10, 850000.00, 850000.00, 8),
(329, 41, 4, 10, 850000.00, 850000.00, 1),
(330, 41, 2, 10, 850000.00, 850000.00, 1),
(331, 41, 3, 10, 850000.00, 850000.00, 5),
(332, 41, 6, 10, 850000.00, 850000.00, 19),
(333, 42, 5, 1, 850000.00, 850000.00, 20),
(334, 42, 4, 1, 850000.00, 850000.00, 0),
(335, 42, 2, 1, 850000.00, 850000.00, 5),
(336, 42, 3, 1, 850000.00, 850000.00, 6),
(337, 42, 6, 1, 850000.00, 850000.00, 13),
(338, 43, 5, 5, 850000.00, 850000.00, 8),
(339, 43, 4, 5, 850000.00, 850000.00, 20),
(340, 43, 2, 5, 850000.00, 850000.00, 14),
(341, 43, 3, 5, 850000.00, 850000.00, 10),
(342, 43, 6, 5, 850000.00, 850000.00, 11),
(343, 44, 5, 1, 850000.00, 850000.00, 3),
(344, 44, 4, 1, 850000.00, 850000.00, 2),
(345, 44, 2, 1, 850000.00, 850000.00, 4),
(346, 44, 3, 1, 850000.00, 850000.00, 15),
(347, 44, 6, 1, 850000.00, 850000.00, 19),
(348, 45, 5, 4, 850000.00, 680000.00, 10),
(349, 45, 4, 4, 850000.00, 680000.00, 16),
(350, 45, 2, 4, 850000.00, 680000.00, 6),
(351, 45, 3, 4, 850000.00, 680000.00, 4),
(352, 45, 6, 4, 850000.00, 680000.00, 1),
(353, 46, 5, 1, 850000.00, 850000.00, 16),
(354, 46, 4, 1, 850000.00, 850000.00, 13),
(355, 46, 2, 1, 850000.00, 850000.00, 17),
(356, 46, 3, 1, 850000.00, 850000.00, 6),
(357, 46, 6, 1, 850000.00, 850000.00, 0),
(358, 47, 5, 6, 850000.00, 850000.00, 5),
(359, 47, 4, 6, 850000.00, 850000.00, 4),
(360, 47, 2, 6, 850000.00, 850000.00, 6),
(361, 47, 3, 6, 850000.00, 850000.00, 18),
(362, 47, 6, 6, 850000.00, 850000.00, 11),
(363, 48, 5, 5, 850000.00, 850000.00, 2),
(364, 48, 4, 5, 850000.00, 850000.00, 17),
(365, 48, 2, 5, 850000.00, 850000.00, 16),
(366, 48, 3, 5, 850000.00, 850000.00, 8),
(367, 48, 6, 5, 850000.00, 850000.00, 16),
(368, 49, 5, 5, 850000.00, 850000.00, 15),
(369, 49, 4, 5, 850000.00, 850000.00, 7),
(370, 49, 2, 5, 850000.00, 850000.00, 10),
(371, 49, 3, 5, 850000.00, 850000.00, 8),
(372, 49, 6, 5, 850000.00, 850000.00, 13),
(373, 50, 5, 8, 850000.00, 850000.00, 19),
(374, 50, 4, 8, 850000.00, 850000.00, 14),
(375, 50, 2, 8, 850000.00, 850000.00, 15),
(376, 50, 3, 8, 850000.00, 850000.00, 14),
(377, 50, 6, 8, 850000.00, 850000.00, 2),
(378, 51, 5, 4, 850000.00, 850000.00, 10),
(379, 51, 4, 4, 850000.00, 850000.00, 2),
(380, 51, 2, 4, 850000.00, 850000.00, 4),
(381, 51, 3, 4, 850000.00, 850000.00, 15),
(382, 51, 6, 4, 850000.00, 850000.00, 1),
(383, 52, 5, 3, 850000.00, 850000.00, 3),
(384, 52, 4, 3, 850000.00, 850000.00, 12),
(385, 52, 2, 3, 850000.00, 850000.00, 9),
(386, 52, 3, 3, 850000.00, 850000.00, 11),
(387, 52, 6, 3, 850000.00, 850000.00, 6),
(388, 53, 5, 3, 850000.00, 850000.00, 19),
(389, 53, 4, 3, 850000.00, 850000.00, 13),
(390, 53, 2, 3, 850000.00, 850000.00, 9),
(391, 53, 3, 3, 850000.00, 850000.00, 5),
(392, 53, 6, 3, 850000.00, 850000.00, 20),
(393, 54, 5, 3, 850000.00, 850000.00, 0),
(394, 54, 4, 3, 850000.00, 850000.00, 4),
(395, 54, 2, 3, 850000.00, 850000.00, 2),
(396, 54, 3, 3, 850000.00, 850000.00, 18),
(397, 54, 6, 3, 850000.00, 850000.00, 2),
(398, 55, 5, 1, 850000.00, 850000.00, 19),
(399, 55, 4, 1, 850000.00, 850000.00, 3),
(400, 55, 2, 1, 850000.00, 850000.00, 4),
(401, 55, 3, 1, 850000.00, 850000.00, 10),
(402, 55, 6, 1, 850000.00, 850000.00, 16),
(403, 56, 5, 3, 850000.00, 850000.00, 9),
(404, 56, 4, 3, 850000.00, 850000.00, 17),
(405, 56, 2, 3, 850000.00, 850000.00, 19),
(406, 56, 3, 3, 850000.00, 850000.00, 0),
(407, 56, 6, 3, 850000.00, 850000.00, 6),
(408, 57, 5, 1, 850000.00, 850000.00, 12),
(409, 57, 4, 1, 850000.00, 850000.00, 0),
(410, 57, 2, 1, 850000.00, 850000.00, 5),
(411, 57, 3, 1, 850000.00, 850000.00, 6),
(412, 57, 6, 1, 850000.00, 850000.00, 16),
(413, 58, 5, 10, 850000.00, 680000.00, 0),
(414, 58, 4, 10, 850000.00, 680000.00, 16),
(415, 58, 2, 10, 850000.00, 680000.00, 16),
(416, 58, 3, 10, 850000.00, 680000.00, 13),
(417, 58, 6, 10, 850000.00, 680000.00, 17),
(418, 59, 5, 3, 850000.00, 850000.00, 3),
(419, 59, 4, 3, 850000.00, 850000.00, 8),
(420, 59, 2, 3, 850000.00, 850000.00, 9),
(421, 59, 3, 3, 850000.00, 850000.00, 0),
(422, 59, 6, 3, 850000.00, 850000.00, 15),
(423, 60, 5, 1, 850000.00, 850000.00, 12),
(424, 60, 4, 1, 850000.00, 850000.00, 15),
(425, 60, 2, 1, 850000.00, 850000.00, 18),
(426, 60, 3, 1, 850000.00, 850000.00, 6),
(427, 60, 6, 1, 850000.00, 850000.00, 17),
(428, 61, 5, 1, 850000.00, 850000.00, 4),
(429, 61, 4, 1, 850000.00, 850000.00, 13),
(430, 61, 2, 1, 850000.00, 850000.00, 12),
(431, 61, 3, 1, 850000.00, 850000.00, 1),
(432, 61, 6, 1, 850000.00, 850000.00, 11),
(433, 62, 5, 1, 850000.00, 850000.00, 11),
(434, 62, 4, 1, 850000.00, 850000.00, 0),
(435, 62, 2, 1, 850000.00, 850000.00, 9),
(436, 62, 3, 1, 850000.00, 850000.00, 6),
(437, 62, 6, 1, 850000.00, 850000.00, 0),
(438, 63, 5, 4, 850000.00, 850000.00, 6),
(439, 63, 4, 4, 850000.00, 850000.00, 11),
(440, 63, 2, 4, 850000.00, 850000.00, 14),
(441, 63, 3, 4, 850000.00, 850000.00, 17),
(442, 63, 6, 4, 850000.00, 850000.00, 3),
(443, 64, 5, NULL, 850000.00, 850000.00, 5),
(444, 64, 4, NULL, 850000.00, 850000.00, 19),
(445, 64, 2, NULL, 850000.00, 850000.00, 15),
(446, 64, 3, NULL, 850000.00, 850000.00, 18),
(447, 64, 6, NULL, 850000.00, 850000.00, 3),
(448, 65, 5, 3, 850000.00, 850000.00, 6),
(449, 65, 4, 3, 850000.00, 850000.00, 20),
(450, 65, 2, 3, 850000.00, 850000.00, 19),
(451, 65, 3, 3, 850000.00, 850000.00, 16),
(452, 65, 6, 3, 850000.00, 850000.00, 0),
(453, 66, 5, 10, 850000.00, 850000.00, 16),
(454, 66, 4, 10, 850000.00, 850000.00, 16),
(455, 66, 2, 10, 850000.00, 850000.00, 14),
(456, 66, 3, 10, 850000.00, 850000.00, 0),
(457, 66, 6, 10, 850000.00, 850000.00, 1),
(458, 67, 5, 5, 850000.00, 850000.00, 5),
(459, 67, 4, 5, 850000.00, 850000.00, 0),
(460, 67, 2, 5, 850000.00, 850000.00, 8),
(461, 67, 3, 5, 850000.00, 850000.00, 0),
(462, 67, 6, 5, 850000.00, 850000.00, 18),
(463, 68, 5, 9, 850000.00, 850000.00, 7),
(464, 68, 4, 9, 850000.00, 850000.00, 4),
(465, 68, 2, 9, 850000.00, 850000.00, 18),
(466, 68, 3, 9, 850000.00, 850000.00, 15),
(467, 68, 6, 9, 850000.00, 850000.00, 2),
(468, 69, 5, 1, 850000.00, 850000.00, 7),
(469, 69, 4, 1, 850000.00, 850000.00, 9),
(470, 69, 2, 1, 850000.00, 850000.00, 2),
(471, 69, 3, 1, 850000.00, 850000.00, 4),
(472, 69, 6, 1, 850000.00, 850000.00, 13),
(473, 70, 5, 3, 850000.00, 850000.00, 15),
(474, 70, 4, 3, 850000.00, 850000.00, 12),
(475, 70, 2, 3, 850000.00, 850000.00, 18),
(476, 70, 3, 3, 850000.00, 850000.00, 11),
(477, 70, 6, 3, 850000.00, 850000.00, 1),
(478, 71, 5, 6, 850000.00, 850000.00, 13),
(479, 71, 4, 6, 850000.00, 850000.00, 3),
(480, 71, 2, 6, 850000.00, 850000.00, 3),
(481, 71, 3, 6, 850000.00, 850000.00, 0),
(482, 71, 6, 6, 850000.00, 850000.00, 19),
(483, 72, 5, 9, 850000.00, 850000.00, 9),
(484, 72, 4, 9, 850000.00, 850000.00, 7),
(485, 72, 2, 9, 850000.00, 850000.00, 11),
(486, 72, 3, 9, 850000.00, 850000.00, 11),
(487, 72, 6, 9, 850000.00, 850000.00, 4),
(488, 73, 5, 1, 850000.00, 850000.00, 7),
(489, 73, 4, 1, 850000.00, 850000.00, 4),
(490, 73, 2, 1, 850000.00, 850000.00, 18),
(491, 73, 3, 1, 850000.00, 850000.00, 16),
(492, 73, 6, 1, 850000.00, 850000.00, 6),
(493, 74, 5, 1, 850000.00, 850000.00, 3),
(494, 74, 4, 1, 850000.00, 850000.00, 20),
(495, 74, 2, 1, 850000.00, 850000.00, 8),
(496, 74, 3, 1, 850000.00, 850000.00, 1),
(497, 74, 6, 1, 850000.00, 850000.00, 4),
(498, 75, 5, 3, 850000.00, 850000.00, 17),
(499, 75, 4, 3, 850000.00, 850000.00, 12),
(500, 75, 2, 3, 850000.00, 850000.00, 6),
(501, 75, 3, 3, 850000.00, 850000.00, 17),
(502, 75, 6, 3, 850000.00, 850000.00, 4),
(503, 76, 5, 1, 850000.00, 850000.00, 11),
(504, 76, 4, 1, 850000.00, 850000.00, 2),
(505, 76, 2, 1, 850000.00, 850000.00, 20),
(506, 76, 3, 1, 850000.00, 850000.00, 8),
(507, 76, 6, 1, 850000.00, 850000.00, 4),
(508, 77, 5, 1, 850000.00, 850000.00, 17),
(509, 77, 4, 1, 850000.00, 850000.00, 8),
(510, 77, 2, 1, 850000.00, 850000.00, 17),
(511, 77, 3, 1, 850000.00, 850000.00, 18),
(512, 77, 6, 1, 850000.00, 850000.00, 16),
(639, 64, 5, NULL, 850000.00, 850000.00, 6),
(640, 64, 4, NULL, 850000.00, 850000.00, 2),
(641, 64, 2, NULL, 850000.00, 850000.00, 15),
(642, 64, 3, NULL, 850000.00, 850000.00, 8),
(643, 64, 6, NULL, 850000.00, 850000.00, 14),
(644, 128, 1, 4, 350000.00, 0.00, 100),
(645, 128, 2, 4, 360000.00, 0.00, 100),
(646, 128, 3, 4, 370000.00, 0.00, 100),
(647, 129, 6, 1, 699000.00, 600000.00, 100),
(648, 130, 1, 5, 45000.00, 0.00, 1),
(649, 130, 2, 5, 460000.00, 0.00, 97),
(650, 130, 3, 5, 470000.00, 0.00, 100),
(651, 130, 4, 5, 480000.00, 0.00, 100),
(652, 130, 5, 5, 490000.00, 0.00, 100),
(653, 131, 1, 1, 690000.00, 0.00, 48),
(654, 131, 2, 1, 700000.00, 0.00, 89),
(655, 132, 1, 5, 400000.00, 0.00, 99),
(656, 132, 2, 5, 410000.00, 0.00, 100),
(657, 132, 3, 5, 420000.00, 0.00, 100),
(658, 132, 4, 5, 430000.00, 0.00, 100),
(659, 132, 5, 5, 440000.00, 0.00, 100),
(660, 133, 1, 1, 300000.00, 0.00, 99),
(661, 133, 2, 1, 310000.00, 0.00, 100),
(662, 133, 3, 1, 320000.00, 0.00, 100),
(663, 133, 4, 1, 330000.00, 0.00, 100),
(664, 133, 5, 1, 340000.00, 0.00, 100),
(665, 134, 1, 6, 300000.00, 210000.00, 0),
(666, 134, 2, 6, 310000.00, 217000.00, 96),
(667, 134, 3, 6, 320000.00, 224000.00, 98),
(668, 134, 4, 6, 330000.00, 231000.00, 99),
(669, 134, 5, 6, 340000.00, 238000.00, 99),
(670, 135, 1, 8, 123456.00, 0.00, 12),
(671, 136, 1, 1, 350000.00, 0.00, 99),
(672, 136, 2, 1, 360000.00, 0.00, 100),
(673, 137, 3, 3, 250000.00, 0.00, 1000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sizes`
--

CREATE TABLE `sizes` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `sizes`
--

INSERT INTO `sizes` (`id`, `name`) VALUES
(3, 'L'),
(2, 'M'),
(1, 'S'),
(4, 'XL'),
(5, 'XXL'),
(7, 'XXXL');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `sex` enum('male','female') DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `fullname`, `avatar`, `password`, `email`, `phone`, `address`, `birthday`, `sex`, `active`, `role`, `created_at`, `otp_code`, `otp_expires_at`, `is_verified`) VALUES
(5, 'trongtien', 'Phan Trọng Tiến', NULL, '$2y$10$aTDl17pLAY70J5QEhkUzqOp9orqynAmBxE0WpDi7eJUgnXkiLpkz.', 'tienptps40528@gmail.com', '0393361913', 'nguyễn văn ni, tổ 1, khu phố 6, thị trấn củ chi', '2005-05-18', 'male', 1, 'admin', '2025-11-27 05:44:49', NULL, NULL, 0),
(6, 'trongtien1', 'Phan Tiến Anh', NULL, '$2y$10$i1hIxzIlQntOLs8.ZvmX4empm8xAwA9SLPcqMxQ.RxxXp4PrJJw5m', 'tienptpssd40528@gmail.com', '0393361913', 'asd', NULL, NULL, 1, 'user', '2025-11-27 17:08:22', NULL, NULL, 0),
(14, 'maianh', 'maianh', NULL, '$2y$10$WfRsYhdSv7h30kyGt.tIUekiykJ0ai3Z9wQb.GjTR/7nSka8uP2tq', 'hutydang@gmail.com', '61649', 'ada', NULL, NULL, 1, 'user', '2025-12-12 03:37:20', NULL, NULL, 1),
(15, 'haidang5305', 'haidang5305', NULL, '$2y$10$R6Z71nIZymRPNIq0UiYYqeiuGI.A0jERcoXQzzzSU0lctaO7JsJb2', 'hutydang3107@gmail.com', '0365858481', 'heloo', NULL, NULL, 1, 'user', '2025-12-12 04:10:55', NULL, NULL, 1),
(18, 'hutydang', 'Hải Đăng', NULL, '$2y$10$VHacJHoxi2i.fXXnhm/72OBFAfURSWiG9eJ1fTTd0D5ZWjV7DyBC2', 'hanmaianh03@gmail.com', '0365858481', '85/2 Pham The Hien', NULL, NULL, 1, 'user', '2025-12-18 15:54:41', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `fullname`, `phone`, `address`, `is_default`, `created_at`) VALUES
(1, 5, 'phan tiến', '0393361913', 'nguyễn văn ni, tổ 1, khu phố 6, thị trấn củ chi', 1, '2025-12-01 09:55:09');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `amount_reduced` decimal(10,2) DEFAULT NULL,
  `minimum_value` decimal(10,2) DEFAULT '0.00',
  `quantity` int DEFAULT '0',
  `begin` date NOT NULL,
  `expired` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `value`, `amount_reduced`, `minimum_value`, `quantity`, `begin`, `expired`, `created_at`) VALUES
(1, 'YMS10', 10.00, 50000.00, 200000.00, 23, '2025-11-24', '2025-12-12', '2025-11-24 15:59:35'),
(10, 'YAMY15', 15.00, 50000.00, 200000.00, 5, '2025-11-24', '2025-12-31', '2025-11-24 16:21:57'),
(11, 'YAMY20', 20.00, 100000.00, 300000.00, 12, '2025-11-24', '2025-12-31', '2025-11-24 16:21:57'),
(12, 'YAMY25', 25.00, 150000.00, 450000.00, 5, '2025-11-24', '2025-12-31', '2025-11-24 16:21:57'),
(13, 'YAMYVIP', 30.00, 200000.00, 600000.00, 0, '2025-11-24', '2026-01-31', '2025-11-24 16:21:57'),
(14, 'YAMYNEW', 15.00, 0.00, 0.00, 100, '2025-11-24', '2025-11-27', '2025-11-24 16:21:57'),
(15, 'YAMY30K', 30000.00, 0.00, 199000.00, 96, '2025-11-30', '2025-12-31', '2025-11-24 16:21:57'),
(16, 'YAMY50K', 50000.00, 0.00, 350000.00, 29, '2025-12-30', '2026-01-30', '2025-11-24 16:21:57'),
(17, 'YAMY80K', 80000.00, 0.00, 499000.00, 9, '2025-11-24', '2026-01-10', '2025-11-24 16:21:57'),
(21, 'YAMYHOT', 30.00, 60000.00, 700000.00, 41, '2025-11-29', '2025-12-29', '2025-11-27 18:08:08');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Đang đổ dữ liệu cho bảng `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(1, 2, 206, '2025-11-01 06:40:00'),
(2, 2, 1235, '2025-11-13 18:44:22'),
(5, 6, 132, '2025-12-04 03:39:21'),
(6, 6, 74, '2025-12-04 03:39:29'),
(7, 6, 77, '2025-12-04 03:39:34'),
(8, 5, 134, '2025-12-04 04:26:57'),
(9, 5, 72, '2025-12-04 04:27:01'),
(10, 5, 71, '2025-12-04 04:27:04'),
(11, 5, 73, '2025-12-04 04:27:08'),
(12, 5, 65, '2025-12-04 04:27:12'),
(13, 5, 64, '2025-12-04 04:27:18'),
(14, 5, 131, '2025-12-04 04:27:21'),
(15, 5, 62, '2025-12-04 04:27:28'),
(16, 5, 61, '2025-12-04 04:27:34'),
(17, 7, 61, '2025-12-10 09:48:19'),
(18, 6, 70, '2025-12-12 03:11:40'),
(19, 15, 134, '2025-12-13 03:26:17'),
(20, 18, 71, '2025-12-18 16:15:07');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `t-shirts-polos` (`id`,`name`),
  ADD KEY `fk_parent` (`parent_id`);

--
-- Chỉ mục cho bảng `colors`
--
ALTER TABLE `colors`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comment_user` (`user_id`),
  ADD KEY `fk_comment_product` (`product_id`);

--
-- Chỉ mục cho bảng `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_order_details_variant` (`variant_id`);

--
-- Chỉ mục cho bảng `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Chỉ mục cho bảng `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wish` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `colors`
--
ALTER TABLE `colors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `news`
--
ALTER TABLE `news`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT cho bảng `order_details`
--
ALTER TABLE `order_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT cho bảng `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=138;

--
-- AUTO_INCREMENT cho bảng `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT cho bảng `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=674;

--
-- AUTO_INCREMENT cho bảng `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT cho bảng `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Ràng buộc đối với các bảng kết xuất
--

--
-- Ràng buộc cho bảng `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Ràng buộc cho bảng `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comment_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ràng buộc cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `fk_order_details_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`);

--
-- Ràng buộc cho bảng `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
