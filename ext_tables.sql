
CREATE TABLE tx_vcsafetycheck_vulnerability (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT 0 NOT NULL,
    tstamp int(11) unsigned DEFAULT 0 NOT NULL,
    crdate int(11) unsigned DEFAULT 0 NOT NULL,
    package_name varchar(255) DEFAULT '' NOT NULL,
    package_version varchar(255) DEFAULT '' NOT NULL,
    package_type varchar(64) DEFAULT '' NOT NULL,
    ecosystem varchar(64) DEFAULT 'Packagist' NOT NULL,
    osv_id varchar(255) DEFAULT '' NOT NULL,
    aliases text,
    summary text,
    details mediumtext,
    severity varchar(16) DEFAULT 'UNKNOWN' NOT NULL,
    fixed_version varchar(255) DEFAULT '' NOT NULL,
    reference_url text,
    detected_at int(11) unsigned DEFAULT 0 NOT NULL,
    last_seen int(11) unsigned DEFAULT 0 NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY osv_lookup (osv_id,package_name,package_version),
);
