#
# First-party usage log for WebMCP tool calls. Append-only, no PII: only the
# tool name and a best-effort, self-reported client hint are stored. Written by
# the event middleware, read by the backend module.
#
CREATE TABLE tx_neoblackwebmcp_event (
    uid int(11) unsigned NOT NULL auto_increment,
    crdate int(11) unsigned DEFAULT 0 NOT NULL,
    tool varchar(64) DEFAULT '' NOT NULL,
    client varchar(64) DEFAULT '' NOT NULL,
    PRIMARY KEY (uid),
    KEY tool (tool),
    KEY client (client),
    KEY crdate (crdate)
);
