
CREATE TABLE `custom_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `product_ids` varchar(255) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `is_out_of_stock` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
