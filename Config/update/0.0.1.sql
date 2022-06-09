
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- siret_customer
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `siret_customer`;

CREATE TABLE `siret_customer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER NOT NULL,
    `code_siret` VARCHAR(100) NOT NULL,
    `denomination_unite_legale` VARCHAR(200),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `customer_id_UNIQUE` (`customer_id`),
    CONSTRAINT `fk_siret_customer_id`
        FOREIGN KEY (`customer_id`)
            REFERENCES `customer` (`id`)
            ON UPDATE CASCADE
            ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;