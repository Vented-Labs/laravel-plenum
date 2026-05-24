-- Each MySQL node gets the same table layout. Because the nodes are not
-- replicated, a row only ever exists where it was written — which is exactly
-- what the integration suite uses to prove that Plenum routed the write
-- somewhere specific.
CREATE TABLE IF NOT EXISTS marker (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(191) NOT NULL,
    UNIQUE KEY uniq_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
