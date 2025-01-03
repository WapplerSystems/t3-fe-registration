#
# Table structure for table 'tx_feregistration_domain_model_optin'
#
CREATE TABLE tx_feregistration_domain_model_optin
(

	encoded_values  text,
	email           varchar(255)        DEFAULT ''  NOT NULL,
	is_validated    tinyint(4) unsigned DEFAULT '0' NOT NULL,
	validation_hash varchar(255)        DEFAULT ''  NOT NULL,
	validation_date int(11) unsigned    DEFAULT '0' NOT NULL,
	last_sent       int(10) unsigned    DEFAULT '0' NOT NULL,

	KEY hash (validation_hash)
);

CREATE TABLE fe_users
(
	registration_completed tinyint(4) unsigned DEFAULT '0' NOT NULL
);
