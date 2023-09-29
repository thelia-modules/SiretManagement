ALTER TABLE `siret_customer`
    ADD `code_tva_intra` varchar(100) COLLATE 'utf8_general_ci' NOT NULL AFTER `code_siret`;
