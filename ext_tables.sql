#
# Table structure for table 'tx_feregistration_domain_model_confirmationrequest'
#
CREATE TABLE tx_feregistration_domain_model_confirmationrequest
(

	encoded_values    text,
	email             varchar(255)     DEFAULT ''  NOT NULL,
	confirmation_hash varchar(255)     DEFAULT ''  NOT NULL,
	confirmation_date int(10) unsigned DEFAULT '0' NOT NULL,
	last_sent         int(10) unsigned DEFAULT '0' NOT NULL,

	KEY hash (confirmation_hash)
);

CREATE TABLE fe_users
(
	registration_request   int(10) unsigned    DEFAULT '0' NOT NULL,
	registration_completed tinyint(4) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_feregistration_domain_model_emailaddress
(
	tablename   varchar(255) DEFAULT ''  NOT NULL,
	fieldname   varchar(255) DEFAULT ''  NOT NULL,
	uid_foreign int(11)      DEFAULT '0' NOT NULL
);
