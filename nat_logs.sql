CREATE TABLE IF NOT EXISTS `nat_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_publico` VARCHAR(45) NOT NULL,
  `porta_publica` SMALLINT UNSIGNED NOT NULL,
  `ip_privado` VARCHAR(45) NOT NULL,
  `porta_privada` SMALLINT UNSIGNED NOT NULL,
  `protocolo` VARCHAR(10) NOT NULL,
  `ts_amostra` DATETIME NOT NULL COMMENT 'Momento da amostragem, em UTC',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_busca_judicial` (`ip_publico`, `porta_publica`, `ts_amostra`),
  KEY `idx_ip_privado` (`ip_privado`, `ts_amostra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
