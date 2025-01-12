#
# Table structure for table 'tx_feregistration_domain_model_confirmationrequest'
#
CREATE TABLE tx_feregistration_domain_model_confirmationrequest
(

	encoded_values    text,
	email             varchar(255)        DEFAULT ''  NOT NULL,
	is_confirmed      tinyint(4) unsigned DEFAULT '0' NOT NULL,
	confirmation_hash varchar(255)        DEFAULT ''  NOT NULL,

	KEY hash (confirmation_hash)
);

CREATE TABLE fe_users
(
	registration_completed tinyint(4) unsigned DEFAULT '0' NOT NULL
);
