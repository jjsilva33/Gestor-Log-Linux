-- Schema padrão do rsyslog-mysql (módulo ommysql), usado pelo container
-- rsyslog para gravar os logs recebidos. Numa instalação bare-metal esse
-- schema normalmente é criado automaticamente pelo pacote `rsyslog-mysql`
-- do Debian via dbconfig-common; em Docker o rsyslog roda num container
-- separado sem esse mecanismo, então criamos aqui manualmente.

CREATE TABLE IF NOT EXISTS `SystemEvents`
(
    `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `CustomerID` BIGINT,
    `ReceivedAt` DATETIME NULL,
    `DeviceReportedTime` DATETIME NULL,
    `Facility` SMALLINT NULL,
    `Priority` SMALLINT NULL,
    `FromHost` VARCHAR(60) NULL,
    `Message` TEXT,
    `NTSeverity` INT NULL,
    `Importance` INT NULL,
    `EventSource` VARCHAR(60),
    `EventUser` VARCHAR(60),
    `EventCategory` INT,
    `EventID` INT,
    `EventBinaryData` TEXT,
    `MaxAvailable` INT,
    `CurrUsage` INT,
    `MinUsage` INT,
    `MaxUsage` INT,
    `InfoUnitID` INT,
    `SysLogTag` VARCHAR(60),
    `EventLogType` VARCHAR(60),
    `GenericFileName` VARCHAR(60),
    `SystemID` INT
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `SystemEventsProperties`
(
    `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `SystemEventID` INT,
    `ParamName` VARCHAR(255) NULL,
    `ParamValue` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Índices usados pelo painel (dashboard, relatório, estatística, top rank)
CREATE INDEX IF NOT EXISTS `idx_receivedat` ON `SystemEvents` (`ReceivedAt`);
CREATE INDEX IF NOT EXISTS `idx_syslogtag_receivedat` ON `SystemEvents` (`SysLogTag`, `ReceivedAt`);
CREATE INDEX IF NOT EXISTS `idx_fromhost` ON `SystemEvents` (`FromHost`);
