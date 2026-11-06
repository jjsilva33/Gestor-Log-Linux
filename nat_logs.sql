CREATE TABLE IF NOT EXISTS `nat_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_publico` VARCHAR(45) NOT NULL,
  `porta_publica` SMALLINT UNSIGNED NOT NULL,
  `ip_privado` VARCHAR(45) NOT NULL,
  `porta_privada` SMALLINT UNSIGNED NOT NULL,
  `protocolo` VARCHAR(10) NOT NULL,
  `ts_amostra` DATETIME NOT NULL COMMENT 'Início da sessão (1ª amostra), em UTC',
  `ts_fim` DATETIME NULL COMMENT 'Última amostra em que essa sessão ainda estava ativa, em UTC',
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_busca_judicial` (`ip_publico`, `porta_publica`, `ts_amostra`),
  KEY `idx_ip_privado` (`ip_privado`, `ts_amostra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migração para quem já tinha a tabela criada antes do campo ts_fim existir.
-- Rodar de novo este arquivo é seguro: CREATE/ADD COLUMN acima e abaixo são idempotentes.
ALTER TABLE `nat_logs` ADD COLUMN IF NOT EXISTS `ts_fim` DATETIME NULL COMMENT 'Última amostra em que essa sessão ainda estava ativa, em UTC' AFTER `ts_amostra`;

-- Registro de quem consultou o quê (obrigação de prestar contas sobre o acesso aos dados de conexão)
CREATE TABLE IF NOT EXISTS `nat_logs_auditoria` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(100) NOT NULL COMMENT 'Usuário do painel que fez a consulta',
  `solicitante` VARCHAR(255) NULL COMMENT 'Identificação de quem pediu (ofício/processo)',
  `ip_pesquisado` VARCHAR(45) NOT NULL,
  `porta_pesquisada` SMALLINT UNSIGNED NOT NULL,
  `momento_pesquisado` DATETIME NULL,
  `encontrado` TINYINT(1) NOT NULL,
  `pesquisado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
